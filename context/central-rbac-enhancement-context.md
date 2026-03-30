# Central RBAC Enhancement - Implementation Context

**Document**: context/central-rbac-enhancement-context.md  
**Date**: March 30, 2026  
**Purpose**: Checklist and implementation guidance for developers building the feature.

---

## Pre-Implementation Checklist

- [ ] Read CENTRAL_APP_RBAC_ENHANCEMENT_SPEC.md for complete requirements
- [ ] Review SYSTEM_CAPABILITIES.md::Central App section for tenancy architecture
- [ ] Understand current four-tier RBAC model (Admin, Manager, Staff, Cashier) from tenant app
- [ ] Verify Laravel 11+ installation in central app source tree
- [ ] Confirm MySQL is configured as default database
- [ ] Verify stancl/tenancy is installed and central tenants table exists
- [ ] Ensure laravel/horizon installed for async job processing
- [ ] Run php artisan test baseline on fresh migrations (all pass)

---

## Implementation Order

### 1. Database & Models (Phase 1)
- [ ] Create migration: features table
- [ ] Create migration: feature_overrides table
- [ ] Create migration: role_templates table
- [ ] Create migration: role_template_permissions table
- [ ] Create migration: tenant_admin_contacts table
- [ ] Create migration: usage_snapshots table
- [ ] Create migration: rbac_change_audits table (immutable, write-once)
- [ ] Create Feature model with relationships
- [ ] Create FeatureOverride model with scopes (find_effective_state, by_tenant)
- [ ] Create RoleTemplate model with clone() method
- [ ] Create other models (Contact, Snapshot, Audit)
- [ ] Add indexes: (tenant_id, feature_id) on feature_overrides, (tenant_id, snapshot_period, period_start) on snapshots
- [ ] Test all migrations on fresh database

### 2. API Endpoints (Phase 1-2)
- [ ] Feature CRUD endpoints (GET list, GET detail, POST create, PATCH update)
- [ ] Feature override endpoints (GET, POST, DELETE, GET effective)
- [ ] Role template endpoints (GET list, GET permissions, POST create, PATCH update)
- [ ] Tenant contact endpoints (GET, POST, DELETE, POST verify)
- [ ] Usage snapshot query endpoints (GET by date range, GET current)
- [ ] Tenant monitoring endpoint (GET /api/admin/tenants/{tenant_id}/monitoring)
- [ ] RBAC audit log endpoints (GET with filters, GET detail)
- [ ] Add request validation and authorization checks
- [ ] Add tests: 80% coverage minimum for all endpoints

### 2.1 Feature-to-Surface Matrix (Do Not Infer)

| feature_key | page_route(s) | navbar_item_key | module_key | default_state |
|---|---|---|---|---|
| analytics | /dashboard/analytics, /dashboard/reports/analytics | nav.analytics | analytics.module | enabled |
| crm-sync | /dashboard/integrations/crm | nav.integrations | integrations.crm_sync | disabled |
| api-access | /dashboard/developer/api-keys | nav.developer | developer.api_access | disabled |
| sso | /dashboard/settings/security/sso | nav.settings | auth.sso | disabled |
| role-template-sync | /dashboard/tenants/{id}/rbac | nav.tenants | rbac.role_template_sync | enabled |
| usage-monitoring | /dashboard/tenants/{id}/rbac, /dashboard/tenants/{id}/usage | nav.tenants | monitoring.usage | enabled |

Implementation rule: apply all three surfaces consistently (page guard, navbar visibility, module authorization).

### 2.2 Tenant Monitoring Response Contract (Required Fields)

- `tenant_domain`
- `tenant_name`
- `admin_name`
- `users_total`
- `active_roles` (list)
- `active_features_count`
- `deactivated_features_count`
- `usage_snapshot_summary`:
    - `snapshot_period`
    - `period_start`
    - `period_end`
    - `active_user_count`
    - `api_call_count`
    - `events_created`
    - `payments_processed`
    - `storage_bytes`
    - `last_captured_at`

