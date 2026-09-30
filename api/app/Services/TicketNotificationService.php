<?php

namespace App\Services;

use App\Enums\DomainEventType;
use App\Enums\Role;
use App\Models\OutboxEvent;
use App\Models\Team;
use App\Models\User;
use App\Notifications\TicketEventNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Decides who is told about each ticket domain event.
 */
class TicketNotificationService
{
    public function notify(OutboxEvent $event): void
    {
        $recipients = $this->recipientsFor($event);

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new TicketEventNotification($event));
        }
    }

    /**
     * @return Collection<int, User>
     */
    public function recipientsFor(OutboxEvent $event): Collection
    {
        $payload = $event->payload;
        $actorId = $payload['actor_id'] ?? null;

        $ids = match (DomainEventType::from($event->event_type)) {
            // Requester gets a confirmation; the team learns about new work.
            DomainEventType::TICKET_CREATED => [
                $payload['requester_id'],
                ...$this->without($this->teamMemberIds($payload['team_id']), $actorId),
            ],
            DomainEventType::TICKET_ASSIGNED => $this->without([
                $payload['assignee_id'],
                $payload['requester_id'],
            ], $actorId),
            DomainEventType::TICKET_STATUS_CHANGED => $this->without([
                $payload['requester_id'],
                $payload['assignee_id'],
            ], $actorId),
            // Escalate to the assignee (or the whole team) plus all admins.
            DomainEventType::TICKET_SLA_BREACHED => [
                ...($payload['assignee_id'] ? [$payload['assignee_id']] : $this->teamMemberIds($payload['team_id'])),
                ...User::query()->where('role', Role::ADMIN)->pluck('id')->all(),
            ],
        };

        $ids = array_values(array_unique(array_filter($ids)));

        return $ids === [] ? collect() : User::query()->whereIn('id', $ids)->get();
    }

    /**
     * @return list<int>
     */
    private function teamMemberIds(?int $teamId): array
    {
        if ($teamId === null) {
            return [];
        }

        return Team::query()->find($teamId)?->users()->pluck('users.id')->all() ?? [];
    }

    private function without(array $ids, ?int $actorId): array
    {
        return array_filter($ids, fn ($id) => $id !== null && $id !== $actorId);
    }
}
