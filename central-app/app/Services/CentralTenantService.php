<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\User;
use App\Support\PlanFeatures;
use App\Support\TenantRoles;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Stancl\Tenancy\Database\Models\Domain;

class CentralTenantService
{
    public function dashboardStats(): array
    {
        $tenants = Tenant::query()->with('domains')->latest()->get();
        $planDefinitions = PlanFeatures::plans();
        $planCounts = $this->buildPlanCounts($tenants);
        $totalTenants = $tenants->count();
        $activeTenants = $tenants->filter(fn (Tenant $tenant) => $this->tenantIsActive($tenant))->count();
        $estimatedMonthlyRevenue = collect($planDefinitions)->reduce(
            fn (int $carry, array $planConfig, string $plan) => $carry + ((int) $planCounts->get($plan, 0) * (int) $planConfig['monthly_price']),
            0,
        );

        $registrationSeries = collect(range(5, 0))
            ->map(function (int $monthOffset) use ($tenants) {
                $month = now()->subMonths($monthOffset);
                $count = $tenants
                    ->filter(
                        fn (Tenant $tenant) => $tenant->created_at
                            && $tenant->created_at->year === $month->year
                            && $tenant->created_at->month === $month->month,
                    )
                    ->count();

                return [
                    'label' => $month->format('M'),
                    'value' => $count,
                ];
            })
            ->values()
            ->all();

        $planBreakdown = collect($planDefinitions)
            ->mapWithKeys(function (array $planConfig, string $plan) use ($planCounts) {
                return [
                    $plan => [
                        'key' => $plan,
                        'label' => $planConfig['label'],
                        'tenant_count' => (int) $planCounts->get($plan, 0),
                        'monthly_price' => (int) $planConfig['monthly_price'],
                    ],
                ];
            });

        $topPlan = $planBreakdown
            ->sortByDesc('tenant_count')
            ->first();

        return [
            'total_tenants' => $totalTenants,
            'active_tenants' => $activeTenants,
            'new_tenants_this_month' => $tenants
                ->filter(fn (Tenant $tenant) => $tenant->created_at && $tenant->created_at->isCurrentMonth())
                ->count(),
            'estimated_monthly_revenue' => $estimatedMonthlyRevenue,
            'avg_plan_label' => $topPlan['label'] ?? 'Free',
            'registration_series' => $registrationSeries,
            'plan_breakdown' => $planBreakdown->all(),
            'plan_distribution' => $planBreakdown
                ->values()
                ->map(fn (array $plan) => [
                    'key' => $plan['key'],
                    'label' => $plan['label'],
                    'count' => $plan['tenant_count'],
                ])
                ->all(),
            'latest_tenants' => $tenants
                ->take(5)
                ->values()
                ->map(fn (Tenant $tenant) => $this->tenantPayload($tenant))
                ->all(),
            'recent_tenant_activity' => $tenants
                ->take(5)
                ->values()
                ->map(fn (Tenant $tenant) => $this->tenantPayload($tenant))
                ->all(),
        ];
    }

    public function plansCatalog(): array
    {
        return collect(PlanFeatures::plans())
            ->map(function (array $planConfig, string $plan) {
                return [
                    'key' => $plan,
                    'label' => $planConfig['label'],
                    'monthly_price' => $planConfig['monthly_price'],
                    'monthly_active_event_limit' => $planConfig['monthly_active_event_limit'],
                    'features' => $planConfig['features'],
                ];
            })
            ->values()
            ->all();
    }

    public function plansPricingOverview(): array
    {
        $tenants = Tenant::query()->latest()->get();
        $planDefinitions = PlanFeatures::plans();
        $planCounts = $this->buildPlanCounts($tenants);

        $plans = collect($planDefinitions)
            ->map(function (array $planConfig, string $plan) use ($planCounts, $tenants) {
                $tenantCount = (int) $planCounts->get($plan, 0);
                $inactiveCount = $tenants
                    ->filter(fn (Tenant $tenant) => PlanFeatures::normalizePlan((string) ($tenant->getAttribute('plan') ?? 'free')) === $plan)
                    ->filter(fn (Tenant $tenant) => ! $this->tenantIsActive($tenant))
                    ->count();
                $churnRate = $tenantCount > 0 ? round(($inactiveCount / $tenantCount) * 100, 1) : 0.0;

                return [
                    'key' => $plan,
                    'label' => $planConfig['label'],
                    'monthly_price' => (int) $planConfig['monthly_price'],
                    'tenant_count' => $tenantCount,
                    'churn_rate' => $churnRate,
                    'user_limit' => $this->userLimitByPlan($plan),
                    'monthly_active_event_limit' => $planConfig['monthly_active_event_limit'],
                    'features' => $planConfig['features'],
                ];
            })
            ->values();

        $featureMatrix = collect($this->featureLabelMap())
            ->map(function (string $label, string $featureKey) use ($planDefinitions) {
                $row = [
                    'feature' => $label,
                ];

                foreach (array_keys($planDefinitions) as $planKey) {
                    $row[$planKey] = PlanFeatures::hasFeature($planKey, $featureKey);
                }

                return $row;
            })
            ->values()
            ->all();

        return [
            'plans' => $plans->all(),
            'feature_matrix' => $featureMatrix,
        ];
    }