### 3. Feature Override Logic (Phase 1-2)
- [ ] Implement is_feature_enabled(tenant, feature) helper
- [ ] Ensure deny precedence: if override.is_enabled=false OR plan excludes, OFF
- [ ] Add audit logging on every access check that denies
- [ ] Add expired override handling (ignore if expires_at < now())
- [ ] Test edge cases: missing plan, null override, expired override

### 4. Role Template Sync (Phase 3)
- [ ] Create RoleTemplateSyncJob class
- [ ] Implement idempotency via idempotency_key
- [ ] Build role/permission application logic to tenant database
- [ ] Enforce role-feature sync: applied role template permissions and feature_keys must be persisted atomically
- [ ] Add preview endpoint (GET diff before sync)
- [ ] Implement rollback to previous template
- [ ] Test merge vs replace strategies
- [ ] Add progress tracking and job status endpoints

### 5. Usage Snapshot Job (Phase 3)
- [ ] Create CaptureUsageSnapshotJob class
- [ ] Implement hourly schedule (every hour)
- [ ] Implement daily schedule (midnight UTC)
- [ ] Query tenant database: active user count, API call count, events created, payments
- [ ] Handle retry with exponential backoff (up to 3x)
- [ ] Idempotent: if snapshot exists for period, increment (append only)
- [ ] Test with synthetic tenant data

### 6. UI Components (Phase 2-3)
- [ ] Feature Toggles Card (RBAC tab)
- [ ] Inline editor modal (reason + expiry date)
- [ ] Role Templates Card with selector
- [ ] Template preview modal
- [ ] Admin Contacts Card
- [ ] Contact verification flow
- [ ] Usage Metrics Card with sparkline
- [ ] Recent Changes Card (audit feed)
- [ ] Feature Catalog management page
- [ ] Role Template Builder page

### 7. Authorization & RBAC (Phase 4)
- [ ] Create Super-Admin role in central app
- [ ] Create Support Analyst role
- [ ] Create Ops Lead role
- [ ] Implement permission gates (can_toggle_feature, can_export_audit, etc.)
- [ ] Guard all endpoints with Gate::authorize()
- [ ] Test permission matrix compliance with integration tests

### 8. Compliance & Monitoring (Phase 4-5)
- [ ] Set audit log retention policy (7 years minimum)
- [ ] Create RevertExpiredOverridesJob (daily at midnight)
- [ ] Create monitoring alerts (missing snapshot, unverified contact)
- [ ] Add health check endpoint
- [ ] Set up audit export to CSV with compliance headers
- [ ] Document 7-year retention in deployment checklist

---

## EnforceFeatureGate Middleware Integration

**Current State:** Tenant app has middleware that checks `Gate::check('feature-name')` using plan-based gates.

**Integration with Catalog (No Breaking Changes):**

1. **Middleware Execution Order:**
   - Request arrives → EnforceFeatureGate middleware runs
   - Middleware calls `FeatureService::isEnabled($tenant, $featureName)`
   - New catalog logic: check override > plan > default
   - If OFF: return 403 Forbidden with header `X-Feature-Disabled-Reason`
   - Audit log created for denied requests

2. **Precedence Chain:**
   - **Active Override (highest):** If `FeatureOverride.is_enabled` and not expired → use override value
   - **Plan Check:** If feature has `requires_plan` and tenant plan excludes it → OFF
   - **Feature Default (lowest):** Use `Feature.default_enabled`
   - **Audit:** Denials logged with change_type='FeatureGate', enforcement_result='Denied'

3. **Coexistence:**
   - Existing gates continue working during migration
   - New catalog features added gradually (no forced migration)
   - Old and new logic can coexist per feature (add `deprecated_at` flag)
   - Tenant app queries central app for overrides (via REST or cached)

**Example Middleware Implementation:**

