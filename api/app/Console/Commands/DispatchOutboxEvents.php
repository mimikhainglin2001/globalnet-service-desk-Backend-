<?php

namespace App\Console\Commands;

use App\Jobs\ProcessOutboxEvent;
use App\Models\OutboxEvent;
use Illuminate\Console\Command;

/**
 * Relay / safety net: events are normally dispatched right after commit.
 * This re-dispatches any pending event whose job was lost (e.g. the queue was
 * unavailable at commit time). ShouldBeUnique prevents double queuing and the
 * consumer is idempotent, so running it often is safe.
 */
class DispatchOutboxEvents extends Command
{
    protected $signature = 'outbox:dispatch
        {--limit= : Maximum number of events to dispatch}
        {--all : Ignore the grace period and dispatch every pending event}';

    protected $description = 'Dispatch pending transactional outbox events to the queue';

    public function handle(): int
    {
        $limit = (int) ($this->option('limit') ?: config('servicedesk.outbox.relay_batch_size'));

        $ids = OutboxEvent::query()
            ->pending()
            ->unless($this->option('all'), fn ($query) => $query->where(
                'created_at',
                '<=',
                now()->subSeconds(config('servicedesk.outbox.relay_grace_seconds')),
            ))
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id');

        $ids->each(fn (int $id) => ProcessOutboxEvent::dispatch($id));

        $this->info("Dispatched {$ids->count()} outbox events.");

        return self::SUCCESS;
    }
}
