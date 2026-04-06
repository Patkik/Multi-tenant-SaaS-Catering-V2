@extends('layouts.tenant-app')

@section('title', 'Orders')

@section('content')
    @php
        $tenantRole = $tenantRole ?? session('tenant_role', 'staff');
        $canEdit = in_array($tenantRole, ['admin', 'manager', 'staff']);
        $canDelete = in_array($tenantRole, ['admin']);
        
        $orders = [
            ['id' => '1234', 'customer' => 'Maria Santos', 'items' => 15, 'total' => 450.00, 'status' => 'preparing', 'time' => '10:30 AM', 'type' => 'delivery'],
            ['id' => '1233', 'customer' => 'Tech Corp Event', 'items' => 50, 'total' => 1250.00, 'status' => 'ready', 'time' => '11:00 AM', 'type' => 'pickup'],
            ['id' => '1232', 'customer' => 'Wedding Reception', 'items' => 120, 'total' => 3500.00, 'status' => 'delivered', 'time' => '09:00 AM', 'type' => 'catering'],
            ['id' => '1231', 'customer' => 'John Smith', 'items' => 8, 'total' => 185.00, 'status' => 'pending', 'time' => '12:00 PM', 'type' => 'delivery'],
            ['id' => '1230', 'customer' => 'Birthday Party', 'items' => 35, 'total' => 875.00, 'status' => 'preparing', 'time' => '02:00 PM', 'type' => 'pickup'],
        ];

        $statusColors = [
            'pending' => 'bg-gray-100 text-gray-700',
            'preparing' => 'bg-amber-100 text-amber-700',
            'ready' => 'bg-blue-100 text-blue-700',
            'delivered' => 'bg-green-100 text-green-700',
            'cancelled' => 'bg-red-100 text-red-700',
        ];
    @endphp

    <!-- Page Header -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="display-font text-3xl lg:text-4xl">Orders</h1>
            <p class="mt-1 text-sm text-[var(--muted)]">
                Manage and track all orders in real-time.
            </p>
        </div>
        @if ($canEdit)
            <button class="rounded-full bg-[var(--brand)] px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-[var(--brand-deep)] transition">
                + New Order
            </button>
        @endif
    </div>

    <!-- Quick Stats -->
    <section class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <article class="tile p-4">
            <div class="flex items-center justify-between">
                <p class="text-xs font-semibold uppercase tracking-[0.12em] text-[var(--muted)]">Today's Orders</p>
                <span class="badge badge-brand">+12%</span>
            </div>
            <p class="mt-2 text-3xl font-bold">24</p>
        </article>
        <article class="tile p-4">
            <p class="text-xs font-semibold uppercase tracking-[0.12em] text-[var(--muted)]">Pending</p>
            <p class="mt-2 text-3xl font-bold text-amber-600">5</p>
        </article>
        <article class="tile p-4">
            <p class="text-xs font-semibold uppercase tracking-[0.12em] text-[var(--muted)]">In Progress</p>
            <p class="mt-2 text-3xl font-bold text-blue-600">12</p>
        </article>
        <article class="tile p-4">
            <p class="text-xs font-semibold uppercase tracking-[0.12em] text-[var(--muted)]">Completed</p>
            <p class="mt-2 text-3xl font-bold text-green-600">7</p>
        </article>
    </section>

    <!-- Filters -->
    <section class="mt-6 flex flex-wrap items-center gap-3">
        <div class="flex items-center gap-2 rounded-lg border border-[var(--line)] bg-white px-3 py-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-[var(--muted)]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
            <input type="text" placeholder="Search orders..." class="bg-transparent text-sm outline-none w-40" />
        </div>
        <select class="rounded-lg border border-[var(--line)] bg-white px-3 py-2 text-sm outline-none">
            <option>All Status</option>
            <option>Pending</option>
            <option>Preparing</option>
            <option>Ready</option>
            <option>Delivered</option>
        </select>
        <select class="rounded-lg border border-[var(--line)] bg-white px-3 py-2 text-sm outline-none">
            <option>All Types</option>
            <option>Delivery</option>
            <option>Pickup</option>
            <option>Catering</option>
        </select>
        <button class="rounded-lg border border-[var(--line)] bg-white px-3 py-2 text-sm hover:bg-gray-50">
            Today
        </button>
    </section>

    <!-- Orders Table -->
    <section class="mt-6">
        <div class="tile overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wider text-[var(--muted)]">
                        <tr>
                            <th class="px-5 py-3">Order ID</th>
                            <th class="px-5 py-3">Customer</th>
                            <th class="px-5 py-3">Items</th>
                            <th class="px-5 py-3">Total</th>
                            <th class="px-5 py-3">Type</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3">Time</th>
                            <th class="px-5 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[var(--line)]">
                        @foreach ($orders as $order)
                            <tr class="hover:bg-gray-50">
                                <td class="px-5 py-4">
                                    <span class="font-mono font-semibold">#{{ $order['id'] }}</span>
                                </td>
                                <td class="px-5 py-4">
                                    <p class="font-medium">{{ $order['customer'] }}</p>
                                </td>
                                <td class="px-5 py-4 text-sm text-[var(--muted)]">
                                    {{ $order['items'] }} items
                                </td>
                                <td class="px-5 py-4 font-semibold">
                                    ${{ number_format($order['total'], 2) }}
                                </td>
                                <td class="px-5 py-4">
                                    <span class="badge badge-muted">{{ ucfirst($order['type']) }}</span>
                                </td>
                                <td class="px-5 py-4">
                                    <span class="badge {{ $statusColors[$order['status']] }}">
                                        {{ ucfirst($order['status']) }}
                                    </span>
                                </td>
                                <td class="px-5 py-4 text-sm text-[var(--muted)]">
                                    {{ $order['time'] }}
                                </td>
                                <td class="px-5 py-4">
                                    <div class="flex items-center justify-end gap-2">
                                        <button class="rounded-lg p-2 text-[var(--muted)] hover:bg-gray-100 hover:text-[var(--ink)]" title="View">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                        </button>
                                        @if ($canEdit)
                                            <button class="rounded-lg p-2 text-[var(--muted)] hover:bg-gray-100 hover:text-[var(--ink)]" title="Edit">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="flex items-center justify-between border-t border-[var(--line)] px-5 py-3">
                <p class="text-sm text-[var(--muted)]">Showing 5 of 24 orders</p>
                <div class="flex gap-2">
                    <button class="rounded-lg border border-[var(--line)] px-3 py-1.5 text-sm hover:bg-gray-50">Previous</button>
                    <button class="rounded-lg border border-[var(--line)] px-3 py-1.5 text-sm hover:bg-gray-50">Next</button>
                </div>
            </div>
        </div>
    </section>
@endsection
