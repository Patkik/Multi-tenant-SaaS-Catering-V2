import { useMemo, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { createTenantUser, deleteTenantUser, fetchTenantUsers, updateTenantUser } from '../../../api/tenantApi';
import { useTenantContext } from '../../../providers/TenantProvider';

const roleOptions = ['Admin', 'Manager', 'Staff', 'Cashier'];

const initialForm = {
    firstname: '',
    lastname: '',
    mi: '',
    business_name: '',
    email: '',
    password: '',
    password_confirmation: '',
    role: 'Staff',
    is_active: true,
};

export function TenantUsersPage() {
    const queryClient = useQueryClient();
    const { authUser } = useTenantContext();
    const [editingId, setEditingId] = useState(null);
    const [form, setForm] = useState(initialForm);

    const usersQuery = useQuery({
        queryKey: ['tenant-users'],
        queryFn: () => fetchTenantUsers(),
        staleTime: 1000 * 30,
    });

    const saveUserMutation = useMutation({
        mutationFn: async (payload) => {
            if (editingId) {
                return updateTenantUser(editingId, payload);
            }

            return createTenantUser(payload);
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['tenant-users'] });
            setForm(initialForm);
            setEditingId(null);
        },
    });

    const deleteUserMutation = useMutation({
        mutationFn: deleteTenantUser,
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['tenant-users'] });
        },
    });

    const users = useMemo(() => usersQuery.data?.data?.data ?? [], [usersQuery.data]);
    const firstAdminUserId = usersQuery.data?.data?.meta?.first_admin_user_id ?? null;
    const canAssignAdminRole = Boolean(usersQuery.data?.data?.meta?.can_assign_admin_role);

    const assignableRoles = useMemo(() => {
        if (canAssignAdminRole) {
            return roleOptions;
        }

        return roleOptions.filter((role) => role !== 'Admin');
    }, [canAssignAdminRole]);

    const roleHelpText = useMemo(() => {
        if (canAssignAdminRole) {
            return 'You can assign all roles, including Admin.';
        }

        if (!firstAdminUserId) {
            return 'Admin assignment is currently unavailable because no first admin was detected.';
        }

        return 'Only the first admin account can create or promote another Admin.';
    }, [canAssignAdminRole, firstAdminUserId]);

    function updateField(key, value) {
        setForm((previous) => ({
            ...previous,
            [key]: value,
        }));
    }

    function normalizeUsername(value) {
        return String(value || '')
            .toLowerCase()
            .trim()
            .replace(/[^a-z0-9._-]/g, '');
    }

    function toGeneratedUsername() {
        const preferred = normalizeUsername(String(form.email || '').split('@')[0]);

        if (preferred) {
            return preferred.slice(0, 50);
        }

        const fallback = normalizeUsername(`${form.firstname}.${form.lastname}`.replace(/\.+/g, '.'));

        if (fallback) {
            return fallback.slice(0, 50);
        }

        return 'user';
    }

    function submit(event) {
        event.preventDefault();

        const selectedRole = assignableRoles.includes(form.role) ? form.role : 'Staff';

        const payload = {
            username: toGeneratedUsername(),
            firstname: form.firstname,
            lastname: form.lastname,
            mi: form.mi || undefined,
            email: form.email || undefined,
            role: selectedRole,
            is_active: Boolean(form.is_active),
            password: form.password || undefined,
            password_confirmation: form.password_confirmation || undefined,
        };

        saveUserMutation.mutate(payload);
    }

    function beginEditUser(user) {
        const nextRole = assignableRoles.includes(user.role) ? user.role : 'Staff';

        setEditingId(user.id);
        setForm({
            firstname: user.firstname || '',
            lastname: user.lastname || '',
            mi: user.mi || '',
            business_name: '',
            email: user.email || '',
            password: '',
            password_confirmation: '',
            role: nextRole,
            is_active: Boolean(user.is_active),
        });
    }

    return (
        <div className="space-y-6">
            <section className="app-shell-panel rounded-3xl p-6">
                <p className="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">User Access Control</p>
                <h1 className="hero-heading mt-2 text-3xl text-slate-900 md:text-4xl">Create role-bound accounts and manage tenant workspace permissions.</h1>
            </section>

            <section className="grid gap-4 xl:grid-cols-[1.05fr_1.95fr]">
                <form onSubmit={submit} className="central-card rounded-3xl p-5">
                    <h2 className="text-lg font-semibold text-slate-900">{editingId ? 'Update User' : 'Add User'}</h2>
                    <div className="mt-4 grid gap-3 md:grid-cols-2">
                        <label className="text-[12px] font-semibold text-slate-700">
                            First name
                            <input
                                className="central-input mt-1 h-10 w-full rounded-[var(--border-radius-md)] border px-3 text-sm"
                                value={form.firstname}
                                onChange={(event) => updateField('firstname', event.target.value)}
                                required
                            />
                        </label>
                        <label className="text-[12px] font-semibold text-slate-700">
                            Last name
                            <input
                                className="central-input mt-1 h-10 w-full rounded-[var(--border-radius-md)] border px-3 text-sm"
                                value={form.lastname}
                                onChange={(event) => updateField('lastname', event.target.value)}
                                required
                            />
                        </label>
                        <label className="text-[12px] font-semibold text-slate-700">
                            Middle initial
                            <input
                                className="central-input mt-1 h-10 w-full rounded-[var(--border-radius-md)] border px-3 text-sm"
                                value={form.mi}
                                maxLength={10}
                                onChange={(event) => updateField('mi', event.target.value)}
                            />
                        </label>
                        <label className="text-[12px] font-semibold text-slate-700">
                            Business name
                            <input
                                className="central-input mt-1 h-10 w-full rounded-[var(--border-radius-md)] border px-3 text-sm"
                                value={form.business_name}
                                onChange={(event) => updateField('business_name', event.target.value)}
                                placeholder="Optional"
                            />
                        </label>
                        <label className="text-[12px] font-semibold text-slate-700 md:col-span-2">
                            Email
                            <input
                                type="email"
                                className="central-input mt-1 h-10 w-full rounded-[var(--border-radius-md)] border px-3 text-sm"
                                value={form.email}
                                onChange={(event) => updateField('email', event.target.value)}
                            />
                        </label>
                        <label className="text-[12px] font-semibold text-slate-700">
                            {editingId ? 'New password' : 'Password'}
                            <input
                                type="password"
                                className="central-input mt-1 h-10 w-full rounded-[var(--border-radius-md)] border px-3 text-sm"
                                placeholder={editingId ? 'Leave blank to keep current password' : ''}
                                value={form.password}
                                onChange={(event) => updateField('password', event.target.value)}
                                required={!editingId}
                                minLength={8}
                            />
                        </label>
                        <label className="text-[12px] font-semibold text-slate-700">
                            Verify password
                            <input
                                type="password"
                                className="central-input mt-1 h-10 w-full rounded-[var(--border-radius-md)] border px-3 text-sm"
                                placeholder={editingId ? 'Repeat new password' : ''}
                                value={form.password_confirmation}
                                onChange={(event) => updateField('password_confirmation', event.target.value)}
                                required={Boolean(form.password)}
                                minLength={8}
                            />
                        </label>
                        <label className="text-[12px] font-semibold text-slate-700">
                            Role
                            <select
                                className="central-input mt-1 h-10 w-full rounded-[var(--border-radius-md)] border px-3 text-sm"
                                value={form.role}
                                onChange={(event) => updateField('role', event.target.value)}
                            >
                                {assignableRoles.map((role) => (
                                    <option key={role} value={role}>
                                        {role}
                                    </option>
                                ))}
                            </select>
                        </label>
                        <label className="inline-flex items-center gap-2 self-end text-sm font-semibold text-slate-700">
                            <input
                                type="checkbox"
                                checked={Boolean(form.is_active)}
                                onChange={(event) => updateField('is_active', event.target.checked)}
                            />
                            Active user
                        </label>
                    </div>

                    <p className="mt-2 text-[11px] text-slate-600">{roleHelpText}</p>
                    {!canAssignAdminRole ? (
                        <p className="mt-2 rounded-[var(--border-radius-md)] border border-amber-300 bg-amber-50 px-3 py-2 text-[11px] text-amber-900">
                            Admin role is hidden for this session. Sign in with the first admin account to create another admin.
                        </p>
                    ) : null}

                    {saveUserMutation.isError ? (
                        <p className="mt-3 rounded-xl border border-rose-300 bg-rose-50 px-3 py-2 text-xs text-rose-900">
                            {saveUserMutation.error?.response?.data?.message ?? 'Unable to save user account.'}
                        </p>
                    ) : null}

                    <div className="mt-4 flex gap-2">
                        <button
                            type="submit"
                            disabled={saveUserMutation.isPending}
                            className="rounded-full bg-[var(--primary-color)] px-4 py-2 text-sm font-semibold text-white disabled:opacity-50"
                        >
                            {saveUserMutation.isPending ? 'Saving...' : editingId ? 'Update User' : 'Create User'}
                        </button>
                        {editingId ? (
                            <button
                                type="button"
                                onClick={() => {
                                    setEditingId(null);
                                    setForm(initialForm);
                                }}
                                className="rounded-full border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700"
                            >
                                Cancel
                            </button>
                        ) : null}
                    </div>
                </form>

                <div className="overflow-hidden rounded-3xl border border-slate-200 bg-white">
                    <div className="border-b border-slate-100 px-4 py-3">
                        <h2 className="text-sm font-semibold text-slate-900">Tenant Users</h2>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead className="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th className="px-4 py-3">User</th>
                                    <th className="px-4 py-3">Role</th>
                                    <th className="px-4 py-3">Status</th>
                                    <th className="px-4 py-3">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {usersQuery.isPending ? (
                                    <tr>
                                        <td className="px-4 py-6 text-slate-600" colSpan={4}>
                                            Loading tenant users...
                                        </td>
                                    </tr>
                                ) : usersQuery.isError ? (
                                    <tr>
                                        <td className="px-4 py-6 text-rose-700" colSpan={4}>
                                            Failed to load tenant users.
                                        </td>
                                    </tr>
                                ) : users.length === 0 ? (
                                    <tr>
                                        <td className="px-4 py-6 text-slate-600" colSpan={4}>
                                            No users found.
                                        </td>
                                    </tr>
                                ) : (
                                    users.map((user) => (
                                        <tr key={user.id} className="border-t border-slate-100">
                                            <td className="px-4 py-3">
                                                <p className="font-semibold text-slate-900">{user.display_name}</p>
                                                <p className="text-xs text-slate-500">{user.username} · {user.email || 'No email'}</p>
                                                {firstAdminUserId !== null && user.id === firstAdminUserId ? (
                                                    <p className="mt-1 inline-flex rounded-full border border-blue-300 bg-blue-50 px-2 py-0.5 text-[10px] font-semibold text-blue-800">
                                                        First Admin
                                                    </p>
                                                ) : null}
                                            </td>
                                            <td className="px-4 py-3 text-slate-700">{user.role}</td>
                                            <td className="px-4 py-3">
                                                <span
                                                    className={`rounded-full px-2.5 py-1 text-xs font-semibold ${
                                                        user.is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-700'
                                                    }`}
                                                >
                                                    {user.is_active ? 'Active' : 'Inactive'}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="flex gap-2">
                                                    <button
                                                        type="button"
                                                        className="rounded-lg border border-slate-300 px-2.5 py-1 text-xs font-semibold text-slate-700"
                                                        onClick={() => beginEditUser(user)}
                                                    >
                                                        Edit
                                                    </button>
                                                    <button
                                                        type="button"
                                                        className="rounded-lg border border-rose-300 px-2.5 py-1 text-xs font-semibold text-rose-700"
                                                        onClick={() => deleteUserMutation.mutate(user.id)}
                                                        disabled={deleteUserMutation.isPending}
                                                    >
                                                        Delete
                                                    </button>
                                                </div>
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
