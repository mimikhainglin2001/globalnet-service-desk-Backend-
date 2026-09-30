<?php

namespace App\Services;

use App\Contracts\TicketRepositoryInterface;
use App\Enums\TicketStatus;
use App\Events\TicketStatusChanged;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The only place a ticket's status is changed.
 *
 * The allowed graph lives in TicketStatus::allowedTransitions(); who may
 * request a transition is decided by TicketPolicy::changeStatus().
 */
class TicketStatusService
{
    public function __construct(
        private readonly TicketRepositoryInterface $tickets,
        private readonly OutboxService $outbox,
    ) {}

    public function changeStatus(Ticket $ticket, TicketStatus $target, ?User $actor = null): Ticket
    {
        DB::transaction(function () use ($ticket, $target, $actor) {
            $ticket = $this->tickets->findForUpdate($ticket->id);
            $current = $ticket->status;

            $this->assertTransitionAllowed($ticket, $target);

            $this->tickets->update($ticket, [
                'status' => $target,
                ...$this->sideEffects($current, $target),
            ]);

            $this->outbox->record(new TicketStatusChanged($ticket, $actor?->id, $current, $target));
        });

        return $this->tickets->findForDisplay($ticket->id);
    }

    public function assertTransitionAllowed(Ticket $ticket, TicketStatus $target): void
    {
        $current = $ticket->status;

        if (! $current->canTransitionTo($target)) {
            throw ValidationException::withMessages([
                'status' => ["Cannot change ticket status from {$current->label()} to {$target->label()}."],
            ]);
        }

        if ($current->isReopenTo($target) && ! $this->withinReopenWindow($ticket)) {
            $days = config('servicedesk.tickets.reopen_window_days');

            throw ValidationException::withMessages([
                'status' => ["Tickets can only be reopened within {$days} days of being resolved or closed."],
            ]);
        }
    }

    public function withinReopenWindow(Ticket $ticket): bool
    {
        $finishedAt = $ticket->closed_at ?? $ticket->resolved_at ?? $ticket->updated_at;

        return $finishedAt->greaterThanOrEqualTo(
            now()->subDays(config('servicedesk.tickets.reopen_window_days'))
        );
    }

    private function sideEffects(TicketStatus $from, TicketStatus $to): array
    {
        return match (true) {
            $to === TicketStatus::RESOLVED => ['resolved_at' => now()],
            $to === TicketStatus::CLOSED => ['closed_at' => now()],
            $from->isReopenTo($to) => ['resolved_at' => null, 'closed_at' => null],
            default => [],
        };
    }
}
