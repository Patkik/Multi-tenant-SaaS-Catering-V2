<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') - {{ $tenant?->name ?? 'CaterFlow' }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=urbanist:400,500,600,700|fraunces:600" rel="stylesheet" />

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
            --brand-light: #e8f5f0;
            --line: #d9dee2;
            --danger: #dc2626;
            --warning: #d97706;
            --success: #059669;
        }

        body {
            font-family: 'Urbanist', system-ui, sans-serif;
            background: radial-gradient(circle at 0% 0%, #ffffff, var(--paper));
            color: var(--ink);
        }

        .display-font {
            font-family: 'Fraunces', Georgia, serif;
        }

        .tile {
            border: 1px solid var(--line);
            background: var(--panel);
            border-radius: 16px;
        }

        .nav-item {
            @apply flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm transition-all duration-200;
        }

        .nav-item:hover {
            background: rgba(10, 122, 76, 0.08);
        }

        .nav-item.active {
            background: rgba(10, 122, 76, 0.12);
            color: var(--brand);
            font-weight: 600;
        }

        .nav-item .nav-icon {
            @apply w-5 h-5 flex-shrink-0;
        }

        .badge {
            @apply inline-flex items-center justify-center px-2 py-0.5 text-[10px] font-semibold rounded-full;
        }

        .badge-brand { background: var(--brand-light); color: var(--brand); }
        .badge-warning { background: #fef3c7; color: var(--warning); }
        .badge-danger { background: #fee2e2; color: var(--danger); }
        .badge-muted { background: #f3f4f6; color: var(--muted); }

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

        /* Mobile sidebar */
        .sidebar-overlay {
            @apply fixed inset-0 bg-black/50 z-40 lg:hidden;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s, visibility 0.3s;
        }

        .sidebar-overlay.open {
            opacity: 1;
            visibility: visible;
        }

        .sidebar {
            @apply fixed lg:relative inset-y-0 left-0 z-50 w-64 transform -translate-x-full lg:translate-x-0 transition-transform duration-300;
        }

        .sidebar.open {
            transform: translateX(0);
        }
    </style>
    @stack('styles')
</head>
<body class="min-h-screen">
    @php
        $currentRoute = request()->route()?->getName() ?? 'tenant.dashboard';
        $tenantRole = $tenantRole ?? session('tenant_role', 'staff');
        $tenantRoleLabel = ucfirst($tenantRole);
        $tenantUserEmail = $tenantUserEmail ?? session('tenant_user_email', 'user@tenant.local');
        $tenantUserName = $tenantUserName ?? session('tenant_user_name', 'User');

        // Role-based navigation items
        $navItems = [
            'dashboard' => [
                'name' => 'Dashboard',
                'route' => 'tenant.dashboard',
                'icon' => 'home',
                'roles' => ['admin', 'manager', 'staff', 'cashier'],
            ],
            'orders' => [
                'name' => 'Orders',
                'route' => 'tenant.orders',
                'icon' => 'clipboard-list',
                'roles' => ['admin', 'manager', 'staff', 'cashier'],
                'badge' => 12,
            ],
            'kitchen' => [
                'name' => 'Kitchen Board',
                'route' => 'tenant.kitchen',
                'icon' => 'fire',
                'roles' => ['admin', 'manager', 'staff'],
            ],
            'calendar' => [
                'name' => 'Calendar',
                'route' => 'tenant.calendar',
                'icon' => 'calendar',
                'roles' => ['admin', 'manager', 'staff'],
            ],
            'payments' => [
                'name' => 'Payments',
                'route' => 'tenant.payments',
                'icon' => 'credit-card',
                'roles' => ['admin', 'manager', 'cashier'],
                'badge' => 3,
                'badge_type' => 'warning',
            ],
            'analytics' => [
                'name' => 'Analytics',
                'route' => 'tenant.analytics',
                'icon' => 'chart-bar',
                'roles' => ['admin', 'manager'],
            ],
            'reports' => [
                'name' => 'Reports',
                'route' => 'tenant.reports',
                'icon' => 'document-chart-bar',
                'roles' => ['admin', 'manager'],
            ],
        ];

        // Admin-only management section
        $adminNavItems = [
            'users' => [
                'name' => 'User Management',
                'route' => 'tenant.admin.users',
                'icon' => 'users',
                'roles' => ['admin'],
            ],
            'roles' => [
                'name' => 'Roles & Permissions',
                'route' => 'tenant.admin.roles',
                'icon' => 'shield-check',
                'roles' => ['admin'],
            ],
            'approvals' => [
                'name' => 'Pending Approvals',
                'route' => 'tenant.admin.approvals',
                'icon' => 'user-plus',
                'roles' => ['admin'],
                'badge' => 2,
                'badge_type' => 'danger',
            ],
            'settings' => [
                'name' => 'Settings',
                'route' => 'tenant.admin.settings',
                'icon' => 'cog-6-tooth',
                'roles' => ['admin'],
            ],
        ];

        // Icons mapping (Heroicons-style SVGs)
        $icons = [
            'home' => '<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25"/>',
            'clipboard-list' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25z"/>',
            'fire' => '<path stroke-linecap="round" stroke-linejoin="round" d="M15.362 5.214A8.252 8.252 0 0112 21 8.25 8.25 0 016.038 7.048 8.287 8.287 0 009 9.6a8.983 8.983 0 013.361-6.867 8.21 8.21 0 003 2.48z"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 18a3.75 3.75 0 00.495-7.467 5.99 5.99 0 00-1.925 3.546 5.974 5.974 0 01-2.133-1A3.75 3.75 0 0012 18z"/>',
            'calendar' => '<path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5"/>',
            'credit-card' => '<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z"/>',
            'chart-bar' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z"/>',
            'document-chart-bar' => '<path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25M9 16.5v.75m3-3v3M15 12v5.25m-4.5-15H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>',
            'users' => '<path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/>',
            'shield-check' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/>',
            'user-plus' => '<path stroke-linecap="round" stroke-linejoin="round" d="M19 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zM4 19.235v-.11a6.375 6.375 0 0112.75 0v.109A12.318 12.318 0 0110.374 21c-2.331 0-4.512-.645-6.374-1.766z"/>',
            'cog-6-tooth' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>',
            'bell' => '<path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0"/>',
            'chat-bubble-left-right' => '<path stroke-linecap="round" stroke-linejoin="round" d="M20.25 8.511c.884.284 1.5 1.128 1.5 2.097v4.286c0 1.136-.847 2.1-1.98 2.193-.34.027-.68.052-1.02.072v3.091l-3-3c-1.354 0-2.694-.055-4.02-.163a2.115 2.115 0 01-.825-.242m9.345-8.334a2.126 2.126 0 00-.476-.095 48.64 48.64 0 00-8.048 0c-1.131.094-1.976 1.057-1.976 2.192v4.286c0 .837.46 1.58 1.155 1.951m9.345-8.334V6.637c0-1.621-1.152-3.026-2.76-3.235A48.455 48.455 0 0011.25 3c-2.115 0-4.198.137-6.24.402-1.608.209-2.76 1.614-2.76 3.235v6.226c0 1.621 1.152 3.026 2.76 3.235.577.075 1.157.14 1.74.194V21l4.155-4.155"/>',
            'magnifying-glass' => '<path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/>',
            'bars-3' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/>',
            'x-mark' => '<path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>',
            'chevron-down' => '<path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/>',
            'arrow-right-on-rectangle' => '<path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75"/>',
        ];

        function renderIcon($icons, $name, $class = '') {
            $path = $icons[$name] ?? '';
            return '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="' . $class . '">' . $path . '</svg>';
        }
    @endphp

    <!-- Mobile sidebar overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <aside class="sidebar tile flex flex-col bg-white lg:bg-[var(--panel)]" id="sidebar">
            <div class="flex h-full flex-col p-4">
                <!-- Logo & Brand -->
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-[var(--brand)] to-[var(--brand-deep)] text-lg text-white">🍽</div>
                        <div>
                            <p class="font-bold text-[var(--ink)]">CaterFlow</p>
                            <p class="text-[10px] text-[var(--muted)]">{{ $tenant?->name ?? 'Tenant' }}</p>
                        </div>
                    </div>
                    <button class="lg:hidden rounded-lg p-2 hover:bg-gray-100" onclick="toggleSidebar()">
                        {!! renderIcon($icons, 'x-mark', 'w-5 h-5') !!}
                    </button>
                </div>

                <!-- Main Navigation -->
                <nav class="mt-8 flex-1 space-y-1">
                    <p class="mb-2 px-3 text-[10px] font-semibold uppercase tracking-[0.14em] text-[var(--muted)]">Operations</p>
                    
                    @foreach ($navItems as $key => $item)
                        @if (in_array($tenantRole, $item['roles']))
                            <a 
                                href="{{ route($item['route']) }}"
                                class="nav-item {{ $currentRoute === $item['route'] ? 'active' : 'text-[var(--muted)]' }}"
                            >
                                {!! renderIcon($icons, $item['icon'], 'nav-icon') !!}
                                <span class="flex-1">{{ $item['name'] }}</span>
                                @if (isset($item['badge']))
                                    <span class="badge {{ isset($item['badge_type']) ? 'badge-' . $item['badge_type'] : 'badge-brand' }}">
                                        {{ $item['badge'] }}
                                    </span>
                                @endif
                            </a>
                        @endif
                    @endforeach

                    @if ($tenantRole === 'admin')
                        <div class="my-4 border-t border-[var(--line)]"></div>
                        <p class="mb-2 px-3 text-[10px] font-semibold uppercase tracking-[0.14em] text-[var(--muted)]">Administration</p>
                        
                        @foreach ($adminNavItems as $key => $item)
                            <a 
                                href="{{ route($item['route']) }}"
                                class="nav-item {{ $currentRoute === $item['route'] ? 'active' : 'text-[var(--muted)]' }}"
                            >
                                {!! renderIcon($icons, $item['icon'], 'nav-icon') !!}
                                <span class="flex-1">{{ $item['name'] }}</span>
                                @if (isset($item['badge']))
                                    <span class="badge {{ isset($item['badge_type']) ? 'badge-' . $item['badge_type'] : 'badge-brand' }}">
                                        {{ $item['badge'] }}
                                    </span>
                                @endif
                            </a>
                        @endforeach
                    @endif
                </nav>

                <!-- Tenant Info Card -->
                <div class="mt-auto">
                    <div class="rounded-xl bg-gradient-to-br from-[#0f3f2e] to-[var(--brand)] p-4 text-white">
                        <p class="text-xs text-white/70">{{ $tenant?->domain ?? 'tenant.local' }}</p>
                        <p class="mt-1 font-semibold">{{ $tenantRoleLabel }} Access</p>
                        <p class="mt-1 text-xs text-white/70">Session active</p>
                    </div>

                    <!-- Logout -->
                    <form method="POST" action="{{ route('auth.tenant.logout') }}" class="mt-3">
                        @csrf
                        <button type="submit" class="nav-item w-full text-[var(--muted)] hover:text-[var(--danger)]">
                            {!! renderIcon($icons, 'arrow-right-on-rectangle', 'nav-icon') !!}
                            <span>Sign Out</span>
                        </button>
                    </form>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col min-w-0">
            <!-- Top Navbar -->
            <header class="sticky top-0 z-30 border-b border-[var(--line)] bg-white/80 backdrop-blur-md">
                <div class="flex h-16 items-center justify-between px-4 lg:px-6">
                    <!-- Mobile menu button -->
                    <button class="lg:hidden rounded-lg p-2 hover:bg-gray-100" onclick="toggleSidebar()">
                        {!! renderIcon($icons, 'bars-3', 'w-6 h-6') !!}
                    </button>

                    <!-- Search -->
                    <div class="hidden sm:flex flex-1 max-w-md items-center gap-2 rounded-xl border border-[var(--line)] bg-white px-3 py-2">
                        {!! renderIcon($icons, 'magnifying-glass', 'w-4 h-4 text-[var(--muted)]') !!}
                        <input 
                            type="text" 
                            placeholder="Search orders, clients, events..." 
                            class="flex-1 bg-transparent text-sm outline-none"
                        />
                        <kbd class="hidden lg:inline-block rounded border border-[var(--line)] bg-gray-50 px-1.5 py-0.5 text-[10px] text-[var(--muted)]">⌘K</kbd>
                    </div>

                    <!-- Right side actions -->
                    <div class="flex items-center gap-2">
                        <!-- Notifications -->
                        <button class="relative rounded-full p-2 hover:bg-gray-100">
                            {!! renderIcon($icons, 'bell', 'w-5 h-5 text-[var(--muted)]') !!}
                            <span class="absolute top-1 right-1 h-2 w-2 rounded-full bg-[var(--danger)]"></span>
                        </button>

                        <!-- Messages -->
                        <button class="relative rounded-full p-2 hover:bg-gray-100">
                            {!! renderIcon($icons, 'chat-bubble-left-right', 'w-5 h-5 text-[var(--muted)]') !!}
                        </button>

                        <!-- User dropdown -->
                        <div class="relative" x-data="{ open: false }">
                            <button 
                                onclick="this.nextElementSibling.classList.toggle('hidden')"
                                class="flex items-center gap-2 rounded-full border border-[var(--line)] bg-white py-1 pl-1 pr-3 hover:bg-gray-50"
                            >
                                <div class="flex h-8 w-8 items-center justify-center rounded-full bg-[var(--brand)]/20 text-sm font-semibold text-[var(--brand)]">
                                    {{ strtoupper(substr($tenantUserName, 0, 1)) }}
                                </div>
                                <div class="hidden sm:block text-left">
                                    <p class="text-xs font-semibold">{{ $tenantUserName }}</p>
                                    <p class="text-[10px] text-[var(--muted)]">{{ $tenantRoleLabel }}</p>
                                </div>
                                {!! renderIcon($icons, 'chevron-down', 'w-4 h-4 text-[var(--muted)]') !!}
                            </button>

                            <!-- Dropdown menu -->
                            <div class="hidden absolute right-0 mt-2 w-48 rounded-xl border border-[var(--line)] bg-white py-1 shadow-lg">
                                <div class="border-b border-[var(--line)] px-4 py-2">
                                    <p class="text-sm font-semibold">{{ $tenantUserName }}</p>
                                    <p class="text-xs text-[var(--muted)]">{{ $tenantUserEmail }}</p>
                                </div>
                                <a href="#" class="block px-4 py-2 text-sm hover:bg-gray-50">Profile Settings</a>
                                <a href="#" class="block px-4 py-2 text-sm hover:bg-gray-50">Help & Support</a>
                                <div class="border-t border-[var(--line)]">
                                    <form method="POST" action="{{ route('auth.tenant.logout') }}">
                                        @csrf
                                        <button type="submit" class="block w-full px-4 py-2 text-left text-sm text-[var(--danger)] hover:bg-red-50">
                                            Sign Out
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Page Content -->
            <main class="flex-1 p-4 lg:p-6">
                @yield('content')
            </main>
        </div>
    </div>

    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('open');
            document.getElementById('sidebarOverlay').classList.toggle('open');
        }

        // Close sidebar on escape
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.getElementById('sidebar').classList.remove('open');
                document.getElementById('sidebarOverlay').classList.remove('open');
            }
        });

        // Close dropdowns when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('[x-data]')) {
                document.querySelectorAll('[x-data] > div:last-child').forEach(el => {
                    if (!el.classList.contains('hidden')) {
                        el.classList.add('hidden');
                    }
                });
            }
        });
    </script>
    @stack('scripts')
</body>
</html>
