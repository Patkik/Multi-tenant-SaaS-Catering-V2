# Central RBAC Enhancement Implementation Guide

**Document**: documentation/central-rbac-enhancement.md  
**Date**: March 30, 2026  
**Purpose**: Detailed developer reference for implementing Central RBAC super-admin features.

---

## Quick Reference: Feature Override Logic

The core logic determining whether a feature is enabled:

```php
public static function isFeatureEnabled(Tenant $tenant, string $featureName): bool
{
    // 1. Check for explicit per-tenant override
    $override = FeatureOverride::where('tenant_id', $tenant->id)
        ->whereHas('feature', fn ($q) => $q->where('name', $featureName))
        ->first();
    
    // If override exists and not expired, use it
    if ($override && ($override->expires_at === null || $override->expires_at > now())) {
        return $override->is_enabled;
    }
    
    // 2. Check tenant plan includes feature
    $feature = Feature::where('name', $featureName)->first();
    if (!$feature) return false;
    
    if ($feature->requires_plan && !$tenant->plan_includes($feature->requires_plan)) {
        return false; // Plan does not include this feature
    }
    
    // 3. Use feature default
    return $feature->default_enabled;
}
```
**Key Principle**: Deny precedence (whitelist model). If ANY deny exists, feature is OFF. Only explicit enable (after checks pass) turns it ON.

---

## Feature-to-Surface Matrix (Implementation Contract)

Use this mapping when wiring page guards, navbar visibility, and backend module gates.

| feature_key | page_route(s) | navbar_item_key | module_key | default_state |
|---|---|---|---|---|
| analytics | /dashboard/analytics, /dashboard/reports/analytics | nav.analytics | analytics.module | enabled |
| crm-sync | /dashboard/integrations/crm | nav.integrations | integrations.crm_sync | disabled |
| api-access | /dashboard/developer/api-keys | nav.developer | developer.api_access | disabled |
| sso | /dashboard/settings/security/sso | nav.settings | auth.sso | disabled |
| role-template-sync | /dashboard/tenants/{id}/rbac | nav.tenants | rbac.role_template_sync | enabled |
| usage-monitoring | /dashboard/tenants/{id}/rbac, /dashboard/tenants/{id}/usage | nav.tenants | monitoring.usage | enabled |

Interpretation:
- Page route toggle blocks route access when disabled.
- Navbar toggle hides item when disabled.
- Module toggle denies backend operation when disabled.

---

## Tenant Monitoring API Contract

Endpoint: `GET /api/admin/tenants/{tenant_id}/monitoring`

Required fields:
- `tenant_domain`
- `tenant_name`
- `admin_name`
- `users_total`
- `active_roles` (list)
- `active_features_count`
- `deactivated_features_count`
- `usage_snapshot_summary` with:
    - `snapshot_period`
    - `period_start`
    - `period_end`
    - `active_user_count`
    - `api_call_count`
    - `events_created`
    - `payments_processed`
    - `storage_bytes`
    - `last_captured_at`

Sample payload:

```json
{
    "tenant_domain": "acme-catering.localhost:8080",
    "tenant_name": "Acme Catering",
    "admin_name": "Jordan Lee",
    "users_total": 42,
    "active_roles": ["Admin", "Manager", "Staff"],
    "active_features_count": 18,
    "deactivated_features_count": 4,
    "usage_snapshot_summary": {
        "snapshot_period": "hourly",
        "period_start": "2026-03-30T14:00:00Z",
        "period_end": "2026-03-30T15:00:00Z",
        "active_user_count": 27,
        "api_call_count": 932,
        "events_created": 19,
        "payments_processed": 245000,
        "storage_bytes": 987654321,
        "last_captured_at": "2026-03-30T15:01:08Z"
    }
}
```

---

## Role Template API Contract (Permissions + Features)

When creating or updating role templates, include both permission grants and feature bindings.

- POST /api/admin/role-templates: accepts role_name, permissions[], feature_keys[]
- PATCH /api/admin/role-templates/{id}: accepts role_name (optional), permissions[] (optional), feature_keys[] (optional)

Sample payload:

```json
{
    "role_name": "EventSupervisor",
    "permissions": ["events.view", "events.update", "staff.assign"],
    "feature_keys": ["analytics", "usage-monitoring"]
}
```

---

## Migration Scripts

