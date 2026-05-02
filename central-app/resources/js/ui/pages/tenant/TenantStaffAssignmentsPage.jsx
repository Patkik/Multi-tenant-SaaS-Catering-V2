import { useMemo, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
    createTenantAssignment,
    createTenantStaff,
    deleteTenantAssignment,
    fetchTenantAssignments,
    fetchTenantEvents,
    fetchTenantStaff,
} from '../../../api/tenantApi';
import { formatNumber } from '../../../lib/formatters';

const initialStaffForm = {
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    position: '',
    is_active: true,
};

const initialAssignmentForm = {
    event_id: '',
    staff_id: '',
    assignment_role: '',
    shift_start_at: '',
    shift_end_at: '',
};

export function TenantStaffAssignmentsPage() {
    const queryClient = useQueryClient();
    const [staffForm, setStaffForm] = useState(initialStaffForm);
    const [assignmentForm, setAssignmentForm] = useState(initialAssignmentForm);

    const staffQuery = useQuery({
        queryKey: ['tenant-staff'],
        queryFn: () => fetchTenantStaff(),
        staleTime: 1000 * 30,
    });

    const eventsQuery = useQuery({
        queryKey: ['tenant-events', { status: '', month: '' }],
        queryFn: () => fetchTenantEvents(),
        staleTime: 1000 * 30,
    });

    const assignmentsQuery = useQuery({
        queryKey: ['tenant-assignments'],
        queryFn: () => fetchTenantAssignments(),
        staleTime: 1000 * 30,
    });

    const createStaffMutation = useMutation({
        mutationFn: createTenantStaff,
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['tenant-staff'] });
            setStaffForm(initialStaffForm);
        },
    });

    const createAssignmentMutation = useMutation({
        mutationFn: createTenantAssignment,
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['tenant-assignments'] });
            setAssignmentForm(initialAssignmentForm);
        },
    });

    const deleteAssignmentMutation = useMutation({
        mutationFn: deleteTenantAssignment,
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['tenant-assignments'] });
        },
    });

    const staffMembers = useMemo(() => staffQuery.data?.data?.data ?? [], [staffQuery.data]);
    const events = useMemo(() => eventsQuery.data?.data ?? [], [eventsQuery.data]);
    const assignments = useMemo(() => assignmentsQuery.data?.data?.data ?? [], [assignmentsQuery.data]);

    function submitStaff(event) {
        event.preventDefault();
        createStaffMutation.mutate(staffForm);
    }

    function submitAssignment(event) {
        event.preventDefault();

        createAssignmentMutation.mutate({
            ...assignmentForm,
            event_id: Number(assignmentForm.event_id),
            staff_id: Number(assignmentForm.staff_id),
            shift_start_at: assignmentForm.shift_start_at || null,
            shift_end_at: assignmentForm.shift_end_at || null,
        });
    }

    return (
        <div className="space-y-6">
            <section className="app-shell-panel rounded-3xl p-6">
                <p className="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Staff Assignment</p>
                <h1 className="hero-heading mt-2 text-3xl text-slate-900 md:text-4xl">Coordinate team assignments and prevent schedule conflicts before service day.</h1>
            </section>

            <section className="grid gap-4 xl:grid-cols-2">
                <form onSubmit={submitStaff} className="app-shell-panel rounded-3xl p-5">
                    <h2 className="text-lg font-semibold text-slate-900">Add Staff Member</h2>
                    <div className="mt-4 grid gap-3 md:grid-cols-2">
                        <input
                            className="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"
                            placeholder="First name"
                            value={staffForm.first_name}
                            onChange={(event) => setStaffForm((prev) => ({ ...prev, first_name: event.target.value }))}
                        />
                        <input
                            className="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"
                            placeholder="Last name"
                            value={staffForm.last_name}
                            onChange={(event) => setStaffForm((prev) => ({ ...prev, last_name: event.target.value }))}
                        />
                        <input
                            className="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"
                            placeholder="Email"
                            value={staffForm.email}
                            onChange={(event) => setStaffForm((prev) => ({ ...prev, email: event.target.value }))}
                        />
                        <input
                            className="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"
                            placeholder="Phone"
                            value={staffForm.phone}
                            onChange={(event) => setStaffForm((prev) => ({ ...prev, phone: event.target.value }))}
                        />
                        <input
                            className="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm md:col-span-2"
                            placeholder="Position"
                            value={staffForm.position}
                            onChange={(event) => setStaffForm((prev) => ({ ...prev, position: event.target.value }))}
                        />
                    </div>
                    <button
                        type="submit"
                        disabled={createStaffMutation.isPending}
                        className="mt-4 rounded-full bg-[var(--primary-color)] px-4 py-2 text-sm font-semibold text-white disabled:opacity-50"
                    >
                        {createStaffMutation.isPending ? 'Adding...' : 'Add Staff'}
                    </button>
                </form>

                <form onSubmit={submitAssignment} className="app-shell-panel rounded-3xl p-5">
                    <h2 className="text-lg font-semibold text-slate-900">Assign Staff to Event</h2>
                    <div className="mt-4 grid gap-3">
                        <select
                            className="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"
                            value={assignmentForm.event_id}
                            onChange={(event) => setAssignmentForm((prev) => ({ ...prev, event_id: event.target.value }))}
                        >
                            <option value="">Select event</option>
                            {events.map((tenantEvent) => (
                                <option key={tenantEvent.id} value={tenantEvent.id}>
                                    {tenantEvent.event_name} ({tenantEvent.event_date})
                                </option>
                            ))}
                        </select>

                        <select
                            className="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"
                            value={assignmentForm.staff_id}
                            onChange={(event) => setAssignmentForm((prev) => ({ ...prev, staff_id: event.target.value }))}
                        >
                            <option value="">Select staff</option>
                            {staffMembers.map((member) => (
                                <option key={member.id} value={member.id}>
                                    {member.full_name} ({member.position || 'Team'})
                                </option>
                            ))}
                        </select>

                        <input
                            className="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"
                            placeholder="Assignment role"
                            value={assignmentForm.assignment_role}
                            onChange={(event) => setAssignmentForm((prev) => ({ ...prev, assignment_role: event.target.value }))}
                        />

                        <label className="space-y-1 text-xs font-semibold uppercase tracking-wide text-slate-500">
                            Shift Start
                            <input
                                type="datetime-local"
                                className="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"
                                value={assignmentForm.shift_start_at}
                                onChange={(event) => setAssignmentForm((prev) => ({ ...prev, shift_start_at: event.target.value }))}
                            />
                        </label>
                        <label className="space-y-1 text-xs font-semibold uppercase tracking-wide text-slate-500">
                            Shift End
                            <input
                                type="datetime-local"
                                className="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"
                                value={assignmentForm.shift_end_at}
                                onChange={(event) => setAssignmentForm((prev) => ({ ...prev, shift_end_at: event.target.value }))}
                            />
                        </label>
                    </div>

                    {createAssignmentMutation.isError ? (
                        <p className="mt-3 rounded-xl border border-rose-300 bg-rose-50 px-3 py-2 text-xs text-rose-900">
                            {createAssignmentMutation.error?.response?.data?.message ?? 'Unable to create assignment.'}
                        </p>
                    ) : null}

                    <button
                        type="submit"
                        disabled={createAssignmentMutation.isPending}
                        className="mt-4 rounded-full bg-[var(--primary-color)] px-4 py-2 text-sm font-semibold text-white disabled:opacity-50"
                    >
                        {createAssignmentMutation.isPending ? 'Assigning...' : 'Create Assignment'}
                    </button>
                </form>
            </section>

            <section className="grid gap-4 xl:grid-cols-[1.2fr_1.8fr]">
                <div className="overflow-hidden rounded-3xl border border-slate-200 bg-white">
                    <div className="flex items-center justify-between border-b border-slate-100 px-4 py-3">
                        <h2 className="text-sm font-semibold text-slate-900">Staff Registry</h2>
                        <span className="text-xs text-slate-500">{formatNumber(staffMembers.length)} members</span>
                    </div>
                    <div className="divide-y divide-slate-100">
                        {staffQuery.isPending ? (
                            <p className="px-4 py-5 text-sm text-slate-600">Loading staff members...</p>
                        ) : staffQuery.isError ? (
                            <p className="px-4 py-5 text-sm text-rose-700">Unable to load staff registry.</p>
                        ) : staffMembers.length === 0 ? (
                            <p className="px-4 py-5 text-sm text-slate-600">No staff members yet.</p>
                        ) : (
                            staffMembers.map((member) => (
                                <article key={member.id} className="px-4 py-3">
                                    <p className="font-semibold text-slate-900">{member.full_name}</p>
                                    <p className="text-xs text-slate-500">{member.position || 'General staff'} · {member.email || 'No email'}</p>
                                </article>
                            ))
                        )}
                    </div>
                </div>

                <div className="overflow-hidden rounded-3xl border border-slate-200 bg-white">
                    <div className="flex items-center justify-between border-b border-slate-100 px-4 py-3">
                        <h2 className="text-sm font-semibold text-slate-900">Assignment Board</h2>
                        <span className="text-xs text-slate-500">{formatNumber(assignments.length)} active assignments</span>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead className="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th className="px-4 py-3">Event</th>
                                    <th className="px-4 py-3">Staff</th>
                                    <th className="px-4 py-3">Role</th>
                                    <th className="px-4 py-3">Window</th>
                                    <th className="px-4 py-3">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                {assignmentsQuery.isPending ? (
                                    <tr>
                                        <td className="px-4 py-6 text-slate-600" colSpan={5}>
                                            Loading assignments...
                                        </td>
                                    </tr>
                                ) : assignmentsQuery.isError ? (
                                    <tr>
                                        <td className="px-4 py-6 text-rose-700" colSpan={5}>
                                            Failed to load assignments.
                                        </td>
                                    </tr>
                                ) : assignments.length === 0 ? (
                                    <tr>
                                        <td className="px-4 py-6 text-slate-600" colSpan={5}>
                                            No assignments yet.
                                        </td>
                                    </tr>
                                ) : (
                                    assignments.map((assignment) => (
                                        <tr key={assignment.id} className="border-t border-slate-100">
                                            <td className="px-4 py-3">
                                                <p className="font-semibold text-slate-900">{assignment.event?.event_name || 'Unknown event'}</p>
                                                <p className="text-xs text-slate-500">{assignment.event?.event_date || 'No date'}</p>
                                            </td>
                                            <td className="px-4 py-3 text-slate-700">{assignment.staff?.full_name || 'N/A'}</td>
                                            <td className="px-4 py-3 text-slate-700">{assignment.assignment_role || 'General'}</td>
                                            <td className="px-4 py-3 text-xs text-slate-600">
                                                {(assignment.shift_start_at || assignment.start_time)
                                                    ? new Date(assignment.shift_start_at || assignment.start_time).toLocaleString()
                                                    : 'TBD'}
                                                <br />
                                                {(assignment.shift_end_at || assignment.end_time)
                                                    ? new Date(assignment.shift_end_at || assignment.end_time).toLocaleString()
                                                    : 'TBD'}
                                            </td>
                                            <td className="px-4 py-3">
                                                <button
                                                    type="button"
                                                    onClick={() => deleteAssignmentMutation.mutate(assignment.id)}
                                                    disabled={deleteAssignmentMutation.isPending}
                                                    className="rounded-lg border border-rose-300 px-2.5 py-1 text-xs font-semibold text-rose-700 disabled:opacity-50"
                                                >
                                                    Remove
                                                </button>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </div>
    );
}
