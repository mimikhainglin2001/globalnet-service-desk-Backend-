<?php

namespace Tests\Feature\Tickets;

use App\Enums\DomainEventType;
use App\Enums\TicketStatus;
use App\Models\OutboxEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketStatusTransitionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedServiceDesk();
    }

    public function test_agent_can_perform_a_valid_transition_and_an_event_is_recorded(): void
    {
        $ticket = $this->ticketFor($this->customer);

        $this->actingAsUser($this->agent)
            ->patchJson("/api/v1/tickets/{$ticket->id}/status", ['status' => 'in_progress'])
            ->assertOk()
            ->assertJsonPath('data.status', 'in_progress');

        $event = OutboxEvent::firstWhere('event_type', DomainEventType::TICKET_STATUS_CHANGED->value);
        $this->assertSame(['open', 'in_progress'], [$event->payload['from'], $event->payload['to']]);
        $this->assertSame($this->agent->id, $event->payload['actor_id']);
    }

    public function test_invalid_transition_is_rejected_by_the_state_machine(): void
    {
        $ticket = $this->ticketFor($this->customer, ['status' => TicketStatus::CLOSED, 'closed_at' => now()]);

        $this->actingAsUser($this->agent)
            ->patchJson("/api/v1/tickets/{$ticket->id}/status", ['status' => 'resolved'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $this->assertSame(TicketStatus::CLOSED, $ticket->fresh()->status);
        $this->assertDatabaseMissing('outbox_events', ['event_type' => DomainEventType::TICKET_STATUS_CHANGED->value]);
    }

    public function test_resolving_sets_resolved_at(): void
    {
        $ticket = $this->ticketFor($this->customer, ['status' => TicketStatus::IN_PROGRESS]);

        $this->actingAsUser($this->agent)
            ->patchJson("/api/v1/tickets/{$ticket->id}/status", ['status' => 'resolved'])
            ->assertOk();

        $this->assertNotNull($ticket->fresh()->resolved_at);
    }

    public function test_customer_can_close_their_own_ticket_but_not_move_it_to_in_progress(): void
    {
        $ticket = $this->ticketFor($this->customer);

        $this->actingAsUser($this->customer)
            ->patchJson("/api/v1/tickets/{$ticket->id}/status", ['status' => 'in_progress'])
            ->assertForbidden();

        $this->patchJson("/api/v1/tickets/{$ticket->id}/status", ['status' => 'closed'])
            ->assertOk()
            ->assertJsonPath('data.status', 'closed');
    }

    public function test_customer_can_reopen_within_the_window(): void
    {
        $ticket = $this->ticketFor($this->customer, ['status' => TicketStatus::CLOSED, 'closed_at' => now()->subDays(6)]);

        $this->actingAsUser($this->customer)
            ->patchJson("/api/v1/tickets/{$ticket->id}/status", ['status' => 'open'])
            ->assertOk()
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.closed_at', null);
    }

    public function test_customer_cannot_reopen_after_the_window(): void
    {
        $days = config('servicedesk.tickets.reopen_window_days');
        $ticket = $this->ticketFor($this->customer, ['status' => TicketStatus::CLOSED, 'closed_at' => now()->subDays($days + 1)]);

        $this->actingAsUser($this->customer)
            ->patchJson("/api/v1/tickets/{$ticket->id}/status", ['status' => 'open'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_customer_sees_only_the_transitions_they_may_request(): void
    {
        $ticket = $this->ticketFor($this->customer, ['status' => TicketStatus::IN_PROGRESS]);

        $this->actingAsUser($this->customer)
            ->getJson("/api/v1/tickets/{$ticket->id}")
            ->assertJsonPath('data.allowed_transitions', ['closed']);

        $this->actingAsUser($this->agent)
            ->getJson("/api/v1/tickets/{$ticket->id}")
            ->assertJsonPath('data.allowed_transitions', ['waiting_on_customer', 'resolved', 'closed']);
    }
}
