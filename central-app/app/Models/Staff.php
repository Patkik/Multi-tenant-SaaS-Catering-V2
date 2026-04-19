<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Staff extends Model
{
    use HasFactory;

    protected $table = 'staff';

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'position',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'bool',
    ];

    public function events(): BelongsToMany
    {
        return $this->belongsToMany(TenantEvent::class, 'event_staff', 'staff_id', 'event_id')
            ->withPivot(['id', 'assignment_role', 'start_time', 'end_time'])
            ->withTimestamps();
    }
}
