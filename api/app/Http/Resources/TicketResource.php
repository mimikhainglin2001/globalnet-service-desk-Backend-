<?php

namespace App\Http\Resources;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Ticket
 */
class TicketResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'subject' => $this->subject,
            'description' => $this->description,

            'priority' => $this->priority?->value,
            'priority_label' => $this->priority?->label(),
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'allowed_transitions' => $this->allowedTransitionsFor($request),

            'requester' => new UserResource($this->whenLoaded('requester')),
            'assignee' => new UserResource($this->whenLoaded('assignee')),
            'team' => new TeamResource($this->whenLoaded('team')),
            'category' => new CategoryResource($this->whenLoaded('category')),
            'attachments' => AttachmentResource::collection($this->whenLoaded('attachments')),

            'is_overdue' => $this->isOverdue(),
            'due_at' => $this->due_at,
            'sla_breached_at' => $this->sla_breached_at,
            'first_responded_at' => $this->first_responded_at,
            'resolved_at' => $this->resolved_at,
            'closed_at' => $this->closed_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * Transitions the current user may request, as a UI hint only; the
     * server re-checks on every status change.
     *
     * @return list<string>
     */
    private function allowedTransitionsFor(Request $request): array
    {
        $user = $request->user();

        if ($user === null || $this->status === null) {
            return [];
        }

        return collect($this->status->allowedTransitions())
            ->filter(fn (TicketStatus $target) => $user->can('changeStatus', [$this->resource, $target]))
            ->map(fn (TicketStatus $target) => $target->value)
            ->values()
            ->all();
    }
}
