# CaterPro SaaS - Technical Debt & LAN Deployment Analysis

**Document Version:** 1.0  
**Date:** April 28, 2026  
**Tech Stack:** Laravel 13, Stancl/Tenancy v3, React 19, MySQL 8, SQLite 3  
**Deployment Target:** LAN (Local Area Network)

---

## EXECUTIVE SUMMARY

CaterPro is a multi-tenant SaaS catering platform with architectural dependencies that create friction in LAN deployments. Key issues:

- **Multi-Database Problem**: Separate MySQL DB per tenant requires sophisticated orchestration or SQLite fallback
- **DNS Subdomain Routing**: Requires wildcard DNS (`*.caterpro.local`) or hardcoded IP:port routing
- **Version Sync Fragmentation**: App version lives in 2+ sources (package.json, config/app.version, GitHub tags)
- **Permission Cache Volatility**: Spatie permission cache can go stale on multi-instance deployments
- **Environment Configuration Complexity**: 15+ environment variables with interdependencies
- **Central User Schema Assumptions**: Code assumes only default Laravel `users` columns (name, email, password)
- **No Tenant Account Creation Validation**: Subdomain availability checks lack atomic transaction guarantees

---

## SECTION 1: DATABASE & MULTI-TENANCY ISSUES

### 1.1 Critical: Template Tenant Connection Conflict
**Severity:** HIGH | **Impact:** Tenant database creation fails

**Current Issue:**
```php
// config/tenancy.php
'template_tenant_connection' => 'tenant_template',

// Problem: If this connection points to a live MySQL server and a tenant DB
// is created from it, the template data gets corrupted or duplicated.
```

**LAN Deployment Problem:**
- If using MySQL on LAN, the `tenant_template` connection must point to a *separate* database instance
- If no separate instance exists, using SQLite is mandatory
- No documentation exists for "what should be in the template DB"

**Recommendation for Another AI:**
"Create a LAN-safe database template strategy: Define exactly what schema/data the template_tenant_connection should contain, handle cases where it's SQLite vs MySQL, and provide migration guards to prevent template corruption."

---

### 1.2 Critical: Tenant Database Naming Collision
**Severity:** HIGH | **Impact:** Tenant creation overwrites existing databases

**Current Issue:**
```php
// config/tenancy.php
'prefix' => 'tenant',
'suffix' => '',

// Creates databases like: tenant<tenant_id>
// If tenant_id is UUID, creates: tenant550e8400-e29b-41d4-a716-446655440000
// Problem: No uniqueness guarantee across deployments or rollback scenarios
```

**LAN Deployment Problem:**
- On LAN with multiple deployment environments (dev/staging/prod), database names collide
- No migration rollback strategy if tenant creation partially fails
- SQLite file naming uses tenant ID as filename: `storage/tenants/{id}.sqlite`
  - If storage directory isn't synchronized across LAN nodes, tenants become unreachable

**Recommendation for Another AI:**
"Implement tenant database naming with deployment environment prefix (e.g., `dev_tenant_<id>`, `prod_tenant_<id>`), ensure rollback atomicity, and handle SQLite file synchronization across LAN storage shares."

---

### 1.3 Critical: Stale Tenant DB References in Cache
**Severity:** MEDIUM-HIGH | **Impact:** Ghost tenant lookups, performance degradation

**Current Issue:**
```php
// config/tenancy.php
'cache' => [
    'tag_base' => 'tenant',
    // Each cache key gets tagged with: tenant<tenant_id>
],

// Problem: If a tenant DB is deleted but cache entries remain,
// subsequent queries will hit a non-existent database and cause 500s
```

**LAN Deployment Problem:**
- Multi-instance LAN deployments must share cache (Redis/Memcached)
- File-based cache on local storage won't sync across network
- No cache invalidation hook exists when tenant databases are deleted
- `TenantDeleted` event doesn't explicitly clear cache tags

