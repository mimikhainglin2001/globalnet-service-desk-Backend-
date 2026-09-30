<?php

namespace App\Services;

use App\Contracts\CommentRepositoryInterface;
use App\Enums\CommentType;
use App\Models\Comment;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CommentService
{
    public function __construct(
        private readonly CommentRepositoryInterface $comments,
    ) {}

    public function create(Ticket $ticket, User $author, string $body, CommentType $type): Comment
    {
        return DB::transaction(function () use ($ticket, $author, $body, $type) {
            $comment = $this->comments->create([
                'ticket_id' => $ticket->id,
                'user_id' => $author->id,
                'type' => $type,
                'body' => $body,
            ]);

            // First public staff reply drives the "first response time" metric.
            if ($author->isStaff() && $type === CommentType::PUBLIC && $ticket->first_responded_at === null) {
                Ticket::query()
                    ->whereKey($ticket->id)
                    ->whereNull('first_responded_at')
                    ->update(['first_responded_at' => $comment->created_at]);
            }

            return $comment->load('user');
        });
    }

    /**
     * @return Collection<int, Comment>
     */
    public function listForTicket(Ticket $ticket, User $viewer): Collection
    {
        return $this->comments->getForTicket($ticket, includeInternal: $viewer->isStaff());
    }
}
