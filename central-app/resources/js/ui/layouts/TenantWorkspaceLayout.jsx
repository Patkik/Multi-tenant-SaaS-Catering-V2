import { NavLink, Outlet } from 'react-router-dom';
import { useMemo } from 'react';
import { useTenantContext } from '../../providers/TenantProvider';
import { useAppStore } from '../../store/appStore';
import { titleCase } from '../../lib/formatters';

const navigation = [
    { to: '/', label: 'Dashboard', module: 'dashboard', end: true },
    { to: '/bookings', label: 'Bookings Kanban', module: 'events', feature: 'event_management' },
    { to: '/calendar', label: 'Calendar Planner', module: 'events', feature: 'event_management' },
    { to: '/menu-builder', label: 'Menu Builder', module: 'packages' },
    { to: '/clients', label: 'Client CRM', module: 'clients' },
    {
        to: '/staff-scheduler',
        label: 'Staff Scheduler',
        module: 'assignments',
        feature: 'staff_assignment',
        lockedReason: 'Upgrade to Starter or higher to assign event staff.',
    },
    { to: '/invoices', label: 'Invoices', module: 'payments' },
    {
        to: '/analytics',
        label: 'Analytics',
        module: 'analytics',
        feature: 'advanced_analytics',
        lockedReason: 'Upgrade to Business or Enterprise to unlock analytics.',
    },
    {
        to: '/appearance',
        label: 'Appearance',
        module: 'branding',
        feature: 'branding_controls',
        lockedReason: 'Branding controls are available on Business and Enterprise plans.',
    },
    { to: '/users', label: 'Users', module: 'users' },
    { to: '/settings', label: 'Settings', module: 'users' },
];

export function TenantWorkspaceLayout() {
    const { tenantProfile, authUser, signOut } = useTenantContext();
    const { mobileSidebarOpen, setMobileSidebarOpen, toggleMobileSidebar } = useAppStore();

    const enabledFeatures = tenantProfile?.enabled_features ?? [];
    const allowedModules = authUser?.modules ?? [];
    const currentRole = authUser?.role ?? tenantProfile?.active_role ?? 'Guest';

    const navigationItems = useMemo(() => {
        return navigation
            .filter((item) => allowedModules.includes(item.module))
            .map((item) => {
                const featureEnabled = item.feature ? enabledFeatures.includes(item.feature) : true;

                return {
                    ...item,
                    featureEnabled,
                };
            });
    }, [allowedModules, enabledFeatures]);

    return (
        <div className="mx-auto flex min-h-screen w-full max-w-[1500px] gap-4 px-3 py-4 md:px-5">
            <button
                type="button"
                onClick={toggleMobileSidebar}
                className="fixed bottom-4 right-4 z-40 rounded-full bg-[var(--primary-color)] px-4 py-2 text-sm font-semibold text-white shadow-lg md:hidden"
            >
                Menu
            </button>

            {mobileSidebarOpen ? (
                <button
                    type="button"
                    aria-label="Close sidebar"
                    className="fixed inset-0 z-30 bg-slate-950/40 md:hidden"
                    onClick={() => setMobileSidebarOpen(false)}
                />
            ) : null}

            <aside
                className={`app-shell-panel fixed left-3 top-3 z-40 h-[calc(100vh-1.5rem)] w-72 rounded-[2rem] p-4 transition md:static md:h-auto md:translate-x-0 ${
                    mobileSidebarOpen ? 'translate-x-0' : '-translate-x-[120%]'
                }`}
            >
                <div className="flex items-center gap-3">
                    {tenantProfile?.branding?.logo_url ? (
                        <img
                            src={tenantProfile.branding.logo_url}
                            alt="Tenant logo"
                            className="h-10 w-10 rounded-xl border border-slate-200 object-cover"
                        />
                    ) : (
                        <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-[var(--primary-color)] text-sm font-bold text-white">
                            {tenantProfile?.company_name?.[0] ?? 'T'}
                        </div>
                    )}
                    <div>
                        <p className="text-xs uppercase tracking-wide text-slate-500">Tenant Workspace</p>
                        <p className="font-semibold text-slate-900">{tenantProfile?.company_name ?? 'CaterPro Tenant'}</p>
                    </div>
                </div>

                <div className="mt-4 rounded-2xl border border-slate-200 bg-white p-3">
                    <p className="text-xs uppercase tracking-wide text-slate-500">Subscription</p>
                    <p className="mt-1 text-sm font-semibold text-slate-900">{titleCase(tenantProfile?.plan ?? 'free')} Plan</p>
                    <p className="mt-1 text-xs text-slate-600">
                        {tenantProfile?.plan_details?.monthly_active_event_limit
                            ? `${tenantProfile.plan_details.monthly_active_event_limit} active events monthly`
                            : 'Unlimited active events'}
                    </p>
                </div>

                <div className="mt-4 rounded-2xl border border-slate-200 bg-white p-3">
                    <p className="text-xs uppercase tracking-wide text-slate-500">Signed in as</p>
                    <p className="mt-1 text-sm font-semibold text-slate-900">{authUser?.display_name ?? 'Guest'}</p>
                    <p className="text-xs text-slate-600">Role: {currentRole}</p>
                    <button
                        type="button"
                        onClick={signOut}
                        className="mt-3 rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                    >
                        Sign Out
                    </button>
                </div>

                <nav className="mt-5 space-y-2">
                    {navigationItems.map((item) => {
                        if (!item.featureEnabled) {
                            return (
                                <div
                                    key={item.to}
                                    className="rounded-xl border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-900"
                                >
                                    <p className="font-semibold">{item.label}</p>
                                    <p className="mt-1 text-xs text-amber-800">{item.lockedReason}</p>
                                </div>
                            );
                        }

                        return (
                            <NavLink
                                key={item.to}
                                to={item.to}
                                end={item.end}
                                onClick={() => setMobileSidebarOpen(false)}
                                className={({ isActive }) =>
                                    `block rounded-xl px-3 py-2 text-sm font-semibold transition ${
                                        isActive
                                            ? 'bg-[var(--primary-color)] text-white'
                                            : 'border border-transparent text-slate-700 hover:border-slate-300 hover:bg-white'
                                    }`
                                }
                            >
                                {item.label}
                            </NavLink>
                        );
                    })}
                </nav>
            </aside>

            <div className="flex min-w-0 flex-1 flex-col">
                <header className="app-shell-panel rounded-[2rem] p-4">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p className="text-xs uppercase tracking-[0.14em] text-slate-500">Current Role</p>
                            <h1 className="hero-heading text-2xl text-slate-900">{currentRole} Console</h1>
                        </div>
                        <div className="rounded-full border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700">
                            Features Enabled: {enabledFeatures.length}
                        </div>
                    </div>
                </header>

                <main className="mt-5 min-w-0 pb-8">
                    <Outlet />
                </main>
            </div>
        </div>
    );
}
