@extends('layouts.tenant-app')

@section('title', 'Reports')

@section('content')
    <!-- Page Header -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="display-font text-3xl lg:text-4xl">Reports</h1>
            <p class="mt-1 text-sm text-[var(--muted)]">
                Generate and download business reports.
            </p>
        </div>
        <button class="rounded-full bg-[var(--brand)] px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-[var(--brand-deep)] transition">
            + Create Report
        </button>
    </div>

    <!-- Report Templates -->
    <section class="mt-6">
        <h2 class="font-semibold text-lg mb-4">Report Templates</h2>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ([
                ['name' => 'Sales Summary', 'description' => 'Daily, weekly, or monthly sales overview', 'icon' => '📊'],
                ['name' => 'Revenue Report', 'description' => 'Detailed revenue breakdown by category', 'icon' => '💰'],
                ['name' => 'Order History', 'description' => 'Complete order transaction log', 'icon' => '📋'],
                ['name' => 'Customer Report', 'description' => 'Customer activity and preferences', 'icon' => '👥'],
                ['name' => 'Inventory Report', 'description' => 'Stock levels and usage trends', 'icon' => '📦'],
                ['name' => 'Staff Performance', 'description' => 'Team productivity metrics', 'icon' => '👔'],
            ] as $report)
                <article class="tile p-5 hover:shadow-md transition cursor-pointer">
                    <div class="flex items-start justify-between">
                        <span class="text-3xl">{{ $report['icon'] }}</span>
                        <button class="text-[var(--muted)] hover:text-[var(--ink)]">⋮</button>
                    </div>
                    <h3 class="mt-4 font-semibold">{{ $report['name'] }}</h3>
                    <p class="mt-1 text-sm text-[var(--muted)]">{{ $report['description'] }}</p>
                    <button class="mt-4 w-full rounded-lg bg-[var(--brand-light)] py-2 text-sm font-medium text-[var(--brand)] hover:bg-[var(--brand)]/20">
                        Generate Report
                    </button>
                </article>
            @endforeach
        </div>
    </section>

    <!-- Recent Reports -->
    <section class="mt-8">
        <h2 class="font-semibold text-lg mb-4">Recent Reports</h2>
        <div class="tile overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wider text-[var(--muted)]">
                    <tr>
                        <th class="px-5 py-3">Report Name</th>
                        <th class="px-5 py-3">Type</th>
                        <th class="px-5 py-3">Date Range</th>
                        <th class="px-5 py-3">Generated</th>
                        <th class="px-5 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[var(--line)]">
                    @foreach ([
                        ['name' => 'Weekly Sales Summary', 'type' => 'Sales', 'range' => 'Mar 31 - Apr 6', 'date' => 'Today'],
                        ['name' => 'Monthly Revenue', 'type' => 'Revenue', 'range' => 'March 2026', 'date' => 'Apr 1'],
                        ['name' => 'Q1 Performance', 'type' => 'Performance', 'range' => 'Jan - Mar 2026', 'date' => 'Apr 1'],
                    ] as $report)
                        <tr class="hover:bg-gray-50">
                            <td class="px-5 py-4 font-medium">{{ $report['name'] }}</td>
                            <td class="px-5 py-4"><span class="badge badge-muted">{{ $report['type'] }}</span></td>
                            <td class="px-5 py-4 text-sm text-[var(--muted)]">{{ $report['range'] }}</td>
                            <td class="px-5 py-4 text-sm text-[var(--muted)]">{{ $report['date'] }}</td>
                            <td class="px-5 py-4 text-right">
                                <button class="rounded-lg px-3 py-1.5 text-sm font-medium text-[var(--brand)] hover:bg-[var(--brand-light)]">
                                    Download
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endsection
