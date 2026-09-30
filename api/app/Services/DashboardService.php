<?php

namespace App\Services;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Aggregates are computed in SQL and scoped with the same visibility rule
 * as the ticket list, so an agent only sees numbers for their teams.
 */
class DashboardService
{
    public function statsFor(User $user): array
    {
        $resolvedWindow = config('servicedesk.dashboard.resolved_window_days');

        return [
            'by_status' => $this->countBy($user, 'status', TicketStatus::cases()),
            'open_by_priority' => $this->countBy($user, 'priority', TicketPriority::cases(), activeOnly: true),
            'overdue' => $this->scoped($user)->overdue()->count(),
            'unassigned_open' => $this->scoped($user)->whereIn('status', TicketStatus::active())->whereNull('assignee_id')->count(),
            'assigned_to_me_open' => $this->scoped($user)->whereIn('status', TicketStatus::active())->where('assignee_id', $user->id)->count(),
            'avg_first_response_minutes' => $this->averageFirstResponseMinutes($user),
            'resolved_last_days' => $resolvedWindow,
            'resolved_recently' => $this->scoped($user)->where('resolved_at', '>=', now()->subDays($resolvedWindow))->count(),
        ];
    }

    private function scoped(User $user): Builder
    {
        return Ticket::query()->visibleTo($user);
    }

    /**
     * @param  list<\BackedEnum>  $cases
     * @return array<string, int> zero-filled for every case
     */
    private function countBy(User $user, string $column, array $cases, bool $activeOnly = false): array
    {
        $counts = $this->scoped($user)
            ->when($activeOnly, fn (Builder $q) => $q->whereIn('status', TicketStatus::active()))
            ->select($column, DB::raw('COUNT(*) as aggregate'))
            ->groupBy($column)
            ->pluck('aggregate', $column);

        return collect($cases)
            ->mapWithKeys(fn (\BackedEnum $case) => [$case->value => (int) ($counts[$case->value] ?? 0)])
            ->all();
    }

    private function averageFirstResponseMinutes(User $user): ?float
    {
        $seconds = match (DB::getDriverName()) {
            'mysql', 'mariadb' => 'TIMESTAMPDIFF(SECOND, created_at, first_responded_at)',
            'pgsql' => 'EXTRACT(EPOCH FROM (first_responded_at - created_at))',
            default => '(julianday(first_responded_at) - julianday(created_at)) * 86400',
        };

        $average = $this->scoped($user)
            ->whereNotNull('first_responded_at')
            ->avg(DB::raw($seconds));

        return $average === null ? null : round($average / 60, 1);
    }
}
