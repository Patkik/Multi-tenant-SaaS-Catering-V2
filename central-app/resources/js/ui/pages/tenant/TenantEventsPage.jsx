import { useMemo, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { fetchTenantEvents } from '../../../api/tenantApi';
import { formatCurrency, formatNumber } from '../../../lib/formatters';
import { StatusBadge } from '../../components/StatusBadge';

const statusFilters = ['', 'pending', 'confirmed', 'completed', 'cancelled'];

export function TenantEventsPage() {
    const [status, setStatus] = useState('');
    const [month, setMonth] = useState('');

    const eventsQuery = useQuery({
        queryKey: ['tenant-events', { status, month }],
        queryFn: () => fetchTenantEvents({ status, month }),
        staleTime: 1000 * 30,
    });

    const events = useMemo(() => eventsQuery.data?.data ?? [], [eventsQuery.data]);

    return (
        <div className="space-y-6">
            <section className="app-shell-panel rounded-3xl p-6">
                <p className="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Event Ledger</p>
                <h1 className="hero-heading mt-2 text-3xl text-slate-900 md:text-4xl">Monitor event lifecycle, client details, and quoted totals.</h1>

                <div className="mt-5 grid gap-3 md:grid-cols-3">
                    <label className="space-y-1 text-sm font-semibold text-slate-700">
                        Status
                        <select
                            className="w-full rounded-xl border border-slate-300 bg-white px-3 py-2"
                            value={status}
                            onChange={(event) => setStatus(event.target.value)}
                        >
                            {statusFilters.map((filter) => (
                                <option key={filter || 'all'} value={filter}>
                                    {filter ? filter[0].toUpperCase() + filter.slice(1) : 'All Statuses'}
                                </option>
                            ))}
                        </select>
                    </label>
                    <label className="space-y-1 text-sm font-semibold text-slate-700">
                        Month
                        <input
                            type="month"
                            className="w-full rounded-xl border border-slate-300 bg-white px-3 py-2"
                            value={month}
                            onChange={(event) => setMonth(event.target.value)}
                        />
                    </label>
                    <div className="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-600">
                        <p className="font-semibold text-slate-900">{formatNumber(events.length)} matching events</p>
                        <p className="mt-1 text-xs">Use filters to narrow by status and month.</p>
                    </div>
                </div>
            </section>

            <section className="overflow-hidden rounded-3xl border border-slate-200 bg-white">
                <div className="overflow-x-auto">
                    <table className="min-w-full text-sm">
                        <thead className="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th className="px-4 py-3">Event</th>
                                <th className="px-4 py-3">Date</th>
                                <th className="px-4 py-3">Client</th>
                                <th className="px-4 py-3">Guests</th>
                                <th className="px-4 py-3">Quoted Total</th>
                                <th className="px-4 py-3">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            {eventsQuery.isPending ? (
                                <tr>
                                    <td className="px-4 py-6 text-slate-600" colSpan={6}>
                                        Loading events...
                                    </td>
                                </tr>
                            ) : eventsQuery.isError ? (
                                <tr>
                                    <td className="px-4 py-6 text-rose-700" colSpan={6}>
                                        Failed to load events. {eventsQuery.error?.message ?? ''}
                                    </td>
                                </tr>
                            ) : events.length === 0 ? (
                                <tr>
                                    <td className="px-4 py-6 text-slate-600" colSpan={6}>
                                        No events found for your selected filters.
                                    </td>
                                </tr>
                            ) : (
                                events.map((event) => (
                                    <tr key={event.id} className="border-t border-slate-100">
                                        <td className="px-4 py-3 font-semibold text-slate-900">{event.event_name}</td>
                                        <td className="px-4 py-3 text-slate-700">{event.event_date}</td>
                                        <td className="px-4 py-3 text-slate-700">{event.client?.full_name || 'N/A'}</td>
                                        <td className="px-4 py-3 text-slate-700">{formatNumber(event.guest_count)}</td>
                                        <td className="px-4 py-3 text-slate-700">{formatCurrency(event.quoted_total ?? 0)}</td>
                                        <td className="px-4 py-3">
                                            <StatusBadge status={event.status} />
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    );
}
