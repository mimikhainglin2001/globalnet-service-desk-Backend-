<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OutboxEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'event_type',
        'aggregate_type',
        'aggregate_id',
        'payload',
        'attempts',
        'last_error',
        'processed_at',
        'failed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'attempts' => 'integer',
        'processed_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public function isProcessed(): bool
    {
        return $this->processed_at !== null;
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('processed_at')->whereNull('failed_at');
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->whereNull('processed_at')->whereNotNull('failed_at');
    }
}
