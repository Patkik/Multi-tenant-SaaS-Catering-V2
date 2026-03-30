<?php

namespace App\Models;

use Database\Factories\RoleTemplatePermissionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoleTemplatePermission extends Model
{
    /** @use HasFactory<RoleTemplatePermissionFactory> */
    use HasFactory;
    use HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'role_template_id',
        'role_name',
        'permission',
    ];

    /**
     * @return BelongsTo<RoleTemplate, $this>
     */
    public function roleTemplate(): BelongsTo
    {
        return $this->belongsTo(RoleTemplate::class);
    }
}
