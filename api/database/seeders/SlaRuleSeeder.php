<?php

namespace Database\Seeders;

use App\Enums\TicketPriority;
use App\Models\SlaRule;
use Illuminate\Database\Seeder;

class SlaRuleSeeder extends Seeder
{
    public function run(): void
    {
        foreach (TicketPriority::cases() as $priority) {
            SlaRule::updateOrCreate(
                ['priority' => $priority],
                [
                    'response_time_hours' => config("servicedesk.sla.fallback_hours.{$priority->value}"),
                    'is_active' => true,
                ],
            );
        }
    }
}
