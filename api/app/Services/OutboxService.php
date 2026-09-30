<?php

namespace App\Services;

use App\Contracts\DomainEvent;
use App\Jobs\ProcessOutboxEvent;
use App\Models\OutboxEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

class OutboxService
{
    /**
     * Persist a domain event in the caller's transaction.
     *
     * The job is dispatched only after commit (fast path). If that dispatch is
     * lost, the outbox:dispatch relay picks the row up later, so an event is
     * never lost once the business change is committed.
     */
    public function record(DomainEvent $event): OutboxEvent
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Outbox events must be recorded inside the transaction that changes the aggregate.');
        }

        $outboxEvent = OutboxEvent::create([
            'event_id' => (string) Str::uuid(),
            'event_type' => $event->type()->value,
            'aggregate_type' => $event->aggregateType(),
            'aggregate_id' => $event->aggregateId(),
            'payload' => $event->payload(),
        ]);

        ProcessOutboxEvent::dispatch($outboxEvent->id)->afterCommit();

        return $outboxEvent;
    }

    /**
     * Re-queue an event that exhausted its retries (dead-lettered).
     */
    public function retry(OutboxEvent $outboxEvent): void
    {
        $outboxEvent->update([
            'failed_at' => null,
            'attempts' => 0,
            'last_error' => null,
        ]);

        ProcessOutboxEvent::dispatch($outboxEvent->id);
    }
}