**Code Reference:**
```php
// app/Providers/TenancyServiceProvider.php
Events\TenantDeleted::class => [
    JobPipeline::make([
        Jobs\DeleteDatabase::class,
    ])->send(function (Events\TenantDeleted $event) {
        return $event->tenant;
    })->shouldBeQueued(false), // Synchronous only
    // MISSING: Cache::tags('tenant' . $event->tenant->id)->flush();
],
```

**Recommendation for Another AI:**
"Implement explicit cache invalidation on tenant deletion: flush all cache tags matching the deleted tenant ID, ensure this runs synchronously (not queued), and add regression tests for ghost tenant lookups."

---

### 1.4 High: Tenant Domain Resolution Race Condition
**Severity:** MEDIUM | **Impact:** Occasional "tenant not found" 404s on first request

**Current Issue:**
```
// Domain resolution flow:
1. Request arrives: user.tenant.local
2. InitializeTenancyBySubdomain middleware extracts "tenant" subdomain
3. Query domains table: WHERE domain = 'user.tenant.local'
4. If domain NOT in table yet, TenantCouldNotBeIdentifiedOnDomain exception
```

**Memory Note Reference:**
> "With `InitializeTenancyBySubdomain`, persist `domains.domain` as subdomain key (e.g., `acme`), not full host (`acme.localhost`), to avoid TenantCouldNotBeIdentifiedOnDomain errors."

**LAN Deployment Problem:**
- Multi-instance deployments: Instance A creates tenant → domain not yet replicated to Instance B
- Domain creation is NOT atomic with tenant creation
- No DNS propagation wait time built in
- LAN users might access old DNS cache entry pointing to wrong instance

**Recommendation for Another AI:**
"Implement atomic tenant+domain creation with retry logic, handle DNS cache invalidation on LAN, and add integration tests for multi-instance domain propagation delays."

---

### 1.5 High: No Tenant Isolation Verification
**Severity:** MEDIUM | **Impact:** Potential data leakage between tenants in edge cases

**Current Issue:**
- No automated tests verify data isolation between tenant databases
- No audit trail when tenant connections switch
- No validation that queries execute on the correct tenant DB connection

**LAN Deployment Problem:**
- Multi-instance deployments might route requests to wrong tenant DB
- Connection pooling issues on LAN could cause connection leaks
- No monitoring/alerting for cross-tenant query attempts

**Recommendation for Another AI:**
"Add tenant isolation regression suite: create 2+ test tenants, verify no data leakage via shared query filters, test connection switching under load, and add telemetry for failed tenant initialization."

---

## SECTION 2: AUTHENTICATION & AUTHORIZATION ISSUES

### 2.1 Critical: Permission Cache Fragmentation on Multi-Instance LAN
**Severity:** HIGH | **Impact:** RBAC inconsistency, unauthorized access, phantom permissions

**Current Issue:**
```php
// app/Providers/TenancyServiceProvider.php
Events\TenancyInitialized::class => [
    ...,
    [$this, 'bootstrapTenantPermissionCache'],
],

// Problem: Spatie permission cache is loaded per request
// but Spatie requires explicit cache reset after permission changes
```

**Known Issue from Repo Memory:**
> "Spatie `hasAnyPermission` expects variadic arguments; passing an array (`hasAnyPermission(CentralPermissions::all())`) can make central login always fail with 'not allowed' despite assigned permissions. Use spread syntax (`hasAnyPermission(...CentralPermissions::all())`) and reset permission cache (`php artisan permission:cache-reset`) when troubleshooting stale RBAC behavior."

**LAN Deployment Problem:**
- Multi-instance LAN deployments share file system for app but NOT permission cache
- Instance A updates permission → Instance B still has old cached permissions
- `php artisan permission:cache-reset` must run on ALL instances simultaneously
- No distributed cache lock prevents race conditions during permission updates

