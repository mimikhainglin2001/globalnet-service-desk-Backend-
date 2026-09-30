<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Safe to re-run (e.g. in production): reference data and demo users are
 * upserted, sample tickets are only created on an empty database.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            SlaRuleSeeder::class,
            TeamAndCategorySeeder::class,
            UserSeeder::class,
            TicketSeeder::class,
        ]);
    }
}