```php
namespace App\Http\Middleware;

use Closure;
use App\Models\Tenant;
use App\Services\FeatureService;
use Illuminate\Http\Request;

class EnforceFeatureGate
{
    public function handle(Request $request, Closure $next, $featureName)
    {
        $tenant = Tenant::current(); // Get from request context
        
        // Consult new catalog (checks override > plan > default)
        if (!FeatureService::isEnabled($tenant, $featureName)) {
            $reason = FeatureService::whyDisabled($tenant, $featureName);
            
            // Optional: audit the denied access
            RBACChangeAudit::log(
                'FeatureGate',
                $tenant->id,
                auth()->user()->email,
                ['feature' => $featureName, 'method' => $request->method()],
                'Denied',
                $reason
            );
            
            return response()->json(
                ['message' => "Feature '{$featureName}' is not available"],
                403
            )->header('X-Feature-Disabled-Reason', $reason);
        }
        
        return $next($request);
    }
}
```

---

## Central-to-Tenant Communication (Database-per-Tenant Model)

**Architecture:** Central app manages feature catalog; tenant apps query for overrides.

**Communication Flow:**

1. **Central App (database-per-tenant, central.cateringpro.local):**
   - Hosts Feature, FeatureOverride, RoleTemplate, UsageSnapshot, RBACChangeAudit tables
   - Super-admin toggles feature override via UI
   - Central app queues job: RoleTemplateSyncJob or CaptureUsageSnapshotJob
   - Job switches to tenant database context via `TenantManager::using($tenant_id)`
   - Updates tenant's roles/permissions or creates audit record

2. **Tenant App (own database, e.g., tenant1.cateringpro.local):**
   - Has EnforceFeatureGate middleware that checks local cache OR calls central
   - Cache strategy: FeatureOverride cached locally via Laravel Cache (TTL 5 min)
   - On cache miss: synchronous HTTP GET to central `/api/admin/tenants/{id}/effective-features`
   - Updates local cache and returns result
   - Admin contact POST `/api/central/contacts/{id}/verify` triggers central job
   - Usage snapshot POST `/api/internal/usage/capture` sends data to central (async)

3. **Authorization Boundaries:**
   - Central super-admin can modify ANY tenant's data (no tenant isolation)
   - Tenant admin can view only own account inside tenant app
   - Feature override cache is per-request (not shared across tenants)
   - Cross-tenant queries impossible: database user credentials per-tenant

**Implementation Example:**

```php
// Central App: RoleTemplateSyncJob
class RoleTemplateSyncJob implements ShouldQueue
{
    public function __construct(private string $tenantId, private string $templateId) {}
    
    public function handle(TenantManager $tm): void
    {
        $tm->using($this->tenantId, function () {
            // Now queries tenant database
            $template = RoleTemplate::find($this->templateId);
            $template->syncToTenant(strategy: 'merge');
        });
    }
}

// Tenant App: Cache feature overrides from central
class FeatureService
{
    public static function isEnabled(Tenant $tenant, string $featureName): bool
    {
        $cacheKey = "feature.{$featureName}.{$tenant->id}";
        
        return cache()->remember($cacheKey, minutes: 5, function () use ($tenant, $featureName) {
            // Cache miss: call central API
            $response = Http::get(
                config('central.api_url') . "/api/admin/tenants/{$tenant->central_id}/effective-features"
            );
            
            $features = $response->collect();
            $feature = $features->firstWhere('name', $featureName);
            return $feature['effective_enabled'] ?? false;
        });
    }
}

// Tenant App: Usage snapshot POST to central
class CaptureUsageSnapshotJob implements ShouldQueue
{
    public function handle(Tenant $tenant): void
    {
        $snapshot = $this->gatherMetrics($tenant);
        
        // POST to central /api/internal/usage/capture (authenticated via API key)
        Http::withToken(config('central.api_key'))->post(
            config('central.api_url') . '/api/internal/usage/capture',
            [
                'tenant_id' => $tenant->central_id,
                'snapshot_period' => 'hourly',
                'period_start' => $snapshot['period_start'],
                'metrics' => $snapshot,
            ]
        );
    }
}
```

---

## Cross-Tenant Authorization Boundaries

**Database Isolation (Stancl/Tenancy):**
- Each tenant has separate database (e.g., `cateringpro_tenant_abc123`)
- Database credentials are per-tenant (user@host restricted to tenant DB)
- `TenantManager::using($id)` switches connection context
- Query scope: **App-level explicitly required** — always include `WHERE tenant_id = ?` in central app queries; do not rely on implicit global scoping