**Code Issue:**
```php
// app/Providers/TenancyServiceProvider.php
protected function bootstrapTenantPermissionCache()
{
    // Likely using file-based cache store per instance
    // Result: Each instance has stale copy of permissions
}
```

**Recommendation for Another AI:**
"Implement distributed permission cache: use Redis/Memcached for LAN deployments, add cache versioning to detect stale caches, implement atomic permission update + broadcast invalidation across all instances, add tests for permission update propagation."

---

### 2.2 High: Central vs Tenant User Schema Mismatch
**Severity:** MEDIUM-HIGH | **Impact:** Unexpected auth failures, field not found errors

**Current Issue:**
```php
// app/Models/User.php
// Only guarantees: name, email, password (default Laravel)

// Problem: Code might query for fields that don't exist:
$user->firstname;  // ❌ Not guaranteed to exist
$user->is_active;  // ❌ Not guaranteed to exist
```

**Known Issue from Repo Memory:**
> "Central user-management search/update must assume landlord `users` table only has default Laravel columns (`name`, `email`, `password`); querying or persisting `firstname/lastname/username/is_active` on central routes can fail on fresh environments."

**LAN Deployment Problem:**
- Fresh LAN setup creates landlord DB without custom user columns
- Central auth routes fail when trying to fetch custom fields
- No migration provides custom user columns
- No validation in User model to document required columns

**Recommendation for Another AI:**
"Create a strict User model with explicit column guards, add a migration for custom user fields, implement factory constraints, and add schema validation tests that run on fresh databases."

---

### 2.3 Medium: API Route CSRF/Authentication Mismatch
**Severity:** MEDIUM | **Impact:** JSON POST requests fail with 419 Unauthorized

**Current Issue:**
```php
// routes/api.php uses auth:sanctum middleware
// routes/web.php uses CSRF middleware

// Problem: Tenant API routes might still apply web middleware
```

**Known Issue from Repo Memory:**
> "Tenant API routes should use `api` middleware (not `web`) to avoid CSRF token mismatch on JSON POST endpoints."

**LAN Deployment Problem:**
- LAN clients making JSON requests get 419 Unauthorized if CSRF enabled
- Sanctum token validation fails if token not in Authorization header
- No clear documentation on token placement in requests

**Recommendation for Another AI:**
"Document API authentication contract: token header format, CSRF exemption for JSON routes, add integration tests for API requests from different clients, verify no web middleware is applied to /api/tenant routes."

---

## SECTION 3: CENTRAL APP CONFIGURATION & VERSIONING

### 3.1 Critical: Version Source Fragmentation
**Severity:** HIGH | **Impact:** Deployment mismatch, UI reports wrong version

**Current Issue:**
```
Version sources (must stay in sync):
1. central-app/package.json → version: "2.0.16"
2. config/app.php → version: env('APP_VERSION', '...')
3. .env → APP_VERSION=2.0.16 (if set)
4. GitHub tags → v2.0.16 (release history)
5. central-app/package-lock.json → implicit version
```

**Known Issue from Repo Memory:**
> "App sync-version uses config app.version (APP_VERSION or central-app/package.json), not GitHub latest tag.
> If package.json version is not bumped, Sync Version can report already synced at old version even when newer release tag exists.
> Keep central-app/package.json and central-app/package-lock.json version fields bumped with each release."

**LAN Deployment Problem:**
- Manual LAN deployments might skip .env APP_VERSION setup
- UI version badge vs backend version can diverge
- No automated sync on deployment
- No validation that all version sources match before starting app

**Code References:**
```php
// config/app.php (likely uses env() fallback)
'version' => env('APP_VERSION', '2.0.16'),

// UI fetches version from backend config
// If ENV not set, UI shows hardcoded fallback instead of real version
```

**Recommendation for Another AI:**
"Create a single version source of truth (composer.json or .env.production), implement pre-deployment validation that enforces version consistency, add startup checks that fail if versions diverge, document the version update procedure for LAN operators."

---

