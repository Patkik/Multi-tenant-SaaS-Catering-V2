import { useQuery } from '@tanstack/react-query';
import { fetchTenantAnalytics } from '../../../api/tenantApi';
import { formatNumber } from '../../../lib/formatters';
import { StatCard } from '../../components/StatCard';

export function TenantAnalyticsPage() {
    const analyticsQuery = useQuery({
        queryKey: ['tenant-analytics'],
        queryFn: fetchTenantAnalytics,
        staleTime: 1000 * 60,
    });

    if (analyticsQuery.isPending) {
        return <div className="rounded-3xl app-shell-panel p-8 text-slate-700">Loading analytics workspace...</div>;
    }

    if (analyticsQuery.isError) {
        return (
            <div className="rounded-3xl border border-rose-300 bg-rose-50 p-8 text-rose-900">
                Unable to load analytics. {analyticsQuery.error?.response?.data?.message ?? ''}
            </div>
        );
    }

    const analytics = analyticsQuery.data;
    const kpis = analytics?.kpis ?? {};
    const monthlyEvents = analytics?.series?.monthly_events ?? [];
    const topClients = analytics?.top_clients ?? [];

    const maxSeriesValue = Math.max(...monthlyEvents.map((row) => Number(row.total ?? 0)), 1);

    return (
        <div className="space-y-6">
            <section className="app-shell-panel rounded-3xl p-6">
                <p className="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Advanced Analytics</p>
                <h1 className="hero-heading mt-2 text-3xl text-slate-900 md:text-4xl">Revenue performance, status health, and client growth metrics.</h1>
            </section>

            <section className="grid gap-4 md:grid-cols-3">
                <StatCard title="Total Events" value={kpis.total_events ?? 0} trend="All recorded tenant events" />
                <StatCard title="Total Revenue" value={kpis.total_revenue ?? 0} kind="currency" trend="Settled payments only" />
                <StatCard title="Pending Collections" value={kpis.pending_collections ?? 0} kind="currency" trend="Open receivables" />
            </section>

            <section className="grid gap-4 xl:grid-cols-[1.7fr_1.3fr]">
                <article className="rounded-3xl border border-slate-200 bg-white p-5">
                    <h2 className="text-base font-semibold text-slate-900">Monthly Event Volume</h2>
                    <div className="mt-4 space-y-3">
                        {monthlyEvents.length === 0 ? (
                            <p className="text-sm text-slate-600">No event analytics data yet.</p>
                        ) : (
                            monthlyEvents.map((point) => (
                                <div key={point.month}>
                                    <div className="mb-1 flex items-center justify-between text-xs text-slate-600">
                                        <span>{point.month}</span>
                                        <span>{formatNumber(point.total)}</span>
                                    </div>
                                    <div className="h-2 rounded-full bg-slate-100">
                                        <div
                                            className="h-2 rounded-full bg-[var(--primary-color)]"
                                            style={{ width: `${Math.max((Number(point.total) / maxSeriesValue) * 100, 4)}%` }}
                                        />
                                    </div>
                                </div>
                            ))
                        )}
                    </div>
                </article>

                <article className="rounded-3xl border border-slate-200 bg-white p-5">
                    <h2 className="text-base font-semibold text-slate-900">Top Clients by Event Volume</h2>
                    <div className="mt-4 space-y-3">
                        {topClients.length === 0 ? (
                            <p className="text-sm text-slate-600">No client performance data yet.</p>
                        ) : (
                            topClients.map((client, index) => (
                                <div key={client.id} className="rounded-2xl border border-slate-200 px-3 py-2">
                                    <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">Rank #{index + 1}</p>
                                    <p className="font-semibold text-slate-900">{client.name}</p>
                                    <p className="text-sm text-slate-600">{formatNumber(client.events_count)} booked events</p>
                                </div>
                            ))
                        )}
                    </div>
                </article>
            </section>

            <section className="rounded-3xl border border-slate-200 bg-white p-5">
                <h2 className="text-base font-semibold text-slate-900">Status Snapshot</h2>
                <div className="mt-3 grid gap-3 md:grid-cols-3">
                    <div className="rounded-2xl border border-sky-300 bg-sky-50 px-4 py-3">
                        <p className="text-xs uppercase tracking-wide text-sky-700">Confirmed</p>
                        <p className="mt-1 text-xl font-semibold text-sky-900">{formatNumber(kpis.confirmed_events ?? 0)}</p>
                    </div>
                    <div className="rounded-2xl border border-emerald-300 bg-emerald-50 px-4 py-3">
                        <p className="text-xs uppercase tracking-wide text-emerald-700">Completed</p>
                        <p className="mt-1 text-xl font-semibold text-emerald-900">{formatNumber(kpis.completed_events ?? 0)}</p>
                    </div>
                    <div className="rounded-2xl border border-rose-300 bg-rose-50 px-4 py-3">
                        <p className="text-xs uppercase tracking-wide text-rose-700">Cancelled</p>
                        <p className="mt-1 text-xl font-semibold text-rose-900">{formatNumber(kpis.cancelled_events ?? 0)}</p>
                    </div>
                </div>
            </section>
        </div>
    );
}
