<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Demo accounts documented in the README. All share DEMO_USER_PASSWORD.
     */
    private const USERS = [
        ['name' => 'Demo Admin', 'email' => 'admin@globalnet.test', 'role' => Role::ADMIN, 'teams' => []],
        ['name' => 'Aung Agent', 'email' => 'agent@globalnet.test', 'role' => Role::AGENT, 'teams' => ['Technical Support', 'Customer Success']],
        ['name' => 'Bella Agent', 'email' => 'agent2@globalnet.test', 'role' => Role::AGENT, 'teams' => ['Billing Support', 'Customer Success']],
        ['name' => 'Demo Customer', 'email' => 'customer@globalnet.test', 'role' => Role::CUSTOMER, 'teams' => []],
        ['name' => 'Chit Customer', 'email' => 'customer2@globalnet.test', 'role' => Role::CUSTOMER, 'teams' => []],
        ['name' => 'Daw Customer', 'email' => 'customer3@globalnet.test', 'role' => Role::CUSTOMER, 'teams' => []],
    ];

    public function run(): void
    {
        $password = config('servicedesk.demo_password');
        $teams = Team::query()->pluck('id', 'name');

        foreach (self::USERS as $definition) {
            $user = User::firstOrNew(['email' => $definition['email']]);
            $user->fill(['name' => $definition['name'], 'password' => $password]);
            $user->role = $definition['role'];
            $user->email_verified_at ??= now();
            $user->save();

            $user->teams()->sync(
                collect($definition['teams'])->map(fn (string $name) => $teams[$name])->all()
            );
        }
    }
}
