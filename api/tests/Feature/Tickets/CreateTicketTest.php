<?php

namespace Tests\Feature\Tickets;

use App\Enums\DomainEventType;
use App\Models\Category;
use App\Models\OutboxEvent;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CreateTicketTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedServiceDesk();
        Storage::fake(config('servicedesk.attachments.disk'));
    }

    private function payload(array $overrides = []): array
    {
        return [
            'subject' => 'Internet is down',
            'description' => 'No connectivity since this morning.',
            'category_id' => $this->category->id,
            'priority' => 'urgent',
            ...$overrides,
        ];
    }

    public function test_ticket_gets_reference_sla_due_date_team_routing_and_outbox_event(): void
    {
        $this->freezeSecond();

        $response = $this->actingAsUser($this->customer)
            ->postJson('/api/v1/tickets', $this->payload(['team_id' => $this->otherTeam->id]))
            ->assertCreated();

        $ticket = Ticket::sole();

        $this->assertMatchesRegularExpression('/^GN-\d{6}$/', $ticket->reference);
        $response->assertJsonPath('data.reference', $ticket->reference);

        // Urgent SLA = 4h; customers cannot choose the team, the category routes it.
        $this->assertTrue($ticket->due_at->equalTo(now()->addHours(4)));
        $this->assertSame($this->team->id, $ticket->team_id);

        $event = OutboxEvent::sole();
        $this->assertSame(DomainEventType::TICKET_CREATED->value, $event->event_type);
        $this->assertSame($ticket->id, $event->aggregate_id);
    }

    public function test_inactive_categories_are_rejected(): void
    {
        $inactive = Category::factory()->inactive()->create();

        $this->actingAsUser($this->customer)
            ->postJson('/api/v1/tickets', $this->payload(['category_id' => $inactive->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('category_id');
    }

    public function test_same_idempotency_key_and_payload_returns_the_original_response(): void
    {
        $this->actingAsUser($this->customer);
        $headers = ['Idempotency-Key' => 'create-ticket-123'];

        $first = $this->postJson('/api/v1/tickets', $this->payload(), $headers)
            ->assertCreated()
            ->assertHeader('Idempotent-Replayed', 'false');

        $second = $this->postJson('/api/v1/tickets', $this->payload(), $headers)
            ->assertCreated()
            ->assertHeader('Idempotent-Replayed', 'true');

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertDatabaseCount('tickets', 1);
        $this->assertDatabaseCount('outbox_events', 1);
    }

    public function test_reusing_an_idempotency_key_with_a_different_payload_is_a_conflict(): void
    {
        $this->actingAsUser($this->customer);
        $headers = ['Idempotency-Key' => 'create-ticket-123'];

        $this->postJson('/api/v1/tickets', $this->payload(), $headers)->assertCreated();

        $this->postJson('/api/v1/tickets', $this->payload(['subject' => 'Different']), $headers)
            ->assertStatus(409)
            ->assertJsonPath('code', 'conflict');

        $this->assertDatabaseCount('tickets', 1);
    }

    public function test_idempotency_keys_are_scoped_per_user(): void
    {
        $headers = ['Idempotency-Key' => 'shared-key'];

        $this->actingAsUser($this->customer)->postJson('/api/v1/tickets', $this->payload(), $headers)->assertCreated();
        $this->actingAsUser($this->otherCustomer)->postJson('/api/v1/tickets', $this->payload(), $headers)
            ->assertCreated()
            ->assertHeader('Idempotent-Replayed', 'false');

        $this->assertDatabaseCount('tickets', 2);
    }

    public function test_expired_idempotency_key_creates_a_new_ticket(): void
    {
        $this->actingAsUser($this->customer);
        $headers = ['Idempotency-Key' => 'old-key'];

        $this->postJson('/api/v1/tickets', $this->payload(), $headers)->assertCreated();

        $this->travel(config('servicedesk.idempotency.ttl_hours') + 1)->hours();

        $this->postJson('/api/v1/tickets', $this->payload(), $headers)
            ->assertCreated()
            ->assertHeader('Idempotent-Replayed', 'false');

        $this->assertDatabaseCount('tickets', 2);
    }

    public function test_valid_attachments_are_stored_privately(): void
    {
        $response = $this->actingAsUser($this->customer)
            ->post('/api/v1/tickets', $this->payload([
                'attachments' => [
                    UploadedFile::fake()->create('screenshot.png', 100, 'image/png'),
                    UploadedFile::fake()->create('invoice.pdf', 200, 'application/pdf'),
                ],
            ]), ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonCount(2, 'data.attachments');

        $path = Ticket::sole()->attachments()->first()->file_path;
        Storage::disk(config('servicedesk.attachments.disk'))->assertExists($path);
        $this->assertStringNotContainsString('screenshot', $path);

        $this->assertNotNull($response->json('data.attachments.0.download_url'));
    }

    public function test_attachment_type_size_and_count_are_validated(): void
    {
        $this->actingAsUser($this->customer);
        $maxKb = config('servicedesk.attachments.max_size_kb');
        $maxFiles = config('servicedesk.attachments.max_files');

        $this->postJson('/api/v1/tickets', $this->payload([
            'attachments' => [UploadedFile::fake()->create('malware.exe', 10, 'application/x-msdownload')],
        ]))->assertJsonValidationErrors('attachments.0');

        $this->postJson('/api/v1/tickets', $this->payload([
            'attachments' => [UploadedFile::fake()->create('huge.pdf', $maxKb + 1, 'application/pdf')],
        ]))->assertJsonValidationErrors('attachments.0');

        $this->postJson('/api/v1/tickets', $this->payload([
            'attachments' => array_map(
                fn ($i) => UploadedFile::fake()->create("file{$i}.pdf", 10, 'application/pdf'),
                range(0, $maxFiles),
            ),
        ]))->assertJsonValidationErrors('attachments');

        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_list_filters_by_status_and_overdue(): void
    {
        $overdue = $this->ticketFor($this->customer, ['due_at' => now()->subHour()]);
        $this->ticketFor($this->customer, ['due_at' => now()->addDay()]);

        $this->actingAsUser($this->agent)
            ->getJson('/api/v1/tickets?overdue=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $overdue->id)
            ->assertJsonPath('data.0.is_overdue', true);

        $this->getJson('/api/v1/tickets?status=resolved')->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/tickets?status=bogus')->assertJsonValidationErrors('status');
    }
}
