<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TenantEvent extends Model
{
    use HasFactory;

    protected $table = 'events';

    protected $fillable = [
        'client_id',
        'catering_package_id',
        'event_name',
        'event_date',
        'start_time',
        'end_time',
        'location',
        'guest_count',
        'status',
        'quoted_total',
        'notes',
    ];

    protected $casts = [
        'event_date' => 'date',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'quoted_total' => 'decimal:2',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function cateringPackage(): BelongsTo
    {
        return $this->belongsTo(CateringPackage::class, 'catering_package_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'event_id');
    }

    public function assignedStaff(): BelongsToMany
    {
        return $this->belongsToMany(Staff::class, 'event_staff', 'event_id', 'staff_id')
            ->withPivot(['id', 'assignment_role', 'start_time', 'end_time'])
            ->withTimestamps();
    }
}
