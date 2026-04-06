@extends('layouts.tenant-app')

@section('title', 'Pending Approvals')

@section('content')
    @php
        $pendingApprovals = [
            [
                'id' => 101,
                'name' => 'Alex Garcia',
                'email' => 'alex.garcia@example.com',
                'phone' => '+1 (555) 123-4567',
                'role' => 'admin',
                'requested_at' => '2 hours ago',
                'requested_date' => '2026-04-06 07:30:00',
                'message' => 'I need admin access to manage the new branch operations.',
            ],
            [
                'id' => 102,
                'name' => 'Emily Chen',
                'email' => 'emily.chen@example.com',
                'phone' => '+1 (555) 987-6543',
                'role' => 'admin',
                'requested_at' => '1 day ago',
                'requested_date' => '2026-04-05 14:15:00',
                'message' => 'Requesting admin privileges for system configuration and user setup.',
            ],
        ];

        $recentlyProcessed = [
            ['name' => 'David Kim', 'email' => 'david@example.com', 'role' => 'admin', 'status' => 'approved', 'processed_at' => '3 days ago', 'processed_by' => 'John Doe'],
            ['name' => 'Lisa Wang', 'email' => 'lisa@example.com', 'role' => 'admin', 'status' => 'denied', 'processed_at' => '5 days ago', 'processed_by' => 'John Doe', 'reason' => 'Insufficient justification'],
        ];
    @endphp

    <!-- Page Header -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="display-font text-3xl lg:text-4xl">Pending Approvals</h1>
            <p class="mt-1 text-sm text-[var(--muted)]">
                Review and process admin access requests from team members.
            </p>
        </div>
        <div class="flex items-center gap-3">
            <span class="badge badge-warning px-3 py-1.5 text-sm">
                {{ count($pendingApprovals) }} pending
            </span>
        </div>
    </div>

    <!-- Security Notice -->
    <div class="mt-6 rounded-2xl border border-purple-200 bg-purple-50 p-5">
        <div class="flex items-start gap-3">
            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-purple-100 text-purple-600">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
                </svg>
            </div>
            <div>
                <h3 class="font-semibold text-purple-800">Security Reminder</h3>
                <p class="text-sm text-purple-700">Admin users have full access to user management, system settings, and sensitive data. Only approve requests from trusted team members with legitimate business needs.</p>
            </div>
        </div>
    </div>

    <!-- Pending Requests -->
    <section class="mt-6">
        <h2 class="font-semibold text-lg mb-4">Pending Requests ({{ count($pendingApprovals) }})</h2>
        
        @if (count($pendingApprovals) === 0)
            <div class="tile p-8 text-center">
                <div class="flex justify-center mb-4">
                    <div class="flex h-16 w-16 items-center justify-center rounded-full bg-green-100 text-green-600">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                </div>
                <h3 class="font-semibold text-lg">All caught up!</h3>
                <p class="text-sm text-[var(--muted)]">No pending approval requests at this time.</p>
            </div>
        @else
            <div class="space-y-4">
                @foreach ($pendingApprovals as $request)
                    <div class="tile p-6">
                        <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
                            <!-- User Info -->
                            <div class="flex items-start gap-4">
                                <div class="flex h-14 w-14 items-center justify-center rounded-xl bg-amber-100 text-xl font-bold text-amber-700">
                                    {{ strtoupper(substr($request['name'], 0, 1)) }}
                                </div>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <h3 class="font-semibold text-lg">{{ $request['name'] }}</h3>
                                        <span class="badge bg-purple-100 text-purple-700">Requesting Admin</span>
                                    </div>
                                    <p class="text-sm text-[var(--muted)]">{{ $request['email'] }}</p>
                                    <p class="text-sm text-[var(--muted)]">{{ $request['phone'] }}</p>
                                    
                                    @if ($request['message'])
                                        <div class="mt-3 rounded-lg bg-gray-50 p-3">
                                            <p class="text-xs font-medium text-[var(--muted)] mb-1">Request Message:</p>
                                            <p class="text-sm italic">"{{ $request['message'] }}"</p>
                                        </div>
                                    @endif
                                    
                                    <p class="mt-3 text-xs text-[var(--muted)]">
                                        Requested {{ $request['requested_at'] }}
                                    </p>
                                </div>
                            </div>

                            <!-- Actions -->
                            <div class="flex flex-col gap-2 lg:items-end">
                                <div class="flex gap-2">
                                    <button 
                                        onclick="approveRequest({{ $request['id'] }})"
                                        class="rounded-lg bg-[var(--brand)] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[var(--brand-deep)] transition"
                                    >
                                        Approve
                                    </button>
                                    <button 
                                        onclick="showDenyModal({{ $request['id'] }}, '{{ $request['name'] }}')"
                                        class="rounded-lg border border-red-300 px-5 py-2.5 text-sm font-semibold text-red-600 hover:bg-red-50 transition"
                                    >
                                        Deny
                                    </button>
                                </div>
                                <button class="text-sm text-[var(--muted)] hover:text-[var(--ink)]">
                                    View Full Profile
                                </button>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    <!-- Recently Processed -->
    <section class="mt-8">
        <h2 class="font-semibold text-lg mb-4">Recently Processed</h2>
        
        <div class="tile overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wider text-[var(--muted)]">
                    <tr>
                        <th class="px-5 py-3">User</th>
                        <th class="px-5 py-3">Request</th>
                        <th class="px-5 py-3">Decision</th>
                        <th class="px-5 py-3">Processed By</th>
                        <th class="px-5 py-3">Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[var(--line)]">
                    @foreach ($recentlyProcessed as $processed)
                        <tr class="hover:bg-gray-50">
                            <td class="px-5 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-9 w-9 items-center justify-center rounded-full bg-gray-100 text-sm font-bold text-[var(--muted)]">
                                        {{ strtoupper(substr($processed['name'], 0, 1)) }}
                                    </div>
                                    <div>
                                        <p class="font-medium text-sm">{{ $processed['name'] }}</p>
                                        <p class="text-xs text-[var(--muted)]">{{ $processed['email'] }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-4">
                                <span class="badge bg-purple-100 text-purple-700">{{ ucfirst($processed['role']) }}</span>
                            </td>
                            <td class="px-5 py-4">
                                @if ($processed['status'] === 'approved')
                                    <span class="inline-flex items-center gap-1.5 text-green-600">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                        </svg>
                                        Approved
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 text-red-600">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                        Denied
                                    </span>
                                    @if (isset($processed['reason']))
                                        <p class="text-xs text-[var(--muted)] mt-0.5">{{ $processed['reason'] }}</p>
                                    @endif
                                @endif
                            </td>
                            <td class="px-5 py-4 text-sm text-[var(--muted)]">
                                {{ $processed['processed_by'] }}
                            </td>
                            <td class="px-5 py-4 text-sm text-[var(--muted)]">
                                {{ $processed['processed_at'] }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <!-- Deny Modal -->
    <div id="denyModal" class="fixed inset-0 z-50 items-center justify-center bg-black/50 backdrop-blur-sm" style="display: none;" onclick="if(event.target === this) this.style.display='none'">
        <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl" onclick="event.stopPropagation()">
            <div class="flex items-center gap-3 mb-4">
                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-red-100 text-red-600">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
                <div>
                    <h2 class="font-semibold text-lg">Deny Request</h2>
                    <p class="text-sm text-[var(--muted)]" id="denyUserName">for User Name</p>
                </div>
            </div>

            <form id="denyForm" class="space-y-4">
                <input type="hidden" id="denyRequestId" name="request_id" />
                
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-[0.12em] text-[var(--muted)]">Reason for Denial</label>
                    <select class="mt-1 w-full rounded-lg border border-[var(--line)] px-3 py-2 text-sm outline-none focus:border-[var(--brand)]" required>
                        <option value="">Select a reason...</option>
                        <option value="insufficient_justification">Insufficient justification</option>
                        <option value="role_not_needed">Admin role not needed for their tasks</option>
                        <option value="not_authorized">Not authorized by management</option>
                        <option value="security_concern">Security concern</option>
                        <option value="other">Other (specify below)</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-[0.12em] text-[var(--muted)]">Additional Comments</label>
                    <textarea class="mt-1 w-full rounded-lg border border-[var(--line)] px-3 py-2 text-sm outline-none focus:border-[var(--brand)]" rows="3" placeholder="Optional: Add more context for the user"></textarea>
                </div>

                <div class="rounded-lg bg-amber-50 border border-amber-200 p-3">
                    <p class="text-xs text-amber-700">
                        <strong>Note:</strong> The user will be notified of this decision via email. They can submit a new request with additional justification if needed.
                    </p>
                </div>

                <div class="flex gap-3 pt-2">
                    <button type="button" onclick="document.getElementById('denyModal').style.display='none'" class="flex-1 rounded-lg border border-[var(--line)] py-2.5 text-sm font-semibold hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit" class="flex-1 rounded-lg bg-red-600 py-2.5 text-sm font-semibold text-white hover:bg-red-700">
                        Confirm Denial
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Approve Confirmation Modal -->
    <div id="approveModal" class="fixed inset-0 z-50 items-center justify-center bg-black/50 backdrop-blur-sm" style="display: none;" onclick="if(event.target === this) this.style.display='none'">
        <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl" onclick="event.stopPropagation()">
            <div class="flex items-center gap-3 mb-4">
                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-green-100 text-green-600">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                </div>
                <div>
                    <h2 class="font-semibold text-lg">Confirm Approval</h2>
                    <p class="text-sm text-[var(--muted)]">Grant admin access</p>
                </div>
            </div>

            <div class="rounded-lg bg-amber-50 border border-amber-200 p-4 mb-4">
                <p class="text-sm text-amber-800">
                    <strong>Warning:</strong> Admin users have full access to:
                </p>
                <ul class="mt-2 text-sm text-amber-700 space-y-1">
                    <li>• User management and role assignments</li>
                    <li>• System settings and configurations</li>
                    <li>• All operational data and reports</li>
                    <li>• Billing and payment information</li>
                </ul>
            </div>

            <form id="approveForm">
                <input type="hidden" id="approveRequestId" name="request_id" />
                
                <div class="flex gap-3">
                    <button type="button" onclick="document.getElementById('approveModal').style.display='none'" class="flex-1 rounded-lg border border-[var(--line)] py-2.5 text-sm font-semibold hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit" class="flex-1 rounded-lg bg-[var(--brand)] py-2.5 text-sm font-semibold text-white hover:bg-[var(--brand-deep)]">
                        Confirm Approval
                    </button>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
    <script>
        function showDenyModal(requestId, userName) {
            document.getElementById('denyRequestId').value = requestId;
            document.getElementById('denyUserName').textContent = 'for ' + userName;
            document.getElementById('denyModal').style.display = 'flex';
        }

        function approveRequest(requestId) {
            document.getElementById('approveRequestId').value = requestId;
            document.getElementById('approveModal').style.display = 'flex';
        }

        document.getElementById('approveForm').addEventListener('submit', function(e) {
            e.preventDefault();
            // In production, this would make an API call
            alert('Request approved! User will be notified.');
            document.getElementById('approveModal').style.display = 'none';
            location.reload();
        });

        document.getElementById('denyForm').addEventListener('submit', function(e) {
            e.preventDefault();
            // In production, this would make an API call
            alert('Request denied. User will be notified.');
            document.getElementById('denyModal').style.display = 'none';
            location.reload();
        });
    </script>
    @endpush
@endsection