### 3.2 High: Missing .env.production Template
**Severity:** MEDIUM-HIGH | **Impact:** Configuration errors on fresh LAN setup

**Current Issue:**
- Project has `.env.example` but no `.env.production` or `.env.local.example`
- No documentation on which env variables are critical for LAN

**LAN Deployment Problem:**
- Fresh LAN deployment requires manual .env setup
- No guidance on which vars should be different for LAN vs cloud
- Database connection strings hardcoded vs templated inconsistently
- Mail configuration, cache driver, session driver not documented

**Recommendation for Another AI:**
"Create .env.production and .env.local.example templates with LAN-specific defaults (file-based cache/session, SQLite tenant DBs, localhost mail, etc.), add .env validation on startup, document which vars are environment-specific vs shared."

---

### 3.3 High: Central Domains Configuration Complexity
**Severity:** MEDIUM | **Impact:** Central app not accessible on LAN

**Current Issue:**
```php
// config/tenancy.php
'central_domains' => [
    ...array_filter(array_map('trim', explode(',', env('CENTRAL_DOMAINS', '127.0.0.1,localhost')))),
],
```

**LAN Deployment Problem:**
- Default: `127.0.0.1,localhost` only works on single machine
- LAN requires: `central.local`, `192.168.1.100`, machine hostname, etc.
- No validation that at least ONE central domain is reachable
- Missing domains cause cryptic "Tenant not found" errors on local requests

**Recommendation for Another AI:**
"Add CENTRAL_DOMAINS validation on startup, provide examples for LAN (hostname, IP, .local domain), add health check that verifies central domain is accessible, document DNS setup requirements for multi-machine LAN."

---

## SECTION 4: SYSTEM HEALTH & MONITORING ISSUES

### 4.1 High: Health Checks Assume Cloud Infrastructure
**Severity:** MEDIUM | **Impact:** False negative health alerts, misleading metrics

**Current Issue:**
```php
// From system-health-monitoring-notes.md:
// "Removed synthetic Queue throughput percentage from resource usage;
//  surface real queue facts via pending/failed job counts"
//
// But health checks still report synthesized uptime %
```

**Current Health Endpoint Structure:**
- `/api/central/system-health` returns:
  - Tenant databases count
  - Pending jobs
  - Failed jobs 24h
  - Avg API latency
  - Service health list
  - Resource usage

**LAN Deployment Problem:**
- Health checks assume Redis for queue (LAN might use database queue or sync)
- Uptime percentages fabricated without telemetry history
- Service health reports hardcoded service names (Redis, S3) not configured services
- Resource usage requires OS-level telemetry (PowerShell CIM on Windows, /proc on Linux)
- No Windows host metrics collection

**Known Issue from Repo Memory:**
> "Windows host metrics require OS telemetry commands (PowerShell CIM) for real CPU/memory; `sys_getloadavg` and PHP memory usage alone can misrepresent host utilization."

**Recommendation for Another AI:**
"Implement configuration-driven health checks: read actual drivers from config (cache.default, queue.default, filesystems.default), gather real host metrics via OS commands (PowerShell CIM for Windows, /proc for Linux), remove fabricated percentages, add local storage disk checks."

---

### 4.2 High: Manual Update Application Command
**Severity:** MEDIUM | **Impact:** App updates fail silently on LAN

**Current Issue:**
```
UI calls: POST /api/central/app-updates/apply
Backend requires: APP_UPDATE_APPLY_COMMAND env var
If not set: Update fails, UI shows confusing error
```

**Known Issue from Repo Memory:**
> "Central update UX: `Update System` calls `/api/central/app-updates/apply`; automatic execution only works when `APP_UPDATE_APPLY_COMMAND` is configured, otherwise UI should route admins to release instructions."

**LAN Deployment Problem:**
- LAN admins don't know about APP_UPDATE_APPLY_COMMAND requirement
- Update button appears to work but silently fails
- No clear error message in UI or logs
- Manual deployment process not documented

