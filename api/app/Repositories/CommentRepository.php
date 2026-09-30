<?php

namespace App\Repositories;

use App\Contracts\CommentRepositoryInterface;
use App\Models\Comment;
use App\Models\Ticket;
use Illuminate\Support\Collection;

class CommentRepository implements CommentRepositoryInterface
{
    public function create(array $data): Comment
    {
        return Comment::create($data);
    }

    public function getForTicket(Ticket $ticket, bool $includeInternal = false): Collection
    {
        return $ticket->comments()
            ->with('user')
            ->unless($includeInternal, fn ($query) => $query->public())
            ->oldest()
            ->oldest('id')
            ->get();
    }
}
