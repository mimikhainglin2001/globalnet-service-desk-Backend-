<?php

namespace App\DTOs;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use Illuminate\Support\Carbon;

final readonly class TicketFilters
{
    public const SORTABLE = ['created_at', 'updated_at', 'due_at', 'priority', 'status', 'reference'];

    public function __construct(
        public ?string $search = null,
        public ?TicketStatus $status = null,
        public ?TicketPriority $priority = null,
        public ?int $categoryId = null,
        public ?int $teamId = null,
        public ?int $assigneeId = null,
        public bool $unassigned = false,
        public ?Carbon $createdFrom = null,
        public ?Carbon $createdTo = null,
        public bool $overdueOnly = false,
        public string $sort = 'created_at',
        public string $direction = 'desc',
        public int $perPage = 15,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            search: $data['search'] ?? null,
            status: isset($data['status']) ? TicketStatus::from($data['status']) : null,
            priority: isset($data['priority']) ? TicketPriority::from($data['priority']) : null,
            categoryId: isset($data['category_id']) ? (int) $data['category_id'] : null,
            teamId: isset($data['team_id']) ? (int) $data['team_id'] : null,
            assigneeId: isset($data['assignee_id']) ? (int) $data['assignee_id'] : null,
            unassigned: (bool) ($data['unassigned'] ?? false),
            createdFrom: isset($data['created_from']) ? Carbon::parse($data['created_from'])->startOfDay() : null,
            createdTo: isset($data['created_to']) ? Carbon::parse($data['created_to'])->endOfDay() : null,
            overdueOnly: (bool) ($data['overdue'] ?? false),
            sort: $data['sort'] ?? 'created_at',
            direction: $data['direction'] ?? 'desc',
            perPage: (int) ($data['per_page'] ?? config('servicedesk.tickets.default_per_page')),
        );
    }
}