**Recommendation for Another AI:**
"Add APP_UPDATE_APPLY_COMMAND validation on startup, display clear error if missing, document LAN update procedure (git pull, composer install, migrations, rebuild assets), implement safe update with rollback."

---

## SECTION 5: FRONTEND/BACKEND INTEGRATION ISSUES

### 5.1 High: Version Badge Inconsistency
**Severity:** MEDIUM | **Impact:** UI displays stale version despite deployment

**Current Issue:**
```javascript
// Frontend fetches version from backend at runtime (good)
// Backend returns config('app.version')
// But config('app.version') can be stale if:
// - APP_VERSION env var not updated
// - package.json updated but APP_VERSION not synced
// - config:cache used with old APP_VERSION
```

**Known Issue from Repo Memory:**
> "Version badges should prefer backend runtime version (`config('app.version')`) over build-time Vite env to avoid stale UI values; release parity is enforced via `.github/workflows/version-tag-sync.yml` against `central-app/package.json`."

**LAN Deployment Problem:**
- LAN deployments don't use GitHub workflows
- Manual deployments easily forget APP_VERSION update
- config:cache traps old version in storage/framework/config.php
- No version validation on app startup

**Recommendation for Another AI:**
"Read version from package.json at runtime instead of env var, skip config:cache for version string, add version verification test on startup, display prominent warning if version is stale."

---

### 5.2 High: React Router SPA Fallback Issue
**Severity:** MEDIUM | **Impact:** Direct URL navigation fails with 404

**Current Issue:**
```php
// routes/web.php
Route::view('/', 'app');
Route::view('/{any}', 'app')->where('any', '^(?!api(?:/|$)).*');

// Problem: Regex catches both /central/tenants AND /api/central/tenants
// Fallback to SPA might happen for API routes
```

**LAN Deployment Problem:**
- If API routes not properly segregated, SPA serves HTML for API requests
- Client receives HTML instead of JSON, JavaScript crashes
- No clear error in browser console (silent failure)
- Subdomain routing on LAN might confuse route matching

**Recommendation for Another AI:**
"Add explicit route tests for API vs SPA routes, ensure API routes are prioritized in route registration, add middleware that validates Accept header (reject API routes requesting HTML), add integration tests for both central.local and api routes."

---

## SECTION 6: DEPLOYMENT & INFRASTRUCTURE ISSUES

### 6.1 Critical: No Deployment Validation Checklist
**Severity:** HIGH | **Impact:** Production deployment fails silently or partially

**LAN Deployment Problem:**
- No pre-flight deployment script
- Required steps unclear (migrations, seeders, asset builds, permission cache reset)
- Database connection validation missing
- Environment variable validation missing
- Filesystem permissions not checked

**Known Deployment Steps (scattered across code):**
1. `php artisan config:cache`
2. `php artisan key:generate`
3. `php artisan migrate --force`
4. `npm install && npm run build`
5. `php artisan tenants:migrate`
6. `php artisan permission:cache-reset`
7. Optional: `php artisan tenants:seed`

**Recommendation for Another AI:**
"Create a deployment checklist script: validate DB connections, check env vars, run migrations atomically, clear caches, rebuild assets, verify all connections, run smoke tests, document rollback procedure."

---

### 6.2 High: No Persistent Storage Configuration for LAN
**Severity:** MEDIUM | **Impact:** File uploads lost on app restart

**Current Issue:**
- `FILESYSTEM_DISK` defaults to `local` (app storage directory)
- On multi-instance LAN, each instance has separate storage
- No network storage documentation (NFS, Samba share)
- SQLite tenant DB files stored locally, not synchronized

**LAN Deployment Problem:**
- Instance A: Upload file → stored in Instance A storage
- User hits Instance B (load balancer): File not found 404
- Multi-instance LAN requires shared network storage
- No documentation on how to set up network storage path

