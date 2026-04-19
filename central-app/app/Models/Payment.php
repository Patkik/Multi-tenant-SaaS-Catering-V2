<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'amount',
        'payment_type',
        'status',
        'payment_method',
        'reference',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(TenantEvent::class, 'event_id');
    }
}
