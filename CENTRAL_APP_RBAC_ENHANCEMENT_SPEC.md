# Central App RBAC Enhancement Specification

**Date**: March 30, 2026  
**Status**: Planned (documentation only)  
**Implementation Precondition**: Runtime code implementation requires central app source tree.

---

## 1. Scope and Goals

### Primary Objectives
1. **Super-Admin Role Management**: Create, clone, and customize tenant role templates beyond standard four-tier model
2. **Granular Feature Control**: Toggle individual features on/off per tenant via centralized feature catalog
3. **Tenant Contact Registry**: Maintain contacts (owner, support, technical) per tenant
4. **Usage Snapshots**: Track active users, events booked, API calls at hourly/daily intervals
5. **Role Template Sync**: Propagate template updates to running tenants with zero downtime
6. **Enforcement Audit**: Log all feature overrides, role changes, access denials with compliance context

### Success Criteria
- Super-admins provision tenant with custom role templates in less than 2 minutes
- Feature toggles use deny precedence: any deny = off (whitelist model)
- Usage snapshots capture 95+ percent of events within 1 hour
- All RBAC changes auditable with 7-year retention
- Role-template sync with zero downtime

---

## 2. Domain Model Additions

### 2.1 Feature (Central Registry)
- id (UUID), name, description, category (Core|CRM|Billing|Reporting|Integration|Admin)
- default_enabled (boolean), requires_plan (nullable), created_at, updated_at, deprecated_at

### 2.2 FeatureOverride (Per-Tenant)
- id, tenant_id, feature_id, is_enabled, reason, set_by_admin, set_at, expires_at
- Index: (tenant_id, feature_id) unique

### 2.3 RoleTemplate
- id, name, description, is_system_default, created_by_admin, created_at, updated_at, metadata (JSON)

### 2.4 RoleTemplatePermission
- id, role_template_id, role_name, permission (dot-notation), created_at, updated_at

### 2.5 RoleTemplateFeature
- id, role_template_id, role_name, feature_key, is_enabled, created_at, updated_at
- Index: (role_template_id, role_name, feature_key) unique

### 2.6 TenantAdminContact
- id, tenant_id, contact_type (Owner|Support|Technical|Billing), name, email, phone, is_primary, verified_at

### 2.7 UsageSnapshot
- id, tenant_id, snapshot_period (hourly|daily), period_start, period_end
- active_user_count, api_call_count, events_created, payments_processed, storage_bytes, metadata (JSON)
- Index: (tenant_id, snapshot_period, period_start)

### 2.8 RBACChangeAudit
- id, tenant_id (nullable), change_type (FeatureOverride|RoleTemplateSync|ContactUpdate|UsageAlert)
- actor_email, change_payload (JSON), enforcement_result, result_reason, created_at
- Retention: 7-year minimum

---

## 3. Central API Contract

### Feature Catalog
- GET /api/admin/features - List all features
- GET /api/admin/features/{id} - Fetch detail
- POST /api/admin/features - Create feature
- PATCH /api/admin/features/{id} - Update/deprecate

### Feature Overrides
- GET /api/admin/tenants/{tenant_id}/feature-overrides - List tenant overrides
- POST /api/admin/tenants/{tenant_id}/feature-overrides - Apply override
- DELETE /api/admin/tenants/{tenant_id}/feature-overrides/{feature_id} - Revert override
- GET /api/admin/tenants/{tenant_id}/effective-features - Compute effective state

### Role Templates
- GET /api/admin/role-templates - List templates
- GET /api/admin/role-templates/{id}/permissions - List role/permission pairs
- POST /api/admin/role-templates - Create template (body includes role_name, permissions[], feature_keys[])
- PATCH /api/admin/role-templates/{id} - Update template (body supports permissions[] and feature_keys[])
- POST /api/admin/role-templates/{id}/apply-to-tenant - Sync to tenant

Sample role template create payload:

```json
{
   "role_name": "EventSupervisor",
   "permissions": ["events.view", "events.update", "staff.assign"],
   "feature_keys": ["analytics", "usage-monitoring"]
}
```

### Tenant Contacts
- GET /api/admin/tenants/{tenant_id}/contacts - List contacts
- POST /api/admin/tenants/{tenant_id}/contacts - Add/update contact
- DELETE /api/admin/tenants/{tenant_id}/contacts/{id} - Remove contact
- POST /api/admin/tenants/{tenant_id}/contacts/{id}/verify - Send verification email