**Recommendation for Another AI:**
"Document shared storage setup for LAN (NFS mount, Samba share setup), implement storage path validation on startup, add file storage migration for multi-instance deployments, handle permission issues on network shares."

---

### 6.3 High: Queue Synchronous Mode Default
**Severity:** MEDIUM | **Impact:** Slow requests under load, no async processing

**Current Issue:**
```php
// routes/web.php shows:
// QUEUE_DRIVER=sync (likely default)

// Problem: All jobs run synchronously
// - Tenant provisioning blocks user
// - Email sending blocks response
// - Migrations block UI
```

**LAN Deployment Problem:**
- Provisioning new tenant takes 30+ seconds (blocking)
- LAN users see hung browser waiting for response
- No background job queue running
- Monitoring can't see pending/failed jobs

**Recommendation for Another AI:**
"Implement async job queue for LAN: use database-backed queue (simpler than Redis), implement job monitoring UI, handle job failures gracefully, document queue worker setup for multi-instance LAN."

---

## SECTION 7: TESTING & VERIFICATION GAPS

### 7.1 High: Tenant Auth & Capability Test Coverage Fragmentation
**Severity:** MEDIUM | **Impact:** Auth regressions not caught before deployment

**Current Issue:**
```
Test files missing:
- TenantAuthApiTest (separate from TenantUserApiTest)
- TenantCapabilityApiTest (separate test suite)

All auth tests cramped into: tests/Feature/TenantUserApiTest.php
```

**Known Issue from Repo Memory:**
> "Tenant auth/capability regression coverage currently lives in tests/Feature/TenantUserApiTest.php (no dedicated TenantAuthApiTest/TenantCapabilityApiTest files), so extend that file when adding tenant auth guards."

**LAN Deployment Problem:**
- Large test files hard to maintain and understand
- Auth regressions not caught by focused tests
- Permission changes not validated by dedicated tests
- Capability feature flag changes not tested

**Recommendation for Another AI:**
"Create dedicated test files: TenantAuthApiTest, TenantCapabilityApiTest, add comprehensive guard tests, add feature flag behavior tests, implement test suite that runs before deployment."

---

### 7.2 High: Multi-Tenancy Isolation Tests Missing
**Severity:** MEDIUM | **Impact:** Data leakage not detected until production

**Current Issue:**
- No integration tests that create 2+ tenants and verify isolation
- No tests that simulate request routing to wrong tenant
- No tests for concurrent requests to different tenants
- No connection pool isolation tests

**Recommendation for Another AI:**
"Create multi-tenancy test suite: verify data isolation, test concurrent requests, simulate connection failures, verify cache tag isolation, add load testing with multiple tenants."

---

## SECTION 8: LAN-SPECIFIC DEPLOYMENT CONSTRAINTS

### 8.1 DNS Configuration for LAN Multi-Tenancy
**Severity:** CRITICAL | **Impact:** Tenant apps unreachable without proper DNS

**LAN Requirements:**
```
Central App: central.local or 192.168.1.100:8000
Tenant subdomains: *.local
Examples:
  - acme.local (subdomain routing)
  - acme.catering.local (alternative)
```

**Options:**
1. **Hosts file**: Manual, doesn't scale beyond 5-10 tenants, doesn't work for mobile clients
2. **Wildcard DNS**: Requires Pi-hole, dnsmasq, or Windows DNS Server on LAN
3. **Reverse proxy**: nginx/Apache with subdomain to port mapping
4. **IP:port routing**: Not viable for SaaS (users expect clean domains)

**Recommendation for Another AI:**
"Document 3 LAN DNS options with setup steps, provide example Dnsmasq config for wildcard DNS, provide nginx reverse proxy config for subdomain routing, include troubleshooting for DNS resolution failures."

---

### 8.2 HTTP vs HTTPS on LAN
**Severity:** MEDIUM | **Impact:** Browser warnings, mixed content errors, API failures

