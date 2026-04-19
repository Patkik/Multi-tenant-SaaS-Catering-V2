<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CateringPackage extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'pricing_mode',
        'base_price',
        'is_active',
    ];

    protected $casts = [
        'base_price' => 'decimal:2',
        'is_active' => 'bool',
    ];

    public function events(): HasMany
    {
        return $this->hasMany(TenantEvent::class, 'catering_package_id');
    }
}