### Create Features Table
```sql
CREATE TABLE features (
    id CHAR(36) PRIMARY KEY,
    name VARCHAR(255) UNIQUE NOT NULL,
    description TEXT,
    category ENUM('Core', 'CRM', 'Billing', 'Reporting', 'Integration', 'Admin'),
    default_enabled BOOLEAN DEFAULT TRUE,
    requires_plan VARCHAR(50),
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    deprecated_at TIMESTAMP NULL,
    INDEX (category),
    INDEX (requires_plan)
);
```
### Create Feature Overrides Table
```sql
CREATE TABLE feature_overrides (
    id CHAR(36) PRIMARY KEY,
    tenant_id CHAR(36) NOT NULL,
    feature_id CHAR(36) NOT NULL,
    is_enabled BOOLEAN DEFAULT TRUE,
    reason VARCHAR(500),
    set_by_admin VARCHAR(255),
    set_at TIMESTAMP,
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    UNIQUE KEY unique_tenant_feature (tenant_id, feature_id),
    FOREIGN KEY (feature_id) REFERENCES features(id) ON DELETE CASCADE,
    INDEX (expires_at)
);
```
### Create Role Templates Table
```sql
CREATE TABLE role_templates (
    id CHAR(36) PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    is_system_default BOOLEAN DEFAULT FALSE,
    created_by_admin VARCHAR(255),
    metadata JSON,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    UNIQUE KEY (name)
);
```
### Create Role Template Permissions Table
```sql
CREATE TABLE role_template_permissions (
    id CHAR(36) PRIMARY KEY,
    role_template_id CHAR(36) NOT NULL,
    role_name VARCHAR(100),
    permission VARCHAR(255),
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    FOREIGN KEY (role_template_id) REFERENCES role_templates(id) ON DELETE CASCADE,
    UNIQUE KEY (role_template_id, role_name, permission),
    INDEX (role_name)
);
```
### Create Role Template Feature Bindings Table
```sql
CREATE TABLE role_template_features (
    id CHAR(36) PRIMARY KEY,
    role_template_id CHAR(36) NOT NULL,
    role_name VARCHAR(100),
    feature_key VARCHAR(120) NOT NULL,
    is_enabled BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    FOREIGN KEY (role_template_id) REFERENCES role_templates(id) ON DELETE CASCADE,
    UNIQUE KEY (role_template_id, role_name, feature_key),
    INDEX (feature_key)
);
```
### Create Tenant Admin Contacts Table
```sql
CREATE TABLE tenant_admin_contacts (
    id CHAR(36) PRIMARY KEY,
    tenant_id CHAR(36) NOT NULL,
    contact_type ENUM('Owner', 'Support', 'Technical', 'Billing'),
    name VARCHAR(255),
    email VARCHAR(255),
    phone VARCHAR(20),
    is_primary BOOLEAN DEFAULT FALSE,
    verified_at TIMESTAMP NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    INDEX (tenant_id, contact_type)
);
```
### Create Usage Snapshots Table
```sql
CREATE TABLE usage_snapshots (
    id CHAR(36) PRIMARY KEY,
    tenant_id CHAR(36) NOT NULL,
    snapshot_period ENUM('hourly', 'daily'),
    period_start TIMESTAMP,
    period_end TIMESTAMP,
    active_user_count INT DEFAULT 0,
    api_call_count INT DEFAULT 0,
    events_created INT DEFAULT 0,
    payments_processed BIGINT DEFAULT 0,
    storage_bytes BIGINT DEFAULT 0,
    metadata JSON,
    created_at TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    UNIQUE KEY (tenant_id, snapshot_period, period_start),
    INDEX (created_at)
);
```
### Create RBAC Audit Log (Immutable)
```sql
CREATE TABLE rbac_change_audits (
    id CHAR(36) PRIMARY KEY,
    tenant_id CHAR(36),
    change_type VARCHAR(50),
    actor_email VARCHAR(255),
    change_payload JSON NOT NULL,
    enforcement_result VARCHAR(50),
    result_reason TEXT,
    created_at TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE SET NULL,
    INDEX (tenant_id, created_at),
    INDEX (change_type),
    INDEX (created_at)
);
```
---

## Model Examples

