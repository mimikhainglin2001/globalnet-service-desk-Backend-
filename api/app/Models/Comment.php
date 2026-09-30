<?php

namespace App\Models;

use App\Enums\CommentType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Comment extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_id',
        'user_id',
        'type',
        'body',
    ];

    protected $casts = [
        'type' => CommentType::class,
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isInternal(): bool
    {
        return $this->type === CommentType::INTERNAL;
    }

    public function scopePublic(Builder $query): Builder
    {
        return $query->where('type', CommentType::PUBLIC);
    }
}
