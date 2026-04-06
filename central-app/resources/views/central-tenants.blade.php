@extends('layouts.central')

@section('title', 'Tenants - Central App')

@section('content')
    @php
        $defaultTenantPort = isset($defaultTenantDomainPort) && is_numeric($defaultTenantDomainPort)
            ? (int) $defaultTenantDomainPort
            : 80;
    @endphp

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
                        id="tenant_name"
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
                        id="tenant_domain"
                        name="domain"
                        value="{{ old('domain') }}"
                        data-default-port="{{ $defaultTenantPort }}"
                        required
                        class="w-full rounded-lg border border-[#15191f]/25 bg-[#fdfaf3] px-3 py-2 text-sm outline-none transition focus:border-[#2f4254]"
                        placeholder="northwind.localhost:{{ $defaultTenantPort }}"
                    >
                </label>

                <label class="block">
                    <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.12em] text-[#15191f]/65">Database Name</span>
                    <input
                        id="database_name"
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
                    <select
                        name="plan_code"
                        class="w-full rounded-lg border border-[#15191f]/25 bg-[#fdfaf3] px-3 py-2 text-sm outline-none transition focus:border-[#2f4254]"
                    >
                        <option value="starter" @selected(old('plan_code', 'starter') === 'starter')>Starter</option>
                        <option value="growth" @selected(old('plan_code') === 'growth')>Growth</option>
                        <option value="enterprise" @selected(old('plan_code') === 'enterprise')>Enterprise</option>
                    </select>
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
                $tenantLoginUrl = static function (string $domain) use ($defaultTenantPort): string {
                    $base = preg_match('/\Ahttps?:\/\//i', $domain) === 1 ? $domain : "http://{$domain}";
                    $parts = parse_url($base);

                    if (! is_array($parts)) {
                        return rtrim($base, '/') . '/auth/tenant/login';
                    }

                    $host = strtolower((string) ($parts['host'] ?? ''));

                    if ($host === '') {
                        return rtrim($base, '/') . '/auth/tenant/login';
                    }

                    $isLoopback = in_array(trim($host, '[]'), ['localhost', '127.0.0.1', '::1'], true);
                    $isLocalEquivalent = $isLoopback || str_ends_with($host, '.localhost');
                    $port = isset($parts['port']) ? (int) $parts['port'] : null;

                    if ($isLocalEquivalent && ($port === null || $port === 8080) && $defaultTenantPort > 0) {
                        $port = $defaultTenantPort;
                    }

                    $scheme = (string) ($parts['scheme'] ?? 'http');
                    $authorityHost = str_contains($host, ':') ? '['.$host.']' : $host;
                    $authority = $port === null ? $authorityHost : sprintf('%s:%d', $authorityHost, $port);

                    return sprintf('%s://%s/auth/tenant/login', $scheme, $authority);
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
                        <th class="pb-2 pr-3">Activation</th>
                        <th class="pb-2 pr-3">Status</th>
                        <th class="pb-2 pr-3">Created</th>
                        <th class="pb-2 pr-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($tenants as $tenant)
                        @php
                            $formId = 'tenant-update-'.(string) $tenant->id;
                        @endphp
                        <tr class="border-b border-[#15191f]/10">
                            <td class="py-3 pr-3 font-medium">{{ $tenant->name }}</td>
                            <td class="py-3 pr-3 text-[#15191f]/70">{{ $tenant->domain }}</td>
                            <td class="py-3 pr-3 font-mono text-xs text-[#15191f]/60">{{ $tenant->database_name }}</td>
                            <td class="py-3 pr-3">
                                @php
                                    $planOptions = ['starter', 'growth', 'enterprise'];
                                    $currentPlan = (string) ($tenant->plan_code ?? 'starter');

                                    if (! in_array($currentPlan, $planOptions, true)) {
                                        array_unshift($planOptions, $currentPlan);
                                    }
                                @endphp
                                <select
                                    name="plan_code"
                                    form="{{ $formId }}"
                                    class="w-full rounded-lg border border-[#15191f]/20 bg-[#fdfaf3] px-2 py-1.5 text-xs font-semibold uppercase tracking-[0.06em] text-[#15191f]/80 outline-none focus:border-[#2f4254]"
                                >
                                    @foreach ($planOptions as $option)
                                        <option value="{{ $option }}" @selected($currentPlan === $option)>{{ $option }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="py-3 pr-3">
                                <select
                                    name="is_active"
                                    form="{{ $formId }}"
                                    class="w-full rounded-lg border border-[#15191f]/20 bg-[#fdfaf3] px-2 py-1.5 text-xs font-semibold uppercase tracking-[0.06em] text-[#15191f]/80 outline-none focus:border-[#2f4254]"
                                >
                                    <option value="1" @selected((bool) ($tenant->is_active ?? true))>Active</option>
                                    <option value="0" @selected(! (bool) ($tenant->is_active ?? true))>Deactivated</option>
                                </select>
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
                            <td class="py-3 pr-3 text-right">
                                <form id="{{ $formId }}" method="POST" action="{{ route('central.tenants.update', $tenant) }}" class="inline">
                                    @csrf
                                    @method('PATCH')
                                </form>
                                <button
                                    type="submit"
                                    form="{{ $formId }}"
                                    class="rounded-md border border-[#15191f]/20 bg-[#f6f2e8] px-3 py-1.5 text-[10px] font-semibold uppercase tracking-[0.12em] text-[#15191f] transition hover:bg-white"
                                >
                                    Save
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-8 text-center text-[#15191f]/60">
                                No tenants registered yet. Use the form above to create one.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </article>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const nameInput = document.getElementById('tenant_name');
        const domainInput = document.getElementById('tenant_domain');
        const dbInput = document.getElementById('database_name');

        if (nameInput && domainInput && dbInput) {
            const defaultTenantPort = Number(domainInput.dataset.defaultPort || '80');

            nameInput.addEventListener('input', function () {
                const nameValue = this.value;
                
                // Only auto-fill if the user hasn't manually edited the other fields, 
                // or keep it simple and just overwrite if we want it to be automatic.
                // We'll do a simple overwrite for ease.
                
                // Create a slug from the name: lowercased, alphanumeric only
                const slug = nameValue.toLowerCase().replace(/[^a-z0-9]/g, '');
                
                if (slug) {
                    domainInput.value = `${slug}.localhost:${defaultTenantPort}`;
                    dbInput.value = `tenant_${slug}`;
                } else {
                    domainInput.value = '';
                    dbInput.value = '';
                }
            });
        }
    });
</script>
@endpush
