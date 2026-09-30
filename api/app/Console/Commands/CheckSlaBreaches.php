<?php

namespace App\Console\Commands;

use App\Events\TicketSlaBreached;
use App\Models\Ticket;
use App\Services\OutboxService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckSlaBreaches extends Command
{
    protected $signature = 'sla:check {--limit= : Maximum number of tickets to flag in one run}';

    protected $description = 'Flag tickets that breached their SLA and emit an SLA breached event';

    public function handle(OutboxService $outbox): int
    {
        $limit = (int) ($this->option('limit') ?: config('servicedesk.sla.check_batch_size'));

        $candidateIds = Ticket::query()
            ->overdue()
            ->whereNull('sla_breached_at')
            ->orderBy('due_at')
            ->limit($limit)
            ->pluck('id');

        $flagged = 0;

        foreach ($candidateIds as $id) {
            // Re-check under a row lock so overlapping runs never flag twice.
            $flagged += DB::transaction(function () use ($id, $outbox) {
                $ticket = Ticket::query()->lockForUpdate()->find($id);

                if ($ticket === null || $ticket->sla_breached_at !== null || ! $ticket->isOverdue()) {
                    return 0;
                }

                $ticket->update(['sla_breached_at' => now()]);

                $outbox->record(new TicketSlaBreached($ticket));

                return 1;
            });
        }

        $this->info("Flagged {$flagged} SLA breaches.");

        return self::SUCCESS;
    }
}
