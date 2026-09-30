<?php

namespace App\Services;

use App\Enums\TicketPriority;
use App\Models\SlaRule;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class SlaService
{
    public function hoursFor(TicketPriority $priority): ?int
    {
        $rule = SlaRule::query()
            ->where('priority', $priority)
            ->where('is_active', true)
            ->first();

        return $rule?->response_time_hours
            ?? config("servicedesk.sla.fallback_hours.{$priority->value}");
    }

    public function calculateDueAt(TicketPriority $priority, ?CarbonInterface $from = null): ?Carbon
    {
        $hours = $this->hoursFor($priority);

        if ($hours === null) {
            return null;
        }

        return Carbon::instance($from ?? now())->addHours($hours);
    }
}
