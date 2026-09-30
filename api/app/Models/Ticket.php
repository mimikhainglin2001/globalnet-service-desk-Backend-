<?php

namespace App\Models;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ticket extends Model
{
    use Auditable, HasFactory;

    protected $fillable = [
        'subject',
        'description',
        'category_id',
        'priority',
        'status',
        'requester_id',
        'assignee_id',
        'team_id',
        'due_at',
        'first_responded_at',
        'sla_breached_at',
        'resolved_at',
        'closed_at',
    ];

    protected $casts = [
        'priority' => TicketPriority::class,
        'status' => TicketStatus::class,
        'due_at' => 'datetime',
        'first_responded_at' => 'datetime',
        'sla_breached_at' => 'datetime',
        'resolved_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    protected static function booting(): void
    {
        // Registered before trait listeners so the audit "created" entry
        // already contains the final reference.
        static::created(function (Ticket $ticket) {
            if ($ticket->reference === null) {
                $ticket->reference = static::formatReference($ticket->id);
                $ticket->saveQuietly();
            }
        });
    }

    public static function formatReference(int $id): string
    {
        return config('servicedesk.tickets.reference_prefix')
            .str_pad((string) $id, config('servicedesk.tickets.reference_padding'), '0', STR_PAD_LEFT);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function publicComments(): HasMany
    {
        return $this->comments()->public();
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }

    public function isOverdue(): bool
    {
        return $this->due_at !== null
            && $this->due_at->isPast()
            && ! $this->status->isFinished();
    }

    /**
     * Row-level RBAC: restricts a query to the tickets the user may see.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isAdmin()) {
            return $query;
        }

        if ($user->isAgent()) {
            return $query->where(function (Builder $query) use ($user) {
                $query->whereIn('team_id', $user->teamIds())
                    ->orWhere('assignee_id', $user->id);
            });
        }

        return $query->where('requester_id', $user->id);
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->whereIn('status', TicketStatus::active());
    }
}
