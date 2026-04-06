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
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="display-font text-2xl lg:text-3xl">Dashboard</h1>
            <p class="mt-1 text-sm text-[var(--muted)]">
                Welcome back, {{ $tenantUserName ?? 'User' }}! Here's what's happening today.
            </p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('tenant.orders.index') }}" class="inline-flex items-center rounded-full bg-[var(--brand)] px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-[var(--brand-deep)] transition">
                + New Order
            </a>
            @if ($isManager)
                <button class="inline-flex items-center rounded-full border border-[var(--brand)] px-4 py-2 text-sm font-semibold text-[var(--brand)] hover:bg-[var(--brand-light)] transition">
                    Import Menu
                </button>
            @endif
        </div>
    </div>

    <!-- Stats Cards -->
    <section class="mt-6 grid gap-4 grid-cols-2 lg:grid-cols-4">
        <article class="reveal rounded-xl bg-gradient-to-br from-[var(--brand)] to-[var(--brand-deep)] p-4 text-white">
            <div class="flex items-center justify-between">
                <p class="text-xs font-semibold uppercase tracking-[0.1em] text-white/80">Orders Today</p>
                <span class="badge badge-brand bg-white/20 text-white text-[10px]">{{ $stats['orders_growth'] ?? '+8%' }}</span>
            </div>
            <p class="mt-2 text-2xl font-bold">{{ $stats['orders_today'] ?? 24 }}</p>
            <p class="mt-1 text-xs text-white/70">Metrics live</p>
        </article>

        <article class="reveal rounded-xl border border-[var(--line)] bg-white p-4 shadow-sm">
            <div class="flex items-center justify-between">
                <p class="text-xs font-semibold uppercase tracking-[0.1em] text-[var(--muted)]">Delivered</p>
                <span class="badge badge-brand text-[10px]">{{ $stats['delivered_percent'] ?? '92%' }}</span>
            </div>
            <p class="mt-2 text-2xl font-bold text-[var(--text)]">{{ $stats['delivered_count'] ?? 10 }}</p>
            <p class="mt-1 text-xs text-[var(--muted)]">On-time completion</p>
        </article>

        <article class="reveal rounded-xl border border-[var(--line)] bg-white p-4 shadow-sm">
            <div class="flex items-center justify-between">
                <p class="text-xs font-semibold uppercase tracking-[0.1em] text-[var(--muted)]">In Kitchen</p>
                <span class="badge badge-warning text-[10px]">Active</span>
            </div>
            <p class="mt-2 text-2xl font-bold text-[var(--text)]">{{ $stats['in_kitchen'] ?? 12 }}</p>
            <p class="mt-1 text-xs text-[var(--muted)]">Prep station workload</p>
        </article>

        <article class="reveal rounded-xl border border-[var(--line)] bg-white p-4 shadow-sm">
            <div class="flex items-center justify-between">
                <p class="text-xs font-semibold uppercase tracking-[0.1em] text-[var(--muted)]">Pending Pickup</p>
                @if ($isCashier)
                    <span class="badge badge-danger text-[10px]">Pending</span>
                @endif
            </div>
            <p class="mt-2 text-2xl font-bold text-[var(--text)]">{{ $stats['pending_pickup'] ?? ($isCashier ? 3 : 2) }}</p>
            <p class="mt-1 text-xs text-[var(--muted)]">{{ $isCashier ? 'Awaiting payment' : 'Driver assignment' }}</p>
        </article>
    </section>

    <!-- Main Content Grid -->
    <section class="mt-4 grid gap-4 {{ $canSeeAnalytics ? 'lg:grid-cols-3' : 'lg:grid-cols-2' }}">
        <!-- Analytics Chart (Admin/Manager only) -->
        @if ($canSeeAnalytics)
            <article class="rounded-xl border border-[var(--line)] bg-white p-5 shadow-sm lg:col-span-2">
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-[var(--text)]">Weekly Performance</h2>
                    <div class="flex gap-2">
                        <a href="{{ route('tenant.analytics') }}" class="rounded-md border border-[var(--line)] px-2 py-1 text-[10px] font-medium hover:bg-gray-50 transition">Analytics</a>
                    </div>
                </div>
                <div class="mt-6 grid grid-cols-7 items-end gap-2">
                    @foreach ($chart_data['revenue'] ?? [58, 76, 64, 92, 48, 85, 61] as $index => $bar)
                        <div class="flex flex-col items-center gap-1">
                            <div class="w-full rounded-md bg-[var(--brand)]/10" style="height: 100px; position: relative; overflow: hidden;">
                                <span class="absolute bottom-0 left-0 right-0 rounded-md bg-gradient-to-t from-[var(--brand-deep)] to-[var(--brand)]" style="height: {{ $bar }}%;"></span>
                            </div>
                            <span class="text-[9px] text-[var(--muted)]">{{ ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'][$index] }}</span>
                        </div>
                    @endforeach
                </div>
            </article>
        @else
            <!-- Operations View for Staff -->
            <article class="rounded-xl border border-[var(--line)] bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-[var(--text)]">Today's Throughput</h2>
                    <span class="text-[10px] text-[var(--muted)]">Updated live</span>
                </div>
                <div class="mt-6 grid grid-cols-7 items-end gap-1.5">
                    @foreach ($chart_data['orders'] ?? [58, 76, 64, 92, 48, 68, 61] as $bar)
                        <div class="flex flex-col items-center gap-1">
                            <div class="w-full rounded-full bg-[var(--brand)]/15" style="height: 60px; position: relative; overflow: hidden;">
                                <span class="absolute bottom-0 left-0 right-0 rounded-full bg-gradient-to-t from-[var(--brand-deep)] to-[var(--brand)]" style="height: {{ $bar }}%;"></span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </article>
        @endif

        <!-- Role-specific Quick Actions -->
        <article class="rounded-xl border border-[var(--line)] bg-white p-5 shadow-sm">
            <h2 class="text-sm font-semibold text-[var(--text)]">
                @if ($isCashier)
                    Payment Tasks
                @elseif ($canManageTeam)
                    Team Overview
                @else
                    Quick Actions
                @endif
            </h2>

            @if ($isCashier)
                <div class="mt-4 space-y-2">
                    <div class="flex items-center justify-between rounded-lg border border-[var(--line)] p-2.5">
                        <div>
                            <p class="text-sm font-medium">Pending Receipts</p>
                        </div>
                        <span class="badge badge-warning text-[10px]">{{ $stats['pending_pickup'] ?? 3 }}</span>
                    </div>
                    <div class="flex items-center justify-between rounded-lg border border-[var(--line)] p-2.5">
                        <div>
                            <p class="text-sm font-medium">Today's Revenue</p>
                        </div>
                        <span class="text-base font-bold text-[var(--brand)]">${{ $stats['revenue'] ?? '2,450' }}</span>
                    </div>
                    <a href="{{ route('tenant.payments') }}" class="mt-3 block w-full rounded-lg bg-[var(--brand)] py-2 text-center text-xs font-semibold text-white hover:bg-[var(--brand-deep)] transition">
                        Open Payments
                    </a>
                </div>
            @elseif ($canManageTeam)
                <div class="mt-4 space-y-2">
                    <div class="flex items-center gap-3 rounded-lg border border-[var(--line)] p-2.5">
                        <div class="flex h-8 w-8 items-center justify-center rounded-full bg-green-100 text-xs font-medium text-green-600">{{ $stats['active_staff'] ?? 5 }}</div>
                        <div>
                            <p class="text-sm font-medium">Active Staff</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 rounded-lg border border-[var(--line)] p-2.5">
                        <div class="flex h-8 w-8 items-center justify-center rounded-full bg-amber-100 text-xs font-medium text-amber-600">{{ $stats['pending_approvals'] ?? 2 }}</div>
                        <div>
                            <p class="text-sm font-medium">Pending Approvals</p>
                        </div>
                    </div>
                    <a href="{{ route('tenant.admin.users') }}" class="mt-3 block w-full rounded-lg bg-[var(--brand)] py-2 text-center text-xs font-semibold text-white hover:bg-[var(--brand-deep)] transition">
                        Manage Team
                    </a>
                </div>
            @else
                <div class="mt-4 space-y-2">
                    <a href="{{ route('tenant.orders.index') }}" class="flex w-full items-center gap-3 rounded-lg border border-[var(--line)] p-2.5 text-left hover:bg-gray-50 transition">
                        <span class="text-lg">📋</span>
                        <div>
                            <p class="text-sm font-medium">View Orders</p>
                        </div>
                    </a>
                    <a href="{{ route('tenant.calendar') }}" class="flex w-full items-center gap-3 rounded-lg border border-[var(--line)] p-2.5 text-left hover:bg-gray-50 transition">
                        <span class="text-lg">📅</span>
                        <div>
                            <p class="text-sm font-medium">My Schedule</p>
                        </div>
                    </a>
                    <a href="{{ route('tenant.kitchen') }}" class="flex w-full items-center gap-3 rounded-lg border border-[var(--line)] p-2.5 text-left hover:bg-gray-50 transition">
                        <span class="text-lg">🍳</span>
                        <div>
                            <p class="text-sm font-medium">Kitchen Board</p>
                        </div>
                    </a>
                </div>
            @endif
        </article>
    </section>

    <!-- Recent Activity & Tasks -->
    <section class="mt-4 grid gap-4 lg:grid-cols-2">
        <!-- Recent Orders -->
        <article class="rounded-xl border border-[var(--line)] bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-semibold text-[var(--text)]">Recent Orders</h2>
                <a href="{{ route('tenant.orders.index') }}" class="text-[10px] font-medium text-[var(--brand)] hover:underline">View All</a>
            </div>
            <div class="mt-4 space-y-2">
                @foreach ($recent_orders ?? [] as $order)
                    <div class="flex items-center justify-between rounded-lg border border-[var(--line)] p-2.5">
                        <div class="flex items-center gap-3">
                            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-[var(--brand-light)] text-xs font-bold text-[var(--brand)]">
                                {{ substr($order['customer'], 0, 1) }}
                            </div>
                            <div>
                                <p class="text-sm font-medium">{{ $order['customer'] }}</p>
                                <p class="text-[10px] text-[var(--muted)]">{{ $order['id'] }} · {{ $order['items'] }}</p>
                            </div>
                        </div>
                        <div class="text-right">
                            <span class="badge text-[9px] px-1.5 py-0.5 {{ $order['status'] === 'delivered' ? 'badge-brand' : ($order['status'] === 'ready' ? 'bg-amber-100 text-amber-700' : 'badge-muted') }}">
                                {{ ucfirst($order['status']) }}
                            </span>
                            <p class="mt-1 text-[9px] text-[var(--muted)]">{{ $order['time'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </article>

        <!-- Today's Tasks -->
        <article class="rounded-xl border border-[var(--line)] bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-semibold text-[var(--text)]">Today's Tasks</h2>
                <span class="badge badge-muted text-[10px]">{{ $stats['assigned_tasks'] ?? 4 }} assigned</span>
            </div>
            <ul class="mt-4 space-y-2">
                @foreach ($tasks ?? [] as $task)
                    <li class="flex items-start gap-2.5 rounded-lg border border-[var(--line)] p-2.5 {{ $task['done'] ? 'bg-gray-50 opacity-60' : '' }}">
                        <input 
                            type="checkbox" 
                            {{ $task['done'] ? 'checked' : '' }}
                            class="mt-0.5 h-3.5 w-3.5 rounded border-gray-300 text-[var(--brand)] focus:ring-[var(--brand)]"
                        />
                        <div class="flex-1">
                            <p class="text-sm {{ $task['done'] ? 'line-through text-[var(--muted)]' : '' }}">{{ $task['task'] }}</p>
                            <span class="badge mt-1 text-[9px] px-1.5 py-0.5 {{ $task['priority'] === 'high' ? 'badge-danger' : ($task['priority'] === 'medium' ? 'badge-warning' : 'badge-muted') }}">
                                {{ ucfirst($task['priority']) }}
                            </span>
                        </div>
                    </li>
                @endforeach
            </ul>
        </article>
    </section>
@endsection
