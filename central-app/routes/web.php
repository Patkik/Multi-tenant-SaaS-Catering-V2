<?php

use App\Exceptions\TenantProvisioningException;
use App\Models\Feature;
use App\Models\RoleTemplate;
use App\Models\RoleTemplateApplication;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantProvisioningService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

$centralHost = strtolower((string) env('CENTRAL_APP_HOST', 'localhost'));

$isCentralRequest = static function (Request $request) use ($centralHost): bool {
    $host = strtolower($request->getHost());
    // Treat 127.0.0.1 and localhost as equivalent for local development
    if ($centralHost === 'localhost' && ($host === '127.0.0.1' || $host === 'localhost')) {
        return true;
    }
    return $host === $centralHost;
};

$resolveTenantFromRequest = static function (Request $request): ?Tenant {
    return Tenant::query()->where('domain', $request->getHttpHost())->first();
};

$tenantAppUrl = static function (Tenant $tenant): string {
    $domain = (string) $tenant->domain;
    $base = preg_match('/\Ahttps?:\/\//i', $domain) === 1 ? $domain : sprintf('http://%s', $domain);

    return rtrim($base, '/').'/tenant-app';
};

$tenantLoginUrl = static function (Tenant $tenant): string {
    $domain = (string) $tenant->domain;
    $base = preg_match('/\Ahttps?:\/\//i', $domain) === 1 ? $domain : sprintf('http://%s', $domain);

    return rtrim($base, '/').'/auth/tenant/login';
};

$centralHomeUrl = static function () use ($centralHost): string {
    return sprintf('http://%s/', $centralHost);
};

Route::get('/auth/central/login', function (Request $request) use ($isCentralRequest) {
    if (! $isCentralRequest($request)) {
        return abort(404);
    }

    return view('auth-login', [
        'title' => 'Central App Login',
        'subtitle' => 'Authenticate as central administrator to access command operations.',
        'action' => route('auth.central.login.submit'),
        'emailLabel' => 'Central Email',
        'passwordLabel' => 'Central Password',
        'submitLabel' => 'Sign In to Central',
    ]);
})->name('auth.central.login');

Route::post('/auth/central/login', function (Request $request) use ($isCentralRequest) {
    if (! $isCentralRequest($request)) {
        return abort(404);
    }

    $credentials = $request->validate([
        'email' => ['required', 'email'],
        'password' => ['required', 'string'],
    ]);

    $expectedEmail = (string) env('CENTRAL_AUTH_EMAIL', 'admin@central.local');
    $expectedPassword = (string) env('CENTRAL_AUTH_PASSWORD', 'central123!');

    if (
        strcasecmp((string) $credentials['email'], $expectedEmail) !== 0
        || ! hash_equals($expectedPassword, (string) $credentials['password'])
    ) {
        return back()->withInput(['email' => $credentials['email']])->withErrors([
            'email' => 'Invalid central credentials.',
        ]);
    }

    $request->session()->regenerate();
    $request->session()->put('central_authenticated', true);

    return redirect()->intended('/');
})->name('auth.central.login.submit');

Route::post('/auth/central/logout', function (Request $request) use ($isCentralRequest, $centralHomeUrl) {
    if (! $isCentralRequest($request)) {
        return abort(404);
    }

    $request->session()->forget('central_authenticated');
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect()->to($centralHomeUrl().'auth/central/login');
})->name('auth.central.logout');

Route::get('/auth/tenant/login', function (Request $request) use ($isCentralRequest, $resolveTenantFromRequest) {
    if ($isCentralRequest($request)) {
        return abort(404);
    }

    $tenant = $resolveTenantFromRequest($request);

    if ($tenant === null) {
        return abort(404);
    }

    return view('auth-login', [
        'title' => 'Tenant App Login',
        'subtitle' => sprintf('Sign in to continue to %s operations.', $tenant->name),
        'action' => route('auth.tenant.login.submit'),
        'emailLabel' => 'Staff Email',
        'passwordLabel' => 'Tenant Password',
        'roleLabel' => 'Tenant Role',
        'roleOptions' => [
            'admin' => 'Admin',
            'manager' => 'Manager',
            'staff' => 'Staff',
            'cashier' => 'Cashier',
        ],
        'submitLabel' => 'Sign In to Tenant',
    ]);
})->name('auth.tenant.login');