    public function userDirectory(?string $search = null): array
    {
        $searchTerm = trim((string) $search);
        $query = User::query()->with('roles');

        if ($searchTerm !== '') {
            $query->where(function ($builder) use ($searchTerm) {
                $builder
                    ->where('name', 'like', '%'.$searchTerm.'%')
                    ->orWhere('email', 'like', '%'.$searchTerm.'%');
            });
        }

        $total = (clone $query)->count();
        $users = $query
            ->latest()
            ->limit(200)
            ->get()
            ->map(fn (User $user) => $this->userPayload($user))
            ->values()
            ->all();

        return [
            'total' => $total,
            'users' => $users,
        ];
    }

    public function updateCentralUser(User $user, array $attributes): array
    {
        $user->fill(Arr::only($attributes, ['name', 'email']));
        $user->save();

        $freshUser = $user->fresh(['roles']) ?? $user->load('roles');

        return $this->userPayload($freshUser);
    }

    public function revenueAnalytics(): array
    {
        $tenants = Tenant::query()->latest()->get();
        $planDefinitions = PlanFeatures::plans();

        $currentMrr = $tenants
            ->filter(fn (Tenant $tenant) => $this->tenantIsActive($tenant))
            ->reduce(function (int $carry, Tenant $tenant) use ($planDefinitions) {
                $plan = PlanFeatures::normalizePlan((string) ($tenant->getAttribute('plan') ?? 'free'));
                $planPrice = (int) Arr::get($planDefinitions, $plan.'.monthly_price', 0);

                return $carry + $planPrice;
            }, 0);

        $arr = $currentMrr * 12;
        $paidTenants = $tenants
            ->filter(fn (Tenant $tenant) => $this->tenantIsActive($tenant))
            ->filter(function (Tenant $tenant) use ($planDefinitions) {
                $plan = PlanFeatures::normalizePlan((string) ($tenant->getAttribute('plan') ?? 'free'));

                return (int) Arr::get($planDefinitions, $plan.'.monthly_price', 0) > 0;
            })
            ->count();
        $arpu = $paidTenants > 0 ? (int) round($currentMrr / $paidTenants) : 0;
        $inactiveTenants = $tenants->filter(fn (Tenant $tenant) => ! $this->tenantIsActive($tenant))->count();
        $avgChurnRate = $tenants->count() > 0 ? round(($inactiveTenants / $tenants->count()) * 100, 1) : 0.0;

        $mrrTrend = collect(range(11, 0))
            ->map(function (int $offset) use ($tenants, $planDefinitions) {
                $month = now()->subMonths($offset);
                $monthEnd = $month->copy()->endOfMonth();

                $mrr = $tenants
                    ->filter(fn (Tenant $tenant) => $tenant->created_at && $tenant->created_at->lte($monthEnd))
                    ->filter(fn (Tenant $tenant) => $this->tenantIsActive($tenant))
                    ->reduce(function (int $carry, Tenant $tenant) use ($planDefinitions) {
                        $plan = PlanFeatures::normalizePlan((string) ($tenant->getAttribute('plan') ?? 'free'));
                        $planPrice = (int) Arr::get($planDefinitions, $plan.'.monthly_price', 0);

                        return $carry + $planPrice;
                    }, 0);

                return [
                    'label' => $month->format('M'),
                    'value' => $mrr,
                ];
            })
            ->values()
            ->all();

        $revenueByPlan = collect($planDefinitions)
            ->map(function (array $planConfig, string $plan) use ($tenants) {
                $activeTenants = $tenants
                    ->filter(fn (Tenant $tenant) => PlanFeatures::normalizePlan((string) ($tenant->getAttribute('plan') ?? 'free')) === $plan)
                    ->filter(fn (Tenant $tenant) => $this->tenantIsActive($tenant))
                    ->count();

                return [
                    'key' => $plan,
                    'label' => $planConfig['label'],
                    'value' => $activeTenants * (int) $planConfig['monthly_price'],
                ];
            })
            ->values()
            ->all();

        $newVsChurned = collect(range(5, 0))
            ->map(function (int $offset) use ($tenants) {
                $month = now()->subMonths($offset);
                $newCount = $tenants
                    ->filter(
                        fn (Tenant $tenant) => $tenant->created_at
                            && $tenant->created_at->year === $month->year
                            && $tenant->created_at->month === $month->month,
                    )
                    ->count();

                $churnedCount = $tenants
                    ->filter(fn (Tenant $tenant) => ! $this->tenantIsActive($tenant))
                    ->filter(
                        fn (Tenant $tenant) => $tenant->updated_at
                            && $tenant->updated_at->year === $month->year
                            && $tenant->updated_at->month === $month->month,
                    )
                    ->count();

                return [
                    'label' => $month->format('M'),
                    'new' => $newCount,
                    'churned' => $churnedCount,
                ];
            })
            ->values()
            ->all();

        return [
            'metrics' => [
                'mrr' => $currentMrr,
                'arr' => $arr,
                'avg_churn_rate' => $avgChurnRate,
                'arpu' => $arpu,
            ],
            'mrr_trend' => $mrrTrend,
            'revenue_by_plan' => $revenueByPlan,
            'new_vs_churned' => $newVsChurned,
        ];
    }

