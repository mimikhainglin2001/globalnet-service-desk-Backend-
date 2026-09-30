<?php

namespace Tests\Feature\Tickets;

use App\Enums\CommentType;
use App\Models\Comment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedServiceDesk();
    }

    public function test_customer_lists_only_their_own_tickets(): void
    {
        $own = $this->ticketFor($this->customer);
        $this->ticketFor($this->otherCustomer);

        $this->actingAsUser($this->customer)
            ->getJson('/api/v1/tickets')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $own->id);
    }

    public function test_customer_cannot_view_another_customers_ticket(): void
    {
        $ticket = $this->ticketFor($this->otherCustomer);

        $this->actingAsUser($this->customer)
            ->getJson("/api/v1/tickets/{$ticket->id}")
            ->assertForbidden()
            ->assertJsonPath('code', 'forbidden');

        $this->getJson("/api/v1/tickets/{$ticket->id}/comments")->assertForbidden();
    }

    public function test_customer_never_receives_internal_notes(): void
    {
        $ticket = $this->ticketFor($this->customer);
        Comment::factory()->for($ticket)->for($this->agent)->create(['body' => 'Public reply']);
        Comment::factory()->internal()->for($ticket)->for($this->agent)->create(['body' => 'Secret note']);

        $this->actingAsUser($this->customer)
            ->getJson("/api/v1/tickets/{$ticket->id}/comments")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.body', 'Public reply')
            ->assertDontSee('Secret note');

        $this->actingAsUser($this->agent)
            ->getJson("/api/v1/tickets/{$ticket->id}/comments")
            ->assertJsonCount(2, 'data');
    }

    public function test_customer_cannot_post_an_internal_note(): void
    {
        $ticket = $this->ticketFor($this->customer);

        $this->actingAsUser($this->customer)
            ->postJson("/api/v1/tickets/{$ticket->id}/comments", ['body' => 'hi', 'type' => CommentType::INTERNAL->value])
            ->assertForbidden();

        $this->assertDatabaseCount('comments', 0);
    }

    public function test_agent_only_sees_tickets_of_their_teams(): void
    {
        $visible = $this->ticketFor($this->customer);
        $hidden = $this->ticketFor($this->customer, ['team_id' => $this->otherTeam->id]);

        $this->actingAsUser($this->agent)
            ->getJson('/api/v1/tickets')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $visible->id);

        $this->getJson("/api/v1/tickets/{$hidden->id}")->assertForbidden();
    }

    public function test_agent_can_self_assign_but_not_assign_someone_else(): void
    {
        $ticket = $this->ticketFor($this->customer);
        $teammate = User::factory()->agent()->inTeam($this->team)->create();

        $this->actingAsUser($this->agent)
            ->patchJson("/api/v1/tickets/{$ticket->id}/assign", ['assignee_id' => $teammate->id])
            ->assertForbidden();

        $this->patchJson("/api/v1/tickets/{$ticket->id}/assign", ['assignee_id' => $this->agent->id])
            ->assertOk()
            ->assertJsonPath('data.assignee.id', $this->agent->id);
    }

    public function test_admin_cannot_assign_an_agent_from_another_team(): void
    {
        $ticket = $this->ticketFor($this->customer);

        $this->actingAsUser($this->admin)
            ->patchJson("/api/v1/tickets/{$ticket->id}/assign", ['assignee_id' => $this->otherAgent->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('assignee_id');
    }

    public function test_customer_cannot_change_triage_fields(): void
    {
        $ticket = $this->ticketFor($this->customer);

        $this->actingAsUser($this->customer)
            ->patchJson("/api/v1/tickets/{$ticket->id}", ['priority' => 'urgent'])
            ->assertForbidden();
    }

    public function test_admin_and_staff_only_endpoints_are_protected(): void
    {
        $this->actingAsUser($this->customer)->getJson('/api/v1/dashboard')->assertForbidden();
        $this->actingAsUser($this->customer)->getJson('/api/v1/agents')->assertForbidden();
        $this->actingAsUser($this->agent)->getJson('/api/v1/admin/users')->assertForbidden();
        $this->actingAsUser($this->agent)->getJson('/api/v1/admin/audit-logs')->assertForbidden();

        $this->actingAsUser($this->agent)->getJson('/api/v1/dashboard')->assertOk();
        $this->actingAsUser($this->admin)->getJson('/api/v1/admin/audit-logs')->assertOk();
    }

    public function test_unknown_ticket_returns_404_without_leaking_the_model_name(): void
    {
        $this->actingAsUser($this->admin)
            ->getJson('/api/v1/tickets/999999')
            ->assertNotFound()
            ->assertExactJson(['message' => 'Resource not found.', 'code' => 'not_found']);
    }
}
