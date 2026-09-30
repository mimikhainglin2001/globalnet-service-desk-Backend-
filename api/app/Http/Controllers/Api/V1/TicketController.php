<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\AssignTicketRequest;
use App\Http\Requests\ChangeTicketStatusRequest;
use App\Http\Requests\CreateTicketRequest;
use App\Http\Requests\ListTicketsRequest;
use App\Http\Requests\UpdateTicketRequest;
use App\Http\Resources\TicketResource;
use App\Models\Ticket;
use App\Services\TicketService;
use App\Services\TicketStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TicketController extends Controller
{
    public function __construct(
        private readonly TicketService $ticketService,
        private readonly TicketStatusService $ticketStatusService,
    ) {}

    public function index(ListTicketsRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Ticket::class);

        return TicketResource::collection(
            $this->ticketService->list($request->user(), $request->toDto())
        );
    }

    /**
     * Supports an optional Idempotency-Key header: the same key and payload
     * within the TTL returns the original response instead of a duplicate.
     */
    public function store(CreateTicketRequest $request): JsonResponse
    {
        $this->authorize('create', Ticket::class);

        $key = $request->idempotencyKey();

        if ($key === null) {
            $ticket = $this->ticketService->create($request->user(), $request->toDto());

            return (new TicketResource($ticket))
                ->additional(['message' => 'Ticket created successfully.'])
                ->response()
                ->setStatusCode(201);
        }

        $result = $this->ticketService->createIdempotent($request->user(), $request->toDto(), $key);

        return response()
            ->json($result['body'], $result['status'])
            ->header('Idempotent-Replayed', $result['replayed'] ? 'true' : 'false');
    }

    public function show(Ticket $ticket): TicketResource
    {
        $this->authorize('view', $ticket);

        return new TicketResource($this->ticketService->findForDisplay($ticket->id));
    }

    public function update(UpdateTicketRequest $request, Ticket $ticket): TicketResource
    {
        $this->authorize('update', $ticket);

        return new TicketResource($this->ticketService->update($ticket, $request->validated()));
    }

    public function assign(AssignTicketRequest $request, Ticket $ticket): TicketResource
    {
        $assignee = $request->assignee();

        $this->authorize('assign', [$ticket, $assignee]);

        return new TicketResource($this->ticketService->assign($ticket, $request->user(), $assignee));
    }

    public function changeStatus(ChangeTicketStatusRequest $request, Ticket $ticket): TicketResource
    {
        $status = $request->status();

        $this->authorize('changeStatus', [$ticket, $status]);

        return new TicketResource(
            $this->ticketStatusService->changeStatus($ticket, $status, $request->user())
        );
    }
}
