<?php

namespace App\Contracts;

use App\DTOs\TicketFilters;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface TicketRepositoryInterface
{
    public function findForDisplay(int $id): ?Ticket;

    /**
     * Fetch a row with a pessimistic lock. Must be called inside a transaction.
     */
    public function findForUpdate(int $id): Ticket;

    public function create(array $data): Ticket;

    public function update(Ticket $ticket, array $data): Ticket;

    public function paginateVisibleTo(User $user, TicketFilters $filters): LengthAwarePaginator;
}
