import { useEffect, useState } from 'react';
import { useMutation, useQuery } from '@tanstack/react-query';
import { fetchTenantBranding, updateTenantBranding } from '../../../api/tenantApi';
import { useTenantContext } from '../../../providers/TenantProvider';

const fallbackBranding = {
    company_name: '',
    primary_color: '#0B8F66',
    logo_url: '',
    logo_path: '',
};

export function TenantBrandingPage() {
    const { refreshProfile } = useTenantContext();
    const [form, setForm] = useState(fallbackBranding);

    const brandingQuery = useQuery({
        queryKey: ['tenant-branding'],
        queryFn: fetchTenantBranding,
        staleTime: 1000 * 60,
    });

    const saveBrandingMutation = useMutation({
        mutationFn: updateTenantBranding,
        onSuccess: async (payload) => {
            setForm({
                company_name: payload.company_name || '',
                primary_color: payload.primary_color || '#0B8F66',
                logo_url: payload.logo_url || '',
                logo_path: payload.logo_path || '',
            });
            await refreshProfile();
        },
    });

    useEffect(() => {
        if (brandingQuery.data) {
            setForm({
                company_name: brandingQuery.data.company_name || '',
                primary_color: brandingQuery.data.primary_color || '#0B8F66',
                logo_url: brandingQuery.data.logo_url || '',
                logo_path: brandingQuery.data.logo_path || '',
            });
        }
    }, [brandingQuery.data]);

    function updateField(key, value) {
        setForm((previous) => ({
            ...previous,
            [key]: value,
        }));
    }

    function submit(event) {
        event.preventDefault();

        saveBrandingMutation.mutate(form);
    }

    return (
        <div className="space-y-6">
            <section className="app-shell-panel rounded-3xl p-6">
                <p className="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Branding Controls</p>
                <h1 className="hero-heading mt-2 text-3xl text-slate-900 md:text-4xl">Apply your company identity across tenant workspace touchpoints.</h1>
            </section>

            <section className="grid gap-4 xl:grid-cols-[1.1fr_1.9fr]">
                <form onSubmit={submit} className="app-shell-panel rounded-3xl p-5">
                    <h2 className="text-lg font-semibold text-slate-900">Brand Profile</h2>
                    <div className="mt-4 grid gap-3">
                        <label className="space-y-1 text-sm font-semibold text-slate-700">
                            Company Name
                            <input
                                className="w-full rounded-xl border border-slate-300 bg-white px-3 py-2"
                                value={form.company_name}
                                onChange={(event) => updateField('company_name', event.target.value)}
                            />
                        </label>

                        <label className="space-y-1 text-sm font-semibold text-slate-700">
                            Primary Color
                            <input
                                type="color"
                                className="h-10 w-full rounded-xl border border-slate-300 bg-white px-1 py-1"
                                value={form.primary_color || '#0B8F66'}
                                onChange={(event) => updateField('primary_color', event.target.value)}
                            />
                        </label>

                        <label className="space-y-1 text-sm font-semibold text-slate-700">
                            Logo URL
                            <input
                                className="w-full rounded-xl border border-slate-300 bg-white px-3 py-2"
                                value={form.logo_url}
                                onChange={(event) => updateField('logo_url', event.target.value)}
                            />
                        </label>
                    </div>

                    {saveBrandingMutation.isError ? (
                        <p className="mt-3 rounded-xl border border-rose-300 bg-rose-50 px-3 py-2 text-xs text-rose-900">
                            {saveBrandingMutation.error?.response?.data?.message ?? 'Unable to save branding profile.'}
                        </p>
                    ) : null}

                    {saveBrandingMutation.isSuccess ? (
                        <p className="mt-3 rounded-xl border border-emerald-300 bg-emerald-50 px-3 py-2 text-xs text-emerald-900">
                            Branding profile saved successfully.
                        </p>
                    ) : null}

                    <button
                        type="submit"
                        disabled={saveBrandingMutation.isPending}
                        className="mt-4 rounded-full bg-[var(--primary-color)] px-4 py-2 text-sm font-semibold text-white disabled:opacity-50"
                    >
                        {saveBrandingMutation.isPending ? 'Saving...' : 'Save Branding'}
                    </button>
                </form>

                <article className="overflow-hidden rounded-3xl border border-slate-200 bg-white">
                    <header className="border-b border-slate-100 px-5 py-4">
                        <p className="text-xs uppercase tracking-[0.15em] text-slate-500">Preview</p>
                        <h2 className="mt-1 text-lg font-semibold text-slate-900">Tenant Interface Card</h2>
                    </header>
                    <div className="p-5">
                        <div className="rounded-3xl border border-slate-200 bg-slate-50 p-6">
                            <div className="flex items-center gap-3">
                                {form.logo_url ? (
                                    <img src={form.logo_url} alt="Logo preview" className="h-12 w-12 rounded-xl border border-slate-200 object-cover" />
                                ) : (
                                    <div
                                        className="flex h-12 w-12 items-center justify-center rounded-xl text-sm font-bold text-white"
                                        style={{ backgroundColor: form.primary_color || '#0B8F66' }}
                                    >
                                        {(form.company_name || 'T')[0]?.toUpperCase()}
                                    </div>
                                )}
                                <div>
                                    <p className="text-xs uppercase tracking-wide text-slate-500">Tenant brand</p>
                                    <p className="text-base font-semibold text-slate-900">{form.company_name || 'Your Company Name'}</p>
                                </div>
                            </div>

                            <div className="mt-5 grid gap-3 md:grid-cols-2">
                                <div className="rounded-2xl border border-slate-200 bg-white px-4 py-3">
                                    <p className="text-xs uppercase tracking-wide text-slate-500">Primary color</p>
                                    <div className="mt-2 flex items-center gap-2">
                                        <span
                                            className="h-5 w-5 rounded-full border border-slate-300"
                                            style={{ backgroundColor: form.primary_color || '#0B8F66' }}
                                        />
                                        <span className="text-sm font-semibold text-slate-900">{form.primary_color || '#0B8F66'}</span>
                                    </div>
                                </div>
                                <div className="rounded-2xl border border-slate-200 bg-white px-4 py-3">
                                    <p className="text-xs uppercase tracking-wide text-slate-500">Logo status</p>
                                    <p className="mt-2 text-sm font-semibold text-slate-900">{form.logo_url ? 'Configured' : 'Not configured'}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </article>
            </section>
        </div>
    );
}