    public function systemHealthSnapshot(): array
    {
        $tenantDatabases = Tenant::query()->count();
        $pendingJobs = DB::table('jobs')->count();
        $failedJobs24h = DB::table('failed_jobs')
            ->where('failed_at', '>=', now()->subDay())
            ->count();

        $serviceChecks = $this->configuredServiceChecks();

        $serviceHealth = $serviceChecks
            ->map(function (array $service) {
                $probeDetail = (string) ($service['probe_detail'] ?? ($service['status'] === 'Healthy' ? 'Live probe' : 'Probe failed'));

                return [
                    'name' => $service['name'],
                    'latency' => sprintf('%dms', $service['latency_ms']),
                    'uptime' => $probeDetail,
                    'detail' => $probeDetail,
                    'status' => $service['status'],
                ];
            })
            ->values()
            ->all();

        $averageServiceLatency = (int) round($serviceChecks->avg('latency_ms') ?: 0);
        $avgApiLatency = max(5, $averageServiceLatency);

        $cpuPercent = $this->systemCpuPercent();
        $memoryPercent = $this->systemMemoryPercent();

        $rootDiskTotal = @disk_total_space(base_path()) ?: 0;
        $rootDiskFree = @disk_free_space(base_path()) ?: 0;
        $diskLandlordPercent = $rootDiskTotal > 0
            ? (int) max(0, min(100, round((1 - ($rootDiskFree / $rootDiskTotal)) * 100)))
            : 0;

        $tenantDbUsageBytes = collect(glob(database_path('tenant*')) ?: [])
            ->map(function (string $path) {
                return is_file($path) ? (int) filesize($path) : 0;
            })
            ->sum();
        $diskTenantDbPercent = $rootDiskTotal > 0
            ? (int) max(0, min(100, round(($tenantDbUsageBytes / $rootDiskTotal) * 100)))
            : 0;

        $failedRows = DB::table('failed_jobs')
            ->select('failed_at')
            ->where('failed_at', '>=', now()->subDay())
            ->get()
            ->map(function (object $row) {
                return now()->parse((string) $row->failed_at);
            });

        $apiResponseSeries = collect(range(12, 0))
            ->map(function (int $offset) use ($avgApiLatency, $failedRows) {
                $slotStart = now()->subHours($offset * 2);
                $slotEnd = (clone $slotStart)->addHours(2);
                $failedCount = $failedRows
                    ->filter(fn (CarbonInterface $failedAt) => $failedAt->betweenIncluded($slotStart, $slotEnd))
                    ->count();

                return [
                    'label' => $slotStart->format('H\h'),
                    'value' => $avgApiLatency + ($failedCount * 6),
                ];
            })
            ->values()
            ->all();

        $jobEvents = collect([
            ...DB::table('jobs')->latest('id')->limit(3)->get()->map(fn (object $job) => [
                'time' => now()->createFromTimestamp((int) $job->created_at)->format('H:i'),
                'job' => (string) $job->queue,
                'status' => 'Pending',
                'sort_key' => (int) $job->created_at,
            ])->all(),
            ...DB::table('failed_jobs')->latest('id')->limit(3)->get()->map(fn (object $job) => [
                'time' => now()->parse((string) $job->failed_at)->format('H:i'),
                'job' => (string) $job->queue,
                'status' => 'Failed',
                'sort_key' => now()->parse((string) $job->failed_at)->timestamp,
            ])->all(),
        ])
            ->sortByDesc('sort_key')
            ->take(5)
            ->values()
            ->map(fn (array $event) => Arr::except($event, ['sort_key']))
            ->all();

        return [
            'metrics' => [
                'tenant_databases' => $tenantDatabases,
                'pending_jobs' => $pendingJobs,
                'failed_jobs_24h' => $failedJobs24h,
                'avg_api_latency' => $avgApiLatency,
            ],
            'service_health' => $serviceHealth,
            'resource_usage' => [
                ['label' => 'CPU', 'value' => $cpuPercent],
                ['label' => 'Memory', 'value' => $memoryPercent],
                ['label' => 'Disk landlord', 'value' => $diskLandlordPercent],
                ['label' => 'Disk tenant DBs', 'value' => $diskTenantDbPercent],
            ],
            'recent_job_events' => $jobEvents,
            'api_response_series' => $apiResponseSeries,
        ];
    }

