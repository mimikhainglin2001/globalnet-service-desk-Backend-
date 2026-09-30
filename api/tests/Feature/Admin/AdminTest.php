<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Enums\TicketPriority;
use App\Models\AuditLog;
use App\Models\SlaRule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedServiceDesk();
    }

    public function test_changing_a_role_is_audited_and_revokes_tokens(): void
    {
        $this->customer->createToken('session');

        $this->actingAsUser($this->admin)
            ->patchJson("/api/v1/admin/users/{$this->customer->id}", ['role' => 'agent', 'team_ids' => [$this->team->id]])
            ->assertOk()
            ->assertJsonPath('data.role', 'agent')
            ->assertJsonPath('data.teams.0.id', $this->team->id);

        $this->assertSame(0, $this->customer->tokens()->count());

        $log = AuditLog::query()
            ->where('auditable_type', User::class)
            ->where('auditable_id', $this->customer->id)
            ->where('action', 'updated')
            ->sole();

        $this->assertSame($this->admin->id, $log->user_id);
        $this->assertSame(['role' => 'customer'], $log->old_values);
        $this->assertSame(['role' => 'agent'], $log->new_values);
    }

    public function test_admin_cannot_demote_themselves(): void
    {
        $this->actingAsUser($this->admin)
            ->patchJson("/api/v1/admin/users/{$this->admin->id}", ['role' => 'agent'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('role');

        $this->assertSame(Role::ADMIN, $this->admin->fresh()->role);
    }

    public function test_sla_rule_changes_are_audited_and_applied_to_new_tickets(): void
    {
        $rule = SlaRule::firstWhere('priority', TicketPriority::URGENT);

        $this->actingAsUser($this->admin)
            ->patchJson("/api/v1/admin/sla-rules/{$rule->id}", ['response_time_hours' => 2])
            ->assertOk()
            ->assertJsonPath('data.response_time_hours', 2);

        $this->getJson('/api/v1/admin/audit-logs?type=sla_rule')
            ->assertOk()
            ->assertJsonPath('data.0.old_values.response_time_hours', 4)
            ->assertJsonPath('data.0.new_values.response_time_hours', 2)
            ->assertJsonPath('data.0.user.id', $this->admin->id);

        $this->freezeSecond();
        $this->actingAsUser($this->customer)->postJson('/api/v1/tickets', [
            'subject' => 'Down',
            'description' => 'Down',
            'category_id' => $this->category->id,
            'priority' => 'urgent',
        ])->assertJsonPath('data.due_at', now()->addHours(2)->toJSON());
    }

    public function test_ticket_activity_timeline_is_staff_only(): void
    {
        $ticket = $this->ticketFor($this->customer);

        $this->actingAsUser($this->agent)
            ->patchJson("/api/v1/tickets/{$ticket->id}/status", ['status' => 'in_progress'])
            ->assertOk();

        $this->getJson("/api/v1/tickets/{$ticket->id}/activity")
            ->assertOk()
            ->assertJsonPath('data.0.new_values.status', 'in_progress')
            ->assertJsonPath('data.0.user.id', $this->agent->id);

        $this->actingAsUser($this->customer)
            ->getJson("/api/v1/tickets/{$ticket->id}/activity")
            ->assertForbidden();
    }

    public function test_dashboard_reports_first_response_time_and_is_team_scoped(): void
    {
        $ticket = $this->ticketFor($this->customer, ['created_at' => now()->subMinutes(30)]);
        $this->ticketFor($this->customer, ['team_id' => $this->otherTeam->id]);

        $this->actingAsUser($this->agent)
            ->postJson("/api/v1/tickets/{$ticket->id}/comments", ['body' => 'On it!'])
            ->assertCreated();

        $this->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('data.by_status.open', 1)
            ->assertJsonPath('data.avg_first_response_minutes', 30);

        $this->actingAsUser($this->admin)
            ->getJson('/api/v1/dashboard')
            ->assertJsonPath('data.by_status.open', 2);
    }
}
