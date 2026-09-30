<?php

namespace App\Events;

use App\Contracts\DomainEvent;
use App\Models\Ticket;

abstract class TicketEvent implements DomainEvent
{
    public function __construct(
        public readonly Ticket $ticket,
        public readonly ?int $actorId = null,
    ) {}

    public function aggregateType(): string
    {
        return $this->ticket->getMorphClass();
    }

    public function aggregateId(): int
    {
        return $this->ticket->id;
    }

    public function payload(): array
    {
        return [
            'ticket_id' => $this->ticket->id,
            'reference' => $this->ticket->reference,
            'subject' => $this->ticket->subject,
            'requester_id' => $this->ticket->requester_id,
            'assignee_id' => $this->ticket->assignee_id,
            'team_id' => $this->ticket->team_id,
            'actor_id' => $this->actorId,
            ...$this->extraPayload(),
        ];
    }

    protected function extraPayload(): array
    {
        return [];
    }
}