Route::post('/auth/tenant/login', function (Request $request) use ($isCentralRequest, $resolveTenantFromRequest, $tenantAppUrl) {
    if ($isCentralRequest($request)) {
        return abort(404);
    }

    $tenant = $resolveTenantFromRequest($request);

    if ($tenant === null) {
        return abort(404);
    }

    $credentials = $request->validate([
        'email' => ['required', 'email'],
        'password' => ['required', 'string'],
        'role' => ['required', 'string', 'in:admin,manager,staff,cashier'],
    ]);

    $expectedTenantPassword = (string) env('TENANT_AUTH_PASSWORD', 'tenant123!');

    if (! hash_equals($expectedTenantPassword, (string) $credentials['password'])) {
        return back()->withInput(['email' => $credentials['email']])->withErrors([
            'email' => 'Invalid tenant credentials.',
        ]);
    }

    $request->session()->regenerate();
    $request->session()->put('tenant_authenticated_domain', (string) $tenant->domain);
    $request->session()->put('tenant_role', (string) $credentials['role']);
    $request->session()->put('tenant_user_email', (string) $credentials['email']);

    return redirect()->to($tenantAppUrl($tenant));
})->name('auth.tenant.login.submit');

Route::post('/auth/tenant/logout', function (Request $request) use ($isCentralRequest, $resolveTenantFromRequest, $tenantLoginUrl) {
    if ($isCentralRequest($request)) {
        return abort(404);
    }

    $tenant = $resolveTenantFromRequest($request);

    if ($tenant === null) {
        return abort(404);
    }

    $request->session()->forget('tenant_authenticated_domain');
    $request->session()->forget('tenant_role');
    $request->session()->forget('tenant_user_email');
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect()->to($tenantLoginUrl($tenant));
})->name('auth.tenant.logout');

Route::get('/', function (Request $request) use ($isCentralRequest) {
    if (! $isCentralRequest($request)) {
        return redirect()->route('auth.tenant.login');
    }

    if (! $request->session()->get('central_authenticated', false)) {
        return redirect()->route('auth.central.login');
    }

    $safe = static function (callable $callback, mixed $fallback = null): mixed {
        try {
            return $callback();
        } catch (\Throwable) {
            return $fallback;
        }
    };

    $databaseOnline = $safe(static fn (): bool => DB::connection()->getPdo() !== null, false);

    if ($databaseOnline) {
        $stats = [
            'tenants' => $safe(static fn (): int => Tenant::count()),
            'features' => $safe(static fn (): int => Feature::count()),
            'role_templates' => $safe(static fn (): int => RoleTemplate::count()),
            'applications_active' => $safe(
                static fn (): int => RoleTemplateApplication::query()
                    ->whereIn('status', [
                        RoleTemplateApplication::STATUS_QUEUED,
                        RoleTemplateApplication::STATUS_APPLYING,
                    ])->count()
            ),
        ];
    } else {
        $stats = [
            'tenants' => null,
            'features' => null,
            'role_templates' => null,
            'applications_active' => null,
        ];
    }

    /** @var Collection<int, array{status:string,total:int}> $tenantStatusBreakdown */
    $tenantStatusBreakdown = $databaseOnline
        ? $safe(
            static fn (): Collection => Tenant::query()
                ->selectRaw('COALESCE(provisioning_status, ?) as status, COUNT(*) as total', ['unknown'])
                ->groupBy('status')
                ->orderByDesc('total')
                ->get()
                ->map(static fn (Tenant $tenant): array => [
                    'status' => (string) ($tenant->getAttribute('status') ?? 'unknown'),
                    'total' => (int) ($tenant->getAttribute('total') ?? 0),
                ]),
            collect()
        )
        : collect();

    $recentApplications = $databaseOnline
        ? $safe(
            static fn (): Collection => RoleTemplateApplication::query()
                ->with([
                    'tenant:id,name,domain',
                    'roleTemplate:id,name',
                ])
                ->latest('id')
                ->limit(6)
                ->get(),
            collect()
        )
        : collect();

    $recentTenants = $databaseOnline
        ? $safe(
            static fn (): Collection => Tenant::query()
                ->latest('id')
                ->limit(8)
                ->get(['id', 'name', 'domain', 'database_name', 'provisioning_status', 'created_at']),
            collect()
        )
        : collect();

    return view('central-dashboard', [
        'databaseOnline' => (bool) $databaseOnline,
        'stats' => $stats,
        'tenantStatusBreakdown' => $tenantStatusBreakdown,
        'recentApplications' => $recentApplications,
        'recentTenants' => $recentTenants,
    ]);
});

