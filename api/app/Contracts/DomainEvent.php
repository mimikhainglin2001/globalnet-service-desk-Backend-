<?php

namespace App\Contracts;

use App\Enums\DomainEventType;

interface DomainEvent
{
    public function type(): DomainEventType;

    public function aggregateType(): string;

    public function aggregateId(): int;

    /**
     * Serialisable data stored in the outbox. Must be self-contained enough
     * for a consumer to act on without guessing the state at event time.
     */
    public function payload(): array;
}
