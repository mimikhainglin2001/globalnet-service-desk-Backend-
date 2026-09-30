<?php

namespace App\Repositories;

use App\Contracts\TicketRepositoryInterface;
use App\DTOs\TicketFilters;
use App\Enums\TicketPriority;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class TicketRepository implements TicketRepositoryInterface
{
    private const LIST_RELATIONS = ['requester', 'assignee', 'team', 'category'];

    public function findForDisplay(int $id): ?Ticket
    {
        return Ticket::query()
            ->with([...self::LIST_RELATIONS, 'attachments.user'])
            ->find($id);
    }

    public function findForUpdate(int $id): Ticket
    {
        return Ticket::query()->lockForUpdate()->findOrFail($id);
    }

    public function create(array $data): Ticket
    {
        return Ticket::create($data);
    }

    public function update(Ticket $ticket, array $data): Ticket
    {
        $ticket->update($data);

        return $ticket;
    }

    public function paginateVisibleTo(User $user, TicketFilters $filters): LengthAwarePaginator
    {
        $query = Ticket::query()
            ->visibleTo($user)
            ->with(self::LIST_RELATIONS);

        $this->applyFilters($query, $filters);
        $this->applySort($query, $filters);

        return $query->paginate($filters->perPage)->withQueryString();
    }

    private function applyFilters(Builder $query, TicketFilters $filters): void
    {
        if ($filters->search !== null && $filters->search !== '') {
            $this->applySearch($query, $filters->search);
        }

        $query
            ->when($filters->status, fn (Builder $q, $status) => $q->where('status', $status))
            ->when($filters->priority, fn (Builder $q, $priority) => $q->where('priority', $priority))
            ->when($filters->categoryId, fn (Builder $q, $id) => $q->where('category_id', $id))
            ->when($filters->teamId, fn (Builder $q, $id) => $q->where('team_id', $id))
            ->when($filters->assigneeId, fn (Builder $q, $id) => $q->where('assignee_id', $id))
            ->when($filters->unassigned, fn (Builder $q) => $q->whereNull('assignee_id'))
            ->when($filters->createdFrom, fn (Builder $q, $from) => $q->where('created_at', '>=', $from))
            ->when($filters->createdTo, fn (Builder $q, $to) => $q->where('created_at', '<=', $to))
            ->when($filters->overdueOnly, fn (Builder $q) => $q->overdue());
    }

    /**
     * MySQL uses the FULLTEXT index on subject/description; other drivers
     * (SQLite in tests) fall back to LIKE. The reference is always matched
     * by prefix so "GN-0001" finds tickets quickly via its unique index.
     */
    private function applySearch(Builder $query, string $search): void
    {
        $like = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);

        $query->where(function (Builder $query) use ($search, $like) {
            $query->where('reference', 'like', "{$like}%");

            if (DB::getDriverName() === 'mysql') {
                $query->orWhereFullText(['subject', 'description'], $search);
            } else {
                $query->orWhere('subject', 'like', "%{$like}%")
                    ->orWhere('description', 'like', "%{$like}%");
            }
        });
    }

    private function applySort(Builder $query, TicketFilters $filters): void
    {
        $direction = $filters->direction === 'asc' ? 'asc' : 'desc';
        $sort = in_array($filters->sort, TicketFilters::SORTABLE, true) ? $filters->sort : 'created_at';

        if ($sort === 'priority') {
            $cases = collect(TicketPriority::cases())
                ->map(fn (TicketPriority $p) => "WHEN '{$p->value}' THEN {$p->weight()}")
                ->implode(' ');

            $query->orderByRaw("CASE priority {$cases} END {$direction}");
        } else {
            $query->orderBy($sort, $direction);
        }

        // Stable ordering for pagination.
        $query->orderBy('id', $direction);
    }
}