Route::post('/tenants/create', function (Request $request, TenantProvisioningService $tenantProvisioningService) use ($isCentralRequest): RedirectResponse {
    if (! $isCentralRequest($request)) {
        return redirect()->route('auth.tenant.login');
    }

    if (! $request->session()->get('central_authenticated', false)) {
        return redirect()->route('auth.central.login');
    }

    $defaultConnection = (string) config('database.default');
    $defaultDriver = (string) config("database.connections.{$defaultConnection}.driver");

    if ($defaultDriver === 'mysql') {
        try {
            DB::connection($defaultConnection)->getPdo();
        } catch (\Throwable $exception) {
            $message = $exception->getMessage();

            if (! str_contains($message, 'Unknown database')) {
                return back()
                    ->withInput()
                    ->with('tenant_create_error', 'Database connection failed before provisioning. Check MySQL service availability.');
            }

            $databaseName = (string) config("database.connections.{$defaultConnection}.database");

            if ($databaseName === '') {
                return back()
                    ->withInput()
                    ->with('tenant_create_error', 'Central database name is not configured. Set DB_DATABASE in your environment.');
            }

            try {
                $provisioningConnection = (string) config('tenancy.provisioning_connection', $defaultConnection);
                $provisioning = DB::connection($provisioningConnection);

                $exists = $provisioning->selectOne(
                    'SELECT 1 FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ? LIMIT 1',
                    [$databaseName]
                );

                if ($exists === null) {
                    $identifier = sprintf('`%s`', str_replace('`', '``', $databaseName));
                    $provisioning->statement(sprintf('CREATE DATABASE %s', $identifier));
                }

                DB::purge($defaultConnection);
                DB::reconnect($defaultConnection);
            } catch (\Throwable) {
                return back()
                    ->withInput()
                    ->with('tenant_create_error', 'Failed to bootstrap central database before tenant creation. Verify DB_PROVISIONING_* privileges and rerun.');
            }
        }

        try {
            $schema = Schema::connection($defaultConnection);

            if (! $schema->hasTable('tenants')) {
                $schema->create('tenants', function (Blueprint $table): void {
                    $table->uuid('id')->primary();
                    $table->string('name');
                    $table->string('domain')->unique();
                    $table->string('database_name')->unique();
                    $table->string('plan_code')->nullable();
                    $table->json('plan_entitlements')->nullable();
                    $table->string('provisioning_status')->default('provisioning');
                    $table->text('provisioning_error')->nullable();
                    $table->timestamp('provisioned_at')->nullable();
                    $table->timestamps();
                });
            } else {
                $schema->table('tenants', function (Blueprint $table) use ($schema): void {
                    if (! $schema->hasColumn('tenants', 'provisioning_status')) {
                        $table->string('provisioning_status')->default('provisioning');
                    }

                    if (! $schema->hasColumn('tenants', 'provisioning_error')) {
                        $table->text('provisioning_error')->nullable();
                    }

                    if (! $schema->hasColumn('tenants', 'provisioned_at')) {
                        $table->timestamp('provisioned_at')->nullable();
                    }
                });
            }
        } catch (\Throwable) {
            return back()
                ->withInput()
                ->with('tenant_create_error', 'Failed to prepare central schema for tenant validation. Run central migrations and retry.');
        }
    }

    $validated = $request->validate([
        'name' => ['required', 'string', 'max:255'],
        'domain' => ['required', 'string', 'max:255', 'unique:tenants,domain'],
        'database_name' => ['required', 'string', 'max:64', 'regex:/\A[a-zA-Z0-9_]+\z/', 'unique:tenants,database_name'],
        'plan_code' => ['nullable', 'string', 'max:50'],
        'plan_entitlements' => ['nullable', 'string', 'max:500'],
    ]);

    $entitlements = collect(explode(',', (string) ($validated['plan_entitlements'] ?? '')))
        ->map(static fn (string $value): string => trim($value))
        ->filter(static fn (string $value): bool => $value !== '')
        ->values()
        ->all();

    try {
        $tenant = $tenantProvisioningService->createTenant([
            'name' => $validated['name'],
            'domain' => $validated['domain'],
            'database_name' => $validated['database_name'],
            'plan_code' => $validated['plan_code'] ?? null,
            'plan_entitlements' => $entitlements,
        ]);
    } catch (TenantProvisioningException) {
        return back()
            ->withInput()
            ->with('tenant_create_error', 'Tenant was saved but database provisioning failed. Check provisioning credentials and MySQL availability.');
    }

    return redirect('/')
        ->with('tenant_create_success', sprintf('Tenant "%s" created and database "%s" provisioned.', $tenant->name, $tenant->database_name));
})->name('tenants.create');

Route::get('/users', function (Request $request) use ($isCentralRequest) {
    if (! $isCentralRequest($request)) {
        return redirect()->route('auth.tenant.login');
    }

    if (! $request->session()->get('central_authenticated', false)) {
        return redirect()->route('auth.central.login');
    }

    return view('central-user-management', [
        'users' => User::query()->orderBy('name')->get(),
    ]);
})->name('central.users.index');

