<?php

namespace App\Events;

use App\Enums\DomainEventType;

class TicketCreated extends TicketEvent
{
    public function type(): DomainEventType
    {
        return DomainEventType::TICKET_CREATED;
    }

    protected function extraPayload(): array
    {
        return [
            'priority' => $this->ticket->priority->value,
            'due_at' => $this->ticket->due_at?->toIso8601String(),
        ];
    }
}
