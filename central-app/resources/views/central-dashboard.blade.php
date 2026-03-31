<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Central App Dashboard</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=space-grotesk:400,500,700|fraunces:600" rel="stylesheet" />

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
    @endif

    <style>
        :root {
            --ink: #15191f;
            --paper: #f6f2e8;
            --coral: #d14b38;
            --teal: #2a8573;
            --mustard: #d89a2a;
            --slate: #2f4254;
        }

        body {
            font-family: 'Space Grotesk', system-ui, sans-serif;
            background:
                radial-gradient(circle at 10% 0%, rgba(209, 75, 56, 0.12), transparent 44%),
                radial-gradient(circle at 90% 100%, rgba(42, 133, 115, 0.16), transparent 40%),
                linear-gradient(180deg, #f6f2e8 0%, #efe9db 100%);
            color: var(--ink);
        }

        .display {
            font-family: 'Fraunces', Georgia, serif;
            letter-spacing: 0.01em;
        }

        .grid-paper {
            background-image:
                linear-gradient(rgba(21, 25, 31, 0.06) 1px, transparent 1px),
                linear-gradient(90deg, rgba(21, 25, 31, 0.06) 1px, transparent 1px);
            background-size: 24px 24px;
        }

        .metric-card {
            animation: rise 420ms ease-out both;
        }

        .metric-card:nth-child(2) { animation-delay: 80ms; }
        .metric-card:nth-child(3) { animation-delay: 160ms; }
        .metric-card:nth-child(4) { animation-delay: 240ms; }

        @keyframes rise {
            from { opacity: 0; transform: translateY(14px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body class="min-h-screen">
    <div class="mx-auto max-w-7xl p-5 sm:p-10">
        @if (session('tenant_create_success'))
            <div class="mb-4 rounded-xl border border-[#2a8573]/40 bg-[#2a8573]/10 px-4 py-3 text-sm font-medium text-[#17463d]">
                {{ session('tenant_create_success') }}
            </div>
        @endif

        @if (session('tenant_create_error'))
            <div class="mb-4 rounded-xl border border-[#d14b38]/40 bg-[#d14b38]/10 px-4 py-3 text-sm font-medium text-[#6b261c]">
                {{ session('tenant_create_error') }}
            </div>
        @endif

        <header class="rounded-2xl border border-[#15191f]/20 bg-[#f6f2e8]/80 p-6 shadow-[0_10px_40px_rgba(21,25,31,0.08)] backdrop-blur-sm">
            <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="mb-2 inline-flex items-center gap-2 rounded-full border border-[#15191f]/20 bg-white/70 px-3 py-1 text-xs font-semibold uppercase tracking-[0.14em]">
                        <span class="h-2 w-2 rounded-full {{ $databaseOnline ? 'bg-[#2a8573]' : 'bg-[#d14b38]' }}"></span>
                        {{ $databaseOnline ? 'Database Online' : 'Database Offline - Safe Mode' }}
                    </p>
                    <h1 class="display text-3xl sm:text-5xl">Central Command</h1>
                    <p class="mt-2 max-w-2xl text-sm text-[#15191f]/70 sm:text-base">
                        Manage tenants, role templates, and rollout operations from a single control surface.
                    </p>
                </div>
                <div class="grid-paper rounded-xl border border-dashed border-[#15191f]/30 px-4 py-3 text-sm">
                    <p class="font-semibold">Operational Focus</p>
                    <p class="text-[#15191f]/70">Prioritize queued template applications and provisioning drift.</p>
                    <a
                        href="{{ route('central.users.index') }}"
                        class="mt-3 inline-flex rounded-md border border-[#15191f]/20 bg-white/70 px-2 py-1 text-[10px] font-semibold uppercase tracking-[0.12em] text-[#15191f]/80 transition hover:bg-white"
                    >
                        Manage Users
                    </a>
                    <form method="POST" action="{{ route('auth.central.logout') }}" class="mt-3">
                        @csrf
                        <button
                            type="submit"
                            class="rounded-md border border-[#15191f]/20 bg-white/70 px-2 py-1 text-[10px] font-semibold uppercase tracking-[0.12em] text-[#15191f]/80 transition hover:bg-white"
                        >
                            Logout
                        </button>
                    </form>
                </div>
            </div>
        </header>

        <section class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <article class="metric-card rounded-2xl border border-[#15191f]/20 bg-white/70 p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.12em] text-[#15191f]/65">Tenants</p>
                <p class="display mt-3 text-4xl">{{ $stats['tenants'] ?? '—' }}</p>
                <p class="mt-1 text-xs text-[#15191f]/60">Registered central tenants</p>
            </article>

            <article class="metric-card rounded-2xl border border-[#15191f]/20 bg-white/70 p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.12em] text-[#15191f]/65">Feature Flags</p>
                <p class="display mt-3 text-4xl">{{ $stats['features'] ?? '—' }}</p>
                <p class="mt-1 text-xs text-[#15191f]/60">Catalog-level toggles</p>
            </article>

            <article class="metric-card rounded-2xl border border-[#15191f]/20 bg-white/70 p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.12em] text-[#15191f]/65">Role Templates</p>
                <p class="display mt-3 text-4xl">{{ $stats['role_templates'] ?? '—' }}</p>
                <p class="mt-1 text-xs text-[#15191f]/60">Reusable RBAC blueprints</p>
            </article>

            <article class="metric-card rounded-2xl border border-[#15191f]/20 bg-white/70 p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.12em] text-[#15191f]/65">Active Applies</p>
                <p class="display mt-3 text-4xl">{{ $stats['applications_active'] ?? '—' }}</p>
                <p class="mt-1 text-xs text-[#15191f]/60">Queued or currently applying</p>
            </article>
        </section>

        <section class="mt-6 grid gap-6 xl:grid-cols-[1.1fr_1.4fr]">
            <article class="rounded-2xl border border-[#15191f]/20 bg-white/70 p-6 shadow-sm">
                <h2 class="display text-2xl">Tenant Status</h2>
                <p class="mt-1 text-sm text-[#15191f]/65">Provisioning health by status bucket.</p>

                <div class="mt-5 space-y-4">
                    @forelse ($tenantStatusBreakdown as $bucket)
                        @php
                            $color = match ($bucket['status']) {
                                'ready' => '#2a8573',
                                'failed' => '#d14b38',
                                'provisioning' => '#d89a2a',
                                default => '#2f4254',
                            };
                        @endphp
                        <div>
                            <div class="mb-1 flex items-center justify-between text-sm">
                                <span class="font-semibold capitalize">{{ $bucket['status'] }}</span>
                                <span>{{ $bucket['total'] }}</span>
                            </div>
                            <div class="h-2 rounded-full bg-[#15191f]/10">
                                <div class="h-2 rounded-full" style="width: {{ max(8, min(100, $bucket['total'] * 12)) }}%; background: {{ $color }}"></div>
                            </div>
                        </div>
                    @empty
                        <p class="rounded-xl border border-dashed border-[#15191f]/25 bg-[#f6f2e8] p-3 text-sm text-[#15191f]/70">
                            No tenant status data available yet.
                        </p>
                    @endforelse
                </div>
            </article>

            <article class="rounded-2xl border border-[#15191f]/20 bg-white/70 p-6 shadow-sm">
                <div class="flex items-end justify-between gap-4">
                    <div>
                        <h2 class="display text-2xl">Recent Template Applications</h2>
                        <p class="mt-1 text-sm text-[#15191f]/65">Latest tenant role-template actions from central orchestration.</p>
                    </div>
                    <a href="/api/admin/tenants" class="rounded-md border border-[#15191f]/25 bg-[#15191f] px-3 py-2 text-xs font-semibold uppercase tracking-[0.12em] text-[#f6f2e8] transition hover:bg-[#2f4254]">Open API</a>
                </div>

                <div class="mt-4 overflow-x-auto">
                    <table class="w-full min-w-[480px] text-sm">
                        <thead>
                            <tr class="border-b border-[#15191f]/15 text-left text-xs uppercase tracking-[0.1em] text-[#15191f]/60">
                                <th class="pb-2 pr-3">Tenant</th>
                                <th class="pb-2 pr-3">Template</th>
                                <th class="pb-2 pr-3">Strategy</th>
                                <th class="pb-2 pr-3">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($recentApplications as $application)
                                <tr class="border-b border-[#15191f]/10">
                                    <td class="py-2 pr-3 font-medium">{{ $application->tenant?->name ?? 'Unknown Tenant' }}</td>
                                    <td class="py-2 pr-3">{{ $application->roleTemplate?->name ?? 'Unknown Template' }}</td>
                                    <td class="py-2 pr-3 uppercase text-xs">{{ $application->strategy }}</td>
                                    <td class="py-2 pr-3">
                                        <span class="rounded-full border border-[#15191f]/20 bg-[#f6f2e8] px-2 py-0.5 text-xs font-semibold uppercase">
                                            {{ $application->status }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="py-4 text-center text-[#15191f]/60">No applications recorded yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </article>
        </section>

        <section class="mt-6 grid gap-6 xl:grid-cols-[1.2fr_1fr]">
            <article class="rounded-2xl border border-[#15191f]/20 bg-white/75 p-6 shadow-sm">
                <h2 class="display text-2xl">Create Tenant</h2>
                <p class="mt-1 text-sm text-[#15191f]/65">Creating a tenant here provisions both the tenant record and its dedicated database.</p>

                <form method="POST" action="{{ route('tenants.create') }}" class="mt-5 grid gap-4 sm:grid-cols-2">
                    @csrf

                    <label class="block">
                        <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.12em] text-[#15191f]/65">Tenant Name</span>
                        <input
                            name="name"
                            value="{{ old('name') }}"
                            required
                            class="w-full rounded-lg border border-[#15191f]/25 bg-[#fdfaf3] px-3 py-2 text-sm outline-none transition focus:border-[#2f4254]"
                            placeholder="Northwind Catering"
                        >
                    </label>

                    <label class="block">
                        <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.12em] text-[#15191f]/65">Tenant Domain</span>
                        <input
                            name="domain"
                            value="{{ old('domain') }}"
                            required
                            class="w-full rounded-lg border border-[#15191f]/25 bg-[#fdfaf3] px-3 py-2 text-sm outline-none transition focus:border-[#2f4254]"
                            placeholder="northwind.localhost:8080"
                        >
                    </label>

                    <label class="block">
                        <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.12em] text-[#15191f]/65">Database Name</span>
                        <input
                            name="database_name"
                            value="{{ old('database_name') }}"
                            required
                            pattern="[a-zA-Z0-9_]+"
                            class="w-full rounded-lg border border-[#15191f]/25 bg-[#fdfaf3] px-3 py-2 text-sm outline-none transition focus:border-[#2f4254]"
                            placeholder="tenant_northwind"
                        >
                    </label>

                    <label class="block">
                        <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.12em] text-[#15191f]/65">Plan Code</span>
                        <input
                            name="plan_code"
                            value="{{ old('plan_code', 'starter') }}"
                            class="w-full rounded-lg border border-[#15191f]/25 bg-[#fdfaf3] px-3 py-2 text-sm outline-none transition focus:border-[#2f4254]"
                            placeholder="starter"
                        >
                    </label>

                    <label class="block sm:col-span-2">
                        <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.12em] text-[#15191f]/65">Plan Entitlements</span>
                        <input
                            name="plan_entitlements"
                            value="{{ old('plan_entitlements', 'starter') }}"
                            class="w-full rounded-lg border border-[#15191f]/25 bg-[#fdfaf3] px-3 py-2 text-sm outline-none transition focus:border-[#2f4254]"
                            placeholder="starter,analytics"
                        >
                    </label>

                    @if ($errors->any())
                        <div class="sm:col-span-2 rounded-lg border border-[#d14b38]/40 bg-[#d14b38]/10 px-3 py-2 text-sm text-[#7a2e23]">
                            {{ $errors->first() }}
                        </div>
                    @endif

                    <div class="sm:col-span-2 flex items-center justify-between gap-3">
                        <p class="text-xs text-[#15191f]/60">Provisioning may take a few seconds depending on your MySQL runtime.</p>
                        <button
                            type="submit"
                            class="rounded-lg border border-[#15191f]/25 bg-[#15191f] px-4 py-2 text-xs font-semibold uppercase tracking-[0.12em] text-[#f6f2e8] transition hover:bg-[#2f4254]"
                        >
                            Create Tenant + Database
                        </button>
                    </div>
                </form>
            </article>

            <article class="rounded-2xl border border-[#15191f]/20 bg-white/75 p-6 shadow-sm">
                <h2 class="display text-2xl">Tenant App UI Starter</h2>
                <p class="mt-1 text-sm text-[#15191f]/65">Preview the first tenant-facing UI shell as we build the tenant app experience.</p>

                @php
                    $latestTenant = $recentTenants->first();
                    $tenantAppUrl = static function (string $domain): string {
                        $base = preg_match('/\Ahttps?:\/\//i', $domain) === 1 ? $domain : "http://{$domain}";

                        return rtrim($base, '/') . '/tenant-app';
                    };
                @endphp

                <a
                    href="{{ $latestTenant ? $tenantAppUrl((string) $latestTenant->domain) : route('tenant.app.preview') }}"
                    class="mt-4 inline-flex rounded-lg border border-[#15191f]/25 bg-[#f6f2e8] px-3 py-2 text-xs font-semibold uppercase tracking-[0.12em] text-[#15191f] transition hover:bg-[#fff]"
                >
                    Open Tenant App Starter
                </a>

                <div class="mt-5 space-y-2">
                    @forelse ($recentTenants as $tenant)
                        <div class="flex items-center justify-between rounded-lg border border-[#15191f]/15 bg-[#fdfaf3] px-3 py-2 text-sm">
                            <div>
                                <p class="font-semibold">{{ $tenant->name }}</p>
                                <p class="text-xs text-[#15191f]/60">{{ $tenant->domain }} · {{ $tenant->database_name }}</p>
                            </div>
                            <a
                                href="{{ $tenantAppUrl((string) $tenant->domain) }}"
                                class="rounded-md border border-[#15191f]/20 px-2 py-1 text-[10px] font-semibold uppercase tracking-[0.12em] text-[#15191f]/80 transition hover:bg-white"
                            >
                                Preview
                            </a>
                        </div>
                    @empty
                        <p class="rounded-lg border border-dashed border-[#15191f]/20 bg-[#fdfaf3] px-3 py-4 text-sm text-[#15191f]/65">
                            No tenants yet. Create your first tenant from the form.
                        </p>
                    @endforelse
                </div>
            </article>
        </section>
    </div>
</body>
</html>
