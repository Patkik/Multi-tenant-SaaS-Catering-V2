@extends('layouts.tenant-app')

@section('title', 'Analytics')

@section('content')
    <!-- Page Header -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="display-font text-3xl lg:text-4xl">Analytics</h1>
            <p class="mt-1 text-sm text-[var(--muted)]">
                Business insights and performance metrics.
            </p>
        </div>
        <div class="flex gap-2">
            <select class="rounded-lg border border-[var(--line)] px-3 py-2 text-sm">
                <option>Last 7 days</option>
                <option>Last 30 days</option>
                <option>Last 90 days</option>
                <option>This year</option>
            </select>
            <button class="rounded-full border border-[var(--line)] px-5 py-2.5 text-sm font-semibold hover:bg-gray-50 transition">
                Export
            </button>
        </div>
    </div>

    <!-- KPI Cards -->
    <section class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <article class="tile p-5">
            <p class="text-xs font-semibold uppercase tracking-[0.12em] text-[var(--muted)]">Total Revenue</p>
            <p class="mt-2 text-4xl font-bold">$45,230</p>
            <p class="mt-1 text-xs text-green-600">↑ 12% from last period</p>
        </article>
        <article class="tile p-5">
            <p class="text-xs font-semibold uppercase tracking-[0.12em] text-[var(--muted)]">Orders Completed</p>
            <p class="mt-2 text-4xl font-bold">342</p>
            <p class="mt-1 text-xs text-green-600">↑ 8% from last period</p>
        </article>
        <article class="tile p-5">
            <p class="text-xs font-semibold uppercase tracking-[0.12em] text-[var(--muted)]">Avg Order Value</p>
            <p class="mt-2 text-4xl font-bold">$132</p>
            <p class="mt-1 text-xs text-green-600">↑ 3% from last period</p>
        </article>
        <article class="tile p-5">
            <p class="text-xs font-semibold uppercase tracking-[0.12em] text-[var(--muted)]">Customer Satisfaction</p>
            <p class="mt-2 text-4xl font-bold">4.8</p>
            <p class="mt-1 text-xs text-[var(--muted)]">Based on 156 reviews</p>
        </article>
    </section>

    <!-- Charts Grid -->
    <section class="mt-6 grid gap-4 xl:grid-cols-2">
        <!-- Revenue Chart -->
        <article class="tile p-5">
            <h2 class="font-semibold">Revenue Trend</h2>
            <div class="mt-6 h-64 flex items-end gap-2">
                @foreach ([65, 82, 71, 93, 56, 78, 89, 95, 72, 88, 91, 85] as $i => $value)
                    <div class="flex-1 flex flex-col items-center gap-2">
                        <div class="w-full rounded-t-lg bg-gradient-to-t from-[var(--brand-deep)] to-[var(--brand)]" style="height: {{ $value * 2 }}px;"></div>
                        <span class="text-[10px] text-[var(--muted)]">{{ ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'][$i] }}</span>
                    </div>
                @endforeach
            </div>
        </article>

        <!-- Order Types -->
        <article class="tile p-5">
            <h2 class="font-semibold">Order Distribution</h2>
            <div class="mt-6 flex items-center justify-center">
                <div class="relative h-48 w-48">
                    <svg viewBox="0 0 36 36" class="h-full w-full">
                        <path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="#0a7a4c" stroke-width="3" stroke-dasharray="45, 100" />
                        <path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="#3b82f6" stroke-width="3" stroke-dasharray="30, 100" stroke-dashoffset="-45" />
                        <path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="#f59e0b" stroke-width="3" stroke-dasharray="25, 100" stroke-dashoffset="-75" />
                    </svg>
                </div>
            </div>
            <div class="mt-6 flex justify-center gap-6 text-sm">
                <span class="flex items-center gap-2"><span class="h-3 w-3 rounded-full bg-[var(--brand)]"></span> Catering 45%</span>
                <span class="flex items-center gap-2"><span class="h-3 w-3 rounded-full bg-blue-500"></span> Delivery 30%</span>
                <span class="flex items-center gap-2"><span class="h-3 w-3 rounded-full bg-amber-500"></span> Pickup 25%</span>
            </div>
        </article>
    </section>

    <!-- Top Items -->
    <section class="mt-6">
        <div class="tile p-5">
            <h2 class="font-semibold mb-4">Top Selling Items</h2>
            <div class="space-y-4">
                @foreach ([
                    ['name' => 'Grilled Chicken Platter', 'orders' => 156, 'revenue' => 4680, 'growth' => 12],
                    ['name' => 'Caesar Salad', 'orders' => 134, 'revenue' => 2680, 'growth' => 8],
                    ['name' => 'Pasta Carbonara', 'orders' => 98, 'revenue' => 1960, 'growth' => -3],
                    ['name' => 'Steak Dinner', 'orders' => 87, 'revenue' => 3480, 'growth' => 15],
                    ['name' => 'Seafood Platter', 'orders' => 76, 'revenue' => 3040, 'growth' => 22],
                ] as $item)
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-[var(--brand-light)] text-[var(--brand)]">🍽</div>
                            <div>
                                <p class="font-medium">{{ $item['name'] }}</p>
                                <p class="text-xs text-[var(--muted)]">{{ $item['orders'] }} orders</p>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="font-semibold">${{ number_format($item['revenue']) }}</p>
                            <p class="text-xs {{ $item['growth'] > 0 ? 'text-green-600' : 'text-red-600' }}">
                                {{ $item['growth'] > 0 ? '↑' : '↓' }} {{ abs($item['growth']) }}%
                            </p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
@endsection
