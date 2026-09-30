<?php

namespace Tests;

use App\Models\Category;
use App\Models\Team;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\SlaRuleSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Sanctum\Sanctum;

abstract class TestCase extends BaseTestCase
{
    protected Team $team;

    protected Team $otherTeam;

    protected Category $category;

    protected User $admin;

    protected User $agent;

    protected User $otherAgent;

    protected User $customer;

    protected User $otherCustomer;

    /**
     * A small but complete world: two teams, an agent in each, two customers,
     * an admin, a category routed to the first team and the SLA rules.
     */
    protected function seedServiceDesk(): void
    {
        $this->seed(SlaRuleSeeder::class);

        $this->team = Team::factory()->create(['name' => 'Technical Support']);
        $this->otherTeam = Team::factory()->create(['name' => 'Billing Support']);
        $this->category = Category::factory()->create(['team_id' => $this->team->id]);

        $this->admin = User::factory()->admin()->create();
        $this->agent = User::factory()->agent()->inTeam($this->team)->create();
        $this->otherAgent = User::factory()->agent()->inTeam($this->otherTeam)->create();
        $this->customer = User::factory()->customer()->create();
        $this->otherCustomer = User::factory()->customer()->create();
    }

    protected function ticketFor(User $requester, array $attributes = []): Ticket
    {
        return Ticket::factory()->create([
            'requester_id' => $requester->id,
            'category_id' => $this->category->id,
            'team_id' => $this->team->id,
            ...$attributes,
        ]);
    }

    protected function actingAsUser(User $user): static
    {
        Sanctum::actingAs($user);

        return $this;
    }
}