Route::post('/users', function (Request $request) use ($isCentralRequest): RedirectResponse {
    if (! $isCentralRequest($request)) {
        return redirect()->route('auth.tenant.login');
    }

    if (! $request->session()->get('central_authenticated', false)) {
        return redirect()->route('auth.central.login');
    }

    $payload = $request->validate([
        'name' => ['required', 'string', 'max:255'],
        'email' => ['required', 'email', 'max:255', 'unique:users,email'],
        'password' => ['required', 'string', 'min:8'],
        'role' => ['required', 'string', 'in:super_admin,operations_admin,auditor'],
    ]);

    User::query()->create([
        'name' => $payload['name'],
        'email' => $payload['email'],
        'password' => Hash::make($payload['password']),
        'role' => $payload['role'],
        'is_active' => true,
    ]);

    return redirect()->route('central.users.index')->with('user_success', 'Central user created.');
})->name('central.users.store');

Route::patch('/users/{user}', function (Request $request, User $user) use ($isCentralRequest): RedirectResponse {
    if (! $isCentralRequest($request)) {
        return redirect()->route('auth.tenant.login');
    }

    if (! $request->session()->get('central_authenticated', false)) {
        return redirect()->route('auth.central.login');
    }

    $payload = $request->validate([
        'role' => ['required', 'string', 'in:super_admin,operations_admin,auditor'],
        'is_active' => ['required', 'boolean'],
    ]);

    $user->fill([
        'role' => $payload['role'],
        'is_active' => (bool) $payload['is_active'],
    ])->save();

    return redirect()->route('central.users.index')->with('user_success', 'Central user updated.');
})->name('central.users.update');

Route::get('/tenant-app/{tenant?}', function (Request $request, ?Tenant $tenant = null) use ($isCentralRequest, $resolveTenantFromRequest, $tenantAppUrl, $tenantLoginUrl, $centralHomeUrl) {
    if ($isCentralRequest($request) && $tenant === null) {
        return redirect()->to($centralHomeUrl());
    }

    $tenant ??= $resolveTenantFromRequest($request);
    $tenant ??= Tenant::query()->latest('id')->first();

    if ($tenant === null) {
        return abort(404);
    }

    $tenantDomain = strtolower((string) $tenant->domain);
    $requestDomain = strtolower($request->getHttpHost());

    if ($tenantDomain !== $requestDomain) {
        return redirect()->away($tenantLoginUrl($tenant));
    }

    if ($request->session()->get('tenant_authenticated_domain') !== (string) $tenant->domain) {
        return redirect()->away($tenantLoginUrl($tenant));
    }

    $tenantRole = (string) $request->session()->get('tenant_role', 'staff');
    $tenantRoleLabel = match ($tenantRole) {
        'admin' => 'Admin',
        'manager' => 'Manager',
        'cashier' => 'Cashier',
        default => 'Staff',
    };

    $tenant ??= Tenant::query()->latest('id')->first();

    if ($tenant !== null) {
        $target = $tenantAppUrl($tenant);
        $current = rtrim($request->getSchemeAndHttpHost().$request->getPathInfo(), '/');

        if ($current !== rtrim($target, '/')) {
            return redirect()->away($target);
        }
    }

    // Load TenantRole from database and use RBAC service
    $tenantRoleModel = null;
    try {
        $tenantRoleModel = \App\Models\TenantRole::where('name', $tenantRole)->first();
    } catch (\Exception $e) {
        // If RBAC tables don't exist yet, allow access with fallback
    }

    $rbacService = app(\App\Services\RBACService::class);
    $availableModules = $rbacService->getAvailableModules($tenantRoleModel);
    $canManageTeam = $rbacService->hasPermission($tenantRoleModel, 'team.manage');
    $canSeeAnalytics = $rbacService->hasFeature($tenantRoleModel, 'analytics');
    $canOpenKitchenBoard = $rbacService->hasFeature($tenantRoleModel, 'kitchen_board');

    return view('tenant-app-home', [
        'tenant' => $tenant,
        'tenantRole' => $tenantRole,
        'tenantRoleLabel' => $tenantRoleLabel,
        'tenantUserEmail' => (string) $request->session()->get('tenant_user_email', 'unknown@tenant.local'),
        'availableModules' => $availableModules,
        'canManageTeam' => $canManageTeam,
        'canSeeAnalytics' => $canSeeAnalytics,
        'canOpenKitchenBoard' => $canOpenKitchenBoard,
    ]);
})->name('tenant.app.preview');
