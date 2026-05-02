import { useEffect, useMemo, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { fetchTenantSettings, fetchTenantUsers, updateTenantSettings } from '../../../api/tenantApi';
import { useTenantContext } from '../../../providers/TenantProvider';
import { formatNumber } from '../../../lib/formatters';

const defaultPreferences = {
    timezone: 'Asia/Manila',
    date_format: 'MMMM DD, YYYY',
    default_guest_capacity: 120,
    reminder_schedule: '48_hours',
    auto_invoice_after_event: true,
    two_factor_required_for_admin: true,
    webhook_url: '',
};

export function TenantSettingsPage() {
    const queryClient = useQueryClient();
    const { tenantProfile, authUser } = useTenantContext();
    const [preferences, setPreferences] = useState(defaultPreferences);
    const [savedAt, setSavedAt] = useState('');

    const settingsQuery = useQuery({
        queryKey: ['tenant-settings'],
        queryFn: fetchTenantSettings,
        staleTime: 1000 * 60,
    });

    const usersQuery = useQuery({
        queryKey: ['tenant-users'],
        queryFn: () => fetchTenantUsers(),
        staleTime: 1000 * 30,
    });

    const saveSettingsMutation = useMutation({
        mutationFn: updateTenantSettings,
        onSuccess: async (payload) => {
            const next = {
                ...defaultPreferences,
                ...payload,
            };

            setPreferences(next);
            setSavedAt(payload?.updated_at ? new Date(payload.updated_at).toLocaleString() : new Date().toLocaleString());
            await queryClient.invalidateQueries({ queryKey: ['tenant-settings'] });
        },
    });

    useEffect(() => {
        if (!settingsQuery.data) {
            return;
        }

        setPreferences({
            ...defaultPreferences,
            ...settingsQuery.data,
        });
    }, [settingsQuery.data]);

    const users = useMemo(() => usersQuery.data?.data?.data ?? [], [usersQuery.data]);

    const roleSummary = useMemo(() => {
        return users.reduce((carry, user) => {
            const role = user.role || 'Unassigned';
            carry[role] = (carry[role] ?? 0) + 1;
            return carry;
        }, {});
    }, [users]);

    function updatePreference(key, value) {
        setPreferences((previous) => ({
            ...previous,
            [key]: value,
        }));
    }

    function saveSettings(event) {
        event.preventDefault();
        saveSettingsMutation.mutate(preferences);
    }

    return (
        <div className="space-y-6">
            <section className="app-shell-panel rounded-[2rem] p-6">
                <p className="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Workspace Settings</p>
                <h1 className="hero-heading mt-2 text-3xl text-slate-900 md:text-4xl">Manage operational defaults, security posture, and team governance.</h1>
            </section>

            <section className="grid gap-4 xl:grid-cols-[1.4fr_1fr]">
                <form onSubmit={saveSettings} className="overflow-hidden rounded-3xl border border-slate-200 bg-white">
                    <header className="border-b border-slate-100 px-4 py-3">
                        <h2 className="text-sm font-semibold text-slate-900">Operational Preferences</h2>
                    </header>

                    <div className="grid gap-4 p-4 md:grid-cols-2">
                        {settingsQuery.isPending ? (
                            <p className="md:col-span-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-600">Loading saved settings...</p>
                        ) : null}

                        {settingsQuery.isError ? (
                            <p className="md:col-span-2 rounded-xl border border-rose-300 bg-rose-50 px-3 py-2 text-sm text-rose-800">
                                Unable to load tenant settings. Editing defaults until the service recovers.
                            </p>
                        ) : null}

                        <label className="space-y-1 text-xs font-semibold uppercase tracking-wide text-slate-500">
                            Timezone
                            <select
                                value={preferences.timezone}
                                onChange={(event) => updatePreference('timezone', event.target.value)}
                                className="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700"
                            >
                                <option value="Asia/Manila">Asia/Manila</option>
                                <option value="Asia/Singapore">Asia/Singapore</option>
                                <option value="America/Los_Angeles">America/Los_Angeles</option>
                                <option value="Europe/London">Europe/London</option>
                            </select>
                        </label>

                        <label className="space-y-1 text-xs font-semibold uppercase tracking-wide text-slate-500">
                            Date Format
                            <select
                                value={preferences.date_format}
                                onChange={(event) => updatePreference('date_format', event.target.value)}
                                className="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700"
                            >
                                <option value="MMMM DD, YYYY">MMMM DD, YYYY</option>
                                <option value="DD MMM YYYY">DD MMM YYYY</option>
                                <option value="YYYY-MM-DD">YYYY-MM-DD</option>
                            </select>
                        </label>

                        <label className="space-y-1 text-xs font-semibold uppercase tracking-wide text-slate-500">
                            Default Guest Capacity
                            <input
                                type="number"
                                min={1}
                                value={preferences.default_guest_capacity}
                                onChange={(event) => updatePreference('default_guest_capacity', Number(event.target.value))}
                                className="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700"
                            />
                        </label>

                        <label className="space-y-1 text-xs font-semibold uppercase tracking-wide text-slate-500">
                            Reminder Schedule
                            <select
                                value={preferences.reminder_schedule}
                                onChange={(event) => updatePreference('reminder_schedule', event.target.value)}
                                className="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700"
                            >
                                <option value="24_hours">24 hours before event</option>
                                <option value="48_hours">48 hours before event</option>
                                <option value="72_hours">72 hours before event</option>
                            </select>
                        </label>

                        <label className="md:col-span-2 space-y-1 text-xs font-semibold uppercase tracking-wide text-slate-500">
                            Webhook Endpoint
                            <input
                                value={preferences.webhook_url}
                                onChange={(event) => updatePreference('webhook_url', event.target.value)}
                                placeholder="https://hooks.yourdomain.com/tenant-events"
                                className="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700"
                            />
                        </label>

                        <label className="inline-flex items-center gap-2 text-sm font-semibold text-slate-700 md:col-span-2">
                            <input
                                type="checkbox"
                                checked={preferences.auto_invoice_after_event}
                                onChange={(event) => updatePreference('auto_invoice_after_event', event.target.checked)}
                            />
                            Generate invoice drafts when events are marked completed.
                        </label>

                        <label className="inline-flex items-center gap-2 text-sm font-semibold text-slate-700 md:col-span-2">
                            <input
                                type="checkbox"
                                checked={preferences.two_factor_required_for_admin}
                                onChange={(event) => updatePreference('two_factor_required_for_admin', event.target.checked)}
                            />
                            Enforce two-factor authentication for admin roles.
                        </label>
                    </div>

                    <div className="flex items-center justify-between border-t border-slate-100 px-4 py-3">
                        {savedAt ? <p className="text-xs text-emerald-700">Saved at {savedAt}</p> : <span />}
                        <button
                            type="submit"
                            disabled={saveSettingsMutation.isPending}
                            className="rounded-full bg-[var(--primary-color)] px-4 py-2 text-sm font-semibold text-white disabled:opacity-50"
                        >
                            {saveSettingsMutation.isPending ? 'Saving...' : 'Save Settings'}
                        </button>
                    </div>

                    {saveSettingsMutation.isError ? (
                        <p className="mx-4 mb-4 rounded-xl border border-rose-300 bg-rose-50 px-3 py-2 text-xs text-rose-900">
                            {saveSettingsMutation.error?.response?.data?.message ?? 'Unable to save workspace settings.'}
                        </p>
                    ) : null}
                </form>

                <div className="space-y-4">
                    <article className="rounded-3xl border border-slate-200 bg-white p-4">
                        <p className="text-xs uppercase tracking-wide text-slate-500">Tenant Context</p>
                        <p className="mt-1 text-base font-semibold text-slate-900">{tenantProfile?.company_name || 'Tenant Workspace'}</p>
                        <p className="mt-1 text-sm text-slate-600">Plan: {tenantProfile?.plan || 'unknown'}</p>
                        <p className="text-sm text-slate-600">Current User: {authUser?.display_name || 'Guest'}</p>
                    </article>

                    <article className="rounded-3xl border border-slate-200 bg-white p-4">
                        <div className="flex items-center justify-between">
                            <h2 className="text-sm font-semibold text-slate-900">Role Distribution</h2>
                            <span className="text-xs text-slate-500">{formatNumber(users.length)} users</span>
                        </div>

                        {usersQuery.isPending ? <p className="mt-3 text-sm text-slate-600">Loading users...</p> : null}
                        {usersQuery.isError ? <p className="mt-3 text-sm text-rose-700">Unable to load user directory.</p> : null}

                        {!usersQuery.isPending && !usersQuery.isError ? (
                            <div className="mt-3 space-y-2">
                                {Object.keys(roleSummary).length === 0 ? (
                                    <p className="text-sm text-slate-600">No user roles available.</p>
                                ) : (
                                    Object.entries(roleSummary).map(([role, count]) => (
                                        <div key={role} className="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                                            <span className="text-sm font-semibold text-slate-800">{role}</span>
                                            <span className="text-xs font-semibold text-slate-600">{formatNumber(count)}</span>
                                        </div>
                                    ))
                                )}
                            </div>
                        ) : null}
                    </article>

                    <article className="rounded-3xl border border-slate-200 bg-white p-4">
                        <h2 className="text-sm font-semibold text-slate-900">Guardrails</h2>
                        <ul className="mt-2 space-y-2 text-sm text-slate-600">
                            <li>Exact-origin CORS policy required for public endpoints.</li>
                            <li>Role permissions and modules should be reviewed quarterly.</li>
                            <li>Rotate webhook secrets after team member offboarding.</li>
                        </ul>
                    </article>
                </div>
            </section>
        </div>
    );
}