**Current Issue:**
- Frontend is SPA (React), requires secure cookies for auth
- Sanctum tokens use HTTP-only cookies
- Browsers block HTTP cookies on HTTPS sites and vice versa

**LAN Options:**
1. **Self-signed certificate**: Requires trust store on all clients
2. **HTTP only**: Disable secure flag (dev only, not recommended)
3. **Let's Encrypt wildcard**: Requires internet access and real domain

**Recommendation for Another AI:**
"Provide self-signed cert setup for LAN with trust store instructions, implement dynamic secure cookie flag based on environment, document HTTPS vs HTTP trade-offs for LAN security posture."

---

### 8.3 Load Balancing & Multi-Instance LAN Deployment
**Severity:** HIGH | **Impact:** Session loss, permission inconsistency, file sync failures

**LAN Requirements:**
```
Instance 1 (192.168.1.100): php artisan serve --port=8000
Instance 2 (192.168.1.101): php artisan serve --port=8000
Reverse proxy (nginx on 192.168.1.1): Routes to both instances
```

**Required Synchronization:**
- File system: Shared NFS/Samba mount for uploads, SQLite tenant DBs
- Cache: Redis/Memcached shared across instances (NOT file-based)
- Session: Redis/Memcached or database (NOT file-based)
- Permissions: Redis cache or Spatie permission cache reset broadcast
- Database connections: Connection pooling on shared MySQL

**Recommendation for Another AI:**
"Provide multi-instance LAN architecture diagram, document nginx load balancer setup, implement session stickiness vs shared sessions comparison, add multi-instance deployment checklist."

---

### 8.4 Windows-Specific LAN Considerations
**Severity:** MEDIUM | **Impact:** File permissions, line endings, PowerShell execution issues

**Windows LAN Issues:**
- File permissions on Samba shares (world-readable default)
- Line endings CRLF vs LF (git config core.autocrlf)
- PowerShell execution policy prevents scripts
- Windows Defender virus scanning slows file access
- UNC paths in config variables (\\server\share)

**Recommendation for Another AI:**
"Document Windows-specific setup: file permissions on Samba, git config, PowerShell policy, Defender exclusions, UNC path handling in .env, Windows network share mounting via NET USE command."

---

## SECTION 9: DATABASE BACKUP & RECOVERY FOR LAN

### 9.1 High: No Backup Strategy for Multi-Database Architecture
**Severity:** MEDIUM | **Impact:** Single tenant DB corruption affects all tenants

**Backup Challenge:**
```
Landlord DB: 1x MySQL database
Tenant DBs:  N x SQLite files OR N x MySQL databases
Total: 1 + N databases to backup
```

**Risks:**
- Incremental backups complex: Some DBs backed up, others not
- No point-in-time recovery
- No backup versioning
- No recovery validation

**Recommendation for Another AI:**
"Implement backup strategy: backup landlord + all tenant DBs atomically, store versioned backups with timestamps, implement recovery testing on schedule, add backup validation checks, document recovery procedure."

---

## SECTION 10: PRODUCTION-READINESS CHECKLIST FOR LAN

### Pre-Deployment Validation

- [ ] **Database**: Landlord connection tested, tenant template connection verified
- [ ] **DNS**: All required domains resolvable (central.local, *.local)
- [ ] **Environment Variables**: All 15+ vars set, validated, no placeholders
- [ ] **File Storage**: Shared network storage configured and tested
- [ ] **Cache**: Redis/Memcached running (NOT file-based on multi-instance)
- [ ] **Session Store**: Redis/Memcached or database (NOT file-based)
- [ ] **Permissions**: Spatie permissions cached and invalidation tested
- [ ] **Version**: APP_VERSION, package.json, and config aligned
- [ ] **Assets**: npm run build executed, public/build populated
- [ ] **Migrations**: php artisan migrate --force verified
- [ ] **Seeders**: Test data seeded and verified
- [ ] **Health Check**: /api/health returns 200 OK
- [ ] **Auth**: Central login tested with multiple users
- [ ] **Tenant Provisioning**: Test tenant created and accessible
- [ ] **Multi-instance**: Load balancer routing verified
- [ ] **Backups**: Backup scripts tested with recovery validation

