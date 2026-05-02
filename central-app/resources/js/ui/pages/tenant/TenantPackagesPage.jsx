import { useMemo, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { createTenantPackage, deleteTenantPackage, fetchTenantPackages, updateTenantPackage } from '../../../api/tenantApi';
import { formatCurrency, formatNumber } from '../../../lib/formatters';

const initialForm = {
    name: '',
    description: '',
    pricing_mode: 'flat',
    base_price: 0,
    is_active: true,
};

export function TenantPackagesPage() {
    const queryClient = useQueryClient();
    const [editingId, setEditingId] = useState(null);
    const [form, setForm] = useState(initialForm);

    const packagesQuery = useQuery({
        queryKey: ['tenant-packages'],
        queryFn: () => fetchTenantPackages(),
        staleTime: 1000 * 30,
    });

    const savePackageMutation = useMutation({
        mutationFn: async (payload) => {
            if (editingId) {
                return updateTenantPackage(editingId, payload);
            }

            return createTenantPackage(payload);
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['tenant-packages'] });
            setForm(initialForm);
            setEditingId(null);
        },
    });

    const deletePackageMutation = useMutation({
        mutationFn: deleteTenantPackage,
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['tenant-packages'] });
        },
    });

    const packages = useMemo(() => packagesQuery.data?.data?.data ?? [], [packagesQuery.data]);

    function updateField(key, value) {
        setForm((previous) => ({ ...previous, [key]: value }));
    }

    function submit(event) {
        event.preventDefault();

        savePackageMutation.mutate({
            ...form,
            base_price: Number(form.base_price ?? 0),
        });
    }

    return (
        <div className="space-y-6">
            <section className="app-shell-panel rounded-3xl p-6">
                <p className="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Package Builder</p>
                <h1 className="hero-heading mt-2 text-3xl text-slate-900 md:text-4xl">Define pricing logic, menu bundles, and package status in a centralized catalog.</h1>
            </section>

            <section className="grid gap-4 xl:grid-cols-[1.1fr_1.9fr]">
                <form onSubmit={submit} className="app-shell-panel rounded-3xl p-5">
                    <h2 className="text-lg font-semibold text-slate-900">{editingId ? 'Update Package' : 'Create Package'}</h2>
                    <div className="mt-4 grid gap-3">
                        <input
                            className="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"
                            placeholder="Package name"
                            value={form.name}
                            onChange={(event) => updateField('name', event.target.value)}
                        />
                        <textarea
                            rows={3}
                            className="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"
                            placeholder="Description"
                            value={form.description}
                            onChange={(event) => updateField('description', event.target.value)}
                        />
                        <select
                            className="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"
                            value={form.pricing_mode}
                            onChange={(event) => updateField('pricing_mode', event.target.value)}
                        >
                            <option value="flat">Flat rate</option>
                            <option value="per_person">Per person</option>
                        </select>
                        <input
                            type="number"
                            min={0}
                            className="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"
                            placeholder="Base price"
                            value={form.base_price}
                            onChange={(event) => updateField('base_price', event.target.value)}
                        />
                        <label className="inline-flex items-center gap-2 text-sm font-semibold text-slate-700">
                            <input
                                type="checkbox"
                                checked={Boolean(form.is_active)}
                                onChange={(event) => updateField('is_active', event.target.checked)}
                            />
                            Active package
                        </label>
                    </div>

                    {savePackageMutation.isError ? (
                        <p className="mt-3 rounded-xl border border-rose-300 bg-rose-50 px-3 py-2 text-xs text-rose-900">
                            {savePackageMutation.error?.response?.data?.message ?? 'Unable to save package.'}
                        </p>
                    ) : null}

                    <div className="mt-4 flex gap-2">
                        <button
                            type="submit"
                            disabled={savePackageMutation.isPending}
                            className="rounded-full bg-[var(--primary-color)] px-4 py-2 text-sm font-semibold text-white disabled:opacity-50"
                        >
                            {savePackageMutation.isPending ? 'Saving...' : editingId ? 'Update' : 'Create'}
                        </button>
                        {editingId ? (
                            <button
                                type="button"
                                className="rounded-full border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700"
                                onClick={() => {
                                    setEditingId(null);
                                    setForm(initialForm);
                                }}
                            >
                                Cancel
                            </button>
                        ) : null}
                    </div>
                </form>

                <div className="overflow-hidden rounded-3xl border border-slate-200 bg-white">
                    <div className="flex items-center justify-between border-b border-slate-100 px-4 py-3">
                        <h2 className="text-sm font-semibold text-slate-900">Package Catalog</h2>
                        <span className="text-xs text-slate-500">{formatNumber(packages.length)} packages</span>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead className="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th className="px-4 py-3">Package</th>
                                    <th className="px-4 py-3">Pricing</th>
                                    <th className="px-4 py-3">Events</th>
                                    <th className="px-4 py-3">State</th>
                                    <th className="px-4 py-3">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {packagesQuery.isPending ? (
                                    <tr>
                                        <td className="px-4 py-6 text-slate-600" colSpan={5}>
                                            Loading package catalog...
                                        </td>
                                    </tr>
                                ) : packagesQuery.isError ? (
                                    <tr>
                                        <td className="px-4 py-6 text-rose-700" colSpan={5}>
                                            Failed to load package catalog.
                                        </td>
                                    </tr>
                                ) : packages.length === 0 ? (
                                    <tr>
                                        <td className="px-4 py-6 text-slate-600" colSpan={5}>
                                            No packages available.
                                        </td>
                                    </tr>
                                ) : (
                                    packages.map((item) => (
                                        <tr key={item.id} className="border-t border-slate-100">
                                            <td className="px-4 py-3">
                                                <p className="font-semibold text-slate-900">{item.name}</p>
                                                <p className="text-xs text-slate-500">{item.description || 'No description'}</p>
                                            </td>
                                            <td className="px-4 py-3 text-slate-700">
                                                <p>{formatCurrency(item.base_price)}</p>
                                                <p className="text-xs text-slate-500">{item.pricing_mode === 'per_person' ? 'Per person' : 'Flat rate'}</p>
                                            </td>
                                            <td className="px-4 py-3 text-slate-700">{formatNumber(item.events_count)}</td>
                                            <td className="px-4 py-3">
                                                <span
                                                    className={`rounded-full px-2.5 py-1 text-xs font-semibold ${
                                                        item.is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-700'
                                                    }`}
                                                >
                                                    {item.is_active ? 'Active' : 'Inactive'}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="flex gap-2">
                                                    <button
                                                        type="button"
                                                        onClick={() => {
                                                            setEditingId(item.id);
                                                            setForm({
                                                                name: item.name ?? '',
                                                                description: item.description ?? '',
                                                                pricing_mode: item.pricing_mode ?? 'flat',
                                                                base_price: Number(item.base_price ?? 0),
                                                                is_active: Boolean(item.is_active),
                                                            });
                                                        }}
                                                        className="rounded-lg border border-slate-300 px-2.5 py-1 text-xs font-semibold text-slate-700"
                                                    >
                                                        Edit
                                                    </button>
                                                    <button
                                                        type="button"
                                                        onClick={() => deletePackageMutation.mutate(item.id)}
                                                        disabled={deletePackageMutation.isPending}
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