### Feature Model
```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\AsCollection;

class Feature extends Model
{
    protected $casts = [
        'default_enabled' => 'boolean',
        'deprecated_at' => 'datetime',
    ];
    
    public function overrides()
    {
        return $this->hasMany(FeatureOverride::class);
    }
    
    public function isDeprecated(): bool
    {
        return $this->deprecated_at !== null;
    }
}
```
### FeatureOverride Model with Audit
```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeatureOverride extends Model
{
    protected $casts = [
        'is_enabled' => 'boolean',
        'set_at' => 'datetime',
        'expires_at' => 'datetime',
    ];
    
    protected $fillable = ['tenant_id', 'feature_id', 'is_enabled', 'reason', 'set_by_admin', 'expires_at'];
    
    protected static function booted()
    {
        static::created(function ($model) {
            RBACChangeAudit::log('FeatureOverride', $model->tenant_id, auth()->user()->email, [
                'feature_id' => $model->feature_id,
                'is_enabled' => $model->is_enabled,
                'reason' => $model->reason,
                'expires_at' => $model->expires_at,
            ], 'Applied');
        });
    }
    
    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at < now();
    }
    
    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
        });
    }
}
```
### RBACChangeAudit Model (Immutable)
```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RBACChangeAudit extends Model
{
    public $timestamps = false;
    
    protected $casts = [
        'change_payload' => 'json',
        'created_at' => 'datetime',
    ];
    
    protected $fillable = ['tenant_id', 'change_type', 'actor_email', 'change_payload', 'enforcement_result', 'result_reason'];
    
    // Immutability: prevent updates
    public function update(array $attributes = [], array $options = [])
    {
        throw new \Exception('RBAC audit logs are immutable');
    }
    
    public function delete()
    {
        throw new \Exception('RBAC audit logs cannot be deleted');
    }
    
    public static function log(string $changeType, ?string $tenantId, string $actorEmail, array $payload, string $enforcementResult, ?string $resultReason = null)
    {
        return static::create([
            'tenant_id' => $tenantId,
            'change_type' => $changeType,
            'actor_email' => $actorEmail,
            'change_payload' => $payload,
            'enforcement_result' => $enforcementResult,
            'result_reason' => $resultReason,
            'created_at' => now(),
        ]);
    }
}
```
**Immutability Strategy:**
- **App Level:** update() and delete() methods throw exceptions
- **DB Level:** MySQL BEFORE UPDATE/DELETE trigger on rbac_change_audits raises error:
  ```sql
  DELIMITER //
  CREATE TRIGGER rbac_change_audits_immutable BEFORE UPDATE ON rbac_change_audits
  FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Audit logs are immutable';
  CREATE TRIGGER rbac_change_audits_no_delete BEFORE DELETE ON rbac_change_audits
  FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Audit logs cannot be deleted';
  DELIMITER ;
  ```
- **Archival:** After 2 years, export to immutable blob storage (Azure Blobs Object Lock / S3 Object Lock). Audit log remains queryable in primary DB for compliance.
---

## API Controller Example

```php
namespace App\Http\Controllers\Api\Admin;

use App\Models\Feature;
use App\Models\FeatureOverride;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class FeatureOverrideController extends Controller
{
    public function index(Request $request, string $tenantId)
    {
        Gate::authorize('view-overrides', $tenantId);
        
        return response()->json(
            FeatureOverride::where('tenant_id', $tenantId)
                ->with('feature')
                ->get()
                ->map(fn ($override) => [
                    'feature_id' => $override->feature_id,
                    'is_enabled' => $override->is_enabled,
                    'reason' => $override->reason,
                    'set_at' => $override->set_at,
                    'expires_at' => $override->expires_at,
                    'expired' => $override->isExpired(),
                ])
        );
    }
    
    public function store(Request $request, string $tenantId)
    {
        Gate::authorize('toggle-feature', $tenantId);
        
        $data = $request->validate([
            'feature_id' => 'required|uuid|exists:features,id',
            'is_enabled' => 'required|boolean',
            'reason' => 'nullable|string|max:500',
            'expires_at' => 'nullable|date|after:now',
        ]);
        
        $override = FeatureOverride::updateOrCreate(
            ['tenant_id' => $tenantId, 'feature_id' => $data['feature_id']],
            [
                'is_enabled' => $data['is_enabled'],
                'reason' => $data['reason'],
                'set_by_admin' => auth()->user()->email,
                'set_at' => now(),
                'expires_at' => $data['expires_at'] ?? null,
            ]
        );
        
        return response()->json(['override_id' => $override->id], 201);
    }
    
    public function effective(Request $request, string $tenantId)
    {
        Gate::authorize('view-features', $tenantId);
        
        $tenant = Tenant::findOrFail($tenantId);
        $features = Feature::all();
        
        return response()->json(
            $features->map(fn ($feature) => [
                'feature_id' => $feature->id,
                'name' => $feature->name,
                'effective_enabled' => FeatureService::isEnabled($tenant, $feature->name),
                'reason' => FeatureService::whyDisabled($tenant, $feature->name),
            ])
        );
    }
}
```
**Key Points:**
- Use `Gate::authorize()` for policy checks (not `$request->authorize()`)
- Include $request parameter in method signature
- Always fetch tenant before returning data
- Return 403 Forbidden automatically if Gate check fails
---

