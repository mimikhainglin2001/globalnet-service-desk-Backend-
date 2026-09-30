<?php

namespace App\Services;

use App\Contracts\TicketRepositoryInterface;
use App\DTOs\CreateTicketData;
use App\DTOs\TicketFilters;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Events\TicketAssigned;
use App\Events\TicketCreated;
use App\Http\Resources\TicketResource;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class TicketService
{
    public function __construct(
        private readonly TicketRepositoryInterface $tickets,
        private readonly SlaService $slaService,
        private readonly OutboxService $outbox,
        private readonly AttachmentService $attachments,
        private readonly IdempotencyService $idempotency,
    ) {}

    public function list(User $user, TicketFilters $filters): LengthAwarePaginator
    {
        return $this->tickets->paginateVisibleTo($user, $filters);
    }

    public function findForDisplay(int $id): ?Ticket
    {
        return $this->tickets->findForDisplay($id);
    }

    public function create(User $requester, CreateTicketData $data): Ticket
    {
        try {
            $ticket = DB::transaction(function () use ($requester, $data) {
                $ticket = $this->tickets->create([
                    'subject' => $data->subject,
                    'description' => $data->description,
                    'category_id' => $data->categoryId,
                    'priority' => $data->priority,
                    'status' => TicketStatus::OPEN,
                    'requester_id' => $requester->id,
                    'team_id' => $this->resolveTeamId($requester, $data),
                    'due_at' => $this->slaService->calculateDueAt($data->priority),
                ]);

                $this->attachments->store($ticket, $requester, $data->attachments);

                $this->outbox->record(new TicketCreated($ticket, $requester->id));

                return $ticket;
            });
        } catch (Throwable $exception) {
            $this->attachments->discardWrittenFiles();

            throw $exception;
        }

        return $this->tickets->findForDisplay($ticket->id);
    }

    /**
     * @return array{status: int, body: array, replayed: bool}
     */
    public function createIdempotent(User $requester, CreateTicketData $data, string $key): array
    {
        try {
            $result = $this->idempotency->run(
                $requester,
                $key,
                $data->fingerprint(),
                fn () => [
                    'status' => 201,
                    'body' => [
                        'message' => 'Ticket created successfully.',
                        'data' => (new TicketResource($this->create($requester, $data)))->resolve(),
                    ],
                ],
            );
        } catch (Throwable $exception) {
            $this->attachments->discardWrittenFiles();

            throw $exception;
        }

        if ($result['replayed']) {
            // A concurrent request with the same key won the race; this attempt
            // was rolled back, so its uploaded files are orphans.
            $this->attachments->discardWrittenFiles();
        }

        return $result;
    }

    /**
     * Staff-only triage fields. Changing the priority re-derives the due date
     * from the ticket's creation time using the SLA rule of the new priority.
     */
    public function update(Ticket $ticket, array $data): Ticket
    {
        DB::transaction(function () use ($ticket, $data) {
            $ticket = $this->tickets->findForUpdate($ticket->id);

            if (isset($data['priority'])) {
                $priority = TicketPriority::from($data['priority']);

                if ($priority !== $ticket->priority) {
                    $data['due_at'] = $this->slaService->calculateDueAt($priority, $ticket->created_at);

                    if ($data['due_at']?->isFuture()) {
                        $data['sla_breached_at'] = null;
                    }
                }
            }

            $this->tickets->update($ticket, $data);
        });

        return $this->tickets->findForDisplay($ticket->id);
    }

    public function assign(Ticket $ticket, User $actor, ?User $assignee): Ticket
    {
        if ($assignee !== null) {
            $this->ensureCanBeAssigned($ticket, $assignee);
        }

        DB::transaction(function () use ($ticket, $actor, $assignee) {
            $ticket = $this->tickets->findForUpdate($ticket->id);
            $previousAssigneeId = $ticket->assignee_id;

            if ($previousAssigneeId === $assignee?->id) {
                return;
            }

            $this->tickets->update($ticket, ['assignee_id' => $assignee?->id]);

            $this->outbox->record(new TicketAssigned($ticket, $actor->id, $previousAssigneeId));
        });

        return $this->tickets->findForDisplay($ticket->id);
    }

    /**
     * Customers cannot pick a team; tickets are routed by category. Staff may
     * route explicitly.
     */
    private function resolveTeamId(User $requester, CreateTicketData $data): ?int
    {
        if ($requester->isStaff() && $data->teamId !== null) {
            return $data->teamId;
        }

        return Category::query()->whereKey($data->categoryId)->value('team_id');
    }

    private function ensureCanBeAssigned(Ticket $ticket, User $assignee): void
    {
        if (! $assignee->isStaff()) {
            throw ValidationException::withMessages([
                'assignee_id' => ['Tickets can only be assigned to agents or admins.'],
            ]);
        }

        if ($assignee->isAgent() && ! $assignee->belongsToTeam($ticket->team_id)) {
            throw ValidationException::withMessages([
                'assignee_id' => ['The agent is not a member of this ticket\'s team.'],
            ]);
        }
    }
}
