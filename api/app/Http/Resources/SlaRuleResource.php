<?php

namespace App\Http\Resources;

use App\Models\SlaRule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SlaRule
 */
class SlaRuleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'priority' => $this->priority?->value,
            'priority_label' => $this->priority?->label(),
            'response_time_hours' => $this->response_time_hours,
            'is_active' => $this->is_active,
            'updated_at' => $this->updated_at,
        ];
    }
}