    public function auditTimeline(?string $search = null, ?string $type = null, ?string $actor = null): array
    {
        $tenantEntries = Tenant::query()
            ->latest()
            ->limit(100)
            ->get()
            ->map(function (Tenant $tenant) {
                $isActive = $this->tenantIsActive($tenant);
                $createdAt = $tenant->created_at;
                $updatedAt = $tenant->updated_at;
                $wasJustCreated = $createdAt && $updatedAt && $createdAt->diffInMinutes($updatedAt) < 2;

                if ($wasJustCreated) {
                    $action = 'Tenant created';
                    $type = 'info';
                } elseif (! $isActive) {
                    $action = 'Tenant suspended';
                    $type = 'danger';
                } else {
                    $action = 'Tenant updated';
                    $type = 'success';
                }

                return [
                    'id' => 'tenant-'.$tenant->getTenantKey(),
                    'action' => $action,
                    'detail' => trim(((string) $tenant->getAttribute('company_name')).' · '.PlanFeatures::detailsForPlan(
                        PlanFeatures::normalizePlan((string) ($tenant->getAttribute('plan') ?? 'free')),
                    )['label']),
                    'user' => 'System',
                    'type' => $type,
                    'timestamp' => ($updatedAt ?? $createdAt)?->toDateTimeString(),
                ];
            });

        $userEntries = User::query()
            ->latest()
            ->limit(100)
            ->get()
            ->map(function (User $user) {
                $fullName = trim(implode(' ', array_filter([
                    (string) ($user->firstname ?? ''),
                    (string) ($user->lastname ?? ''),
                ])));

                return [
                    'id' => 'user-'.$user->id,
                    'action' => 'Admin account created',
                    'detail' => $fullName !== '' ? $fullName : (string) ($user->username ?? $user->email),
                    'user' => $fullName !== '' ? $fullName : 'System',
                    'type' => 'info',
                    'timestamp' => optional($user->created_at)->toDateTimeString(),
                ];
            });

        $failedJobEntries = DB::table('failed_jobs')
            ->latest('id')
            ->limit(100)
            ->get()
            ->map(function (object $failedJob) {
                return [
                    'id' => 'failed-job-'.$failedJob->id,
                    'action' => 'Queue job failed',
                    'detail' => (string) $failedJob->queue,
                    'user' => 'System',
                    'type' => 'warning',
                    'timestamp' => now()->parse((string) $failedJob->failed_at)->toDateTimeString(),
                ];
            });

        $entries = collect([
            ...$tenantEntries->all(),
            ...$userEntries->all(),
            ...$failedJobEntries->all(),
        ])->filter(fn (array $entry) => ! empty($entry['timestamp']))
            ->sortByDesc('timestamp')
            ->values();

        $searchTerm = trim((string) $search);
        if ($searchTerm !== '') {
            $entries = $entries->filter(function (array $entry) use ($searchTerm) {
                return str_contains(Str::lower($entry['action']), Str::lower($searchTerm))
                    || str_contains(Str::lower($entry['detail']), Str::lower($searchTerm));
            })->values();
        }

        $typeFilter = trim((string) $type);
        if (in_array($typeFilter, ['success', 'danger', 'info', 'warning'], true)) {
            $entries = $entries->filter(fn (array $entry) => $entry['type'] === $typeFilter)->values();
        }

        $actorFilter = trim((string) $actor);
        if ($actorFilter !== '') {
            $entries = $entries->filter(fn (array $entry) => $entry['user'] === $actorFilter)->values();
        }

        $total = $entries->count();
        $availableUsers = $entries
            ->pluck('user')
            ->filter(fn (?string $user) => is_string($user) && $user !== '')
            ->unique()
            ->values()
            ->all();

        return [
            'total' => $total,
            'entries' => $entries->take(10)->values()->all(),
            'available_users' => $availableUsers,
        ];
    }

    public function listTenants(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        $search = trim((string) Arr::get($filters, 'search', ''));
        $planFilter = trim((string) Arr::get($filters, 'plan', ''));
        $statusFilter = trim((string) Arr::get($filters, 'status', ''));

        $tenants = Tenant::query()
            ->with('domains')
            ->latest()
            ->get()
            ->filter(function (Tenant $tenant) use ($search, $planFilter, $statusFilter) {
                $plan = PlanFeatures::normalizePlan((string) ($tenant->getAttribute('plan') ?? 'free'));
                $status = $this->tenantStatusLabel($tenant);
                $companyName = (string) ($tenant->getAttribute('company_name') ?? '');
                $subdomain = (string) optional($tenant->domains->first())->domain;
                $dbName = (string) ($tenant->database()->getName() ?? '');

                if ($search !== '') {
                    $haystack = Str::lower($companyName.' '.$subdomain.' '.$dbName.' '.$tenant->getTenantKey());
                    if (! str_contains($haystack, Str::lower($search))) {
                        return false;
                    }
                }

                if ($planFilter !== '' && in_array($planFilter, PlanFeatures::planKeys(), true) && $plan !== $planFilter) {
                    return false;
                }

                if ($statusFilter !== '' && in_array($statusFilter, ['active', 'suspended'], true) && $status !== $statusFilter) {
                    return false;
                }

                return true;
            })
            ->values();

        $page = max(Paginator::resolveCurrentPage(), 1);
        $total = $tenants->count();
        $offset = ($page - 1) * $perPage;
        $pagedItems = $tenants
            ->slice($offset, $perPage)
            ->values()
            ->map(fn (Tenant $tenant) => $this->tenantPayload($tenant));

        return new Paginator(
            $pagedItems,
            $total,
            $perPage,
            $page,
            [
                'path' => Paginator::resolveCurrentPath(),
                'query' => request()->query(),
                'pageName' => 'page',
            ],
        );
    }

    public function tenantEditContext(Tenant $tenant): array
    {
        $freshTenant = $tenant->fresh(['domains']) ?? $tenant->load('domains');

        return [
            'tenant' => $this->tenantPayload($freshTenant),
            'available_plans' => collect($this->plansCatalog())
                ->map(fn (array $plan) => [
                    ...$plan,
                    'default_features' => PlanFeatures::forPlan((string) $plan['key']),
                ])
                ->values()
                ->all(),
            'feature_catalog' => collect($this->featureLabelMap())
                ->map(fn (string $label, string $key) => [
                    'key' => $key,
                    'label' => $label,
                ])
                ->values()
                ->all(),
            'available_roles' => TenantRoles::all(),
            'users' => $this->tenantUsers($freshTenant),
        ];
    }