**Central App Isolation:**
- Central DB queries NOT scoped to single tenant (super-admin sees all)
- FeatureOverride queries ALWAYS include `WHERE tenant_id = ?`
- Never return data from another tenant without explicit authorization
- Test: `testSuperAdminCanSeeTenantAOverrides`, `testSuperAdminCannotAccessTenantBData` (verify scoped)

**Authorization Policy (Laravel Policies):**

```php
namespace App\Policies;

use App\Models\User;
use App\Models\FeatureOverride;

class FeatureOverridePolicy
{
    public function view(User $user, FeatureOverride $override): bool
    {
        // Super-admin can view all
        if ($user->isSuperAdmin()) return true;
        
        // Support analyst can view all (within central app)
        if ($user->isSupportAnalyst()) return true;
        
        // Ops lead can only toggle (not view reason/export)
        return false;
    }
    
    public function update(User $user, FeatureOverride $override): bool
    {
        if ($user->isSuperAdmin()) return true;
        if ($user->isSupportAnalyst()) return true;
        return false;
    }
    
    public function delete(User $user, FeatureOverride $override): bool
    {
        return $user->isSuperAdmin();
    }
}
```

---

1. **Problem**: Feature override precedence unclear (does override beat plan?)
   **Solution**: Implement is_feature_enabled() with explicit order: override > plan > default

2. **Problem**: Role template sync breaks running tenants
   **Solution**: Always preview before apply; implement rollback; use idempotency keys

3. **Problem**: Usage snapshots missing or duplicated
   **Solution**: Make job idempotent (increment if exists); retry up to 3x; alert if missing

4. **Problem**: Audit logs grow unbounded
   **Solution**: Implement archival strategy; write-once immutable table; 7-year purge policy

5. **Problem**: Contact verification emails not sent
   **Solution**: Use queue-based mail; log failures; test with fake mailer in tests

6. **Problem**: Super-admin can't see all tenants' overrides
   **Solution**: Query feature_overrides by tenant scope; add index (tenant_id, feature_id)

---

## Testing Strategy

### Unit Tests
- Feature model: creation, deletion, default_enabled toggle
- FeatureOverride: expiry logic, deny precedence
- RoleTemplate: clone method, permission preservation
- UsageSnapshot: aggregation calculations

### Integration Tests
- Override precedence (override > plan > default)
- Role template sync with rollback
- Usage snapshot job idempotency
- Audit log immutability

### E2E Tests
- Super-admin toggles feature for tenant; verify tenant sees effective change
- Apply role template; verify tenant roles updated; rollback works
- Capture usage snapshot; query it; verify counts accurate
- Export audit log; verify all entries present and immutable

### Security Tests
- Support Analyst cannot export audit logs
- Ops Lead cannot create custom role templates
- Expired overrides auto-revert correctly
- Unverified contacts do not receive critical notifications

---

## Deployment Checklist

- [ ] All migrations pass on production schema
- [ ] Feature flags registered for gradual rollout (use Laravel Pennant)
- [ ] RBAC roles seeded (Super-Admin, Support Analyst, Ops Lead)
- [ ] Audit log retention policy configured (7 years)
- [ ] Monitoring alerts set up (missing snapshots, unverified contacts)
- [ ] Email delivery tested (verification, notifications)
- [ ] Database backups verified (7-year archival strategy)
- [ ] Documentation updated in deployment checklist
- [ ] Runbook created for common operations (override management, template sync, audit audit)

---

## Success Metrics

- 95% uptime for RBAC endpoints
- Feature override toggle latency < 100ms
- Role template sync completes in < 60 seconds per tenant
- Usage snapshots capture > 95% of events within 1 hour
- Audit logs have zero data loss (write-once, 7-year retention)
- Support Analyst reduces feature toggle time from 10 mins to 30 seconds

---

## References

See CENTRAL_APP_RBAC_ENHANCEMENT_SPEC.md for detailed requirements, domain model, API contract, UI design, and permission matrix.
