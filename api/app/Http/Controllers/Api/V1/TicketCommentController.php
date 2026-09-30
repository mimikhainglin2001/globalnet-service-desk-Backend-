<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateCommentRequest;
use App\Http\Resources\CommentResource;
use App\Models\Ticket;
use App\Services\CommentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TicketCommentController extends Controller
{
    public function __construct(
        private readonly CommentService $commentService,
    ) {}

    /**
     * Internal notes are filtered out in the query for customers, not in the UI.
     */
    public function index(Request $request, Ticket $ticket): AnonymousResourceCollection
    {
        $this->authorize('view', $ticket);

        return CommentResource::collection(
            $this->commentService->listForTicket($ticket, $request->user())
        );
    }

    public function store(CreateCommentRequest $request, Ticket $ticket): JsonResponse
    {
        $type = $request->type();

        $this->authorize('comment', [$ticket, $type]);

        $comment = $this->commentService->create($ticket, $request->user(), $request->validated('body'), $type);

        return (new CommentResource($comment))->response()->setStatusCode(201);
    }
}
