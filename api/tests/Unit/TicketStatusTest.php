<?php

namespace Tests\Unit;

use App\Enums\TicketStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TicketStatusTest extends TestCase
{
    public static function transitions(): array
    {
        return [
            'open -> in progress' => [TicketStatus::OPEN, TicketStatus::IN_PROGRESS, true],
            'in progress -> waiting' => [TicketStatus::IN_PROGRESS, TicketStatus::WAITING_ON_CUSTOMER, true],
            'waiting -> in progress' => [TicketStatus::WAITING_ON_CUSTOMER, TicketStatus::IN_PROGRESS, true],
            'resolved -> reopen' => [TicketStatus::RESOLVED, TicketStatus::OPEN, true],
            'closed -> reopen' => [TicketStatus::CLOSED, TicketStatus::OPEN, true],
            'closed -> resolved' => [TicketStatus::CLOSED, TicketStatus::RESOLVED, false],
            'closed -> in progress' => [TicketStatus::CLOSED, TicketStatus::IN_PROGRESS, false],
            'in progress -> open' => [TicketStatus::IN_PROGRESS, TicketStatus::OPEN, false],
            'open -> open' => [TicketStatus::OPEN, TicketStatus::OPEN, false],
        ];
    }

    #[DataProvider('transitions')]
    public function test_state_machine_graph(TicketStatus $from, TicketStatus $to, bool $allowed): void
    {
        $this->assertSame($allowed, $from->canTransitionTo($to));
    }

    public function test_reopen_detection(): void
    {
        $this->assertTrue(TicketStatus::CLOSED->isReopenTo(TicketStatus::OPEN));
        $this->assertFalse(TicketStatus::RESOLVED->isReopenTo(TicketStatus::CLOSED));
        $this->assertFalse(TicketStatus::OPEN->isReopenTo(TicketStatus::IN_PROGRESS));
    }
}
