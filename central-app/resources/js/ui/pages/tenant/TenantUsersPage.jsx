import { useMemo, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { createTenantUser, deleteTenantUser, fetchTenantUsers, updateTenantUser } from '../../../api/tenantApi';

const roleOptions = ['Admin', 'Manager', 'Staff', 'Cashier'];

const initialForm = {
    username: '',
    firstname: '',
    lastname: '',
    email: '',
    password: '',
    role: 'Staff',
    is_active: true,
};

export function TenantUsersPage() {
    const queryClient = useQueryClient();
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

    function updateField(key, value) {
        setForm((previous) => ({
            ...previous,
            [key]: value,
        }));
    }

    function submit(event) {
        event.preventDefault();

        const payload = {
            ...form,
            password: form.password || undefined,
        };

        saveUserMutation.mutate(payload);
    }

    return (
        <div className="space-y-6">
            <section className="app-shell-panel rounded-3xl p-6">
                <p className="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">User Access Control</p>
                <h1 className="hero-heading mt-2 text-3xl text-slate-900 md:text-4xl">Create role-bound accounts and manage tenant workspace permissions.</h1>
            </section>

            <section className="grid gap-4 xl:grid-cols-[1.05fr_1.95fr]">
                <form onSubmit={submit} className="app-shell-panel rounded-3xl p-5">
                    <h2 className="text-lg font-semibold text-slate-900">{editingId ? 'Update User' : 'Add User'}</h2>
                    <div className="mt-4 grid gap-3">
                        <input
                            className="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"
                            placeholder="Username"
                            value={form.username}
                            onChange={(event) => updateField('username', event.target.value)}
                        />
                        <input
                            className="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"
                            placeholder="First name"
                            value={form.firstname}
                            onChange={(event) => updateField('firstname', event.target.value)}
                        />
                        <input
                            className="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"
                            placeholder="Last name"
                            value={form.lastname}
                            onChange={(event) => updateField('lastname', event.target.value)}
                        />
                        <input
                            className="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"
                            placeholder="Email"
                            value={form.email}
                            onChange={(event) => updateField('email', event.target.value)}
                        />
                        <input
                            type="password"
                            className="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"
                            placeholder={editingId ? 'New password (optional)' : 'Password'}
                            value={form.password}
                            onChange={(event) => updateField('password', event.target.value)}
                        />
                        <select
                            className="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"
                            value={form.role}
                            onChange={(event) => updateField('role', event.target.value)}
                        >
                            {roleOptions.map((role) => (
                                <option key={role} value={role}>
                                    {role}
                                </option>
                            ))}
                        </select>
                        <label className="inline-flex items-center gap-2 text-sm font-semibold text-slate-700">
                            <input
                                type="checkbox"
                                checked={Boolean(form.is_active)}
                                onChange={(event) => updateField('is_active', event.target.checked)}
                            />
                            Active user
                        </label>
                    </div>

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
                                                        onClick={() => {
                                                            setEditingId(user.id);
                                                            setForm({
                                                                username: user.username || '',
                                                                firstname: user.firstname || '',
                                                                lastname: user.lastname || '',
                                                                email: user.email || '',
                                                                password: '',
                                                                role: user.role || 'Staff',
                                                                is_active: Boolean(user.is_active),
                                                            });
                                                        }}
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
