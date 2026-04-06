@extends('layouts.tenant-app')

@section('title', 'User Management')

@section('content')
    @php
        // Sample users data - in production, this comes from the database
        $users = [
            ['id' => 1, 'name' => 'John Doe', 'email' => 'john@example.com', 'role' => 'admin', 'status' => 'active', 'last_active' => '2 min ago'],
            ['id' => 2, 'name' => 'Jane Smith', 'email' => 'jane@example.com', 'role' => 'manager', 'status' => 'active', 'last_active' => '15 min ago'],
            ['id' => 3, 'name' => 'Mike Johnson', 'email' => 'mike@example.com', 'role' => 'staff', 'status' => 'active', 'last_active' => '1 hour ago'],
            ['id' => 4, 'name' => 'Sarah Wilson', 'email' => 'sarah@example.com', 'role' => 'cashier', 'status' => 'active', 'last_active' => '3 hours ago'],
            ['id' => 5, 'name' => 'Tom Brown', 'email' => 'tom@example.com', 'role' => 'staff', 'status' => 'inactive', 'last_active' => '2 days ago'],
        ];

        $pendingApprovals = [
            ['id' => 101, 'name' => 'Alex Garcia', 'email' => 'alex@example.com', 'role' => 'admin', 'requested_at' => '2 hours ago'],
            ['id' => 102, 'name' => 'Emily Chen', 'email' => 'emily@example.com', 'role' => 'admin', 'requested_at' => '1 day ago'],
        ];

        $roleColors = [
            'admin' => 'bg-purple-100 text-purple-700',
            'manager' => 'bg-blue-100 text-blue-700',
            'staff' => 'bg-green-100 text-green-700',
            'cashier' => 'bg-amber-100 text-amber-700',
        ];
    @endphp

    <!-- Page Header -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="display-font text-3xl lg:text-4xl">User Management</h1>
            <p class="mt-1 text-sm text-[var(--muted)]">
                Manage team members, roles, and access permissions.
            </p>
        </div>
        <button 
            onclick="document.getElementById('addUserModal').classList.remove('hidden')"
            class="rounded-full bg-[var(--brand)] px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-[var(--brand-deep)] transition"
        >
            + Add User
        </button>
    </div>

    <!-- Stats Overview -->
    <section class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <article class="tile p-5">
            <p class="text-xs font-semibold uppercase tracking-[0.12em] text-[var(--muted)]">Total Users</p>
            <p class="mt-2 text-4xl font-bold">{{ count($users) }}</p>
            <p class="mt-1 text-xs text-[var(--muted)]">Across all roles</p>
        </article>
        <article class="tile p-5">
            <p class="text-xs font-semibold uppercase tracking-[0.12em] text-[var(--muted)]">Active Now</p>
            <p class="mt-2 text-4xl font-bold text-[var(--brand)]">3</p>
            <p class="mt-1 text-xs text-[var(--muted)]">Currently online</p>
        </article>
        <article class="tile p-5">
            <p class="text-xs font-semibold uppercase tracking-[0.12em] text-[var(--muted)]">Pending Approvals</p>
            <p class="mt-2 text-4xl font-bold text-amber-600">{{ count($pendingApprovals) }}</p>
            <p class="mt-1 text-xs text-[var(--muted)]">Admin requests</p>
        </article>
        <article class="tile p-5">
            <p class="text-xs font-semibold uppercase tracking-[0.12em] text-[var(--muted)]">Roles Defined</p>
            <p class="mt-2 text-4xl font-bold">4</p>
            <p class="mt-1 text-xs text-[var(--muted)]">Admin, Manager, Staff, Cashier</p>
        </article>
    </section>

    <!-- Pending Approvals Alert -->
    @if (count($pendingApprovals) > 0)
        <section class="mt-6">
            <div class="rounded-2xl border-2 border-amber-200 bg-amber-50 p-5">
                <div class="flex items-start justify-between">
                    <div class="flex items-start gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-full bg-amber-100 text-amber-600">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="font-semibold text-amber-800">Pending Admin Approvals</h3>
                            <p class="text-sm text-amber-700">{{ count($pendingApprovals) }} user(s) are requesting admin access and need your approval.</p>
                        </div>
                    </div>
                    <a href="{{ route('tenant.admin.approvals') }}" class="rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-700 transition">
                        Review Requests
                    </a>
                </div>

                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    @foreach ($pendingApprovals as $approval)
                        <div class="flex items-center justify-between rounded-lg bg-white p-3 border border-amber-200">
                            <div class="flex items-center gap-3">
                                <div class="flex h-9 w-9 items-center justify-center rounded-full bg-amber-100 text-sm font-bold text-amber-700">
                                    {{ strtoupper(substr($approval['name'], 0, 1)) }}
                                </div>
                                <div>
                                    <p class="font-medium text-sm">{{ $approval['name'] }}</p>
                                    <p class="text-xs text-[var(--muted)]">{{ $approval['email'] }} · {{ $approval['requested_at'] }}</p>
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <button class="rounded-lg bg-[var(--brand)] px-3 py-1.5 text-xs font-semibold text-white hover:bg-[var(--brand-deep)]">Approve</button>
                                <button class="rounded-lg border border-red-300 px-3 py-1.5 text-xs font-semibold text-red-600 hover:bg-red-50">Deny</button>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    <!-- Users Table -->
    <section class="mt-6">
        <div class="tile overflow-hidden">
            <div class="flex items-center justify-between border-b border-[var(--line)] p-5">
                <h2 class="font-semibold">All Users</h2>
                <div class="flex items-center gap-3">
                    <div class="flex items-center gap-2 rounded-lg border border-[var(--line)] px-3 py-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-[var(--muted)]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        <input type="text" placeholder="Search users..." class="bg-transparent text-sm outline-none w-40" />
                    </div>
                    <select class="rounded-lg border border-[var(--line)] px-3 py-2 text-sm outline-none">
                        <option>All Roles</option>
                        <option>Admin</option>
                        <option>Manager</option>
                        <option>Staff</option>
                        <option>Cashier</option>
                    </select>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wider text-[var(--muted)]">
                        <tr>
                            <th class="px-5 py-3">User</th>
                            <th class="px-5 py-3">Role</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3">Last Active</th>
                            <th class="px-5 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[var(--line)]">
                        @foreach ($users as $user)
                            <tr class="hover:bg-gray-50">
                                <td class="px-5 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-10 w-10 items-center justify-center rounded-full bg-[var(--brand-light)] text-sm font-bold text-[var(--brand)]">
                                            {{ strtoupper(substr($user['name'], 0, 1)) }}
                                        </div>
                                        <div>
                                            <p class="font-medium">{{ $user['name'] }}</p>
                                            <p class="text-xs text-[var(--muted)]">{{ $user['email'] }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-5 py-4">
                                    <span class="badge {{ $roleColors[$user['role']] }}">
                                        {{ ucfirst($user['role']) }}
                                    </span>
                                </td>
                                <td class="px-5 py-4">
                                    <span class="inline-flex items-center gap-1.5">
                                        <span class="h-2 w-2 rounded-full {{ $user['status'] === 'active' ? 'bg-green-500' : 'bg-gray-400' }}"></span>
                                        <span class="text-sm {{ $user['status'] === 'active' ? 'text-green-700' : 'text-[var(--muted)]' }}">
                                            {{ ucfirst($user['status']) }}
                                        </span>
                                    </span>
                                </td>
                                <td class="px-5 py-4 text-sm text-[var(--muted)]">
                                    {{ $user['last_active'] }}
                                </td>
                                <td class="px-5 py-4">
                                    <div class="flex items-center justify-end gap-2">
                                        <button class="rounded-lg p-2 text-[var(--muted)] hover:bg-gray-100 hover:text-[var(--ink)]" title="Edit">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                        </button>
                                        <button class="rounded-lg p-2 text-[var(--muted)] hover:bg-gray-100 hover:text-[var(--ink)]" title="Change Role">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
                                            </svg>
                                        </button>
                                        @if ($user['role'] !== 'admin')
                                            <button class="rounded-lg p-2 text-red-400 hover:bg-red-50 hover:text-red-600" title="Remove">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
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
                <p class="text-sm text-[var(--muted)]">Showing {{ count($users) }} of {{ count($users) }} users</p>
                <div class="flex gap-2">
                    <button class="rounded-lg border border-[var(--line)] px-3 py-1.5 text-sm hover:bg-gray-50" disabled>Previous</button>
                    <button class="rounded-lg border border-[var(--line)] px-3 py-1.5 text-sm hover:bg-gray-50" disabled>Next</button>
                </div>
            </div>
        </div>
    </section>

    <!-- Add User Modal -->
    <div id="addUserModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 backdrop-blur-sm flex" style="display: none;" onclick="if(event.target === this) this.style.display='none'">
        <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl" onclick="event.stopPropagation()">
            <h2 class="display-font text-2xl">Add New User</h2>
            <p class="mt-1 text-sm text-[var(--muted)]">Invite a team member to your organization.</p>

            <form class="mt-6 space-y-4">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-[0.12em] text-[var(--muted)]">Full Name</label>
                    <input type="text" class="mt-1 w-full rounded-lg border border-[var(--line)] px-3 py-2 text-sm outline-none focus:border-[var(--brand)]" placeholder="Enter full name" />
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-[0.12em] text-[var(--muted)]">Email Address</label>
                    <input type="email" class="mt-1 w-full rounded-lg border border-[var(--line)] px-3 py-2 text-sm outline-none focus:border-[var(--brand)]" placeholder="user@example.com" />
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-[0.12em] text-[var(--muted)]">Role</label>
                    <select class="mt-1 w-full rounded-lg border border-[var(--line)] px-3 py-2 text-sm outline-none focus:border-[var(--brand)]">
                        <option value="staff">Staff</option>
                        <option value="cashier">Cashier</option>
                        <option value="manager">Manager</option>
                        <option value="admin">Admin (Requires Approval)</option>
                    </select>
                </div>

                <div class="flex gap-3 pt-4">
                    <button type="button" onclick="document.getElementById('addUserModal').style.display='none'" class="flex-1 rounded-lg border border-[var(--line)] py-2.5 text-sm font-semibold hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit" class="flex-1 rounded-lg bg-[var(--brand)] py-2.5 text-sm font-semibold text-white hover:bg-[var(--brand-deep)]">
                        Send Invitation
                    </button>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
    <script>
        document.getElementById('addUserModal').style.display = 'none';
        
        function showAddUserModal() {
            document.getElementById('addUserModal').style.display = 'flex';
        }
    </script>
    @endpush
@endsection
