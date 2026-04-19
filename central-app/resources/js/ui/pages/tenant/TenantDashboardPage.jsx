import { Link } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { fetchTenantEvents } from '../../../api/tenantApi';
import { formatNumber } from '../../../lib/formatters';
import { StatCard } from '../../components/StatCard';
import { StatusBadge } from '../../components/StatusBadge';

const workflowMap = [
    { title: 'Dashboard', path: '/', detail: 'KPI pulse and operational health snapshot.' },
    { title: 'Bookings Kanban', path: '/bookings', detail: 'Lead-to-close booking progression board.' },
    { title: 'Calendar Planner', path: '/calendar', detail: 'Month-level schedule orchestration and load balancing.' },
    { title: 'Menu Builder', path: '/menu-builder', detail: 'Package menu composition with cost controls.' },
    { title: 'Client CRM', path: '/clients', detail: 'Client records, notes, and relationship timeline.' },
    { title: 'Staff Scheduler', path: '/staff-scheduler', detail: 'Staff assignments, role windows, and conflict handling.' },
    { title: 'Invoices', path: '/invoices', detail: 'Payment and invoice lifecycle tracking.' },
    { title: 'Analytics', path: '/analytics', detail: 'Revenue, conversion, and status trend insights.' },
    { title: 'Appearance', path: '/appearance', detail: 'WordPress-style visual customization and publish flow.' },
    { title: 'Settings', path: '/settings', detail: 'Governance, defaults, and workspace controls.' },
];

export function TenantDashboardPage() {
    const eventsQuery = useQuery({
        queryKey: ['tenant-events', { status: '', month: '' }],
        queryFn: () => fetchTenantEvents(),
        staleTime: 1000 * 30,
    });

    if (eventsQuery.isPending) {
        return <div className="rounded-3xl app-shell-panel p-8 text-slate-700">Loading tenant dashboard...</div>;
    }

    if (eventsQuery.isError) {
        return (
            <div className="rounded-3xl border border-rose-300 bg-rose-50 p-8 text-rose-900">
                Failed to load tenant events. {eventsQuery.error?.message ?? ''}
            </div>
        );
    }

    const events = eventsQuery.data?.data ?? [];
    const totalGuests = events.reduce((carry, event) => carry + Number(event.guest_count ?? 0), 0);
    const upcoming = events.slice(0, 5);

    const statusCounts = events.reduce(
        (counts, event) => {
            const status = event.status ?? 'pending';

            return {
                ...counts,
                [status]: (counts[status] ?? 0) + 1,
            };
        },
        {},
    );

    return (
        <div className="space-y-7">
            <section className="app-shell-panel relative overflow-hidden rounded-[2rem] px-6 py-8 md:px-10">
                <div className="absolute -top-24 right-[-2rem] h-52 w-52 rounded-full bg-[color-mix(in_oklab,var(--primary-color)_28%,white_72%)] blur-3xl" />
                <p className="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Tenant Operations</p>
                <h1 className="hero-heading mt-2 text-3xl text-slate-900 md:text-5xl">Run your full tenant workflow from booking pipeline to post-event analytics.</h1>
                <div className="mt-6 flex flex-wrap gap-3">
                    <Link
                        to="/booking/new"
                        className="rounded-full bg-[var(--primary-color)] px-5 py-2 text-sm font-semibold text-white"
                    >
                        Create Booking
                    </Link>
                    <Link
                        to="/bookings"
                        className="rounded-full border border-slate-300 bg-white px-5 py-2 text-sm font-semibold text-slate-700"
                    >
                        Open Booking Board
                    </Link>
                </div>
            </section>

            <section className="grid gap-4 md:grid-cols-3">
                <StatCard title="Tracked Events" value={events.length} trend="Records in current tenant context" />
                <StatCard title="Confirmed" value={statusCounts.confirmed ?? 0} trend="Confirmed and locked in" />
                <StatCard title="Guests Booked" value={totalGuests} trend="Projected total guest volume" />
            </section>

            <section className="rounded-3xl border border-slate-200 bg-white p-5">
                <div className="flex items-center justify-between">
                    <h2 className="text-lg font-semibold text-slate-900">Tenant Workflow Blueprint</h2>
                    <span className="text-xs uppercase tracking-wide text-slate-500">10 modules</span>
                </div>

                <div className="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    {workflowMap.map((step, index) => (
                        <Link key={step.path} to={step.path} className="rounded-2xl border border-slate-200 bg-slate-50 p-3 hover:border-slate-300">
                            <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">Step {index + 1}</p>
                            <p className="mt-1 text-sm font-semibold text-slate-900">{step.title}</p>
                            <p className="mt-1 text-xs text-slate-600">{step.detail}</p>
                        </Link>
                    ))}
                </div>
            </section>

            <section className="app-shell-panel rounded-3xl p-6">
                <div className="flex items-center justify-between">
                    <h2 className="text-lg font-semibold text-slate-900">Upcoming Events</h2>
                    <span className="text-xs uppercase tracking-wide text-slate-500">{formatNumber(upcoming.length)} shown</span>
                </div>

                <div className="mt-4 space-y-3">
                    {upcoming.length ? (
                        upcoming.map((event) => (
                            <article key={event.id} className="rounded-2xl border border-slate-200 bg-white p-4">
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <h3 className="font-semibold text-slate-900">{event.event_name}</h3>
                                    <StatusBadge status={event.status} />
                                </div>
                                <p className="mt-1 text-sm text-slate-600">
                                    {event.event_date} · {event.location} · {formatNumber(event.guest_count)} guests
                                </p>
                                <p className="mt-2 text-sm text-slate-500">Client: {event.client?.full_name || 'N/A'}</p>
                            </article>
                        ))
                    ) : (
                        <div className="rounded-2xl border border-dashed border-slate-300 bg-white p-6 text-sm text-slate-600">
                            No tenant events yet. Start by creating your first booking.
                        </div>
                    )}
                </div>
            </section>
        </div>
    );
}
