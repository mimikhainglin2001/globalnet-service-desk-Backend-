<?php

namespace App\Policies;

use App\Enums\CommentType;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class TicketPolicy
{
    /**
     * Everyone may list; rows are scoped by Ticket::scopeVisibleTo().
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Ticket $ticket): bool
    {
        return $this->canAccess($user, $ticket);
    }

    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Triage fields (priority, category, team) are staff only.
     */
    public function update(User $user, Ticket $ticket): Response
    {
        return $user->isStaff() && $this->canAccess($user, $ticket)
            ? Response::allow()
            : Response::deny('Only agents of the ticket\'s team or admins can update tickets.');
    }

    /**
     * Admins assign anyone; agents may only take or release a ticket themselves.
     */
    public function assign(User $user, Ticket $ticket, ?User $assignee = null): Response
    {
        if ($user->isAdmin()) {
            return Response::allow();
        }

        if (! $user->isAgent() || ! $this->canAccess($user, $ticket)) {
            return Response::deny('You cannot assign this ticket.');
        }

        $selfAssign = $assignee?->id === $user->id;
        $selfRelease = $assignee === null && $ticket->assignee_id === $user->id;

        return $selfAssign || $selfRelease
            ? Response::allow()
            : Response::deny('Agents can only assign tickets to themselves.');
    }

    /**
     * Customers may only close their ticket or reopen it; staff may perform
     * any transition the state machine allows.
     */
    public function changeStatus(User $user, Ticket $ticket, TicketStatus $target): Response
    {
        if (! $this->canAccess($user, $ticket)) {
            return Response::deny('You cannot change the status of this ticket.');
        }

        if ($user->isStaff()) {
            return Response::allow();
        }

        $isClose = $target === TicketStatus::CLOSED;
        $isReopen = $target === TicketStatus::OPEN && $ticket->status->isFinished();

        return $isClose || $isReopen
            ? Response::allow()
            : Response::deny('Customers can only close or reopen their own tickets.');
    }

    public function comment(User $user, Ticket $ticket, CommentType $type): Response
    {
        if (! $this->canAccess($user, $ticket)) {
            return Response::deny('You cannot comment on this ticket.');
        }

        if ($type === CommentType::INTERNAL && ! $user->isStaff()) {
            return Response::deny('Only agents and admins can add internal notes.');
        }

        if ($user->isCustomer() && $ticket->status === TicketStatus::CLOSED) {
            return Response::deny('Reopen the ticket before adding a reply.');
        }

        return Response::allow();
    }

    public function viewInternalNotes(User $user, Ticket $ticket): bool
    {
        return $user->isStaff() && $this->canAccess($user, $ticket);
    }

    public function viewActivity(User $user, Ticket $ticket): bool
    {
        return $user->isStaff() && $this->canAccess($user, $ticket);
    }

    public function uploadAttachment(User $user, Ticket $ticket): Response
    {
        if (! $this->canAccess($user, $ticket)) {
            return Response::deny('You cannot upload files to this ticket.');
        }

        return $ticket->status === TicketStatus::CLOSED
            ? Response::deny('Files cannot be added to a closed ticket.')
            : Response::allow();
    }

    public function delete(User $user, Ticket $ticket): bool
    {
        return $user->isAdmin();
    }

    private function canAccess(User $user, Ticket $ticket): bool
    {
        return match (true) {
            $user->isAdmin() => true,
            $user->isAgent() => $user->belongsToTeam($ticket->team_id) || $ticket->assignee_id === $user->id,
            default => $ticket->requester_id === $user->id,
        };
    }
}
