<?php

namespace Tests\Feature\Reliability;

use App\Enums\DomainEventType;
use App\Enums\TicketStatus;
use App\Models\OutboxEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SlaBreachCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedServiceDesk();
    }

    public function test_overdue_active_tickets_are_flagged_once_and_escalated(): void
    {
        $breached = $this->ticketFor($this->customer, ['due_at' => now()->subMinutes(10), 'assignee_id' => $this->agent->id]);
        $onTime = $this->ticketFor($this->customer, ['due_at' => now()->addHour()]);
        $resolved = $this->ticketFor($this->customer, ['due_at' => now()->subHour(), 'status' => TicketStatus::RESOLVED]);

        $this->artisan('sla:check')->expectsOutput('Flagged 1 SLA breaches.')->assertSuccessful();
        $this->artisan('sla:check')->expectsOutput('Flagged 0 SLA breaches.')->assertSuccessful();

        $this->assertNotNull($breached->fresh()->sla_breached_at);
        $this->assertNull($onTime->fresh()->sla_breached_at);
        $this->assertNull($resolved->fresh()->sla_breached_at);

        $events = OutboxEvent::where('event_type', DomainEventType::TICKET_SLA_BREACHED->value)->get();
        $this->assertCount(1, $events);
        $this->assertSame($breached->id, $events->first()->aggregate_id);

        // Assignee and admins are notified; the customer is not.
        $this->assertSame(1, $this->agent->notifications()->where('type', DomainEventType::TICKET_SLA_BREACHED->value)->count());
        $this->assertSame(1, $this->admin->notifications()->count());
        $this->assertSame(0, $this->customer->notifications()->count());
    }

    public function test_changing_priority_recalculates_the_due_date_and_clears_the_breach(): void
    {
        $ticket = $this->ticketFor($this->customer, [
            'priority' => 'urgent',
            'due_at' => now()->subHour(),
            'sla_breached_at' => now()->subMinutes(30),
        ]);

        $this->actingAsUser($this->agent)
            ->patchJson("/api/v1/tickets/{$ticket->id}", ['priority' => 'low'])
            ->assertOk()
            ->assertJsonPath('data.priority', 'low');

        $ticket->refresh();
        $this->assertTrue($ticket->due_at->equalTo($ticket->created_at->copy()->addHours(72)));
        $this->assertNull($ticket->sla_breached_at);
    }

    public function test_sla_command_is_scheduled_every_five_minutes(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event) => str_contains($event->command ?? '', 'sla:check'));

        $this->assertNotNull($event);
        $this->assertSame('*/5 * * * *', $event->expression);
    }
}
