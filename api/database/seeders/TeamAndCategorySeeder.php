<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Team;
use Illuminate\Database\Seeder;

class TeamAndCategorySeeder extends Seeder
{
    /**
     * Team => categories routed to that team by default.
     */
    private const ROUTING = [
        'Technical Support' => [
            'description' => 'Connectivity, outages and technical incidents.',
            'categories' => [
                'Technical Issue' => 'Technical problems and incidents.',
                'Network Outage' => 'Loss of connectivity or degraded service.',
            ],
        ],
        'Billing Support' => [
            'description' => 'Invoices, payments and plan changes.',
            'categories' => [
                'Billing' => 'Billing and payment related requests.',
                'Plan Change' => 'Upgrade, downgrade or cancel a service plan.',
            ],
        ],
        'Customer Success' => [
            'description' => 'Accounts, access and general enquiries.',
            'categories' => [
                'Account' => 'Account and access requests.',
                'General Enquiry' => 'Anything else.',
            ],
        ],
    ];

    public function run(): void
    {
        foreach (self::ROUTING as $teamName => $definition) {
            $team = Team::updateOrCreate(
                ['name' => $teamName],
                ['description' => $definition['description']],
            );

            foreach ($definition['categories'] as $name => $description) {
                Category::updateOrCreate(
                    ['name' => $name],
                    ['description' => $description, 'team_id' => $team->id, 'is_active' => true],
                );
            }
        }
    }
}
