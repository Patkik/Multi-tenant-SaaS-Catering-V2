<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tenant Dashboard</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=urbanist:400,500,700|fraunces:600" rel="stylesheet" />

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
    @endif

    <style>
        :root {
            --paper: #eef0f1;
            --panel: #f9f9f9;
            --ink: #141a1e;
            --muted: #7a8289;
            --brand: #0a7a4c;
            --brand-deep: #085f3b;
            --line: #d9dee2;
        }

        body {
            font-family: 'Urbanist', system-ui, sans-serif;
            background: radial-gradient(circle at 0% 0%, #ffffff, var(--paper));
            color: var(--ink);
        }

        .tenant-display {
            font-family: 'Fraunces', Georgia, serif;
        }

        .tile {
            border: 1px solid var(--line);
            background: var(--panel);
            border-radius: 16px;
        }

        .hero {
            background: linear-gradient(135deg, var(--brand), var(--brand-deep));
            color: #eef9f3;
        }

        .reveal {
            animation: reveal 380ms ease-out both;
        }

        .reveal:nth-child(2) { animation-delay: 70ms; }
        .reveal:nth-child(3) { animation-delay: 140ms; }
        .reveal:nth-child(4) { animation-delay: 210ms; }

        @keyframes reveal {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body class="min-h-screen p-3 sm:p-6">
    @php
        $tenantRole = $tenantRole ?? 'staff';
        $tenantRoleLabel = $tenantRoleLabel ?? ucfirst($tenantRole);
        $tenantUserEmail = $tenantUserEmail ?? 'unknown@tenant.local';

        // Use database-driven available modules if provided, fallback to hardcoded for backward compatibility
        $availableModules = $availableModules ?? [
            'dashboard' => ['name' => 'Dashboard', 'icon' => 'home', 'route' => 'dashboard'],
            // Fallback to hardcoded if database lookup failed
        ];
        
        // Legacy support: if availableModules is empty (no DB), use role-based defaults
        if (empty($availableModules) || !isset($availableModules['dashboard'])) {
            $roleModules = [
                'admin' => ['dashboard', 'orders', 'kitchen', 'calendar', 'analytics', 'team'],
                'manager' => ['dashboard', 'orders', 'kitchen', 'calendar', 'analytics'],
                'staff' => ['dashboard', 'orders', 'kitchen', 'calendar'],
                'cashier' => ['dashboard', 'orders'],
            ];
            $allowedModules = $roleModules[$tenantRole] ?? $roleModules['staff'];
        }

        $canManageTeam = $canManageTeam ?? in_array('team', $allowedModules ?? [], true);
        $canSeeAnalytics = $canSeeAnalytics ?? in_array('analytics', $allowedModules ?? [], true);
        $canOpenKitchenBoard = $canOpenKitchenBoard ?? in_array('kitchen', $allowedModules ?? [], true);
        $canUseCalendar = $canUseCalendar ?? in_array('calendar', $allowedModules ?? [], true);
    @endphp

    <div class="mx-auto max-w-[1500px] rounded-[28px] border border-[#d7dcdf] bg-white p-3 shadow-[0_16px_40px_rgba(20,26,30,0.1)] sm:p-4">
        <div class="grid gap-3 lg:grid-cols-[250px_1fr]">
            <aside class="tile p-5">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full border border-[#0a7a4c]/30 bg-[#0a7a4c]/10 text-lg">🍽</div>
                    <div>
                        <p class="font-bold">CaterFlow</p>
                        <p class="text-xs text-[var(--muted)]">{{ $tenantRoleLabel }} Dashboard</p>
                    </div>
                </div>

                <p class="mt-8 text-[10px] font-semibold uppercase tracking-[0.14em] text-[var(--muted)]">Menu</p>
                <nav class="mt-2 space-y-1 text-sm">
                    @foreach ($availableModules as $moduleKey => $module)
                        <a 
                            class="flex items-center gap-2 rounded-lg px-3 py-2 {{ $moduleKey === 'dashboard' ? 'bg-[#0a7a4c]/10 font-semibold text-[#096341]' : 'text-[var(--muted)] hover:bg-[#eef2f4]' }}" 
                            href="#"
                        >
                            {{ $module['name'] }}
                        </a>
                    @endforeach
                </nav>

                <div class="mt-8 rounded-2xl bg-gradient-to-br from-[#0f3f2e] to-[#0a7a4c] p-4 text-white">
                    <p class="text-xs text-white/80">{{ $tenant?->domain ?? 'Tenant Experience' }}</p>
                    <p class="mt-2 text-sm font-semibold">Kitchen Sync Window</p>
                    <p class="text-xs text-white/80">Active 06:00 - 22:00</p>
                </div>

                <a href="{{ 'http://' . env('CENTRAL_APP_HOST', 'localhost') . '/' }}" class="mt-6 inline-flex w-full items-center justify-center rounded-lg border border-[var(--line)] px-3 py-2 text-xs font-semibold uppercase tracking-[0.12em] text-[var(--ink)] transition hover:bg-[#f1f4f6]">
                    Back to Central
                </a>

                <form method="POST" action="{{ route('auth.tenant.logout') }}" class="mt-2">
                    @csrf
                    <button
                        type="submit"
                        class="inline-flex w-full items-center justify-center rounded-lg border border-[var(--line)] px-3 py-2 text-xs font-semibold uppercase tracking-[0.12em] text-[var(--ink)] transition hover:bg-[#f1f4f6]"
                    >
                        Logout
                    </button>
                </form>
            </aside>

            <main class="tile p-4 sm:p-6">
                <header class="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
                    <div class="flex w-full items-center gap-3 rounded-xl border border-[var(--line)] bg-white px-3 py-2 xl:max-w-md">
                        <span class="text-[var(--muted)]">🔍</span>
                        <input class="w-full bg-transparent text-sm outline-none" placeholder="Search orders, clients, events" />
                    </div>

                    <div class="flex items-center gap-3">
                        <button class="rounded-full border border-[var(--line)] px-3 py-2 text-xs font-semibold">Alerts</button>
                        <button class="rounded-full border border-[var(--line)] px-3 py-2 text-xs font-semibold">Messages</button>
                        <div class="flex items-center gap-2 rounded-full border border-[var(--line)] bg-white px-3 py-1.5">
                            <div class="h-8 w-8 rounded-full bg-[#0a7a4c]/20"></div>
                            <div>
                                <p class="text-xs font-semibold">{{ $tenant?->name ?? 'Tenant Experience' }}</p>
                                <p class="text-[10px] text-[var(--muted)]">{{ $tenantUserEmail }} · {{ $tenantRoleLabel }}</p>
                            </div>
                        </div>
                    </div>
                </header>

                <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h1 class="tenant-display text-4xl">Dashboard</h1>
                        <p class="mt-1 text-sm text-[var(--muted)]">Role-aware tenant operations for {{ $tenantRoleLabel }} access.</p>
                    </div>
                    <div class="flex gap-2">
                        <button class="rounded-full bg-[var(--brand)] px-5 py-2 text-sm font-semibold text-white">+ New Event</button>
                        @if ($tenantRole === 'admin' || $tenantRole === 'manager')
                            <button class="rounded-full border border-[var(--brand)] px-5 py-2 text-sm font-semibold text-[var(--brand)]">Import Menu</button>
                        @endif
                    </div>
                </div>

                <section class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <article class="hero reveal rounded-2xl p-4">
                        <p class="text-xs uppercase tracking-[0.12em] text-white/80">Orders Today</p>
                        <p class="mt-2 text-5xl font-bold">24</p>
                        <p class="mt-1 text-xs text-white/80">+8% from last week</p>
                    </article>
                    <article class="reveal tile p-4">
                        <p class="text-xs uppercase tracking-[0.12em] text-[var(--muted)]">Delivered</p>
                        <p class="mt-2 text-5xl font-bold">10</p>
                        <p class="mt-1 text-xs text-[var(--muted)]">On-time completion rate</p>
                    </article>
                    <article class="reveal tile p-4">
                        <p class="text-xs uppercase tracking-[0.12em] text-[var(--muted)]">In Kitchen</p>
                        <p class="mt-2 text-5xl font-bold">12</p>
                        <p class="mt-1 text-xs text-[var(--muted)]">Prep station workload</p>
                    </article>
                    <article class="reveal tile p-4">
                        <p class="text-xs uppercase tracking-[0.12em] text-[var(--muted)]">Pending Pickup</p>
                        <p class="mt-2 text-5xl font-bold">2</p>
                        <p class="mt-1 text-xs text-[var(--muted)]">Driver assignment waiting</p>
                    </article>
                </section>

                <section class="mt-4 grid gap-3 {{ $canSeeAnalytics ? 'xl:grid-cols-[1.5fr_1fr_1fr]' : 'xl:grid-cols-[1fr_1fr]' }}">
                    <article class="tile p-4">
                        <div class="flex items-center justify-between">
                            <h2 class="font-semibold">{{ $canSeeAnalytics ? 'Prep Analytics' : 'Operations Throughput' }}</h2>
                            <span class="text-xs text-[var(--muted)]">This Week</span>
                        </div>
                        <div class="mt-4 grid grid-cols-7 items-end gap-2">
                            @foreach ([58, 76, 64, 92, 48, 68, 61] as $bar)
                                <div class="flex flex-col items-center gap-2">
                                    <div class="w-full rounded-full bg-[#0a7a4c]/15" style="height: 88px; position: relative; overflow: hidden;">
                                        <span style="position:absolute;bottom:0;left:0;right:0;height: {{ $bar }}%; background: linear-gradient(180deg, #0a7a4c, #085f3b);"></span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </article>

                    <article class="tile p-4">
                        <h2 class="font-semibold">{{ $tenantRole === 'cashier' ? 'Payment Tasks' : 'Reminders' }}</h2>
                        @if ($tenantRole === 'cashier')
                            <p class="mt-4 text-3xl font-bold leading-9">Reconcile Daily Payments</p>
                            <p class="mt-1 text-xs text-[var(--muted)]">3 pending receipts</p>
                            <button class="mt-5 w-full rounded-full bg-[var(--brand)] px-4 py-2 text-sm font-semibold text-white">Open Payments</button>
                        @else
                            <p class="mt-4 text-3xl font-bold leading-9">Meet with Arc Foods</p>
                            <p class="mt-1 text-xs text-[var(--muted)]">14:30 - 15:15</p>
                            <button class="mt-5 w-full rounded-full bg-[var(--brand)] px-4 py-2 text-sm font-semibold text-white">Start Meeting</button>
                        @endif
                    </article>

                    <article class="tile p-4 {{ $canManageTeam ? '' : 'xl:col-span-1' }}">
                        <div class="flex items-center justify-between">
                            <h2 class="font-semibold">Today Queue</h2>
                            <span class="rounded-full border border-[var(--line)] px-2 py-0.5 text-[10px] font-semibold">+ New</span>
                        </div>
                        <ul class="mt-4 space-y-3 text-sm">
                            <li class="flex items-start gap-2"><span>•</span><span>Finalize API endpoints for ordering.</span></li>
                            <li class="flex items-start gap-2"><span>•</span><span>Review onboarding setup for new kitchen staff.</span></li>
                            <li class="flex items-start gap-2"><span>•</span><span>Ship dashboard performance updates.</span></li>
                            <li class="flex items-start gap-2"><span>•</span><span>Cross-browser QA pass for dispatch board.</span></li>
                        </ul>
                    </article>
                </section>
            </main>
        </div>
    </div>
</body>
</html>
