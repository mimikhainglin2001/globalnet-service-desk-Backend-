<?php

namespace App\Jobs;

use App\Services\OutboxEventProcessor;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Retries with backoff; after the last attempt Laravel stores the job in
 * failed_jobs and failed() dead-letters the outbox row so an admin can
 * inspect and retry it.
 */
class ProcessOutboxEvent implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries;

    /** Seconds the uniqueness lock is held, so the relay cannot queue duplicates. */
    public int $uniqueFor = 600;

    public function __construct(
        public readonly int $outboxEventId,
    ) {
        $this->tries = config('servicedesk.outbox.tries');
        $this->onQueue(config('servicedesk.outbox.queue'));
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return config('servicedesk.outbox.backoff_seconds');
    }

    public function uniqueId(): string
    {
        return (string) $this->outboxEventId;
    }

    public function handle(OutboxEventProcessor $processor): void
    {
        $processor->process($this->outboxEventId);
    }

    public function failed(?Throwable $exception): void
    {
        app(OutboxEventProcessor::class)->markFailed(
            $this->outboxEventId,
            $exception?->getMessage() ?? 'Unknown error',
        );
    }
}
