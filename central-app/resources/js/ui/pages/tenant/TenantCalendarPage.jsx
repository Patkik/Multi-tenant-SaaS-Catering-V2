import { useMemo, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { fetchTenantEvents } from '../../../api/tenantApi';
import { formatNumber } from '../../../lib/formatters';
import { StatusBadge } from '../../components/StatusBadge';

const weekDays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

function getMonthKey(date) {
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
}

function toIsoDate(date) {
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}

export function TenantCalendarPage() {
    const [monthCursor, setMonthCursor] = useState(() => {
        const now = new Date();
        return new Date(now.getFullYear(), now.getMonth(), 1);
    });

    const [selectedDate, setSelectedDate] = useState(() => toIsoDate(new Date()));

    const monthFilter = getMonthKey(monthCursor);

    const eventsQuery = useQuery({
        queryKey: ['tenant-events', { status: '', month: monthFilter }],
        queryFn: () => fetchTenantEvents({ month: monthFilter }),
        staleTime: 1000 * 30,
    });

    const events = useMemo(() => eventsQuery.data?.data ?? [], [eventsQuery.data]);

    const eventsByDate = useMemo(() => {
        return events.reduce((carry, event) => {
            const key = String(event.event_date ?? '').slice(0, 10);

            if (!key) {
                return carry;
            }

            if (!carry[key]) {
                carry[key] = [];
            }

            carry[key].push(event);
            return carry;
        }, {});
    }, [events]);

    const monthGrid = useMemo(() => {
        const firstWeekDay = new Date(monthCursor.getFullYear(), monthCursor.getMonth(), 1).getDay();
        const totalDays = new Date(monthCursor.getFullYear(), monthCursor.getMonth() + 1, 0).getDate();
        const cells = [];

        for (let index = 0; index < firstWeekDay; index += 1) {
            cells.push({ key: `pad-${index}`, empty: true });
        }

        for (let day = 1; day <= totalDays; day += 1) {
            const current = new Date(monthCursor.getFullYear(), monthCursor.getMonth(), day);
            const iso = toIsoDate(current);

            cells.push({
                key: iso,
                iso,
                day,
                events: eventsByDate[iso] ?? [],
            });
        }

        return cells;
    }, [eventsByDate, monthCursor]);

    const selectedDayEvents = eventsByDate[selectedDate] ?? [];

    const monthLabel = monthCursor.toLocaleString('en-US', {
        month: 'long',
        year: 'numeric',
    });

    function jumpMonth(offset) {
        setMonthCursor((previous) => new Date(previous.getFullYear(), previous.getMonth() + offset, 1));
    }

    return (
        <div className="space-y-6">
            <section className="app-shell-panel rounded-[2rem] p-6">
                <p className="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Calendar Planner</p>
                <h1 className="hero-heading mt-2 text-3xl text-slate-900 md:text-4xl">Coordinate every event date, staffing horizon, and operational window visually.</h1>
            </section>

            <section className="grid gap-4 xl:grid-cols-[2fr_1fr]">
                <article className="overflow-hidden rounded-3xl border border-slate-200 bg-white">
                    <header className="flex items-center justify-between border-b border-slate-100 px-4 py-3">
                        <div>
                            <p className="text-xs uppercase tracking-wide text-slate-500">Month View</p>
                            <h2 className="text-lg font-semibold text-slate-900">{monthLabel}</h2>
                        </div>
                        <div className="flex gap-2">
                            <button
                                type="button"
                                onClick={() => jumpMonth(-1)}
                                className="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm font-semibold text-slate-700"
                            >
                                Previous
                            </button>
                            <button
                                type="button"
                                onClick={() => jumpMonth(1)}
                                className="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm font-semibold text-slate-700"
                            >
                                Next
                            </button>
                        </div>
                    </header>

                    {eventsQuery.isPending ? (
                        <p className="px-4 py-5 text-sm text-slate-600">Loading monthly events...</p>
                    ) : null}

                    {eventsQuery.isError ? (
                        <p className="px-4 py-5 text-sm text-rose-700">Unable to load calendar events.</p>
                    ) : null}

                    {!eventsQuery.isPending && !eventsQuery.isError ? (
                        <div className="p-3">
                            <div className="grid grid-cols-7 gap-2 text-center text-xs font-semibold uppercase tracking-wide text-slate-500">
                                {weekDays.map((day) => (
                                    <div key={day} className="py-1">
                                        {day}
                                    </div>
                                ))}
                            </div>

                            <div className="mt-2 grid grid-cols-7 gap-2">
                                {monthGrid.map((cell) => {
                                    if (cell.empty) {
                                        return <div key={cell.key} className="min-h-[6.2rem] rounded-xl border border-dashed border-slate-200 bg-slate-50" />;
                                    }

                                    const isSelected = selectedDate === cell.iso;

                                    return (
                                        <button
                                            key={cell.key}
                                            type="button"
                                            onClick={() => setSelectedDate(cell.iso)}
                                            className={`min-h-[6.2rem] rounded-xl border p-2 text-left transition ${
                                                isSelected
                                                    ? 'border-[var(--primary-color)] bg-[color-mix(in_oklab,var(--primary-color)_12%,white_88%)]'
                                                    : 'border-slate-200 bg-white hover:border-slate-300'
                                            }`}
                                        >
                                            <div className="flex items-center justify-between">
                                                <span className="text-sm font-semibold text-slate-900">{cell.day}</span>
                                                {cell.events.length > 0 ? (
                                                    <span className="rounded-full bg-slate-900 px-1.5 py-0.5 text-[10px] font-semibold text-white">
                                                        {cell.events.length}
                                                    </span>
                                                ) : null}
                                            </div>
                                            <div className="mt-1 space-y-1">
                                                {cell.events.slice(0, 2).map((event) => (
                                                    <p key={event.id} className="truncate rounded bg-slate-100 px-1.5 py-0.5 text-[10px] text-slate-700">
                                                        {event.event_name}
                                                    </p>
                                                ))}
                                            </div>
                                        </button>
                                    );
                                })}
                            </div>
                        </div>
                    ) : null}
                </article>

                <article className="overflow-hidden rounded-3xl border border-slate-200 bg-white">
                    <header className="border-b border-slate-100 px-4 py-3">
                        <p className="text-xs uppercase tracking-wide text-slate-500">Selected Day</p>
                        <h2 className="text-base font-semibold text-slate-900">{selectedDate}</h2>
                    </header>

                    <div className="space-y-3 p-4">
                        <div className="rounded-2xl border border-slate-200 bg-slate-50 px-3 py-2">
                            <p className="text-xs uppercase tracking-wide text-slate-500">Events on date</p>
                            <p className="mt-1 text-lg font-semibold text-slate-900">{formatNumber(selectedDayEvents.length)}</p>
                        </div>

                        {selectedDayEvents.length === 0 ? (
                            <div className="rounded-2xl border border-dashed border-slate-300 bg-white px-3 py-4 text-sm text-slate-600">
                                No events scheduled for this date.
                            </div>
                        ) : (
                            selectedDayEvents.map((event) => (
                                <div key={event.id} className="rounded-2xl border border-slate-200 bg-white p-3">
                                    <div className="flex items-start justify-between gap-2">
                                        <h3 className="text-sm font-semibold text-slate-900">{event.event_name}</h3>
                                        <StatusBadge status={event.status} />
                                    </div>
                                    <p className="mt-1 text-xs text-slate-600">{event.location || 'No venue set'}</p>
                                    <p className="text-xs text-slate-600">Client: {event.client?.full_name || 'N/A'}</p>
                                    <p className="text-xs text-slate-600">Guests: {formatNumber(event.guest_count)}</p>
                                </div>
                            ))
                        )}
                    </div>
                </article>
            </section>
        </div>
    );
}
