<?php

namespace App\Notifications;

use App\Enums\DomainEventType;
use App\Enums\TicketStatus;
use App\Models\OutboxEvent;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent synchronously by the outbox consumer (not queued on its own), so the
 * database notification is written in the same transaction that marks the
 * event as processed. That is what makes the consumer idempotent.
 */
class TicketEventNotification extends Notification
{
    public function __construct(
        public readonly OutboxEvent $event,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function databaseType(object $notifiable): string
    {
        return $this->event->event_type;
    }

    public function toArray(object $notifiable): array
    {
        $payload = $this->event->payload;

        return [
            'event_id' => $this->event->event_id,
            'event_type' => $this->event->event_type,
            'ticket_id' => $payload['ticket_id'] ?? null,
            'reference' => $payload['reference'] ?? null,
            'subject' => $payload['subject'] ?? null,
            'message' => $this->message(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $payload = $this->event->payload;
        $url = rtrim(config('servicedesk.frontend_url'), '/')."/tickets/{$payload['ticket_id']}";

        return (new MailMessage)
            ->subject("[{$payload['reference']}] {$this->message()}")
            ->greeting("Hello {$notifiable->name},")
            ->line($this->message())
            ->line("Subject: {$payload['subject']}")
            ->action('View ticket', $url);
    }

    public function message(): string
    {
        $payload = $this->event->payload;
        $reference = $payload['reference'] ?? '';

        return match (DomainEventType::from($this->event->event_type)) {
            DomainEventType::TICKET_CREATED => "Ticket {$reference} was created.",
            DomainEventType::TICKET_ASSIGNED => $payload['assignee_id']
                ? "Ticket {$reference} was assigned."
                : "Ticket {$reference} was unassigned.",
            DomainEventType::TICKET_STATUS_CHANGED => sprintf(
                'Ticket %s status changed from %s to %s.',
                $reference,
                TicketStatus::from($payload['from'])->label(),
                TicketStatus::from($payload['to'])->label(),
            ),
            DomainEventType::TICKET_SLA_BREACHED => "Ticket {$reference} breached its SLA.",
        };
    }
}
