import { useMemo, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { createTenantClient, deleteTenantClient, fetchTenantClients, updateTenantClient } from '../../../api/tenantApi';
import { formatNumber } from '../../../lib/formatters';

const initialForm = {
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    address: '',
    notes: '',
};

export function TenantClientsPage() {
    const queryClient = useQueryClient();
    const [editingId, setEditingId] = useState(null);
    const [form, setForm] = useState(initialForm);

    const clientsQuery = useQuery({
        queryKey: ['tenant-clients'],
        queryFn: () => fetchTenantClients(),
        staleTime: 1000 * 30,
    });

    const saveClientMutation = useMutation({
        mutationFn: async (payload) => {
            if (editingId) {
                return updateTenantClient(editingId, payload);
            }

            return createTenantClient(payload);
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['tenant-clients'] });
            setForm(initialForm);
            setEditingId(null);
        },
    });

    const deleteClientMutation = useMutation({
        mutationFn: deleteTenantClient,
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['tenant-clients'] });
        },
    });

    const clients = useMemo(() => clientsQuery.data?.data?.data ?? [], [clientsQuery.data]);

    function handleChange(key, value) {
        setForm((previous) => ({
            ...previous,
            [key]: value,
        }));
    }

    function handleSubmit(event) {
        event.preventDefault();
        saveClientMutation.mutate(form);
    }

    return (
        <div className="space-y-6">
            <section className="app-shell-panel rounded-3xl p-6">
                <p className="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Client Profiling</p>
                <h1 className="hero-heading mt-2 text-3xl text-slate-900 md:text-4xl">Track every client profile, communication note, and event history in one place.</h1>
            </section>

            <section className="grid gap-4 xl:grid-cols-[1.15fr_1.85fr]">
                <form onSubmit={handleSubmit} className="app-shell-panel rounded-3xl p-5">
                    <h2 className="text-lg font-semibold text-slate-900">{editingId ? 'Update Client' : 'New Client'}</h2>
                    <div className="mt-4 grid gap-3">
                        <input
                            className="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"
                            placeholder="First name"
                            value={form.first_name}
                            onChange={(event) => handleChange('first_name', event.target.value)}
                        />
                        <input
                            className="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"
                            placeholder="Last name"
                            value={form.last_name}
                            onChange={(event) => handleChange('last_name', event.target.value)}
                        />
                        <input
                            className="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"
                            placeholder="Email"
                            value={form.email}
                            onChange={(event) => handleChange('email', event.target.value)}
                        />
                        <input
                            className="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"
                            placeholder="Phone"
                            value={form.phone}
                            onChange={(event) => handleChange('phone', event.target.value)}
                        />
                        <input
                            className="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"
                            placeholder="Address"
                            value={form.address}
                            onChange={(event) => handleChange('address', event.target.value)}
                        />
                        <textarea
                            rows={3}
                            className="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"
                            placeholder="Notes"
                            value={form.notes}
                            onChange={(event) => handleChange('notes', event.target.value)}
                        />
                    </div>

                    {saveClientMutation.isError ? (
                        <p className="mt-3 rounded-xl border border-rose-300 bg-rose-50 px-3 py-2 text-xs text-rose-900">
                            {saveClientMutation.error?.response?.data?.message ?? 'Unable to save client profile.'}
                        </p>
                    ) : null}

                    <div className="mt-4 flex gap-2">
                        <button
                            type="submit"
                            disabled={saveClientMutation.isPending}
                            className="rounded-full bg-[var(--primary-color)] px-4 py-2 text-sm font-semibold text-white disabled:opacity-50"
                        >
                            {saveClientMutation.isPending ? 'Saving...' : editingId ? 'Update' : 'Create'}
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
                    <div className="flex items-center justify-between border-b border-slate-100 px-4 py-3">
                        <h2 className="text-sm font-semibold text-slate-900">Client Directory</h2>
                        <span className="text-xs text-slate-500">{formatNumber(clients.length)} records</span>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead className="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th className="px-4 py-3">Client</th>
                                    <th className="px-4 py-3">Contact</th>
                                    <th className="px-4 py-3">Events</th>
                                    <th className="px-4 py-3">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {clientsQuery.isPending ? (
                                    <tr>
                                        <td className="px-4 py-6 text-slate-600" colSpan={4}>
                                            Loading clients...
                                        </td>
                                    </tr>
                                ) : clientsQuery.isError ? (
                                    <tr>
                                        <td className="px-4 py-6 text-rose-700" colSpan={4}>
                                            Failed to load client directory.
                                        </td>
                                    </tr>
                                ) : clients.length === 0 ? (
                                    <tr>
                                        <td className="px-4 py-6 text-slate-600" colSpan={4}>
                                            No clients yet.
                                        </td>
                                    </tr>
                                ) : (
                                    clients.map((client) => (
                                        <tr key={client.id} className="border-t border-slate-100">
                                            <td className="px-4 py-3">
                                                <p className="font-semibold text-slate-900">{client.full_name}</p>
                                                <p className="text-xs text-slate-500">{client.address || 'No address'}</p>
                                            </td>
                                            <td className="px-4 py-3 text-slate-700">
                                                <p>{client.email || 'No email'}</p>
                                                <p className="text-xs text-slate-500">{client.phone || 'No phone'}</p>
                                            </td>
                                            <td className="px-4 py-3 text-slate-700">{formatNumber(client.events_count)}</td>
                                            <td className="px-4 py-3">
                                                <div className="flex gap-2">
                                                    <button
                                                        type="button"
                                                        onClick={() => {
                                                            setEditingId(client.id);
                                                            setForm({
                                                                first_name: client.first_name || '',
                                                                last_name: client.last_name || '',
                                                                email: client.email || '',
                                                                phone: client.phone || '',
                                                                address: client.address || '',
                                                                notes: client.notes || '',
                                                            });
                                                        }}
                                                        className="rounded-lg border border-slate-300 px-2.5 py-1 text-xs font-semibold text-slate-700"
                                                    >
                                                        Edit
                                                    </button>
                                                    <button
                                                        type="button"
                                                        onClick={() => deleteClientMutation.mutate(client.id)}
                                                        disabled={deleteClientMutation.isPending}
                                                        className="rounded-lg border border-rose-300 px-2.5 py-1 text-xs font-semibold text-rose-700 disabled:opacity-50"
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
