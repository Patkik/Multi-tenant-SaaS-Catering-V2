@extends('layouts.tenant-app')

@section('title', 'Settings')

@section('content')
    <!-- Page Header -->
    <div class="mb-6">
        <h1 class="display-font text-3xl lg:text-4xl">Settings</h1>
        <p class="mt-1 text-sm text-[var(--muted)]">
            Manage your organization's settings and preferences.
        </p>
    </div>

    <div class="grid gap-6 lg:grid-cols-[250px_1fr]">
        <!-- Settings Navigation -->
        <nav class="tile p-4 h-fit">
            <ul class="space-y-1">
                @foreach ([
                    ['id' => 'general', 'name' => 'General', 'icon' => '⚙️'],
                    ['id' => 'notifications', 'name' => 'Notifications', 'icon' => '🔔'],
                    ['id' => 'security', 'name' => 'Security', 'icon' => '🔒'],
                    ['id' => 'billing', 'name' => 'Billing', 'icon' => '💳'],
                    ['id' => 'integrations', 'name' => 'Integrations', 'icon' => '🔗'],
                ] as $item)
                    <li>
                        <a href="#{{ $item['id'] }}" class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm {{ $loop->first ? 'bg-[var(--brand-light)] text-[var(--brand)] font-medium' : 'text-[var(--muted)] hover:bg-gray-100' }}">
                            {{ $item['icon'] }} {{ $item['name'] }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </nav>

        <!-- Settings Content -->
        <div class="space-y-6">
            <!-- General Settings -->
            <section id="general" class="tile p-6">
                <h2 class="font-semibold text-lg">General Settings</h2>
                <p class="text-sm text-[var(--muted)]">Basic organization information</p>

                <div class="mt-6 space-y-4">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-[0.12em] text-[var(--muted)]">Organization Name</label>
                        <input type="text" value="{{ $tenant?->name ?? 'CaterFlow Demo' }}" class="mt-1 w-full max-w-md rounded-lg border border-[var(--line)] px-3 py-2 text-sm outline-none focus:border-[var(--brand)]" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-[0.12em] text-[var(--muted)]">Business Email</label>
                        <input type="email" value="contact@caterflow.com" class="mt-1 w-full max-w-md rounded-lg border border-[var(--line)] px-3 py-2 text-sm outline-none focus:border-[var(--brand)]" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-[0.12em] text-[var(--muted)]">Time Zone</label>
                        <select class="mt-1 w-full max-w-md rounded-lg border border-[var(--line)] px-3 py-2 text-sm outline-none focus:border-[var(--brand)]">
                            <option>America/New_York (EST)</option>
                            <option>America/Los_Angeles (PST)</option>
                            <option>Europe/London (GMT)</option>
                            <option>Asia/Manila (PHT)</option>
                        </select>
                    </div>
                </div>

                <button class="mt-6 rounded-lg bg-[var(--brand)] px-4 py-2 text-sm font-semibold text-white hover:bg-[var(--brand-deep)]">
                    Save Changes
                </button>
            </section>

            <!-- Notification Settings -->
            <section id="notifications" class="tile p-6">
                <h2 class="font-semibold text-lg">Notification Preferences</h2>
                <p class="text-sm text-[var(--muted)]">Configure how you receive alerts</p>

                <div class="mt-6 space-y-4">
                    @foreach ([
                        ['name' => 'New Orders', 'description' => 'Get notified when new orders are placed'],
                        ['name' => 'Order Status', 'description' => 'Updates when order status changes'],
                        ['name' => 'Payment Alerts', 'description' => 'Payment received and failed notifications'],
                        ['name' => 'Daily Summary', 'description' => 'End of day performance summary'],
                    ] as $notif)
                        <div class="flex items-center justify-between rounded-lg border border-[var(--line)] p-4">
                            <div>
                                <p class="font-medium">{{ $notif['name'] }}</p>
                                <p class="text-sm text-[var(--muted)]">{{ $notif['description'] }}</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" class="sr-only peer" checked>
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-[var(--brand)]"></div>
                            </label>
                        </div>
                    @endforeach
                </div>
            </section>

            <!-- Security Settings -->
            <section id="security" class="tile p-6">
                <h2 class="font-semibold text-lg">Security</h2>
                <p class="text-sm text-[var(--muted)]">Manage security settings</p>

                <div class="mt-6 space-y-4">
                    <div class="flex items-center justify-between rounded-lg border border-[var(--line)] p-4">
                        <div>
                            <p class="font-medium">Two-Factor Authentication</p>
                            <p class="text-sm text-[var(--muted)]">Add an extra layer of security</p>
                        </div>
                        <button class="rounded-lg bg-[var(--brand-light)] px-4 py-2 text-sm font-medium text-[var(--brand)]">
                            Enable
                        </button>
                    </div>
                    <div class="flex items-center justify-between rounded-lg border border-[var(--line)] p-4">
                        <div>
                            <p class="font-medium">Session Timeout</p>
                            <p class="text-sm text-[var(--muted)]">Auto logout after inactivity</p>
                        </div>
                        <select class="rounded-lg border border-[var(--line)] px-3 py-2 text-sm">
                            <option>15 minutes</option>
                            <option>30 minutes</option>
                            <option selected>1 hour</option>
                            <option>4 hours</option>
                        </select>
                    </div>
                </div>
            </section>
        </div>
    </div>
@endsection