---

## SUMMARY TABLE: Critical Issues Needing Resolution

| Category | Issue | Severity | LAN Impact | Recommendation |
|----------|-------|----------|-----------|-----------------|
| **Tenancy** | Template DB conflict | HIGH | Tenant creation fails | Define template schema, separate connection |
| **Tenancy** | Domain race condition | HIGH | 404 on first access | Atomic tenant+domain, retry logic |
| **Auth** | Permission cache fragmentation | HIGH | RBAC mismatch across instances | Distributed cache (Redis) for multi-instance |
| **Database** | No isolation verification | MEDIUM | Data leakage risk | Tenant isolation test suite |
| **Config** | Version source fragmentation | HIGH | Stale UI version | Single source of truth, validation on startup |
| **Config** | Missing .env.production | HIGH | Manual setup errors | Template .env files with LAN defaults |
| **Health** | Cloud infrastructure assumptions | MEDIUM | False alerts, missing metrics | Configuration-driven health checks |
| **Infrastructure** | No deployment validation | HIGH | Silent failures | Pre-flight checklist script |
| **Infrastructure** | No persistent storage config | MEDIUM | Files lost on restart | Shared storage setup documentation |
| **DNS** | Subdomain routing on LAN | CRITICAL | Tenants unreachable | DNS documentation, dnsmasq/nginx examples |
| **Monitoring** | No multi-instance sync | HIGH | Session/permission inconsistency | Load balancer & cache setup |
| **Testing** | Auth tests fragmented | MEDIUM | Regressions missed | Dedicated test files |

---

## NEXT STEPS FOR ANOTHER AI

1. **Choose LAN Architecture**: Single-instance vs multi-instance with load balancer
2. **Database Strategy**: SQLite for all tenants vs MySQL + separate tenant connections
3. **Cache/Session**: File-based (dev-only) vs Redis/Memcached (production)
4. **DNS**: Hosts file (testing) vs dnsmasq (LAN) vs reverse proxy
5. **Backup**: Manual scripts vs automated backup cronjobs
6. **Deployment**: Manual setup vs Docker/Compose for reproducibility

---

## CONFIGURATION REFERENCE

### Critical Environment Variables
```bash
# Database
DB_CONNECTION=mysql
DB_LANDLORD_HOST=localhost
DB_LANDLORD_DATABASE=caterpro_landlord
DB_LANDLORD_USERNAME=root
DB_LANDLORD_PASSWORD=password
DB_TENANT_DRIVER=sqlite

# Tenancy
CENTRAL_DOMAINS=127.0.0.1,localhost,central.local
APP_UPDATE_APPLY_COMMAND=

# Cache (MUST be Redis on multi-instance LAN)
CACHE_DRIVER=file
CACHE_STORE=file

# Session
SESSION_DRIVER=file

# Queue
QUEUE_DRIVER=sync

# App Version (must match package.json)
APP_VERSION=2.0.16

# Mail
MAIL_DRIVER=log
MAIL_FROM_ADDRESS=noreply@caterpro.local
```

### Known Working LAN Setup
```
OS: Ubuntu 20.04 LTS on Raspberry Pi or Virtual Machine
PHP: 8.3+
MySQL: 8.0 (1 instance on 192.168.1.100)
Redis: Optional (for multi-instance deployments)
Reverse Proxy: nginx on 192.168.1.1
DNS: dnsmasq on router or separate Pi-hole
```

---

**Document Prepared For:** AI-assisted LAN deployment  
**Estimated Implementation Time:** 40-80 engineering hours  
**Risk Level:** MEDIUM-HIGH (multi-tenancy complexity)  
**Recommendation:** Use Docker Compose for reproducible LAN deployments
