<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventStaff extends Model
{
    use HasFactory;

    protected $table = 'event_staff';

    protected $fillable = [
        'event_id',
        'staff_id',
        'assignment_role',
        'shift_start_at',
        'shift_end_at',
        'start_time',
        'end_time',
    ];

    protected $casts = [
        'shift_start_at' => 'datetime',
        'shift_end_at' => 'datetime',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(TenantEvent::class, 'event_id');
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'staff_id');
    }
}
