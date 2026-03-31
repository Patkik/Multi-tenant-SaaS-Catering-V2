<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UsageSnapshot extends Model
{
    use HasFactory;
    use HasUuids;

    public const WINDOW_HOURLY = 'hourly';

    public const WINDOW_DAILY = 'daily';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'window_type',
        'captured_at',
        'users_total',
        'storage_mb',
        'orders_count',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'captured_at' => 'datetime',
            'users_total' => 'integer',
            'orders_count' => 'integer',
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
