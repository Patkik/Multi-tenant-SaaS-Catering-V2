- Domain-based tenancy via SafeDomainTenantInitialization/Stancl should read tenant via tenant() helper, not request attributes.
- Path-based tenancy via ResolveTenantFromRequest sets request attributes tenant and tenant_id; route closures reading request attributes must null-guard anyway.
- When fixing tenant null crashes, verify both domain-based and path-based root/health handlers for consistent defensive checks.
- Tenant creation flow: central Livewire TenantManager creates the tenant row and domain first, then the TenantCreated event triggers database creation + migrations and MarkTenantReady runs after DatabaseMigrated.
- Domain format in this repo: path-based uses /tenant/{tenant}; domain-based uses tenant.localhost style subdomains, with localhost and 127.0.0.1 treated as central domains.
- Tenant database naming is prefixed with tenant_ and tracked in tenants.database_name; provisioning_status gates access and should be ready before requests are allowed.
- Environment constraint: MySQL is the required database backend for this project; treat non-MySQL paths as out of scope unless explicitly requested.
- Critical reliability priority: tenant creation/provisioning must always work (tenant row/domain creation, DB creation, migrations, and ready state transition).

## Central Feature Override Resolution

**Feature Toggle Resolution Order** (whitelist model with deny precedence):
1. Check for per-tenant override (FeatureOverride table): if exists AND not expired, return override state
2. Check tenant plan: if plan does not include feature, return disabled (deny)
3. Check feature default: return Feature.default_enabled state

**Role Template Sync Behavior**:
- Central RoleTemplate is applied to tenant role set asynchronously via RoleTemplateSyncJob
- Backward compatible: existing role names must not be removed; only extend permissions or add new roles
- Previous template version retained in audit trail; rollback capability via revert action
- Template sync is idempotent (idempotency_key prevents duplicate runs)
- Tenant continues serving requests during sync; job uses background queue (no downtime)
