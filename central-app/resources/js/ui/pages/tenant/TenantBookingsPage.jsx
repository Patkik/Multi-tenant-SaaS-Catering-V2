import { Link } from 'react-router-dom';
import { useMemo, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { fetchTenantEvents, updateTenantEventStatus } from '../../../api/tenantApi';
import { formatCurrency, formatNumber } from '../../../lib/formatters';
import { StatCard } from '../../components/StatCard';
import { StatusBadge } from '../../components/StatusBadge';

const boardColumns = [
    { key: 'pending', label: 'Leads / Inquiry', hint: 'Incoming requests awaiting qualification.' },
    { key: 'confirmed', label: 'Confirmed', hint: 'Approved bookings with deposits or contracts.' },
    { key: 'completed', label: 'Completed', hint: 'Delivered events ready for final wrap-up.' },
    { key: 'cancelled', label: 'Cancelled', hint: 'Dropped opportunities and lost bookings.' },
];

const statusOrder = boardColumns.map((column) => column.key);

export function TenantBookingsPage() {
    const queryClient = useQueryClient();
    const [statusFilter, setStatusFilter] = useState('all');
    const [monthFilter, setMonthFilter] = useState('');
    const [viewMode, setViewMode] = useState('kanban');
    const [searchTerm, setSearchTerm] = useState('');
    const [statusUpdateError, setStatusUpdateError] = useState('');

    const eventsQuery = useQuery({
        queryKey: ['tenant-events', { statusFilter, monthFilter }],
        queryFn: () =>
            fetchTenantEvents({
                status: statusFilter === 'all' ? '' : statusFilter,
                month: monthFilter,
            }),
        staleTime: 1000 * 30,
    });

    const events = useMemo(() => eventsQuery.data?.data ?? [], [eventsQuery.data]);

    const updateStatusMutation = useMutation({
        mutationFn: ({ eventId, status }) => updateTenantEventStatus(eventId, status),
        onSuccess: async () => {
            setStatusUpdateError('');
            await queryClient.invalidateQueries({ queryKey: ['tenant-events'] });
            await queryClient.invalidateQueries({ queryKey: ['tenant-analytics'] });
        },
        onError: (error) => {
            setStatusUpdateError(error?.response?.data?.message ?? 'Unable to update booking status.');
        },
    });

    const normalizedEvents = useMemo(() => {
        return events.map((event) => ({
            ...event,
            board_status: event.status ?? 'pending',
        }));
    }, [events]);

    const filteredEvents = useMemo(() => {
        const query = searchTerm.trim().toLowerCase();

        if (!query) {
            return normalizedEvents;
        }

        return normalizedEvents.filter((event) => {
            const haystack = `${event.event_name ?? ''} ${event.location ?? ''} ${event.client?.full_name ?? ''}`.toLowerCase();

            return haystack.includes(query);
        });
    }, [normalizedEvents, searchTerm]);

    const groupedEvents = useMemo(() => {
        return boardColumns.reduce((groups, column) => {
            groups[column.key] = filteredEvents.filter((event) => event.board_status === column.key);
            return groups;
        }, {});
    }, [filteredEvents]);

    const totalGuests = filteredEvents.reduce((carry, event) => carry + Number(event.guest_count ?? 0), 0);
    const quotedPipeline = filteredEvents.reduce((carry, event) => carry + Number(event.quoted_total ?? 0), 0);

    function moveBooking(eventId, direction) {
        const currentStatus = normalizedEvents.find((event) => event.id === eventId)?.board_status ?? 'pending';
        const currentIndex = statusOrder.indexOf(currentStatus);

        if (currentIndex < 0) {
            return;
        }

        const nextIndex = direction === 'forward' ? currentIndex + 1 : currentIndex - 1;

        if (nextIndex < 0 || nextIndex >= statusOrder.length) {
            return;
        }

        const nextStatus = statusOrder[nextIndex];

        updateStatusMutation.mutate({
            eventId,
            status: nextStatus,
        });
    }

    return (
        <div className="space-y-6">
            <section className="app-shell-panel rounded-[2rem] p-6">
                <p className="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Bookings Workspace</p>
                <h1 className="hero-heading mt-2 text-3xl text-slate-900 md:text-4xl">Visualize your pipeline from inquiry to completed event in one command board.</h1>

                <div className="mt-5 flex flex-wrap gap-2">
                    <Link
                        to="/booking/new"
                        className="rounded-full bg-[var(--primary-color)] px-4 py-2 text-sm font-semibold text-white"
                    >
                        New Booking
                    </Link>
                    <Link
                        to="/calendar"
                        className="rounded-full border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700"
                    >
                        Open Calendar
                    </Link>
                </div>
            </section>

            <section className="grid gap-4 md:grid-cols-3">
                <StatCard title="Booking Cards" value={filteredEvents.length} trend="Records shown in current view" />
                <StatCard title="Guest Pipeline" value={totalGuests} trend="Total expected guests" />
                <StatCard title="Quoted Revenue" value={formatCurrency(quotedPipeline)} trend="Projected deal value" kind="text" />
            </section>

            <section className="app-shell-panel rounded-3xl p-5">
                <div className="grid gap-3 lg:grid-cols-[1fr_1fr_1fr_auto]">
                    <label className="space-y-1 text-sm font-semibold text-slate-700">
                        Search
                        <input
                            value={searchTerm}
                            onChange={(event) => setSearchTerm(event.target.value)}
                            placeholder="Event, location, or client"
                            className="w-full rounded-xl border border-slate-300 bg-white px-3 py-2"
                        />
                    </label>

                    <label className="space-y-1 text-sm font-semibold text-slate-700">
                        Status
                        <select
                            value={statusFilter}
                            onChange={(event) => setStatusFilter(event.target.value)}
                            className="w-full rounded-xl border border-slate-300 bg-white px-3 py-2"
                        >
                            <option value="all">All statuses</option>
                            {statusOrder.map((status) => (
                                <option key={status} value={status}>
                                    {status[0].toUpperCase() + status.slice(1)}
                                </option>
                            ))}
                        </select>
                    </label>

                    <label className="space-y-1 text-sm font-semibold text-slate-700">
                        Month
                        <input
                            type="month"
                            value={monthFilter}
                            onChange={(event) => setMonthFilter(event.target.value)}
                            className="w-full rounded-xl border border-slate-300 bg-white px-3 py-2"
                        />
                    </label>

                    <div className="flex items-end gap-2">
                        <button
                            type="button"
                            onClick={() => setViewMode('kanban')}
                            className={`rounded-xl px-3 py-2 text-sm font-semibold ${
                                viewMode === 'kanban'
                                    ? 'bg-[var(--primary-color)] text-white'
                                    : 'border border-slate-300 bg-white text-slate-700'
                            }`}
                        >
                            Kanban
                        </button>
                        <button
                            type="button"
                            onClick={() => setViewMode('list')}
                            className={`rounded-xl px-3 py-2 text-sm font-semibold ${
                                viewMode === 'list'
                                    ? 'bg-[var(--primary-color)] text-white'
                                    : 'border border-slate-300 bg-white text-slate-700'
                            }`}
                        >
                            List
                        </button>
                    </div>
                </div>

                {statusUpdateError ? (
                    <p className="mt-3 rounded-xl border border-rose-300 bg-rose-50 px-3 py-2 text-xs text-rose-900">{statusUpdateError}</p>
                ) : null}
            </section>

            {eventsQuery.isPending ? (
                <div className="rounded-3xl border border-slate-200 bg-white p-6 text-sm text-slate-600">Loading booking workflow...</div>
            ) : null}

            {eventsQuery.isError ? (
                <div className="rounded-3xl border border-rose-300 bg-rose-50 p-6 text-sm text-rose-900">
                    Unable to load bookings. {eventsQuery.error?.message ?? ''}
                </div>
            ) : null}

            {!eventsQuery.isPending && !eventsQuery.isError ? (
                viewMode === 'kanban' ? (
                    <section className="grid gap-4 xl:grid-cols-4">
                        {boardColumns.map((column) => (
                            <article key={column.key} className="overflow-hidden rounded-3xl border border-slate-200 bg-white">
                                <header className="border-b border-slate-100 px-4 py-3">
                                    <div className="flex items-center justify-between">
                                        <h2 className="text-sm font-semibold text-slate-900">{column.label}</h2>
                                        <span className="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-700">
                                            {formatNumber(groupedEvents[column.key]?.length ?? 0)}
                                        </span>
                                    </div>
                                    <p className="mt-1 text-xs text-slate-500">{column.hint}</p>
                                </header>

                                <div className="max-h-[34rem] space-y-3 overflow-y-auto p-3">
                                    {(groupedEvents[column.key] ?? []).length === 0 ? (
                                        <div className="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-3 py-4 text-xs text-slate-500">
                                            No bookings in this stage.
                                        </div>
                                    ) : (
                                        (groupedEvents[column.key] ?? []).map((event) => {
                                            const statusIndex = statusOrder.indexOf(event.board_status);

                                            return (
                                                <div key={event.id} className="rounded-2xl border border-slate-200 bg-slate-50 p-3">
                                                    <div className="flex items-start justify-between gap-2">
                                                        <h3 className="text-sm font-semibold text-slate-900">{event.event_name}</h3>
                                                        <StatusBadge status={event.board_status} />
                                                    </div>
                                                    <p className="mt-1 text-xs text-slate-600">
                                                        {event.event_date || 'No date'} · {event.location || 'No location'}
                                                    </p>
                                                    <p className="mt-2 text-xs text-slate-600">Client: {event.client?.full_name || 'N/A'}</p>
                                                    <p className="text-xs text-slate-600">
                                                        Guests: {formatNumber(event.guest_count)} · Quote: {formatCurrency(event.quoted_total ?? 0)}
                                                    </p>

                                                    <div className="mt-3 flex gap-2">
                                                        <button
                                                            type="button"
                                                            onClick={() => moveBooking(event.id, 'backward')}
                                                            disabled={statusIndex <= 0 || updateStatusMutation.isPending}
                                                            className="rounded-lg border border-slate-300 bg-white px-2.5 py-1 text-xs font-semibold text-slate-700 disabled:cursor-not-allowed disabled:opacity-50"
                                                        >
                                                            Previous
                                                        </button>
                                                        <button
                                                            type="button"
                                                            onClick={() => moveBooking(event.id, 'forward')}
                                                            disabled={statusIndex >= statusOrder.length - 1 || updateStatusMutation.isPending}
                                                            className="rounded-lg border border-slate-300 bg-white px-2.5 py-1 text-xs font-semibold text-slate-700 disabled:cursor-not-allowed disabled:opacity-50"
                                                        >
                                                            Next
                                                        </button>
                                                    </div>
                                                </div>
                                            );
                                        })
                                    )}
                                </div>
                            </article>
                        ))}
                    </section>
                ) : (
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
                                        <th className="px-4 py-3">Board Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {filteredEvents.length === 0 ? (
                                        <tr>
                                            <td className="px-4 py-6 text-slate-600" colSpan={6}>
                                                No bookings found.
                                            </td>
                                        </tr>
                                    ) : (
                                        filteredEvents.map((event) => (
                                            <tr key={event.id} className="border-t border-slate-100">
                                                <td className="px-4 py-3 font-semibold text-slate-900">{event.event_name}</td>
                                                <td className="px-4 py-3 text-slate-700">{event.event_date || 'No date'}</td>
                                                <td className="px-4 py-3 text-slate-700">{event.client?.full_name || 'N/A'}</td>
                                                <td className="px-4 py-3 text-slate-700">{formatNumber(event.guest_count)}</td>
                                                <td className="px-4 py-3 text-slate-700">{formatCurrency(event.quoted_total ?? 0)}</td>
                                                <td className="px-4 py-3">
                                                    <StatusBadge status={event.board_status} />
                                                </td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </section>
                )
            ) : null}
        </div>
    );
}
