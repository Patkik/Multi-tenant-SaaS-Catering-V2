@extends('layouts.tenant-app')

@section('title', 'Payments')

@section('content')
    @php
        $tenantRole = $tenantRole ?? session('tenant_role', 'cashier');
        $canRefund = in_array($tenantRole, ['admin', 'manager']);
        
        $transactions = [
            ['id' => 'TXN-001', 'order' => '#1234', 'customer' => 'Maria Santos', 'amount' => 450.00, 'method' => 'card', 'status' => 'completed', 'time' => '10:35 AM'],
            ['id' => 'TXN-002', 'order' => '#1233', 'customer' => 'Tech Corp', 'amount' => 1250.00, 'method' => 'invoice', 'status' => 'pending', 'time' => '11:05 AM'],
            ['id' => 'TXN-003', 'order' => '#1232', 'customer' => 'Wedding Party', 'amount' => 3500.00, 'method' => 'card', 'status' => 'completed', 'time' => '09:15 AM'],
            ['id' => 'TXN-004', 'order' => '#1231', 'customer' => 'John Smith', 'amount' => 185.00, 'method' => 'cash', 'status' => 'pending', 'time' => '12:00 PM'],
            ['id' => 'TXN-005', 'order' => '#1230', 'customer' => 'Birthday Party', 'amount' => 875.00, 'method' => 'card', 'status' => 'refunded', 'time' => '02:00 PM'],
        ];

        $statusColors = [
            'completed' => 'bg-green-100 text-green-700',
            'pending' => 'bg-amber-100 text-amber-700',
            'refunded' => 'bg-red-100 text-red-700',
            'failed' => 'bg-gray-100 text-gray-700',
        ];

        $methodIcons = [
            'card' => '💳',
            'cash' => '💵',
            'invoice' => '📄',
        ];
    @endphp

    <!-- Page Header -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="display-font text-3xl lg:text-4xl">Payments</h1>
            <p class="mt-1 text-sm text-[var(--muted)]">
                Process and track all payment transactions.
            </p>
        </div>
        <div class="flex gap-2">
            <button class="rounded-full bg-[var(--brand)] px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-[var(--brand-deep)] transition">
                Process Payment
            </button>
            <button class="rounded-full border border-[var(--line)] px-5 py-2.5 text-sm font-semibold hover:bg-gray-50 transition">
                Export
            </button>
        </div>
    </div>

    <!-- Revenue Stats -->
    <section class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <article class="tile p-5 bg-gradient-to-br from-[var(--brand)] to-[var(--brand-deep)] text-white">
            <p class="text-xs font-semibold uppercase tracking-[0.12em] text-white/80">Today's Revenue</p>
            <p class="mt-2 text-4xl font-bold">$5,260</p>
            <p class="mt-1 text-xs text-white/70">+18% from yesterday</p>
        </article>
        <article class="tile p-5">
            <p class="text-xs font-semibold uppercase tracking-[0.12em] text-[var(--muted)]">Pending Payments</p>
            <p class="mt-2 text-4xl font-bold text-amber-600">$1,435</p>
            <p class="mt-1 text-xs text-[var(--muted)]">3 transactions</p>
        </article>
        <article class="tile p-5">
            <p class="text-xs font-semibold uppercase tracking-[0.12em] text-[var(--muted)]">Completed</p>
            <p class="mt-2 text-4xl font-bold text-green-600">$3,950</p>
            <p class="mt-1 text-xs text-[var(--muted)]">8 transactions</p>
        </article>
        <article class="tile p-5">
            <p class="text-xs font-semibold uppercase tracking-[0.12em] text-[var(--muted)]">Refunds</p>
            <p class="mt-2 text-4xl font-bold text-red-600">$875</p>
            <p class="mt-1 text-xs text-[var(--muted)]">1 transaction</p>
        </article>
    </section>

    <!-- Quick Actions for Cashier -->
    @if ($tenantRole === 'cashier')
        <section class="mt-6">
            <div class="tile p-5">
                <h2 class="font-semibold mb-4">Quick Actions</h2>
                <div class="grid gap-3 sm:grid-cols-3">
                    <button class="flex items-center gap-3 rounded-lg border-2 border-dashed border-[var(--line)] p-4 hover:border-[var(--brand)] hover:bg-[var(--brand-light)] transition">
                        <span class="text-2xl">💳</span>
                        <div class="text-left">
                            <p class="font-medium">Card Payment</p>
                            <p class="text-xs text-[var(--muted)]">Process card transaction</p>
                        </div>
                    </button>
                    <button class="flex items-center gap-3 rounded-lg border-2 border-dashed border-[var(--line)] p-4 hover:border-[var(--brand)] hover:bg-[var(--brand-light)] transition">
                        <span class="text-2xl">💵</span>
                        <div class="text-left">
                            <p class="font-medium">Cash Payment</p>
                            <p class="text-xs text-[var(--muted)]">Record cash transaction</p>
                        </div>
                    </button>
                    <button class="flex items-center gap-3 rounded-lg border-2 border-dashed border-[var(--line)] p-4 hover:border-[var(--brand)] hover:bg-[var(--brand-light)] transition">
                        <span class="text-2xl">🧾</span>
                        <div class="text-left">
                            <p class="font-medium">Print Receipt</p>
                            <p class="text-xs text-[var(--muted)]">Reprint last receipt</p>
                        </div>
                    </button>
                </div>
            </div>
        </section>
    @endif

    <!-- Transactions Table -->
    <section class="mt-6">
        <div class="tile overflow-hidden">
            <div class="flex items-center justify-between border-b border-[var(--line)] p-5">
                <h2 class="font-semibold">Recent Transactions</h2>
                <div class="flex items-center gap-3">
                    <div class="flex items-center gap-2 rounded-lg border border-[var(--line)] px-3 py-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-[var(--muted)]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        <input type="text" placeholder="Search transactions..." class="bg-transparent text-sm outline-none w-40" />
                    </div>
                    <select class="rounded-lg border border-[var(--line)] px-3 py-2 text-sm outline-none">
                        <option>All Status</option>
                        <option>Completed</option>
                        <option>Pending</option>
                        <option>Refunded</option>
                    </select>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wider text-[var(--muted)]">
                        <tr>
                            <th class="px-5 py-3">Transaction</th>
                            <th class="px-5 py-3">Order</th>
                            <th class="px-5 py-3">Customer</th>
                            <th class="px-5 py-3">Amount</th>
                            <th class="px-5 py-3">Method</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3">Time</th>
                            <th class="px-5 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[var(--line)]">
                        @foreach ($transactions as $txn)
                            <tr class="hover:bg-gray-50">
                                <td class="px-5 py-4">
                                    <span class="font-mono text-sm font-semibold">{{ $txn['id'] }}</span>
                                </td>
                                <td class="px-5 py-4">
                                    <a href="#" class="text-sm text-[var(--brand)] hover:underline">{{ $txn['order'] }}</a>
                                </td>
                                <td class="px-5 py-4">
                                    <p class="font-medium text-sm">{{ $txn['customer'] }}</p>
                                </td>
                                <td class="px-5 py-4 font-semibold {{ $txn['status'] === 'refunded' ? 'text-red-600' : '' }}">
                                    {{ $txn['status'] === 'refunded' ? '-' : '' }}${{ number_format($txn['amount'], 2) }}
                                </td>
                                <td class="px-5 py-4">
                                    <span class="inline-flex items-center gap-1.5 text-sm">
                                        {{ $methodIcons[$txn['method']] }}
                                        {{ ucfirst($txn['method']) }}
                                    </span>
                                </td>
                                <td class="px-5 py-4">
                                    <span class="badge {{ $statusColors[$txn['status']] }}">
                                        {{ ucfirst($txn['status']) }}
                                    </span>
                                </td>
                                <td class="px-5 py-4 text-sm text-[var(--muted)]">
                                    {{ $txn['time'] }}
                                </td>
                                <td class="px-5 py-4">
                                    <div class="flex items-center justify-end gap-2">
                                        <button class="rounded-lg p-2 text-[var(--muted)] hover:bg-gray-100 hover:text-[var(--ink)]" title="View">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                        </button>
                                        <button class="rounded-lg p-2 text-[var(--muted)] hover:bg-gray-100 hover:text-[var(--ink)]" title="Print Receipt">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                            </svg>
                                        </button>
                                        @if ($canRefund && $txn['status'] === 'completed')
                                            <button class="rounded-lg p-2 text-red-400 hover:bg-red-50 hover:text-red-600" title="Refund">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" />
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
                <p class="text-sm text-[var(--muted)]">Showing 5 of 15 transactions</p>
                <div class="flex gap-2">
                    <button class="rounded-lg border border-[var(--line)] px-3 py-1.5 text-sm hover:bg-gray-50">Previous</button>
                    <button class="rounded-lg border border-[var(--line)] px-3 py-1.5 text-sm hover:bg-gray-50">Next</button>
                </div>
            </div>
        </div>
    </section>
@endsection