## Job Example: Usage Snapshot

```php
namespace App\Jobs;

use App\Models\UsageSnapshot;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\SerializesModels;

class CaptureUsageSnapshotJob implements ShouldQueue
{
    use Queueable, SerializesModels;
    
    public function handle(): void
    {
        Tenant::all()->each(function ($tenant) {
            $this->captureHourlySnapshot($tenant);
        });
    }
    
    private function captureHourlySnapshot(Tenant $tenant): void
    {
        $now = now();
        $period = 'hourly';
        $periodStart = $now->copy()->startOfHour();
        $periodEnd = $periodStart->copy()->addHour();
        
        // Idempotent: if snapshot exists for this period, skip
        $existing = UsageSnapshot::where('tenant_id', $tenant->id)
            ->where('snapshot_period', $period)
            ->where('period_start', $periodStart)
            ->first();
        
        if ($existing) {
            return; // Already captured
        }
        
        // Query tenant database using stancl/tenancy connection
        $activeUsers = $this->countActiveUsers($tenant);
        $apiCalls = $this->countApiCalls($tenant);
        $events = $this->countEvents($tenant);
        $payments = $this->sumPayments($tenant);
        $storage = $this->sumStorageBytes($tenant);
        
        UsageSnapshot::create([
            'tenant_id' => $tenant->id,
            'snapshot_period' => $period,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'active_user_count' => $activeUsers,
            'api_call_count' => $apiCalls,
            'events_created' => $events,
            'payments_processed' => $payments,
            'storage_bytes' => $storage,
        ]);
    }
    
    private function countActiveUsers(Tenant $tenant): int
    {
        // Switch to tenant database, count active users (last activity within 24h)
        return $tenant->database()->table('users')
            ->where('last_activity_at', '>=', now()->subDay())
            ->count();
    }
    
    private function countApiCalls(Tenant $tenant): int
    {
        // Count API calls for today
        return $tenant->database()->table('api_logs')
            ->whereDate('created_at', now()->toDateString())
            ->count();
    }
    
    private function countEvents(Tenant $tenant): int
    {
        // Count events created today
        return $tenant->database()->table('events')
            ->whereDate('created_at', now()->toDateString())
            ->count();
    }
    
    private function sumPayments(Tenant $tenant): int
    {
        // Sum payment amounts for today (in cents)
        return (int) ($tenant->database()->table('payments')
            ->whereDate('created_at', now()->toDateString())
            ->sum('amount') ?? 0);
    }
    
    private function sumStorageBytes(Tenant $tenant): int
    {
        // Sum all file storage (aggregate across tenant)
        return (int) ($tenant->database()->table('files')
            ->sum('size_bytes') ?? 0);
    }
}
```
**Key Points:**
- Use `$tenant->database()` to resolve tenant's DB connection (stancl/tenancy)
- Each metric is a separate query scoped to correct time period
- Idempotent: skips if snapshot already exists for period
- Returns integers (sum operations may return null, cast to 0)
- Storage summation includes all files (not just today's)
---

## Testing Example

```php
use Tests\TestCase;
use App\Models\Feature;
use App\Models\FeatureOverride;
use App\Models\Tenant;

class FeatureOverrideTest extends TestCase
{
    public function test_feature_enabled_when_no_override_and_default_true()
    {
        $feature = Feature::create(['name' => 'analytics', 'default_enabled' => true]);
        $tenant = Tenant::factory()->create();
        
        $result = FeatureService::isEnabled($tenant, 'analytics');
        $this->assertTrue($result);
    }
    
    public function test_feature_disabled_by_explicit_override()
    {
        $feature = Feature::create(['name' => 'analytics', 'default_enabled' => true]);
        $tenant = Tenant::factory()->create();
        
        FeatureOverride::create([
            'tenant_id' => $tenant->id,
            'feature_id' => $feature->id,
            'is_enabled' => false,
            'reason' => 'Testing',
        ]);
        
        $result = FeatureService::isEnabled($tenant, 'analytics');
        $this->assertFalse($result);
    }
    
    public function test_expired_override_ignored_falls_back_to_default()
    {
        $feature = Feature::create(['name' => 'analytics', 'default_enabled' => false]);
        $tenant = Tenant::factory()->create();
        
        FeatureOverride::create([
            'tenant_id' => $tenant->id,
            'feature_id' => $feature->id,
            'is_enabled' => true,
            'expires_at' => now()->subHour(),
        ]);
        
        $result = FeatureService::isEnabled($tenant, 'analytics');
        $this->assertFalse($result); // Expired override ignored, falls back to default
    }
    
    public function test_active_override_takes_precedence_over_plan()
    {
        // Feature requires 'pro' plan, tenant has 'basic' plan
        $feature = Feature::create([
            'name' => 'advanced-reporting',
            'requires_plan' => 'pro',
            'default_enabled' => false,
        ]);
        $tenant = Tenant::factory()->create(['plan_name' => 'basic']);
        
        // Override explicitly enables it (overrides plan restriction)
        FeatureOverride::create([
            'tenant_id' => $tenant->id,
            'feature_id' => $feature->id,
            'is_enabled' => true,
            'reason' => 'Customer upgrade pending',
        ]);
        
        $result = FeatureService::isEnabled($tenant, 'advanced-reporting');
        $this->assertTrue($result); // Active override forces ON despite plan
    }
}
```

---

## Backward Compatibility: Migration from Plan-Based Gates

**Scenario:** Existing plan-based feature gates (e.g., tenant.pro_plan includes 'analytics') being replaced by centralized catalog.

**Safe Migration Example:**

```php
// BEFORE: Old gate logic in Policies or Helper
public function hasFeature(Tenant $tenant, string $feature): bool
{
    $planFeatures = [
        'pro' => ['analytics', 'api-access', 'sso'],
        'standard' => ['analytics'],
        'basic' => [],
    ];
    return in_array($feature, $planFeatures[$tenant->plan_name] ?? []);
}

// AFTER: New catalog-aware logic (backward-compatible)
public function hasFeature(Tenant $tenant, string $feature): bool
{
    // 1. Check new catalog overrides first (take precedence)
    $override = FeatureOverride::where('tenant_id', $tenant->id)
        ->whereHas('feature', fn ($q) => $q->where('name', $feature))
        ->where(function ($q) {
            $q->whereNull('expires_at')
              ->orWhere('expires_at', '>', now());
        })
        ->first();
    
    if ($override) {
        return $override->is_enabled; // Explicit override respected
    }
    
    // 2. Fall back to plan check (legacy behavior preserved)
    $planFeatures = [
        'pro' => ['analytics', 'api-access', 'sso'],
        'standard' => ['analytics'],
        'basic' => [],
    ];
    return in_array($feature, $planFeatures[$tenant->plan_name] ?? []);
}

// MIGRATION JOB: Seed catalog from legacy plan features
php artisan make:job SeedLegacyFeaturesToCatalog

class SeedLegacyFeaturesToCatalog implements ShouldQueue
{
    public function handle(): void
    {
        $legacyMappings = [
            'analytics' => ['pro', 'standard'],
            'api-access' => ['pro'],
            'sso' => ['pro'],
        ];
        
        foreach ($legacyMappings as $featureName => $plans) {
            Feature::firstOrCreate(
                ['name' => $featureName],
                [
                    'description' => "Migrated from legacy plan features",
                    'default_enabled' => false,
                    'requires_plan' => $plans[0] ?? null,
                    'category' => 'Core',
                ]
            );
        }
    }
}
```

**Migration Checklist:**
- [ ] Week 1: Seed Feature records (run SeedLegacyFeaturesToCatalog job)
- [ ] Week 1: Deploy new hasFeature() logic (both checks work)
- [ ] Week 2: Test 10 random tenants: same result from old/new logic
- [ ] Week 3: Enable feature flag `use_catalog_overrides` for 5% of tenants
- [ ] Week 3-4: Monitor logs for override creation/expiry events
- [ ] Week 4: Expand to 100% (gradual via Laravel Pennant)
- [ ] Week 5: Decommission old plan-feature constants (document deprecation)
- [ ] Week 6: If issues, rollback: set `use_catalog_overrides = false`

**Rollback Strategy:**
- If issues during pilot, disable feature flag: `config(['features.use_catalog' => false])`
- hasFeature() reverts to legacy plan check only
- No data loss: FeatureOverride records retained for 90 days
- Re-enable when issues resolved with rollout rate reduced by 50%

---

## Next Steps

1. Read context/central-rbac-enhancement-context.md for checklist
2. Follow Phase 1 (weeks 1-2): Implement models, migrations, basic API
3. Phase-gate implementation with tests at each step
4. Verify API contract matches CENTRAL_APP_RBAC_ENHANCEMENT_SPEC.md
5. Deploy to production with feature flag (Laravel Pennant) initially disabled

---

## References

- Specification: CENTRAL_APP_RBAC_ENHANCEMENT_SPEC.md
- Implementation Context: context/central-rbac-enhancement-context.md
- Laravel Models: https://laravel.com/docs/eloquent
- Laravel Policies: https://laravel.com/docs/authorization
- Laravel Horizon: https://horizon.laravel.com
