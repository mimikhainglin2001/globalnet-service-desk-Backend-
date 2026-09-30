<?php

namespace App\Models;

use App\Enums\TicketPriority;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SlaRule extends Model
{
    use Auditable, HasFactory;

    protected $fillable = [
        'priority',
        'response_time_hours',
        'is_active',
    ];

    protected $casts = [
        'priority' => TicketPriority::class,
        'response_time_hours' => 'integer',
        'is_active' => 'boolean',
    ];
}
