<?php

namespace Tests\Feature\Reliability;

use App\Enums\DomainEventType;
use App\Events\TicketCreated;
use App\Jobs\ProcessOutboxEvent;
use App\Models\OutboxEvent;
use App\Services\OutboxEventProcessor;
use App\Services\OutboxService;
use App\Services\TicketNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use LogicException;
use Tests\TestCase;

class OutboxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedServiceDesk();
    }

    public function test_ticket_creation_notifies_requester_and_team_via_the_outbox(): void
    {
        // QUEUE_CONNECTION=sync: the job runs right after the transaction commits.
        $this->actingAsUser($this->customer)->postJson('/api/v1/tickets', [
            'subject' => 'Help',
            'description' => 'Please',
            'category_id' => $this->category->id,
        ])->assertCreated();

        $event = OutboxEvent::sole();
        $this->assertNotNull($event->processed_at);
        $this->assertDatabaseHas('processed_events', ['event_id' => $event->event_id]);

        $this->assertSame(1, $this->customer->notifications()->count());
        $this->assertSame(1, $this->agent->notifications()->count());
        $this->assertSame(0, $this->otherAgent->notifications()->count());
        $this->assertSame(DomainEventType::TICKET_CREATED->value, $this->agent->notifications()->first()->type);
    }

    public function test_processing_the_same_event_twice_does_not_notify_twice(): void
    {
        Queue::fake();
        $ticket = $this->ticketFor($this->customer);
        $event = DB::transaction(fn () => app(OutboxService::class)->record(new TicketCreated($ticket)));

        $processor = app(OutboxEventProcessor::class);
        $processor->process($event->id);

        // Simulate a redelivery where the outbox row was not marked processed:
        // the processed_events claim still prevents a second notification.
        $event->update(['processed_at' => null]);
        $processor->process($event->id);
        $processor->process($event->id);

        $this->assertSame(1, $this->customer->notifications()->count());
        $this->assertDatabaseCount('processed_events', 1);
        $this->assertNotNull($event->fresh()->processed_at);
    }

    public function test_outbox_events_can_only_be_recorded_inside_a_transaction(): void
    {
        $ticket = $this->ticketFor($this->customer);

        // RefreshDatabase wraps every test in a transaction, so simulate "none".
        DB::shouldReceive('transactionLevel')->andReturn(0);

        $this->expectException(LogicException::class);

        app(OutboxService::class)->record(new TicketCreated($ticket));
    }

    public function test_a_failing_event_is_retried_then_dead_lettered_and_admin_can_retry_it(): void
    {
        Queue::fake();
        $ticket = $this->ticketFor($this->customer);
        $event = DB::transaction(fn () => app(OutboxService::class)->record(new TicketCreated($ticket)));

        $this->mock(TicketNotificationService::class)
            ->shouldReceive('notify')->andThrow(new \RuntimeException('SMTP down'));

        $job = new ProcessOutboxEvent($event->id);
        $this->assertSame(config('servicedesk.outbox.tries'), $job->tries);
        $this->assertSame(config('servicedesk.outbox.backoff_seconds'), $job->backoff());

        try {
            app()->call([$job, 'handle']);
            $this->fail('Expected the handler to throw.');
        } catch (\RuntimeException) {
            // The failed attempt is rolled back: nothing marked as processed.
        }
        $this->assertDatabaseCount('processed_events', 0);
        $this->assertSame(1, $event->fresh()->attempts);

        // Laravel calls failed() after the final attempt.
        $job->failed(new \RuntimeException('SMTP down'));
        $this->assertNotNull($event->fresh()->failed_at);

        $this->actingAsUser($this->admin)
            ->getJson('/api/v1/admin/outbox-events?status=failed')
            ->assertOk()
            ->assertJsonPath('data.0.id', $event->id)
            ->assertJsonPath('data.0.last_error', 'SMTP down');

        $this->postJson("/api/v1/admin/outbox-events/{$event->id}/retry")->assertOk();

        $this->assertNull($event->fresh()->failed_at);
        Queue::assertPushed(ProcessOutboxEvent::class, fn ($job) => $job->outboxEventId === $event->id);
    }

    public function test_relay_dispatches_only_stale_pending_events(): void
    {
        Queue::fake();
        $ticket = $this->ticketFor($this->customer);
        $service = app(OutboxService::class);

        [$stale, $fresh, $processed, $failed] = DB::transaction(fn () => [
            $service->record(new TicketCreated($ticket)),
            $service->record(new TicketCreated($ticket)),
            $service->record(new TicketCreated($ticket)),
            $service->record(new TicketCreated($ticket)),
        ]);
        Queue::fake(); // Reset the after-commit dispatches.

        $grace = config('servicedesk.outbox.relay_grace_seconds');
        OutboxEvent::whereKey([$stale->id, $processed->id, $failed->id])->update(['created_at' => now()->subSeconds($grace + 5)]);
        $processed->update(['processed_at' => now()]);
        $failed->update(['failed_at' => now()]);

        $this->artisan('outbox:dispatch')->assertSuccessful();

        Queue::assertPushed(ProcessOutboxEvent::class, 1);
        Queue::assertPushed(ProcessOutboxEvent::class, fn ($job) => $job->outboxEventId === $stale->id);
    }
}