### Usage Snapshots
- GET /api/admin/tenants/{tenant_id}/usage - Query historical usage
- GET /api/admin/tenants/{tenant_id}/usage/current - Get most recent
- GET /api/admin/tenants/{tenant_id}/monitoring - Tenant monitoring summary (identity + roles + feature posture + usage snapshot summary)
- POST /api/internal/usage/capture - Internal job endpoint

### RBAC Audit Log
- GET /api/admin/audit/rbac - Query audit trail with filters
- GET /api/admin/audit/rbac/{id} - Fetch single entry

---

## 4. UI Surfaces (Central Control Center)

### RBAC Tab (/dashboard/tenants/{id}/rbac)

**Feature Toggles Card**
- Grid/list of all features with toggle + expiry + reason bubble
- Toggle color indicates override vs default
- Inline editor for reason and expiry date
- Revert action if override exists

**Role Templates Card**
- Current active template name
- Apply New Template button
- Modal with template selector and strategy (Replace|Merge)
- Live preview of role/permission changes
- Progress indicator (est. 15-60 seconds)

**Admin Contacts Card**
- List of owner/support/technical contacts
- Add/edit forms with verification indicator
- Primary contact badge
- Email test button

**Usage Metrics Card**
- KPIs (active users, API calls, events, payments)
- Hourly/daily view toggle
- 7-day sparkline graph
- Details link with download option

**Recent Changes Card**
- Feed of last 10 RBAC audit entries
- Timestamp, actor, change type, description
- Expandable details with full payload
- Revert action if applicable

### Feature-to-Surface Matrix (Authoritative Mapping)

| feature_key | page_route(s) | navbar_item_key | module_key | default_state |
|---|---|---|---|---|
| analytics | /dashboard/analytics, /dashboard/reports/analytics | nav.analytics | analytics.module | enabled |
| crm-sync | /dashboard/integrations/crm | nav.integrations | integrations.crm_sync | disabled |
| api-access | /dashboard/developer/api-keys | nav.developer | developer.api_access | disabled |
| sso | /dashboard/settings/security/sso | nav.settings | auth.sso | disabled |
| role-template-sync | /dashboard/tenants/{id}/rbac | nav.tenants | rbac.role_template_sync | enabled |
| usage-monitoring | /dashboard/tenants/{id}/rbac, /dashboard/tenants/{id}/usage | nav.tenants | monitoring.usage | enabled |

**Toggle Interpretation Rules:**
- Page route toggle: route is blocked (403) when feature is disabled.
- Navbar toggle: navbar item is hidden when feature is disabled.
- Module toggle: backend capability is denied even if route is accessed directly.

### Tenant Monitoring Contract (Required Response Schema)

Endpoint: `GET /api/admin/tenants/{tenant_id}/monitoring`

Required top-level fields:
- `tenant_domain` (string)
- `tenant_name` (string)
- `admin_name` (string)
- `users_total` (integer)
- `active_roles` (string array)
- `active_features_count` (integer)
- `deactivated_features_count` (integer)
- `usage_snapshot_summary` (object)

Required `usage_snapshot_summary` fields:
- `snapshot_period` (hourly|daily)
- `period_start` (ISO-8601 datetime)
- `period_end` (ISO-8601 datetime)
- `active_user_count` (integer)
- `api_call_count` (integer)
- `events_created` (integer)
- `payments_processed` (integer, smallest currency unit)
- `storage_bytes` (integer)
- `last_captured_at` (ISO-8601 datetime)

Sample response payload:

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

## 5. Enforcement Rules

### Feature Toggle Deny Precedence

**Exact Evaluation Order:**
1. Check if override exists AND not expired (`expires_at IS NULL OR expires_at > now()`)
   - If override is active: return `override.is_enabled` (stop evaluation)
2. If override expired or missing, check plan entitlement:
   - If feature requires plan AND tenant plan excludes it: return `false`
3. If plan check passes or feature has no plan requirement: return `feature.default_enabled`

**Example:** Tenant has override `is_enabled=true, expires_at=2026-03-25 14:00` (expired).
- Override ignored (expired) → check plan → if plan includes, use default_enabled → result applies

### Role Template Sync Safety (Replace vs Merge)