    public function updateTenant(Tenant $tenant, array $attributes): Tenant
    {
        $tenant = $tenant->fresh(['domains']) ?? $tenant->load('domains');
        $requestedPlan = Arr::get($attributes, 'plan', (string) ($tenant->getAttribute('plan') ?? 'free'));
        $plan = PlanFeatures::normalizePlan((string) $requestedPlan);
        $existingFeatures = $tenant->getAttribute('enabled_features');
        $requestedFeatures = Arr::get($attributes, 'enabled_features', $existingFeatures);
        $enabledFeatures = is_array($requestedFeatures) ? $requestedFeatures : PlanFeatures::forPlan($plan);
        $enabledFeatures = $this->normalizeEnabledFeatures($enabledFeatures, $plan);
        $currentDomainRecord = $tenant->domains->first();
        $currentSubdomain = (string) ($currentDomainRecord?->domain ?? '');

        if (array_key_exists('subdomain', $attributes)) {
            $nextSubdomain = Str::lower((string) $attributes['subdomain']);

            if ($nextSubdomain !== $currentSubdomain) {
                $baseDomain = (string) Arr::first(config('tenancy.central_domains', ['127.0.0.1']));
                $fullDomain = $nextSubdomain.'.'.$baseDomain;
                $reservedDomains = (array) config('tenancy.central_domains', []);

                if (in_array($nextSubdomain, $reservedDomains, true) || in_array($fullDomain, $reservedDomains, true)) {
                    throw ValidationException::withMessages([
                        'subdomain' => ['This subdomain is reserved for the central application.'],
                    ]);
                }

                $exists = Domain::query()
                    ->when($currentDomainRecord, fn ($query) => $query->whereKeyNot($currentDomainRecord->getKey()))
                    ->whereIn('domain', [$nextSubdomain, $fullDomain])
                    ->exists();

                if ($exists) {
                    throw ValidationException::withMessages([
                        'subdomain' => ['This subdomain is already taken.'],
                    ]);
                }

                if ($currentDomainRecord) {
                    $currentDomainRecord->forceFill(['domain' => $nextSubdomain])->save();
                } else {
                    $tenant->domains()->create(['domain' => $nextSubdomain]);
                }
            }
        }

        if (array_key_exists('company_name', $attributes)) {
            $tenant->setAttribute('company_name', $attributes['company_name']);
        }

        $tenant->setAttribute('plan', $plan);
        $tenant->setAttribute('enabled_features', $enabledFeatures);
        $tenant->setAttribute(
            'client_access',
            array_key_exists('client_access', $attributes)
                ? (bool) $attributes['client_access']
                : in_array(PlanFeatures::CLIENT_PORTAL, $enabledFeatures, true),
        );

        if (array_key_exists('is_active', $attributes)) {
            $tenant->setAttribute('is_active', (bool) $attributes['is_active']);
        }

        $tenant->save();

        return $tenant->fresh(['domains']);
    }

    public function tenantUsers(Tenant $tenant): array
    {
        return $this->runInTenantContext($tenant, function () {
            return User::query()
                ->with('roles')
                ->orderBy('id')
                ->get()
                ->map(fn (User $user) => $this->tenantUserPayload($user))
                ->values()
                ->all();
        });
    }

    public function updateTenantUser(Tenant $tenant, int $userId, array $attributes): array
    {
        return $this->runInTenantContext($tenant, function () use ($userId, $attributes) {
            $member = User::query()->with('roles')->findOrFail($userId);
            $username = Arr::get($attributes, 'username');
            $email = Arr::get($attributes, 'email');

            if (is_string($username) && $username !== '' && $username !== $member->username) {
                $usernameExists = User::query()
                    ->where('id', '!=', $member->id)
                    ->where('username', $username)
                    ->exists();

                if ($usernameExists) {
                    throw ValidationException::withMessages([
                        'username' => ['The username has already been taken.'],
                    ]);
                }
            }

            if (is_string($email) && $email !== '' && $email !== $member->email) {
                $emailExists = User::query()
                    ->where('id', '!=', $member->id)
                    ->where('email', $email)
                    ->exists();

                if ($emailExists) {
                    throw ValidationException::withMessages([
                        'email' => ['The email has already been taken.'],
                    ]);
                }
            }

            $member->fill([
                'name' => array_key_exists('firstname', $attributes) || array_key_exists('lastname', $attributes)
                    ? trim((string) (($attributes['firstname'] ?? $member->firstname).' '.($attributes['lastname'] ?? $member->lastname)))
                    : $member->name,
                'username' => Arr::get($attributes, 'username', $member->username),
                'firstname' => Arr::get($attributes, 'firstname', $member->firstname),
                'lastname' => Arr::get($attributes, 'lastname', $member->lastname),
                'mi' => array_key_exists('mi', $attributes) ? $attributes['mi'] : $member->mi,
                'email' => array_key_exists('email', $attributes) ? $attributes['email'] : $member->email,
                'is_active' => array_key_exists('is_active', $attributes) ? (bool) $attributes['is_active'] : $member->is_active,
            ]);

            $password = Arr::get($attributes, 'password');

            if (is_string($password) && $password !== '') {
                $member->password = Hash::make($password);
            }

            $member->save();

            if (array_key_exists('role', $attributes)) {
                $member->syncRoles([TenantRoles::normalize((string) $attributes['role'])]);
            }

            if (! $member->is_active) {
                $member->tokens()->delete();
            }

            return $this->tenantUserPayload($member->fresh(['roles']) ?? $member);
        });
    }

    public function updatePlan(Tenant $tenant, string $plan): Tenant
    {
        $normalizedPlan = PlanFeatures::normalizePlan($plan);

        $tenant->setAttribute('plan', $normalizedPlan);
        $tenant->setAttribute('enabled_features', PlanFeatures::forPlan($normalizedPlan));
        $tenant->setAttribute('client_access', PlanFeatures::supportsClientPortal($normalizedPlan));
        $tenant->save();

        return $tenant->fresh(['domains']);
    }

    public function updateBranding(Tenant $tenant, array $attributes): Tenant
    {
        if (array_key_exists('company_name', $attributes)) {
            $tenant->setAttribute('company_name', $attributes['company_name']);
        }

        $branding = $tenant->getAttribute('branding');

        if (! is_array($branding)) {
            $branding = [];
        }

        foreach (['primary_color', 'logo_url', 'logo_path'] as $brandingKey) {
            if (array_key_exists($brandingKey, $attributes)) {
                $branding[$brandingKey] = $attributes[$brandingKey];
            }
        }

        $tenant->setAttribute('branding', $branding);
        $tenant->save();

        return $tenant->fresh(['domains']);
    }

