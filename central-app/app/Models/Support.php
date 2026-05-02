<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Support extends Model
{
    protected $table = 'support_messages';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'source',
        'category',
        'subject',
        'message',
        'contact_name',
        'contact_email',
        'workspace_name',
        'workspace_id',
        'tenant_id',
        'page_path',
        'app_version',
        'user_role',
        'tenant_domain',
        'request_ip',
        'user_agent',
    ];

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'id');
    }

    /**
     * Scope to get only tenant support submissions (not central)
     */
    public function scopeTenantSubmissions($query)
    {
        return $query->where('source', 'tenant');
    }

    /**
     * Scope to get only central support submissions
     */
    public function scopeCentralSubmissions($query)
    {
        return $query->where('source', 'central');
    }
}
