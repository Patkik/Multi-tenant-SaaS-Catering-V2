<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\Pivot;

class RoleFeature extends Pivot
{
    use HasUuids;

    public $guarded = [];
    public $timestamps = true;
    public $incrementing = false;

    protected $casts = [
        'is_enabled' => 'boolean',
    ];
}