**Strategy: Merge (Default)**
- Non-destructive: existing permissions not removed
- New permissions added to tenant roles
- Conflict resolution: if tenant has custom permission not in template, keep tenant version
- Idempotency: idempotency_key prevents duplicate syncs
- Safe rollback: previous template ID stored, can revert atomically

**Strategy: Replace**
- Tenant roles **exactly match** template (remove unmapped roles/permissions)
- Only used with explicit admin confirmation
- Creates full audit trail of removed permissions
- Requires preview approval before execution

### Contact Verification

**Critical Notifications** = notifications marked with `is_critical=true` in audit log settings:
- Feature override expiry alerts (24h, 1h before)
- Failed role template syncs
- Usage threshold breaches (>2x monthly average)
- Unauthorized access attempts
- Plan upgrade/downgrade changes

Contacts must have `verified_at IS NOT NULL` to receive critical notifications. Unverified contacts on CC field only (for audit, no active delivery).

### Usage Snapshot Accuracy

**Capture Schedule:**
- **Hourly**: CaptureUsageSnapshotJob runs every hour at :00 UTC
  - Idempotent: if snapshot exists for `(tenant_id, 'hourly', period_start)`, skip
  - Captures: active_user_count, api_call_count, events_created, payments_processed, storage_bytes
  - period_start = hour floor (e.g., 14:00), period_end = hour floor + 1h

- **Daily Aggregate**: AggregateUsageSnapshotJob runs daily at 00:01 UTC
  - Sums 24 hourly snapshots from previous day (00:00-23:59 UTC)
  - Creates single daily record with aggregated totals
  - Idempotent: if daily record exists for date, update (not insert)

**Retry Strategy:** If capture fails, retry up to 3x with exponential backoff (30s, 90s, 270s).

### Audit Log Immutability

Write-once, never deleted. 7-year minimum retention. Enforced via:
- **Database Level:** MySQL trigger prevents UPDATE/DELETE on rbac_change_audits
  - `BEFORE UPDATE ... SIGNAL SQLSTATE '45000'; BEFORE DELETE ... SIGNAL SQLSTATE '45000'`
- **Application Level:** RBACChangeAudit::update() throws exception
- **Storage:** Archive to immutable blob storage (S3 Object Lock / Azure Immutable Blobs) at 2-year mark

### EnforceFeatureGate Middleware Integration

**Middleware Precedence Chain:**
1. EnforceFeatureGate (existing) checks: `FeatureService::isEnabled()` → uses new override + plan logic
2. Feature override checks happen BEFORE plan checks (override has higher priority)
3. If feature disabled, return 403 Forbidden with reason header: `X-Feature-Disabled-Reason: expired-override`
4. Audit log created for all denied requests (change_type='FeatureGate', enforcement_result='Denied')

**Coexistence:** Existing feature gates continue to work; add new catalog features gradually.

### Central-to-Tenant Communication (Database-per-Tenant)

**Central App → Tenant DB:**
- RoleTemplateSyncJob dispatches per-tenant job via async queue
- Tenant connection resolved via `TenantManager::using($tenant_id)` during execution
- Sync updates tenant's roles/permissions tables directly
- Cross-tenant boundaries: tenants cannot access other tenants' DBS (enforced at DB user level)

**Tenant App → Central DB:**
- Tenant POST /api/central/usage captures and sends to central `POST /api/internal/usage/capture`
- Central queues UsageSnapshot creation (tenant_id known from request context)
- Tenant audit events can post to central audit log (optional, for compliance)

**Authorization Boundaries:**
- Central super-admin can modify any tenant's overrides/templates
- Tenant admin can view only own account (no cross-tenant visibility inside tenant app)
- Central app enforces tenant ID isolation on all queries (no assumption of single-tenant context)

---

## 6. Permission Matrix

| Action | Super-Admin | Support Analyst | Ops Lead |
|---|---|---|---|
| List features | Y | Y | N |
| Create/deprecate | Y | N | N |
| Toggle feature per tenant | Y | Y | Y |
| View override reason/expiry | Y | Y | Y |
| Set expiry date | Y | Y | N |
| List role templates | Y | Y | N |
| Create template | Y | N | N |
| Apply template to tenant | Y | Y | N |
| List contacts | Y | Y | N |
| Add/update contact | Y | Y | Y |
| View usage | Y | Y | Y |
| Export usage | Y | Y | N |
| View audit trail | Y | Y | Y |
| Export audit (7yr) | Y | N | N |

