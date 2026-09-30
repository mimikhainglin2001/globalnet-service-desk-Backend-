<?php

namespace App\Models;

use App\Enums\Role;
use App\Models\Concerns\Auditable;
use App\Notifications\ResetPasswordNotification;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use Auditable, HasApiTokens, HasFactory, Notifiable;

    /**
     * "role" is intentionally not mass assignable; it is only changed through
     * UserService so a crafted payload can never escalate privileges.
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    private ?array $cachedTeamIds = null;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
        ];
    }

    protected function auditOnly(): ?array
    {
        return ['role'];
    }

    public function isCustomer(): bool
    {
        return $this->role === Role::CUSTOMER;
    }

    public function isAgent(): bool
    {
        return $this->role === Role::AGENT;
    }

    public function isAdmin(): bool
    {
        return $this->role === Role::ADMIN;
    }

    public function isStaff(): bool
    {
        return $this->role?->isStaff() ?? false;
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class)->withTimestamps();
    }

    /**
     * Memoised per request: policies call this for every ticket check.
     *
     * @return list<int>
     */
    public function teamIds(): array
    {
        return $this->cachedTeamIds ??= $this->teams()->pluck('teams.id')->all();
    }

    public function belongsToTeam(?int $teamId): bool
    {
        return $teamId !== null && in_array($teamId, $this->teamIds(), true);
    }

    public function requestedTickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'requester_id');
    }

    public function assignedTickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'assignee_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function scopeStaff(Builder $query): Builder
    {
        return $query->whereIn('role', Role::staff());
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }
}
