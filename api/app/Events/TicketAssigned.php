<?php

namespace App\Events;

use App\Enums\DomainEventType;
use App\Models\Ticket;

class TicketAssigned extends TicketEvent
{
    public function __construct(
        Ticket $ticket,
        ?int $actorId,
        public readonly ?int $previousAssigneeId,
    ) {
        parent::__construct($ticket, $actorId);
    }

    public function type(): DomainEventType
    {
        return DomainEventType::TICKET_ASSIGNED;
    }

    protected function extraPayload(): array
    {
        return [
            'previous_assignee_id' => $this->previousAssigneeId,
        ];
    }
}
