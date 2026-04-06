@extends('layouts.central')

@section('title', 'Dashboard - Central App')

@section('content')
    {{-- Page Header --}}
    <header class="rounded-2xl border border-[#15191f]/20 bg-[#f6f2e8]/80 p-6 shadow-[0_10px_40px_rgba(21,25,31,0.08)] backdrop-blur-sm">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <h1 class="display text-3xl sm:text-4xl">Dashboard</h1>
                <p class="mt-2 max-w-2xl text-sm text-[#15191f]/70">
                    System overview and recent activity at a glance.
                </p>
            </div>
            <div class="grid-paper rounded-xl border border-dashed border-[#15191f]/30 px-4 py-3 text-sm">
                <p class="font-semibold">Quick Actions</p>
                <div class="mt-2 flex flex-wrap gap-2">
                    <a href="{{ route('central.tenants') }}" class="inline-flex rounded-md border border-[#15191f]/20 bg-white/70 px-2 py-1 text-[10px] font-semibold uppercase tracking-[0.12em] text-[#15191f]/80 transition hover:bg-white">
                        New Tenant
                    </a>
                    <a href="/api/admin/tenants" class="inline-flex rounded-md border border-[#15191f]/20 bg-white/70 px-2 py-1 text-[10px] font-semibold uppercase tracking-[0.12em] text-[#15191f]/80 transition hover:bg-white">
                        Open API
                    </a>
                </div>
            </div>
        </div>
    </header>

    {{-- Metrics Cards --}}
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

    {{-- Status and Activity --}}
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
                    <p class="mt-1 text-sm text-[#15191f]/65">Latest tenant role-template actions.</p>
                </div>
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
@endsection