---

## 7. Phased Implementation

### Phase 1: Core Data Model (Weeks 1-2)
- Feature, FeatureOverride, RoleTemplate, RoleTemplatePermission tables
- RBACChangeAudit, TenantAdminContact, UsageSnapshot tables
- Feature CRUD endpoints
- Unit tests for CRUD

### Phase 2: Admin UI - Feature & Contact Mgmt (Weeks 3-4)
- Feature Toggles Card + inline editor
- Admin Contacts Card + verification flow
- Feature Catalog management page
- Email verification delivery

### Phase 3: Role Templates & Usage Metrics (Weeks 5-6)
- Role Templates Card + selector
- Template sync job + progress tracking
- Usage Snapshot periodic job
- Usage Metrics Card + 7-day sparkline

### Phase 4: Audit & Compliance (Week 7)
- RBAC Audit Log UI with filtering
- CSV export with 7-year validation
- Role enforcement (Super-Admin, Analyst, Ops Lead)
- Compliance checklist

### Phase 5: Auto-Expiry & Monitoring (Week 8)
- Expired override auto-revert job
- Missing snapshot alerting
- Unverified contact alerting
- Health dashboard

---

## 8. Implementation Precondition

**CRITICAL**: This specification documents contract and domain model ONLY.

**Runtime code implementation requires**:
1. Central App Laravel source tree (models, controllers, migrations, routes)
2. Current workspace does NOT contain executable central app code
3. Apply specification to dedicated central app repository

**Before Coding**:
- Confirm Laravel structure in target repo
- php artisan tinker with central DB
- Verify stancl/tenancy installed
- Run test suite baseline

---

## 10. Backward Compatibility: Migration from Plan-Based Gates

**Legacy Model:**
- Feature availability determined by `Tenant.plan_name` (Pro|Standard|Basic)
- Constants defined per plan: `PLAN_PRO_FEATURES`, `PLAN_STANDARD_FEATURES`, etc.

**Migration Path (Zero Downtime):**
1. **Phase 1 - Feature Catalog Bootstrap (Day 1)**
   - Create Feature records for each legacy gate (e.g., 'analytics', 'crm-sync')
   - Set `requires_plan` field to corresponding plan ID
   - Set `default_enabled = true` (gates currently on = default on)
   
2. **Phase 2 - Code Update (Week 1)**
   - Update `isFeatureEnabled()` AND old `hasPlanFeature()` methods to consult new catalog
   - Both methods return same result (backward-compatible logic)
   - No tenant changes required
   
3. **Phase 3 - Opt-In Overrides (Weeks 2-3)**
   - Admins can now override per-tenant BEFORE plan changes take effect
   - Example: disable 'analytics' for a trial tenant even though they have Pro plan
   - Existing behavior unchanged; overrides are additive
   
4. **Phase 4 - Deprecate Legacy Gates (Week 4)**
   - Set `Feature.deprecated_at = now()` for old-style gates
   - Log deprecation warning in app logs
   - No tenant impact; gates still work via fallback
   
5. **Phase 5 - Full Catalog Enforcement (Month 2)**
   - Remove legacy `hasPlanFeature()` method
   - All feature checks use catalog + overrides
   - Rollback: if catalog check fails, restore deprecated features to default_enabled

**Rollout/Rollback Guardrails:**
- Feature flag `use_catalog_overrides` controls rollout (Laravel Pennant or config toggle)
- Admins can enable/disable per tenant during testing
- Rollback: set `Feature.deprecated_at = null` to restore legacy behavior
- No data loss: override records kept for 90 days after deprecation

**Testing Checklist:**
- Legacy plan checks still return same result post-migration
- New overrides respected before plan checks
- Deprecation warnings appear in logs, no exceptions
- Rollback restores old method behavior without data loss

---

## 11. References

- Central App Architecture: SYSTEM_CAPABILITIES.md
- Tenant Multi-DB Model: memories/repo/tenancy-route-context.md
- Feature Flags: Laravel Pennant (https://laravel.com/docs/pennant)
- Jobs: Laravel Horizon (https://horizon.laravel.com)
- RBAC: Laravel Policies
- Compliance: SOC 2 Type II, 7-year retention
