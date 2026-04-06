@extends('layouts.central')

@section('title', 'Tenants - Central App')

@section('content')
    {{-- Page Header --}}
    <header class="rounded-2xl border border-[#15191f]/20 bg-[#f6f2e8]/80 p-6 shadow-[0_10px_40px_rgba(21,25,31,0.08)] backdrop-blur-sm">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <h1 class="display text-3xl sm:text-4xl">Tenants</h1>
                <p class="mt-2 max-w-2xl text-sm text-[#15191f]/70">
                    Provision and manage tenant instances with dedicated databases.
                </p>
            </div>
            <div class="text-sm text-[#15191f]/70">
                <span class="font-semibold">{{ $tenants->count() }}</span> tenant(s) registered
            </div>
        </div>
    </header>

    <div class="mt-6 grid gap-6 xl:grid-cols-[1.2fr_1fr]">
        {{-- Create Tenant Form --}}
        <article class="rounded-2xl border border-[#15191f]/20 bg-white/75 p-6 shadow-sm">
            <h2 class="display text-2xl">Create Tenant</h2>
            <p class="mt-1 text-sm text-[#15191f]/65">Creating a tenant provisions both the tenant record and its dedicated database.</p>

            <form method="POST" action="{{ route('tenants.create') }}" class="mt-5 grid gap-4 sm:grid-cols-2">
                @csrf

                <label class="block">
                    <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.12em] text-[#15191f]/65">Tenant Name</span>
                    <input
                        name="name"
                        value="{{ old('name') }}"
                        required
                        class="w-full rounded-lg border border-[#15191f]/25 bg-[#fdfaf3] px-3 py-2 text-sm outline-none transition focus:border-[#2f4254]"
                        placeholder="Northwind Catering"
                    >
                </label>

                <label class="block">
                    <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.12em] text-[#15191f]/65">Tenant Domain</span>
                    <input
                        name="domain"
                        value="{{ old('domain') }}"
                        required
                        class="w-full rounded-lg border border-[#15191f]/25 bg-[#fdfaf3] px-3 py-2 text-sm outline-none transition focus:border-[#2f4254]"
                        placeholder="northwind.localhost:8080"
                    >
                </label>

                <label class="block">
                    <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.12em] text-[#15191f]/65">Database Name</span>
                    <input
                        name="database_name"
                        value="{{ old('database_name') }}"
                        required
                        pattern="[a-zA-Z0-9_]+"
                        class="w-full rounded-lg border border-[#15191f]/25 bg-[#fdfaf3] px-3 py-2 text-sm outline-none transition focus:border-[#2f4254]"
                        placeholder="tenant_northwind"
                    >
                </label>

                <label class="block">
                    <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.12em] text-[#15191f]/65">Plan Code</span>
                    <input
                        name="plan_code"
                        value="{{ old('plan_code', 'starter') }}"
                        class="w-full rounded-lg border border-[#15191f]/25 bg-[#fdfaf3] px-3 py-2 text-sm outline-none transition focus:border-[#2f4254]"
                        placeholder="starter"
                    >
                </label>

                <label class="block sm:col-span-2">
                    <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.12em] text-[#15191f]/65">Plan Entitlements</span>
                    <input
                        name="plan_entitlements"
                        value="{{ old('plan_entitlements', 'starter') }}"
                        class="w-full rounded-lg border border-[#15191f]/25 bg-[#fdfaf3] px-3 py-2 text-sm outline-none transition focus:border-[#2f4254]"
                        placeholder="starter,analytics"
                    >
                </label>

                @if ($errors->any())
                    <div class="sm:col-span-2 rounded-lg border border-[#d14b38]/40 bg-[#d14b38]/10 px-3 py-2 text-sm text-[#7a2e23]">
                        {{ $errors->first() }}
                    </div>
                @endif

                <div class="sm:col-span-2 flex items-center justify-between gap-3">
                    <p class="text-xs text-[#15191f]/60">Provisioning may take a few seconds.</p>
                    <button
                        type="submit"
                        class="rounded-lg border border-[#15191f]/25 bg-[#15191f] px-4 py-2 text-xs font-semibold uppercase tracking-[0.12em] text-[#f6f2e8] transition hover:bg-[#2f4254]"
                    >
                        Create Tenant + Database
                    </button>
                </div>
            </form>
        </article>

        {{-- Tenant App Preview --}}
        <article class="rounded-2xl border border-[#15191f]/20 bg-white/75 p-6 shadow-sm">
            <h2 class="display text-2xl">Tenant App Preview</h2>
            <p class="mt-1 text-sm text-[#15191f]/65">Access the tenant-facing UI shell.</p>

            @php
                $latestTenant = $tenants->first();
                $tenantLoginUrl = static function (string $domain): string {
                    $base = preg_match('/\Ahttps?:\/\//i', $domain) === 1 ? $domain : "http://{$domain}";
                    return rtrim($base, '/') . '/auth/tenant/login';
                };
            @endphp

            @if ($latestTenant)
                <a
                    href="{{ $tenantLoginUrl((string) $latestTenant->domain) }}"
                    class="mt-4 inline-flex rounded-lg border border-[#15191f]/25 bg-[#f6f2e8] px-3 py-2 text-xs font-semibold uppercase tracking-[0.12em] text-[#15191f] transition hover:bg-[#fff]"
                >
                    Open Tenant App
                </a>
            @endif

            <div class="mt-5 max-h-[300px] space-y-2 overflow-y-auto">
                @forelse ($tenants as $tenant)
                    <div class="flex items-center justify-between rounded-lg border border-[#15191f]/15 bg-[#fdfaf3] px-3 py-2 text-sm">
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold truncate">{{ $tenant->name }}</p>
                            <p class="text-xs text-[#15191f]/60 truncate">{{ $tenant->domain }} · {{ $tenant->database_name }}</p>
                        </div>
                        <a
                            href="{{ $tenantLoginUrl((string) $tenant->domain) }}"
                            class="ml-3 shrink-0 rounded-md border border-[#15191f]/20 px-2 py-1 text-[10px] font-semibold uppercase tracking-[0.12em] text-[#15191f]/80 transition hover:bg-white"
                        >
                            Preview
                        </a>
                    </div>
                @empty
                    <p class="rounded-lg border border-dashed border-[#15191f]/20 bg-[#fdfaf3] px-3 py-4 text-sm text-[#15191f]/65">
                        No tenants yet. Create your first tenant from the form.
                    </p>
                @endforelse
            </div>
        </article>
    </div>

    {{-- All Tenants Table --}}
    <article class="mt-6 rounded-2xl border border-[#15191f]/20 bg-white/70 p-6 shadow-sm">
        <h2 class="display text-2xl">All Tenants</h2>
        <p class="mt-1 text-sm text-[#15191f]/65">Complete list of registered tenants and their status.</p>

        <div class="mt-4 overflow-x-auto">
            <table class="w-full min-w-[600px] text-sm">
                <thead>
                    <tr class="border-b border-[#15191f]/15 text-left text-xs uppercase tracking-[0.1em] text-[#15191f]/60">
                        <th class="pb-2 pr-3">Name</th>
                        <th class="pb-2 pr-3">Domain</th>
                        <th class="pb-2 pr-3">Database</th>
                        <th class="pb-2 pr-3">Plan</th>
                        <th class="pb-2 pr-3">Status</th>
                        <th class="pb-2 pr-3">Created</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($tenants as $tenant)
                        <tr class="border-b border-[#15191f]/10">
                            <td class="py-3 pr-3 font-medium">{{ $tenant->name }}</td>
                            <td class="py-3 pr-3 text-[#15191f]/70">{{ $tenant->domain }}</td>
                            <td class="py-3 pr-3 font-mono text-xs text-[#15191f]/60">{{ $tenant->database_name }}</td>
                            <td class="py-3 pr-3">
                                <span class="rounded-full border border-[#15191f]/15 bg-[#f6f2e8] px-2 py-0.5 text-xs">
                                    {{ $tenant->plan_code ?? 'starter' }}
                                </span>
                            </td>
                            <td class="py-3 pr-3">
                                @php
                                    $statusColor = match ($tenant->provisioning_status ?? 'ready') {
                                        'ready' => 'bg-[#2a8573]/15 text-[#1a5449] border-[#2a8573]/30',
                                        'failed' => 'bg-[#d14b38]/15 text-[#7a2e23] border-[#d14b38]/30',
                                        'provisioning' => 'bg-[#d89a2a]/15 text-[#7a5c1a] border-[#d89a2a]/30',
                                        default => 'bg-[#2f4254]/15 text-[#2f4254] border-[#2f4254]/30',
                                    };
                                @endphp
                                <span class="rounded-full border px-2 py-0.5 text-xs font-semibold uppercase {{ $statusColor }}">
                                    {{ $tenant->provisioning_status ?? 'ready' }}
                                </span>
                            </td>
                            <td class="py-3 pr-3 text-xs text-[#15191f]/60">
                                {{ $tenant->created_at?->format('M j, Y') ?? '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-8 text-center text-[#15191f]/60">
                                No tenants registered yet. Use the form above to create one.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </article>
@endsection
