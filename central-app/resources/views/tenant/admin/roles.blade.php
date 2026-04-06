@extends('layouts.tenant-app')

@section('title', 'Roles & Permissions')

@section('content')
    @php
        $roles = [
            [
                'id' => 1,
                'name' => 'Admin',
                'description' => 'Full access to all features including user management and settings',
                'color' => 'purple',
                'users_count' => 2,
                'is_system' => true,
                'permissions' => [
                    'dashboard' => true,
                    'orders.view' => true,
                    'orders.create' => true,
                    'orders.edit' => true,
                    'orders.delete' => true,
                    'kitchen.view' => true,
                    'kitchen.manage' => true,
                    'calendar.view' => true,
                    'calendar.manage' => true,
                    'payments.view' => true,
                    'payments.process' => true,
                    'payments.refund' => true,
                    'analytics.view' => true,
                    'reports.view' => true,
                    'reports.export' => true,
                    'users.view' => true,
                    'users.manage' => true,
                    'roles.manage' => true,
                    'settings.manage' => true,
                ],
            ],
            [
                'id' => 2,
                'name' => 'Manager',
                'description' => 'Manage operations, staff schedules, and view analytics',
                'color' => 'blue',
                'users_count' => 3,
                'is_system' => true,
                'permissions' => [
                    'dashboard' => true,
                    'orders.view' => true,
                    'orders.create' => true,
                    'orders.edit' => true,
                    'orders.delete' => false,
                    'kitchen.view' => true,
                    'kitchen.manage' => true,
                    'calendar.view' => true,
                    'calendar.manage' => true,
                    'payments.view' => true,
                    'payments.process' => true,
                    'payments.refund' => false,
                    'analytics.view' => true,
                    'reports.view' => true,
                    'reports.export' => true,
                    'users.view' => true,
                    'users.manage' => false,
                    'roles.manage' => false,
                    'settings.manage' => false,
                ],
            ],
            [
                'id' => 3,
                'name' => 'Staff',
                'description' => 'Handle day-to-day operations and order fulfillment',
                'color' => 'green',
                'users_count' => 8,
                'is_system' => true,
                'permissions' => [
                    'dashboard' => true,
                    'orders.view' => true,
                    'orders.create' => true,
                    'orders.edit' => false,
                    'orders.delete' => false,
                    'kitchen.view' => true,
                    'kitchen.manage' => false,
                    'calendar.view' => true,
                    'calendar.manage' => false,
                    'payments.view' => false,
                    'payments.process' => false,
                    'payments.refund' => false,
                    'analytics.view' => false,
                    'reports.view' => false,
                    'reports.export' => false,
                    'users.view' => false,
                    'users.manage' => false,
                    'roles.manage' => false,
                    'settings.manage' => false,
                ],
            ],
            [
                'id' => 4,
                'name' => 'Cashier',
                'description' => 'Process payments and manage transactions',
                'color' => 'amber',
                'users_count' => 4,
                'is_system' => true,
                'permissions' => [
                    'dashboard' => true,
                    'orders.view' => true,
                    'orders.create' => false,
                    'orders.edit' => false,
                    'orders.delete' => false,
                    'kitchen.view' => false,
                    'kitchen.manage' => false,
                    'calendar.view' => false,
                    'calendar.manage' => false,
                    'payments.view' => true,
                    'payments.process' => true,
                    'payments.refund' => false,
                    'analytics.view' => false,
                    'reports.view' => false,
                    'reports.export' => false,
                    'users.view' => false,
                    'users.manage' => false,
                    'roles.manage' => false,
                    'settings.manage' => false,
                ],
            ],
        ];

        $permissionGroups = [
            'General' => ['dashboard'],
            'Orders' => ['orders.view', 'orders.create', 'orders.edit', 'orders.delete'],
            'Kitchen' => ['kitchen.view', 'kitchen.manage'],
            'Calendar' => ['calendar.view', 'calendar.manage'],
            'Payments' => ['payments.view', 'payments.process', 'payments.refund'],
            'Analytics & Reports' => ['analytics.view', 'reports.view', 'reports.export'],
            'Administration' => ['users.view', 'users.manage', 'roles.manage', 'settings.manage'],
        ];

        $permissionLabels = [
            'dashboard' => 'Access Dashboard',
            'orders.view' => 'View Orders',
            'orders.create' => 'Create Orders',
            'orders.edit' => 'Edit Orders',
            'orders.delete' => 'Delete Orders',
            'kitchen.view' => 'View Kitchen Board',
            'kitchen.manage' => 'Manage Kitchen',
            'calendar.view' => 'View Calendar',
            'calendar.manage' => 'Manage Events',
            'payments.view' => 'View Payments',
            'payments.process' => 'Process Payments',
            'payments.refund' => 'Issue Refunds',
            'analytics.view' => 'View Analytics',
            'reports.view' => 'View Reports',
            'reports.export' => 'Export Reports',
            'users.view' => 'View Users',
            'users.manage' => 'Manage Users',
            'roles.manage' => 'Manage Roles',
            'settings.manage' => 'Manage Settings',
        ];

        $colorClasses = [
            'purple' => ['bg' => 'bg-purple-100', 'text' => 'text-purple-700', 'border' => 'border-purple-200'],
            'blue' => ['bg' => 'bg-blue-100', 'text' => 'text-blue-700', 'border' => 'border-blue-200'],
            'green' => ['bg' => 'bg-green-100', 'text' => 'text-green-700', 'border' => 'border-green-200'],
            'amber' => ['bg' => 'bg-amber-100', 'text' => 'text-amber-700', 'border' => 'border-amber-200'],
        ];
    @endphp

    <!-- Page Header -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="display-font text-3xl lg:text-4xl">Roles & Permissions</h1>
            <p class="mt-1 text-sm text-[var(--muted)]">
                Configure access control and permissions for each role in your organization.
            </p>
        </div>
        <button 
            onclick="document.getElementById('createRoleModal').style.display='flex'"
            class="rounded-full bg-[var(--brand)] px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-[var(--brand-deep)] transition"
        >
            + Create Custom Role
        </button>
    </div>

    <!-- Roles Overview -->
    <section class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($roles as $role)
            <article class="tile p-5 hover:shadow-md transition cursor-pointer" onclick="selectRole({{ $role['id'] }})">
                <div class="flex items-start justify-between">
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl {{ $colorClasses[$role['color']]['bg'] }} {{ $colorClasses[$role['color']]['text'] }}">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
                        </svg>
                    </div>
                    @if ($role['is_system'])
                        <span class="badge badge-muted">System</span>
                    @endif
                </div>
                <h3 class="mt-4 font-semibold text-lg">{{ $role['name'] }}</h3>
                <p class="mt-1 text-sm text-[var(--muted)] line-clamp-2">{{ $role['description'] }}</p>
                <div class="mt-4 flex items-center justify-between">
                    <span class="text-sm text-[var(--muted)]">{{ $role['users_count'] }} users</span>
                    <span class="text-sm font-medium text-[var(--brand)]">{{ count(array_filter($role['permissions'])) }} permissions</span>
                </div>
            </article>
        @endforeach
    </section>

    <!-- Permission Matrix -->
    <section class="mt-8">
        <div class="tile overflow-hidden">
            <div class="flex items-center justify-between border-b border-[var(--line)] p-5">
                <div>
                    <h2 class="font-semibold text-lg">Permission Matrix</h2>
                    <p class="text-sm text-[var(--muted)]">Compare permissions across all roles</p>
                </div>
                <button class="rounded-lg border border-[var(--line)] px-4 py-2 text-sm font-medium hover:bg-gray-50">
                    Export Matrix
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="sticky left-0 bg-gray-50 px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-[var(--muted)]">Permission</th>
                            @foreach ($roles as $role)
                                <th class="px-5 py-3 text-center">
                                    <span class="badge {{ $colorClasses[$role['color']]['bg'] }} {{ $colorClasses[$role['color']]['text'] }}">
                                        {{ $role['name'] }}
                                    </span>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[var(--line)]">
                        @foreach ($permissionGroups as $groupName => $permissions)
                            <tr class="bg-gray-50/50">
                                <td colspan="{{ count($roles) + 1 }}" class="px-5 py-2">
                                    <span class="text-xs font-bold uppercase tracking-wider text-[var(--muted)]">{{ $groupName }}</span>
                                </td>
                            </tr>
                            @foreach ($permissions as $permission)
                                <tr class="hover:bg-gray-50">
                                    <td class="sticky left-0 bg-white px-5 py-3 text-sm">
                                        {{ $permissionLabels[$permission] }}
                                    </td>
                                    @foreach ($roles as $role)
                                        <td class="px-5 py-3 text-center">
                                            @if ($role['permissions'][$permission] ?? false)
                                                <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-green-100 text-green-600">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                                    </svg>
                                                </span>
                                            @else
                                                <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-gray-100 text-gray-400">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                                    </svg>
                                                </span>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <!-- Role Details Panel (shown when a role is selected) -->
    <section class="mt-8" id="roleDetailsPanel" style="display: none;">
        <div class="tile p-6">
            <div class="flex items-start justify-between">
                <div>
                    <h2 class="font-semibold text-lg" id="selectedRoleName">Admin</h2>
                    <p class="text-sm text-[var(--muted)]" id="selectedRoleDescription">Full access to all features</p>
                </div>
                <div class="flex gap-2">
                    <button class="rounded-lg border border-[var(--line)] px-4 py-2 text-sm font-medium hover:bg-gray-50">
                        Edit Role
                    </button>
                    <button class="rounded-lg border border-red-300 px-4 py-2 text-sm font-medium text-red-600 hover:bg-red-50">
                        Delete Role
                    </button>
                </div>
            </div>

            <div class="mt-6 grid gap-6 lg:grid-cols-2">
                <!-- Permissions Editor -->
                <div>
                    <h3 class="font-medium mb-4">Permissions</h3>
                    <div class="space-y-4" id="permissionsEditor">
                        @foreach ($permissionGroups as $groupName => $permissions)
                            <div class="rounded-lg border border-[var(--line)] p-4">
                                <h4 class="font-medium text-sm mb-3">{{ $groupName }}</h4>
                                <div class="space-y-2">
                                    @foreach ($permissions as $permission)
                                        <label class="flex items-center justify-between">
                                            <span class="text-sm">{{ $permissionLabels[$permission] }}</span>
                                            <input type="checkbox" class="h-4 w-4 rounded border-gray-300 text-[var(--brand)] focus:ring-[var(--brand)]" />
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- Users with this role -->
                <div>
                    <h3 class="font-medium mb-4">Users with this Role</h3>
                    <div class="space-y-2" id="roleUsers">
                        <div class="flex items-center justify-between rounded-lg border border-[var(--line)] p-3">
                            <div class="flex items-center gap-3">
                                <div class="flex h-9 w-9 items-center justify-center rounded-full bg-[var(--brand-light)] text-sm font-bold text-[var(--brand)]">J</div>
                                <div>
                                    <p class="font-medium text-sm">John Doe</p>
                                    <p class="text-xs text-[var(--muted)]">john@example.com</p>
                                </div>
                            </div>
                            <button class="text-sm text-[var(--muted)] hover:text-red-600">Remove</button>
                        </div>
                    </div>

                    <button class="mt-4 w-full rounded-lg border-2 border-dashed border-[var(--line)] py-3 text-sm text-[var(--muted)] hover:border-[var(--brand)] hover:text-[var(--brand)]">
                        + Add User to Role
                    </button>
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <button onclick="document.getElementById('roleDetailsPanel').style.display='none'" class="rounded-lg border border-[var(--line)] px-4 py-2 text-sm font-medium hover:bg-gray-50">
                    Cancel
                </button>
                <button class="rounded-lg bg-[var(--brand)] px-4 py-2 text-sm font-semibold text-white hover:bg-[var(--brand-deep)]">
                    Save Changes
                </button>
            </div>
        </div>
    </section>

    <!-- Create Role Modal -->
    <div id="createRoleModal" class="fixed inset-0 z-50 items-center justify-center bg-black/50 backdrop-blur-sm" style="display: none;" onclick="if(event.target === this) this.style.display='none'">
        <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl max-h-[90vh] overflow-y-auto" onclick="event.stopPropagation()">
            <h2 class="display-font text-2xl">Create Custom Role</h2>
            <p class="mt-1 text-sm text-[var(--muted)]">Define a new role with specific permissions.</p>

            <form class="mt-6 space-y-4">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-[0.12em] text-[var(--muted)]">Role Name</label>
                    <input type="text" class="mt-1 w-full rounded-lg border border-[var(--line)] px-3 py-2 text-sm outline-none focus:border-[var(--brand)]" placeholder="e.g., Supervisor" />
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-[0.12em] text-[var(--muted)]">Description</label>
                    <textarea class="mt-1 w-full rounded-lg border border-[var(--line)] px-3 py-2 text-sm outline-none focus:border-[var(--brand)]" rows="2" placeholder="Describe the role's responsibilities"></textarea>
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-[0.12em] text-[var(--muted)]">Base Template</label>
                    <select class="mt-1 w-full rounded-lg border border-[var(--line)] px-3 py-2 text-sm outline-none focus:border-[var(--brand)]">
                        <option value="">Start from scratch</option>
                        <option value="staff">Copy from Staff</option>
                        <option value="cashier">Copy from Cashier</option>
                        <option value="manager">Copy from Manager</option>
                    </select>
                </div>

                <div class="pt-4">
                    <label class="block text-xs font-semibold uppercase tracking-[0.12em] text-[var(--muted)] mb-3">Permissions</label>
                    <div class="max-h-60 overflow-y-auto space-y-3 rounded-lg border border-[var(--line)] p-4">
                        @foreach ($permissionGroups as $groupName => $permissions)
                            <div>
                                <p class="text-xs font-bold uppercase tracking-wider text-[var(--muted)] mb-2">{{ $groupName }}</p>
                                @foreach ($permissions as $permission)
                                    <label class="flex items-center gap-2 py-1">
                                        <input type="checkbox" class="h-4 w-4 rounded border-gray-300 text-[var(--brand)] focus:ring-[var(--brand)]" />
                                        <span class="text-sm">{{ $permissionLabels[$permission] }}</span>
                                    </label>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="flex gap-3 pt-4">
                    <button type="button" onclick="document.getElementById('createRoleModal').style.display='none'" class="flex-1 rounded-lg border border-[var(--line)] py-2.5 text-sm font-semibold hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit" class="flex-1 rounded-lg bg-[var(--brand)] py-2.5 text-sm font-semibold text-white hover:bg-[var(--brand-deep)]">
                        Create Role
                    </button>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
    <script>
        function selectRole(roleId) {
            document.getElementById('roleDetailsPanel').style.display = 'block';
            document.getElementById('roleDetailsPanel').scrollIntoView({ behavior: 'smooth' });
        }
    </script>
    @endpush
@endsection
