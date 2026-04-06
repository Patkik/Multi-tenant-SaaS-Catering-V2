@extends('layouts.tenant-app')

@section('title', 'Dashboard')

@section('content')
    @php
        $tenantRole = $tenantRole ?? session('tenant_role', 'staff');
        $canSeeAnalytics = in_array($tenantRole, ['admin', 'manager']);
        $canManageTeam = $tenantRole === 'admin';
        $isManager = in_array($tenantRole, ['admin', 'manager']);
        $isCashier = $tenantRole === 'cashier';
    @endphp

    <!-- Page Header -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="display-font text-3xl lg:text-4xl">Dashboard</h1>
            <p class="mt-1 text-sm text-[var(--muted)]">
                Welcome back! Here's what's happening with your operations today.
            </p>
        </div>
        <div class="flex gap-2">
            <button class="rounded-full bg-[var(--brand)] px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-[var(--brand-deep)] transition">
                + New Order
            </button>
            @if ($isManager)
                <button class="rounded-full border border-[var(--brand)] px-5 py-2.5 text-sm font-semibold text-[var(--brand)] hover:bg-[var(--brand-light)] transition">
                    Import Menu
                </button>
            @endif
        </div>
    </div>

    <!-- Stats Cards -->
    <section class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <article class="reveal rounded-2xl bg-gradient-to-br from-[var(--brand)] to-[var(--brand-deep)] p-5 text-white">
            <div class="flex items-center justify-between">
                <p class="text-xs font-semibold uppercase tracking-[0.12em] text-white/80">Orders Today</p>
                <span class="badge badge-brand bg-white/20 text-white">+8%</span>
            </div>
            <p class="mt-3 text-4xl font-bold">24</p>
            <p class="mt-1 text-xs text-white/70">vs 22 last week</p>
        </article>

        <article class="reveal tile p-5">
            <div class="flex items-center justify-between">
                <p class="text-xs font-semibold uppercase tracking-[0.12em] text-[var(--muted)]">Delivered</p>
                <span class="badge badge-brand">92%</span>
            </div>
            <p class="mt-3 text-4xl font-bold">10</p>
            <p class="mt-1 text-xs text-[var(--muted)]">On-time completion</p>
        </article>

        <article class="reveal tile p-5">
            <div class="flex items-center justify-between">
                <p class="text-xs font-semibold uppercase tracking-[0.12em] text-[var(--muted)]">In Kitchen</p>
                <span class="badge badge-warning">Active</span>
            </div>
            <p class="mt-3 text-4xl font-bold">12</p>
            <p class="mt-1 text-xs text-[var(--muted)]">Prep station workload</p>
        </article>

        <article class="reveal tile p-5">
            <div class="flex items-center justify-between">
                <p class="text-xs font-semibold uppercase tracking-[0.12em] text-[var(--muted)]">Pending Pickup</p>
                @if ($isCashier)
                    <span class="badge badge-danger">Pending</span>
                @endif
            </div>
            <p class="mt-3 text-4xl font-bold">{{ $isCashier ? '3' : '2' }}</p>
            <p class="mt-1 text-xs text-[var(--muted)]">{{ $isCashier ? 'Awaiting payment' : 'Driver assignment' }}</p>
        </article>
    </section>

    <!-- Main Content Grid -->
    <section class="mt-6 grid gap-4 {{ $canSeeAnalytics ? 'xl:grid-cols-3' : 'xl:grid-cols-2' }}">
        <!-- Analytics Chart (Admin/Manager only) -->
        @if ($canSeeAnalytics)
            <article class="tile p-5 xl:col-span-2">
                <div class="flex items-center justify-between">
                    <h2 class="font-semibold">Weekly Performance</h2>
                    <div class="flex gap-2">
                        <button class="rounded-lg border border-[var(--line)] px-3 py-1 text-xs font-medium hover:bg-gray-50">Revenue</button>
                        <button class="rounded-lg bg-[var(--brand-light)] px-3 py-1 text-xs font-medium text-[var(--brand)]">Orders</button>
                    </div>
                </div>
                <div class="mt-6 grid grid-cols-7 items-end gap-3">
                    @foreach ([58, 76, 64, 92, 48, 85, 61] as $index => $bar)
                        <div class="flex flex-col items-center gap-2">
                            <div class="w-full rounded-xl bg-[var(--brand)]/10" style="height: 120px; position: relative; overflow: hidden;">
                                <span class="absolute bottom-0 left-0 right-0 rounded-xl bg-gradient-to-t from-[var(--brand-deep)] to-[var(--brand)]" style="height: {{ $bar }}%;"></span>
                            </div>
                            <span class="text-[10px] text-[var(--muted)]">{{ ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'][$index] }}</span>
                        </div>
                    @endforeach
                </div>
            </article>
        @else
            <!-- Operations View for Staff -->
            <article class="tile p-5">
                <div class="flex items-center justify-between">
                    <h2 class="font-semibold">Today's Throughput</h2>
                    <span class="text-xs text-[var(--muted)]">Updated live</span>
                </div>
                <div class="mt-6 grid grid-cols-7 items-end gap-2">
                    @foreach ([58, 76, 64, 92, 48, 68, 61] as $bar)
                        <div class="flex flex-col items-center gap-2">
                            <div class="w-full rounded-full bg-[var(--brand)]/15" style="height: 80px; position: relative; overflow: hidden;">
                                <span class="absolute bottom-0 left-0 right-0 rounded-full bg-gradient-to-t from-[var(--brand-deep)] to-[var(--brand)]" style="height: {{ $bar }}%;"></span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </article>
        @endif

        <!-- Role-specific Quick Actions -->
        <article class="tile p-5">
            <h2 class="font-semibold">
                @if ($isCashier)
                    Payment Tasks
                @elseif ($canManageTeam)
                    Team Overview
                @else
                    Quick Actions
                @endif
            </h2>

            @if ($isCashier)
                <div class="mt-4 space-y-3">
                    <div class="flex items-center justify-between rounded-lg border border-[var(--line)] p-3">
                        <div>
                            <p class="font-medium">Pending Receipts</p>
                            <p class="text-xs text-[var(--muted)]">3 orders awaiting payment</p>
                        </div>
                        <span class="badge badge-warning">3</span>
                    </div>
                    <div class="flex items-center justify-between rounded-lg border border-[var(--line)] p-3">
                        <div>
                            <p class="font-medium">Today's Revenue</p>
                            <p class="text-xs text-[var(--muted)]">Collected so far</p>
                        </div>
                        <span class="text-lg font-bold text-[var(--brand)]">$2,450</span>
                    </div>
                    <button class="mt-4 w-full rounded-xl bg-[var(--brand)] py-3 text-sm font-semibold text-white hover:bg-[var(--brand-deep)] transition">
                        Open Payment Terminal
                    </button>
                </div>
            @elseif ($canManageTeam)
                <div class="mt-4 space-y-3">
                    <div class="flex items-center gap-3 rounded-lg border border-[var(--line)] p-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-full bg-green-100 text-green-600">5</div>
                        <div>
                            <p class="font-medium">Active Staff</p>
                            <p class="text-xs text-[var(--muted)]">Currently on shift</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 rounded-lg border border-[var(--line)] p-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-full bg-amber-100 text-amber-600">2</div>
                        <div>
                            <p class="font-medium">Pending Approvals</p>
                            <p class="text-xs text-[var(--muted)]">Admin requests waiting</p>
                        </div>
                    </div>
                    <a href="{{ route('tenant.admin.users') }}" class="mt-4 block w-full rounded-xl bg-[var(--brand)] py-3 text-center text-sm font-semibold text-white hover:bg-[var(--brand-deep)] transition">
                        Manage Team
                    </a>
                </div>
            @else
                <div class="mt-4 space-y-3">
                    <button class="flex w-full items-center gap-3 rounded-lg border border-[var(--line)] p-3 text-left hover:bg-gray-50 transition">
                        <span class="text-xl">📋</span>
                        <div>
                            <p class="font-medium">View My Tasks</p>
                            <p class="text-xs text-[var(--muted)]">4 tasks assigned today</p>
                        </div>
                    </button>
                    <button class="flex w-full items-center gap-3 rounded-lg border border-[var(--line)] p-3 text-left hover:bg-gray-50 transition">
                        <span class="text-xl">🔔</span>
                        <div>
                            <p class="font-medium">Check Notifications</p>
                            <p class="text-xs text-[var(--muted)]">2 new updates</p>
                        </div>
                    </button>
                    <button class="flex w-full items-center gap-3 rounded-lg border border-[var(--line)] p-3 text-left hover:bg-gray-50 transition">
                        <span class="text-xl">📅</span>
                        <div>
                            <p class="font-medium">My Schedule</p>
                            <p class="text-xs text-[var(--muted)]">Next shift: Tomorrow 8AM</p>
                        </div>
                    </button>
                </div>
            @endif
        </article>
    </section>

    <!-- Recent Activity & Tasks -->
    <section class="mt-6 grid gap-4 xl:grid-cols-2">
        <!-- Recent Orders -->
        <article class="tile p-5">
            <div class="flex items-center justify-between">
                <h2 class="font-semibold">Recent Orders</h2>
                <a href="{{ route('tenant.orders') }}" class="text-xs font-medium text-[var(--brand)] hover:underline">View All</a>
            </div>
            <div class="mt-4 space-y-3">
                @foreach ([
                    ['id' => '#1234', 'customer' => 'Maria Santos', 'items' => '15 items', 'status' => 'preparing', 'time' => '10 min ago'],
                    ['id' => '#1233', 'customer' => 'Tech Corp', 'items' => '50 items', 'status' => 'ready', 'time' => '25 min ago'],
                    ['id' => '#1232', 'customer' => 'Wedding Party', 'items' => '120 items', 'status' => 'delivered', 'time' => '1 hour ago'],
                ] as $order)
                    <div class="flex items-center justify-between rounded-lg border border-[var(--line)] p-3">
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-[var(--brand-light)] text-sm font-bold text-[var(--brand)]">
                                {{ substr($order['customer'], 0, 1) }}
                            </div>
                            <div>
                                <p class="font-medium">{{ $order['customer'] }}</p>
                                <p class="text-xs text-[var(--muted)]">{{ $order['id'] }} · {{ $order['items'] }}</p>
                            </div>
                        </div>
                        <div class="text-right">
                            <span class="badge {{ $order['status'] === 'delivered' ? 'badge-brand' : ($order['status'] === 'ready' ? 'bg-amber-100 text-amber-700' : 'badge-muted') }}">
                                {{ ucfirst($order['status']) }}
                            </span>
                            <p class="mt-1 text-[10px] text-[var(--muted)]">{{ $order['time'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </article>

        <!-- Today's Tasks -->
        <article class="tile p-5">
            <div class="flex items-center justify-between">
                <h2 class="font-semibold">Today's Tasks</h2>
                <span class="badge badge-muted">4 remaining</span>
            </div>
            <ul class="mt-4 space-y-3">
                @foreach ([
                    ['task' => 'Prepare seafood batch for evening event', 'priority' => 'high', 'done' => false],
                    ['task' => 'Check inventory levels for weekend', 'priority' => 'medium', 'done' => false],
                    ['task' => 'Review new menu items with chef', 'priority' => 'low', 'done' => true],
                    ['task' => 'Update delivery schedule', 'priority' => 'medium', 'done' => false],
                ] as $task)
                    <li class="flex items-start gap-3 rounded-lg border border-[var(--line)] p-3 {{ $task['done'] ? 'bg-gray-50 opacity-60' : '' }}">
                        <input 
                            type="checkbox" 
                            {{ $task['done'] ? 'checked' : '' }}
                            class="mt-0.5 h-4 w-4 rounded border-gray-300 text-[var(--brand)] focus:ring-[var(--brand)]"
                        />
                        <div class="flex-1">
                            <p class="text-sm {{ $task['done'] ? 'line-through text-[var(--muted)]' : '' }}">{{ $task['task'] }}</p>
                            <span class="badge mt-1 {{ $task['priority'] === 'high' ? 'badge-danger' : ($task['priority'] === 'medium' ? 'badge-warning' : 'badge-muted') }}">
                                {{ ucfirst($task['priority']) }}
                            </span>
                        </div>
                    </li>
                @endforeach
            </ul>
        </article>
    </section>
@endsection
