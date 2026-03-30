<?php

namespace App\Models;

use Database\Factories\RoleTemplateFeatureFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoleTemplateFeature extends Model
{
    /** @use HasFactory<RoleTemplateFeatureFactory> */
    use HasFactory;
    use HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'role_template_id',
        'role_name',
        'feature_key',
        'is_enabled',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<RoleTemplate, $this>
     */
    public function roleTemplate(): BelongsTo
    {
        return $this->belongsTo(RoleTemplate::class);
    }
}
