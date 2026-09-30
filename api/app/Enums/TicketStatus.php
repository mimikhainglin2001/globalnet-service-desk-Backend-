<?php

namespace App\Enums;

enum TicketStatus: string
{
    case OPEN = 'open';
    case IN_PROGRESS = 'in_progress';
    case WAITING_ON_CUSTOMER = 'waiting_on_customer';
    case RESOLVED = 'resolved';
    case CLOSED = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::OPEN => 'Open',
            self::IN_PROGRESS => 'In Progress',
            self::WAITING_ON_CUSTOMER => 'Waiting on Customer',
            self::RESOLVED => 'Resolved',
            self::CLOSED => 'Closed',
        };
    }

    /**
     * The single source of truth for the ticket state machine.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::OPEN => [self::IN_PROGRESS, self::WAITING_ON_CUSTOMER, self::RESOLVED, self::CLOSED],
            self::IN_PROGRESS => [self::WAITING_ON_CUSTOMER, self::RESOLVED, self::CLOSED],
            self::WAITING_ON_CUSTOMER => [self::IN_PROGRESS, self::RESOLVED, self::CLOSED],
            self::RESOLVED => [self::OPEN, self::IN_PROGRESS, self::CLOSED],
            self::CLOSED => [self::OPEN],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public function isFinished(): bool
    {
        return in_array($this, self::finished(), true);
    }

    /**
     * Moving from a finished status back to an active one.
     */
    public function isReopenTo(self $target): bool
    {
        return $this->isFinished() && ! $target->isFinished();
    }

    /**
     * @return list<self>
     */
    public static function finished(): array
    {
        return [self::RESOLVED, self::CLOSED];
    }

    /**
     * @return list<self>
     */
    public static function active(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $status) => ! $status->isFinished(),
        ));
    }
}
