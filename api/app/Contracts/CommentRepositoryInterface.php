<?php

namespace App\Contracts;

use App\Models\Comment;
use App\Models\Ticket;
use Illuminate\Support\Collection;

interface CommentRepositoryInterface
{
    public function create(array $data): Comment;

    /**
     * @return Collection<int, Comment>
     */
    public function getForTicket(Ticket $ticket, bool $includeInternal = false): Collection;
}