    public function updateStatus(Tenant $tenant, bool $isActive): Tenant
    {
        $tenant->setAttribute('is_active', $isActive);
        $tenant->save();

        return $tenant->fresh(['domains']);
    }

    public function tenantPayload(Tenant $tenant): array
    {
        $plan = PlanFeatures::normalizePlan((string) ($tenant->getAttribute('plan') ?? 'free'));
        $enabledFeatures = $tenant->getAttribute('enabled_features');

        if (! is_array($enabledFeatures)) {
            $enabledFeatures = PlanFeatures::forPlan($plan);
        }

        $branding = $tenant->getAttribute('branding');

        if (! is_array($branding)) {
            $branding = [];
        }

        $clientAccess = $tenant->getAttribute('client_access');

        if (! is_bool($clientAccess)) {
            $clientAccess = PlanFeatures::supportsClientPortal($plan);
        }

        $subdomain = optional($tenant->domains->first())->domain;
        $baseDomain = (string) Arr::first(config('tenancy.central_domains', ['localhost']));
        $status = $this->tenantStatusLabel($tenant);

        return [
            'tenant_id' => $tenant->getTenantKey(),
            'company_name' => $tenant->getAttribute('company_name'),
            'subdomain' => $subdomain,
            'db_name' => $tenant->database()->getName(),
            'full_domain' => $subdomain ? sprintf('%s.%s', $subdomain, $baseDomain) : null,
            'plan' => $plan,
            'plan_details' => PlanFeatures::detailsForPlan($plan),
            'enabled_features' => $enabledFeatures,
            'client_access' => $clientAccess,
            'feature_flags' => $this->featureFlags($plan, $enabledFeatures),
            'status' => $status,
            'is_active' => $status === 'active',
            'branding' => [
                'primary_color' => Arr::get($branding, 'primary_color', '#0B8F66'),
                'logo_url' => Arr::get($branding, 'logo_url'),
                'logo_path' => Arr::get($branding, 'logo_path'),
            ],
            'created_at' => optional($tenant->created_at)->toIso8601String(),
            'updated_at' => optional($tenant->updated_at)->toIso8601String(),
        ];
    }

    private function userPayload(User $user): array
    {
        $roles = $user->getRoleNames()->values();
        $primaryRole = (string) ($roles->first() ?? 'No role');
        $hasIsActiveField = array_key_exists('is_active', $user->getAttributes());

        return [
            'id' => $user->id,
            'name' => (string) ($user->name ?? 'Unknown user'),
            'email' => (string) ($user->email ?? ''),
            'role' => Str::title(str_replace(['_', '-'], ' ', $primaryRole)),
            'status' => $hasIsActiveField && ! ($user->is_active ?? true) ? 'Inactive' : 'Active',
            'added_at' => optional($user->created_at)->format('Y-m-d'),
        ];
    }

    private function buildPlanCounts(Collection $tenants): Collection
    {
        return $tenants
            ->groupBy(fn (Tenant $tenant) => PlanFeatures::normalizePlan((string) ($tenant->getAttribute('plan') ?? 'free')))
            ->map(fn (Collection $groupedTenants) => $groupedTenants->count());
    }

    private function normalizeEnabledFeatures(array $enabledFeatures, string $plan): array
    {
        $knownFeatures = array_keys($this->featureLabelMap());

        $normalized = collect($enabledFeatures)
            ->map(fn ($feature) => (string) $feature)
            ->filter(fn (string $feature) => in_array($feature, $knownFeatures, true))
            ->unique()
            ->values()
            ->all();

        if ($normalized === []) {
            return PlanFeatures::forPlan($plan);
        }

        return $normalized;
    }

    private function featureFlags(string $plan, array $enabledFeatures): array
    {
        $knownFeatures = array_keys($this->featureLabelMap());

        return Collection::make($knownFeatures)
            ->mapWithKeys(fn (string $feature) => [$feature => in_array($feature, $enabledFeatures, true)])
            ->all();
    }

    private function featureLabelMap(): array
    {
        return [
            PlanFeatures::EVENT_MANAGEMENT => 'Event management',
            PlanFeatures::CLIENT_PORTAL => 'Client portal',
            PlanFeatures::STAFF_ASSIGNMENT => 'Staff assignment',
            PlanFeatures::ADVANCED_ANALYTICS => 'Revenue analytics',
            PlanFeatures::BRANDING_CONTROLS => 'Branding controls',
        ];
    }

    private function tenantUserPayload(User $user): array
    {
        $role = TenantRoles::resolveFromUser($user);

        return [
            'id' => $user->id,
            'username' => $user->username,
            'firstname' => $user->firstname,
            'lastname' => $user->lastname,
            'display_name' => trim((string) (($user->firstname ?? '').' '.($user->lastname ?? ''))) ?: ($user->name ?? $user->username),
            'email' => $user->email,
            'is_active' => (bool) $user->is_active,
            'role' => $role,
            'roles' => $user->getRoleNames()->values()->all(),
            'permissions' => $user->getAllPermissions()->pluck('name')->values()->all(),
            'modules' => TenantRoles::moduleCapabilities()[$role] ?? [],
            'created_at' => optional($user->created_at)?->toIso8601String(),
        ];
    }

    private function runInTenantContext(Tenant $tenant, callable $callback)
    {
        tenancy()->initialize($tenant);

        try {
            return $callback();
        } finally {
            tenancy()->end();
        }
    }

