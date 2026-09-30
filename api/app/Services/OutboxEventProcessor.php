<?php

namespace App\Services;

use App\Enums\DomainEventType;
use App\Models\OutboxEvent;
use App\Models\ProcessedEvent;
use Illuminate\Support\Facades\DB;

/**
 * Idempotent consumer for outbox events.
 *
 * Inside one transaction it: locks the outbox row, claims the event_id in
 * processed_events (unique index), runs the handler and marks the row as
 * processed. A duplicate delivery either sees processed_at already set or
 * fails to claim the event_id, so no second notification is written.
 */
class OutboxEventProcessor
{
    public function __construct(
        private readonly TicketNotificationService $notifications,
    ) {}

    public function process(int $outboxEventId): void
    {
        // Counted outside the transaction so failed attempts are persisted.
        OutboxEvent::query()->whereKey($outboxEventId)->whereNull('processed_at')->increment('attempts');

        DB::transaction(function () use ($outboxEventId) {
            $event = OutboxEvent::query()->lockForUpdate()->find($outboxEventId);

            if ($event === null || $event->isProcessed()) {
                return;
            }

            $claimed = ProcessedEvent::query()->insertOrIgnore([
                'event_id' => $event->event_id,
                'processed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($claimed === 1) {
                $this->handle($event);
            }

            $event->update([
                'processed_at' => now(),
                'failed_at' => null,
                'last_error' => null,
            ]);
        });
    }

    public function markFailed(int $outboxEventId, string $error): void
    {
        OutboxEvent::query()->whereKey($outboxEventId)->whereNull('processed_at')->update([
            'failed_at' => now(),
            'last_error' => mb_substr($error, 0, 2000),
        ]);
    }

    private function handle(OutboxEvent $event): void
    {
        match (DomainEventType::tryFrom($event->event_type)) {
            DomainEventType::TICKET_CREATED,
            DomainEventType::TICKET_ASSIGNED,
            DomainEventType::TICKET_STATUS_CHANGED,
            DomainEventType::TICKET_SLA_BREACHED => $this->notifications->notify($event),
            null => throw new \UnexpectedValueException("Unsupported outbox event type: {$event->event_type}"),
        };
    }
}
