@extends('layouts.tenant-app')

@section('title', 'Orders')

@section('content')
    @php
        $tenantRole = $tenantRole ?? session('tenant_role', 'staff');
        $canCreateOrders = (bool) ($canCreateOrders ?? false);
        $canUpdateOrders = (bool) ($canUpdateOrders ?? ($canEdit ?? false));
        $canDeleteOrders = (bool) ($canDeleteOrders ?? ($canDelete ?? false));
        $filters = $filters ?? ['search' => '', 'status' => '', 'type' => '', 'today' => false];
    @endphp

    @if (session('success'))
        <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
            {{ session('success') }}
        </div>
    @endif

    <!-- Page Header -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="display-font text-3xl lg:text-4xl">Orders</h1>
            <p class="mt-1 text-sm text-[var(--muted)]">
                Manage and track all orders in real-time.
            </p>
        </div>
        @if ($canCreateOrders)
            <a href="{{ route('tenant.orders.create') }}" class="inline-flex rounded-full bg-[var(--brand)] px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-[var(--brand-deep)] transition">
                + New Order
            </a>
        @endif
    </div>

    <!-- Quick Stats -->
    <section class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <article class="tile p-4">
            <div class="flex items-center justify-between">
                <p class="text-xs font-semibold uppercase tracking-[0.12em] text-[var(--muted)]">Today's Orders</p>
            </div>
            <p class="mt-2 text-3xl font-bold">{{ $stats['today_orders'] ?? 0 }}</p>
        </article>
        <article class="tile p-4">
            <p class="text-xs font-semibold uppercase tracking-[0.12em] text-[var(--muted)]">Pending</p>
            <p class="mt-2 text-3xl font-bold text-amber-600">{{ $stats['pending_orders'] ?? 0 }}</p>
        </article>
        <article class="tile p-4">
            <p class="text-xs font-semibold uppercase tracking-[0.12em] text-[var(--muted)]">In Progress</p>
            <p class="mt-2 text-3xl font-bold text-blue-600">{{ $stats['in_progress_orders'] ?? 0 }}</p>
        </article>
        <article class="tile p-4">
            <p class="text-xs font-semibold uppercase tracking-[0.12em] text-[var(--muted)]">Completed</p>
            <p class="mt-2 text-3xl font-bold text-green-600">{{ $stats['completed_orders'] ?? 0 }}</p>
        </article>
    </section>

    <!-- Filters -->
    <form method="GET" action="{{ route('tenant.orders.index') }}" class="mt-6 flex flex-wrap items-center gap-3">
        <div class="flex items-center gap-2 rounded-lg border border-[var(--line)] bg-white px-3 py-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-[var(--muted)]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
            <input name="search" value="{{ $filters['search'] }}" type="text" placeholder="Search orders..." class="bg-transparent text-sm outline-none w-40" />
        </div>
        <select name="status" class="rounded-lg border border-[var(--line)] bg-white px-3 py-2 text-sm outline-none">
            <option value="">All Status</option>
            @foreach (($orderStatuses ?? []) as $status)
                <option value="{{ $status }}" @selected($filters['status'] === $status)>{{ $status }}</option>
            @endforeach
        </select>
        <select name="type" class="rounded-lg border border-[var(--line)] bg-white px-3 py-2 text-sm outline-none">
            <option value="">All Types</option>
            @foreach (($orderTypes ?? []) as $type)
                <option value="{{ $type }}" @selected($filters['type'] === $type)>{{ $type }}</option>
            @endforeach
        </select>
        <button name="today" value="1" class="rounded-lg border border-[var(--line)] px-3 py-2 text-sm {{ $filters['today'] ? 'bg-[var(--brand-light)] text-[var(--brand)]' : 'bg-white hover:bg-gray-50' }}">
            Today
        </button>
        <button type="submit" class="rounded-lg bg-[var(--brand)] px-3 py-2 text-sm font-semibold text-white hover:bg-[var(--brand-deep)]">Apply</button>
        <a href="{{ route('tenant.orders.index') }}" class="rounded-lg border border-[var(--line)] bg-white px-3 py-2 text-sm hover:bg-gray-50">Reset</a>
    </form>

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
                        @forelse ($orders as $order)
                            <tr class="hover:bg-gray-50">
                                <td class="px-5 py-4">
                                    <span class="font-mono font-semibold">{{ $order->order_number }}</span>
                                </td>
                                <td class="px-5 py-4">
                                    <p class="font-medium">{{ $order->customer_name }}</p>
                                </td>
                                <td class="px-5 py-4 text-sm text-[var(--muted)]">
                                    {{ $order->items_count }} items
                                </td>
                                <td class="px-5 py-4 font-semibold">
                                    ${{ number_format((float) $order->total_amount, 2) }}
                                </td>
                                <td class="px-5 py-4">
                                    <span class="badge badge-muted">{{ $order->order_type }}</span>
                                </td>
                                <td class="px-5 py-4">
                                    <span class="badge {{ $statusColors[$order->status] ?? 'bg-gray-100 text-gray-700' }}">
                                        {{ $order->status }}
                                    </span>
                                </td>
                                <td class="px-5 py-4 text-sm text-[var(--muted)]">
                                    {{ $order->ordered_at?->format('h:i A') }}
                                </td>
                                <td class="px-5 py-4">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="{{ route('tenant.orders.show', $order) }}" class="rounded-lg p-2 text-[var(--muted)] hover:bg-gray-100 hover:text-[var(--ink)]" title="View">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                        </a>
                                        @if ($canUpdateOrders)
                                            <a href="{{ route('tenant.orders.edit', $order) }}" class="rounded-lg p-2 text-[var(--muted)] hover:bg-gray-100 hover:text-[var(--ink)]" title="Edit">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                            </a>
                                        @endif
                                        @if ($canDeleteOrders)
                                            <form method="POST" action="{{ route('tenant.orders.destroy', $order) }}" onsubmit="return confirm('Delete this order?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="rounded-lg p-2 text-red-600 hover:bg-red-50" title="Delete">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3m-7 0h8" />
                                                    </svg>
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-5 py-8 text-center text-sm text-[var(--muted)]">
                                    No orders found for the current filters.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="flex items-center justify-between border-t border-[var(--line)] px-5 py-3">
                <p class="text-sm text-[var(--muted)]">Showing {{ $orders->count() }} of {{ $orders->total() }} orders</p>
                <div class="flex gap-2">
                    @if ($orders->previousPageUrl())
                        <a href="{{ $orders->previousPageUrl() }}" class="rounded-lg border border-[var(--line)] px-3 py-1.5 text-sm hover:bg-gray-50">Previous</a>
                    @else
                        <span class="rounded-lg border border-[var(--line)] px-3 py-1.5 text-sm text-[var(--muted)]">Previous</span>
                    @endif
                    @if ($orders->nextPageUrl())
                        <a href="{{ $orders->nextPageUrl() }}" class="rounded-lg border border-[var(--line)] px-3 py-1.5 text-sm hover:bg-gray-50">Next</a>
                    @else
                        <span class="rounded-lg border border-[var(--line)] px-3 py-1.5 text-sm text-[var(--muted)]">Next</span>
                    @endif
                </div>
            </div>
        </div>
    </section>
@endsection
