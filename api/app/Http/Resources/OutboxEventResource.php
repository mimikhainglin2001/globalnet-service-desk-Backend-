<?php

namespace App\Http\Resources;

use App\Models\OutboxEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OutboxEvent
 */
class OutboxEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_id' => $this->event_id,
            'event_type' => $this->event_type,
            'aggregate_type' => class_basename($this->aggregate_type),
            'aggregate_id' => $this->aggregate_id,
            'payload' => $this->payload,
            'attempts' => $this->attempts,
            'last_error' => $this->last_error,
            'processed_at' => $this->processed_at,
            'failed_at' => $this->failed_at,
            'created_at' => $this->created_at,
        ];
    }
}
