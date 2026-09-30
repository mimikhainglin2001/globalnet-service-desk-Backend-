<?php

namespace App\Events;

use App\Enums\DomainEventType;
use App\Enums\TicketStatus;
use App\Models\Ticket;

class TicketStatusChanged extends TicketEvent
{
    public function __construct(
        Ticket $ticket,
        ?int $actorId,
        public readonly TicketStatus $from,
        public readonly TicketStatus $to,
    ) {
        parent::__construct($ticket, $actorId);
    }

    public function type(): DomainEventType
    {
        return DomainEventType::TICKET_STATUS_CHANGED;
    }

    protected function extraPayload(): array
    {
        return [
            'from' => $this->from->value,
            'to' => $this->to->value,
        ];
    }
}
