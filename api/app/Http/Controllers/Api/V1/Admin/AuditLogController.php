<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\SlaRule;
use App\Models\Team;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AuditLogController extends Controller
{
    private const TYPES = [
        'ticket' => Ticket::class,
        'user' => User::class,
        'sla_rule' => SlaRule::class,
        'team' => Team::class,
        'category' => Category::class,
    ];

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'type' => ['nullable', 'in:'.implode(',', array_keys(self::TYPES))],
            'auditable_id' => ['nullable', 'integer'],
            'user_id' => ['nullable', 'integer'],
            'action' => ['nullable', 'in:created,updated,deleted'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $logs = AuditLog::query()
            ->with('user')
            ->when($filters['type'] ?? null, fn ($q, $type) => $q->where('auditable_type', (new (self::TYPES[$type]))->getMorphClass()))
            ->when($filters['auditable_id'] ?? null, fn ($q, $id) => $q->where('auditable_id', $id))
            ->when($filters['user_id'] ?? null, fn ($q, $id) => $q->where('user_id', $id))
            ->when($filters['action'] ?? null, fn ($q, $action) => $q->where('action', $action))
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->where('created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->where('created_at', '<=', $to.' 23:59:59'))
            ->latest()
            ->latest('id')
            ->paginate($filters['per_page'] ?? 25)
            ->withQueryString();

        return AuditLogResource::collection($logs);
    }
}
