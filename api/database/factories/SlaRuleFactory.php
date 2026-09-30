<?php

namespace Database\Factories;

use App\Enums\TicketPriority;
use App\Models\SlaRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SlaRule>
 */
class SlaRuleFactory extends Factory
{
    public function definition(): array
    {
        $priority = fake()->unique()->randomElement(TicketPriority::cases());

        return [
            'priority' => $priority,
            'response_time_hours' => config("servicedesk.sla.fallback_hours.{$priority->value}"),
            'is_active' => true,
        ];
    }
}