    private function tenantIsActive(Tenant $tenant): bool
    {
        $value = $tenant->getAttribute('is_active');

        if ($value === null) {
            return true;
        }

        if (is_bool($value)) {
            return $value;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL) !== false;
    }

    private function tenantStatusLabel(Tenant $tenant): string
    {
        return $this->tenantIsActive($tenant) ? 'active' : 'suspended';
    }

    private function userLimitByPlan(string $plan): ?int
    {
        return match ($plan) {
            'free' => 3,
            'starter' => 10,
            'business' => 25,
            'enterprise' => null,
            default => 3,
        };
    }

    private function configuredServiceChecks(): Collection
    {
        $queueConnection = (string) config('queue.default', 'sync');
        $queueConfig = (array) config("queue.connections.$queueConnection", []);
        $queueDriver = (string) ($queueConfig['driver'] ?? $queueConnection);

        $cacheStore = (string) config('cache.default', 'database');
        $cacheConfig = (array) config("cache.stores.$cacheStore", []);
        $cacheDriver = (string) ($cacheConfig['driver'] ?? $cacheStore);

        $diskName = (string) config('filesystems.default', 'local');
        $diskConfig = (array) config("filesystems.disks.$diskName", []);
        $diskDriver = (string) ($diskConfig['driver'] ?? $diskName);

        $mailerName = (string) config('mail.default', 'log');
        $mailerConfig = (array) config("mail.mailers.$mailerName", []);
        $mailTransport = (string) ($mailerConfig['transport'] ?? $mailerName);

        return collect([
            $this->probeService('Landlord DB', fn () => DB::select('SELECT 1'), 'Primary database'),
            $this->probeService($this->queueServiceName($queueDriver), function () use ($queueDriver, $queueConfig) {
                if ($queueDriver === 'database') {
                    DB::table((string) ($queueConfig['table'] ?? 'jobs'))->latest('id')->limit(1)->get();

                    return;
                }

                if ($queueDriver === 'redis') {
                    Redis::connection((string) ($queueConfig['connection'] ?? 'default'))->ping();

                    return;
                }

                if (in_array($queueDriver, ['sync', 'deferred', 'background', 'null'], true)) {
                    return;
                }

                if ($queueDriver === 'failover') {
                    $connections = (array) ($queueConfig['connections'] ?? []);

                    if ($connections === []) {
                        throw new RuntimeException('Failover queue is missing fallback connections.');
                    }

                    return;
                }

                if ($queueDriver === 'beanstalkd' && empty($queueConfig['host'])) {
                    throw new RuntimeException('Beanstalkd queue host is not configured.');
                }

                if ($queueDriver === 'sqs' && empty($queueConfig['queue'])) {
                    throw new RuntimeException('SQS queue name is not configured.');
                }
            }, sprintf('Driver: %s', $queueDriver)),
            $this->probeService($this->cacheServiceName($cacheDriver), function () use ($cacheDriver, $cacheConfig) {
                if ($cacheDriver === 'database') {
                    DB::table((string) ($cacheConfig['table'] ?? 'cache'))->limit(1)->get();

                    return;
                }

                if ($cacheDriver === 'file') {
                    $this->assertDirectoryUsable((string) ($cacheConfig['path'] ?? storage_path('framework/cache/data')));

                    return;
                }

                if ($cacheDriver === 'redis') {
                    Redis::connection((string) ($cacheConfig['connection'] ?? 'default'))->ping();

                    return;
                }

                if ($cacheDriver === 'memcached' && ! extension_loaded('memcached')) {
                    throw new RuntimeException('Memcached PHP extension is not available.');
                }

                if ($cacheDriver === 'failover') {
                    $stores = (array) ($cacheConfig['stores'] ?? []);

                    if ($stores === []) {
                        throw new RuntimeException('Failover cache is missing store definitions.');
                    }

                    return;
                }
            }, sprintf('Store: %s', $cacheDriver)),
            $this->probeService($this->storageServiceName($diskDriver), function () use ($diskDriver, $diskConfig, $diskName) {
                if ($diskDriver === 'local') {
                    $this->assertDirectoryUsable((string) ($diskConfig['root'] ?? storage_path('app/private')));

                    return;
                }

                Storage::disk($diskName)->exists('/');
            }, sprintf('Disk: %s', $diskName)),
            $this->probeService($this->mailServiceName($mailTransport), function () use ($mailTransport, $mailerConfig) {
                if ($mailTransport === 'smtp') {
                    $host = (string) ($mailerConfig['host'] ?? '');
                    $port = (int) ($mailerConfig['port'] ?? 0);

                    if ($host === '' || $port <= 0) {
                        throw new RuntimeException('SMTP host and port must be configured.');
                    }

                    $errorCode = 0;
                    $errorMessage = '';
                    $socket = @fsockopen($host, $port, $errorCode, $errorMessage, 1.5);

                    if (! is_resource($socket)) {
                        throw new RuntimeException('Unable to connect to SMTP endpoint.');
                    }

                    fclose($socket);

                    return;
                }

                if ($mailTransport === 'sendmail') {
                    $sendmailPath = (string) ($mailerConfig['path'] ?? '/usr/sbin/sendmail -bs -i');
                    $binaryPath = Str::before($sendmailPath, ' ');

                    if ($binaryPath === '' || ! file_exists($binaryPath)) {
                        throw new RuntimeException('Sendmail binary path is invalid.');
                    }

                    return;
                }

                if (in_array($mailTransport, ['failover', 'roundrobin'], true) && empty($mailerConfig['mailers'])) {
                    throw new RuntimeException('Mail transport has no configured mailers.');
                }
            }, sprintf('Transport: %s', $mailTransport)),
        ]);
    }

    private function queueServiceName(string $driver): string
    {
        return match ($driver) {
            'database' => 'Database Queue',
            'redis' => 'Redis Queue',
            'sync' => 'Sync Queue Driver',
            'deferred' => 'Deferred Queue Driver',
            'background' => 'Background Queue Driver',
            'beanstalkd' => 'Beanstalkd Queue',
            'sqs' => 'SQS Queue',
            'failover' => 'Failover Queue',
            'null' => 'Null Queue Driver',
            default => Str::title($driver).' Queue',
        };
    }

    private function cacheServiceName(string $driver): string
    {
        return match ($driver) {
            'database' => 'Database Cache',
            'file' => 'File Cache',
            'redis' => 'Redis Cache',
            'array' => 'Array Cache',
            'memcached' => 'Memcached Cache',
            'dynamodb' => 'DynamoDB Cache',
            'failover' => 'Failover Cache',
            'null' => 'Null Cache',
            default => Str::title($driver).' Cache',
        };
    }

    private function storageServiceName(string $driver): string
    {
        return match ($driver) {
            'local' => 'Local Storage',
            's3' => 'S3 Storage',
            default => Str::title($driver).' Storage',
        };
    }

    private function mailServiceName(string $transport): string
    {
        return match ($transport) {
            'smtp' => 'SMTP Mailer',
            'sendmail' => 'Sendmail Mailer',
            'log' => 'Log Mailer',
            'array' => 'Array Mailer',
            'failover' => 'Failover Mailer',
            'roundrobin' => 'Round-robin Mailer',
            default => Str::title($transport).' Mailer',
        };
    }

    private function assertDirectoryUsable(string $path): void
    {
        if (! is_dir($path)) {
            throw new RuntimeException(sprintf('Directory does not exist: %s', $path));
        }

        if (! is_readable($path) || ! is_writable($path)) {
            throw new RuntimeException(sprintf('Directory is not readable/writable: %s', $path));
        }
    }

    private function probeService(string $name, callable $probe, ?string $probeDetail = null): array
    {
        $startTime = microtime(true);
        $status = 'Healthy';

        try {
            $probe();
        } catch (RuntimeException) {
            $status = 'Degraded';
        } catch (\Throwable) {
            $status = 'Degraded';
        }

        $latencyMs = (int) round((microtime(true) - $startTime) * 1000);

        return [
            'name' => $name,
            'status' => $status,
            'latency_ms' => max(1, $latencyMs),
            'probe_detail' => $probeDetail,
        ];
    }

    private function parseBytes(string $value): int
    {
        $trimmed = trim($value);
        if ($trimmed === '' || $trimmed === '-1') {
            return 0;
        }

        $unit = strtolower(substr($trimmed, -1));
        $bytes = (float) $trimmed;

        return match ($unit) {
            'g' => (int) ($bytes * 1024 * 1024 * 1024),
            'm' => (int) ($bytes * 1024 * 1024),
            'k' => (int) ($bytes * 1024),
            default => (int) $bytes,
        };
    }

    private function systemCpuPercent(): ?int
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $output = $this->runCommand(
                'powershell -NoProfile -NonInteractive -Command "(Get-CimInstance Win32_Processor | Measure-Object -Property LoadPercentage -Average).Average"',
            );

            if ($output !== null && preg_match('/(\d+(?:\.\d+)?)/', $output, $matches) === 1) {
                return (int) max(0, min(100, round((float) $matches[1])));
            }

            return null;
        }

        if (function_exists('sys_getloadavg')) {
            $cpuLoad = (float) (sys_getloadavg()[0] ?? 0.0);
            $processorCount = max(1, (int) ($this->runCommand('getconf _NPROCESSORS_ONLN') ?: 1));

            return (int) max(0, min(100, round(($cpuLoad / $processorCount) * 100)));
        }

        return null;
    }

    private function systemMemoryPercent(): ?int
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $output = $this->runCommand(
                'powershell -NoProfile -NonInteractive -Command "$os = Get-CimInstance Win32_OperatingSystem; Write-Output ($os.TotalVisibleMemorySize.ToString() + \",\" + $os.FreePhysicalMemory.ToString())"',
            );

            if ($output !== null && preg_match('/(\d+)\s*,\s*(\d+)/', $output, $matches) === 1) {
                $total = (int) $matches[1];
                $free = (int) $matches[2];

                if ($total > 0) {
                    return (int) max(0, min(100, round((($total - $free) / $total) * 100)));
                }
            }

            return null;
        }

        if (is_readable('/proc/meminfo')) {
            $content = @file_get_contents('/proc/meminfo');

            if (is_string($content)
                && preg_match('/MemTotal:\s*(\d+)\s*kB/i', $content, $totalMatches) === 1
                && preg_match('/MemAvailable:\s*(\d+)\s*kB/i', $content, $availableMatches) === 1
            ) {
                $total = (int) $totalMatches[1];
                $available = (int) $availableMatches[1];

                if ($total > 0) {
                    return (int) max(0, min(100, round((($total - $available) / $total) * 100)));
                }
            }
        }

        return null;
    }

    private function runCommand(string $command): ?string
    {
        $nullDevice = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
        $output = @shell_exec($command.' 2>'.$nullDevice);

        if (! is_string($output)) {
            return null;
        }

        $trimmed = trim($output);

        return $trimmed === '' ? null : $trimmed;
    }
}

