import { Link, NavLink, Outlet } from 'react-router-dom';
import { useMemo, useState } from 'react';
import { useMutation, useQuery } from '@tanstack/react-query';
import { applyTenantAppUpdate, fetchTenantAppUpdates } from '../../api/tenantApi';
import { useTenantContext } from '../../providers/TenantProvider';
import { useAppStore } from '../../store/appStore';
import { titleCase } from '../../lib/formatters';

const navigation = [
    { to: '/', label: 'Dashboard', module: 'dashboard', end: true },
    { to: '/support', label: 'Support', alwaysVisible: true },
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

const buildTimeVersion = import.meta.env.VITE_APP_VERSION || '0.0.0';

export function TenantWorkspaceLayout() {
    const { tenantProfile, authUser, signOut } = useTenantContext();
    const { mobileSidebarOpen, setMobileSidebarOpen, toggleMobileSidebar } = useAppStore();
    const [hasCheckedUpdates, setHasCheckedUpdates] = useState(false);
    const [updateFeedback, setUpdateFeedback] = useState(null);

    const appUpdatesQuery = useQuery({
        queryKey: ['tenant-app-updates'],
        queryFn: fetchTenantAppUpdates,
        retry: false,
        enabled: false,
    });

    const applyUpdateMutation = useMutation({
        mutationFn: applyTenantAppUpdate,
        onSuccess: async (result) => {
            const status = String(result?.status ?? 'info');
            const message = String(result?.message ?? 'Update action completed.');

            setUpdateFeedback({
                status,
                message,
            });

            setHasCheckedUpdates(true);
            await appUpdatesQuery.refetch();

            if (status === 'manual_required' && result?.release_url) {
                window.open(result.release_url, '_blank', 'noopener,noreferrer');
            }
        },
        onError: (error) => {
            const message = String(error?.response?.data?.message || 'Failed to trigger update command.');

            setUpdateFeedback({
                status: 'failed',
                message,
            });
        },
    });

    const enabledFeatures = tenantProfile?.enabled_features ?? [];
    const allowedModules = authUser?.modules ?? [];
    const tenantPermissions = authUser?.permissions ?? [];
    const currentRole = authUser?.role ?? tenantProfile?.active_role ?? 'Guest';
    const signedInDisplayName = authUser?.display_name ?? 'Guest';
    const signedInAvatarUrl = authUser?.avatar_url ?? '';
    const signedInInitial = signedInDisplayName.trim().charAt(0)?.toUpperCase() || 'G';
    const displayedAppVersion = tenantProfile?.app_version || buildTimeVersion;
    const updateInfo = hasCheckedUpdates ? appUpdatesQuery.data : null;
    const hasAvailableUpdate = Boolean(updateInfo?.enabled && updateInfo?.update_available);
    const latestVersionLabel = updateInfo?.latest_tag || updateInfo?.latest_version;
    const canApplyAutomatically = Boolean(updateInfo?.can_apply);
    const canApplyUpdate = tenantPermissions.includes('settings.manage');
    const isCheckingUpdates = appUpdatesQuery.fetchStatus === 'fetching';
    const isApplyingUpdate = applyUpdateMutation.isPending;

    const updateFeedbackStyle = useMemo(() => {
        if (!updateFeedback) {
            return null;
        }

        if (updateFeedback.status === 'applied') {
            return {
                borderColor: '#1D9E75',
                backgroundColor: '#E1F5EE',
                color: '#085041',
            };
        }

        if (updateFeedback.status === 'failed') {
            return {
                borderColor: '#D85A30',
                backgroundColor: '#FAECE7',
                color: '#712B13',
            };
        }

        return {
            borderColor: '#EF9F27',
            backgroundColor: '#FAEEDA',
            color: '#633806',
        };
    }, [updateFeedback]);

    const navigationItems = useMemo(() => {
        return navigation
            .filter((item) => item.alwaysVisible || allowedModules.includes(item.module))
            .map((item) => {
                const featureEnabled = item.feature ? enabledFeatures.includes(item.feature) : true;

                return {
                    ...item,
                    featureEnabled,
                };
            });
    }, [allowedModules, enabledFeatures]);

    const handleApplyUpdate = () => {
        if (!canApplyUpdate) {
            if (updateInfo?.release_url) {
                window.open(updateInfo.release_url, '_blank', 'noopener,noreferrer');
                setUpdateFeedback({
                    status: 'manual_required',
                    message: 'Only tenant admins can apply updates from this dashboard. Release instructions opened in a new tab.',
                });
                return;
            }

            setUpdateFeedback({
                status: 'manual_required',
                message: 'Release link is not available right now. Ask an admin to review central app updates.',
            });

            return;
        }

        setUpdateFeedback(null);
        applyUpdateMutation.mutate();
    };

    const handleCheckForUpdates = async () => {
        setUpdateFeedback(null);

        const result = await appUpdatesQuery.refetch();
        setHasCheckedUpdates(true);

        if (result.error) {
            const message = String(result.error?.response?.data?.message || 'Failed to check for updates.');

            setUpdateFeedback({
                status: 'failed',
                message,
            });

            return;
        }

        if (!result.data?.enabled) {
            setUpdateFeedback({
                status: 'manual_required',
                message: String(result.data?.error || 'Update checks are not configured yet.'),
            });

            return;
        }

        if (!result.data?.update_available) {
            setUpdateFeedback({
                status: 'up_to_date',
                message: 'No new release found. This system is up to date.',
            });
        }
    };

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
                        <span className="mt-1 inline-flex rounded-full border border-slate-300 bg-white px-2 py-0.5 text-[10px] font-semibold uppercase leading-none text-slate-700">
                            v{displayedAppVersion}
                        </span>
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
                    <div className="mt-2 flex items-center gap-3">
                        {signedInAvatarUrl ? (
                            <img
                                src={signedInAvatarUrl}
                                alt={`${signedInDisplayName} avatar`}
                                className="h-10 w-10 rounded-full border border-slate-200 object-cover"
                            />
                        ) : (
                            <div className="flex h-10 w-10 items-center justify-center rounded-full bg-[var(--primary-color)] text-sm font-semibold text-white">
                                {signedInInitial}
                            </div>
                        )}
                        <div>
                            <p className="text-sm font-semibold text-slate-900">{signedInDisplayName}</p>
                            <p className="text-xs text-slate-600">Role: {currentRole}</p>
                        </div>
                    </div>
                    <div className="mt-3 flex items-center gap-2">
                        <Link
                            to="/profile"
                            onClick={() => setMobileSidebarOpen(false)}
                            className="rounded-lg border border-slate-900 bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-800"
                        >
                            Profile
                        </Link>
                        <button
                            type="button"
                            onClick={signOut}
                            className="cursor-pointer rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                        >
                            Sign Out
                        </button>
                    </div>
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
                        <div className="flex items-center gap-2">
                            <div className="rounded-full border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700">
                                Features Enabled: {enabledFeatures.length}
                            </div>
                            <button
                                type="button"
                                onClick={handleCheckForUpdates}
                                disabled={isCheckingUpdates || isApplyingUpdate}
                                className="cursor-pointer rounded-full border px-2.5 py-1 text-[10px] font-semibold uppercase leading-none transition disabled:cursor-not-allowed disabled:opacity-60"
                                style={{
                                    borderColor: '#378ADD',
                                    backgroundColor: '#E6F1FB',
                                    color: '#0C447C',
                                }}
                                title="Check whether a newer release is available"
                            >
                                {isCheckingUpdates ? 'Checking...' : 'Check for Update'}
                            </button>
                            {hasAvailableUpdate ? (
                                <button
                                    type="button"
                                    onClick={handleApplyUpdate}
                                    className="cursor-pointer rounded-full border px-2.5 py-1 text-[10px] font-semibold uppercase leading-none"
                                    style={{
                                        borderColor: '#D85A30',
                                        backgroundColor: '#FAECE7',
                                        color: '#712B13',
                                    }}
                                    title={latestVersionLabel
                                        ? `Latest release: ${latestVersionLabel}. Click to ${canApplyAutomatically && canApplyUpdate ? 'apply' : 'open release notes'}.`
                                        : `A newer release is available. Click to ${canApplyAutomatically && canApplyUpdate ? 'apply it' : 'open release notes'}.`}
                                >
                                    Update Available
                                </button>
                            ) : null}
                            {hasAvailableUpdate ? (
                                <button
                                    type="button"
                                    onClick={handleApplyUpdate}
                                    disabled={isApplyingUpdate && canApplyUpdate}
                                    className="cursor-pointer rounded-full border px-2.5 py-1 text-[10px] font-semibold uppercase leading-none transition disabled:cursor-not-allowed disabled:opacity-60"
                                    style={{
                                        borderColor: '#378ADD',
                                        backgroundColor: '#E6F1FB',
                                        color: '#0C447C',
                                    }}
                                    title={canApplyUpdate
                                        ? (canApplyAutomatically ? 'Apply update command now' : 'Open release instructions for manual update')
                                        : 'Open release instructions (tenant admin required for one-click apply)'}
                                >
                                    {isApplyingUpdate
                                        ? 'Updating...'
                                        : (!canApplyUpdate || !canApplyAutomatically)
                                            ? 'Open Release'
                                            : 'Update System'}
                                </button>
                            ) : null}
                        </div>
                    </div>
                </header>

                {updateFeedback ? (
                    <div
                        className="mt-3 rounded-2xl border px-4 py-2 text-[11px]"
                        style={{
                            ...(updateFeedbackStyle ?? {}),
                        }}
                    >
                        {updateFeedback.message}
                    </div>
                ) : null}

                <main className="mt-5 min-w-0 pb-8">
                    <Outlet />
                </main>
            </div>
        </div>
    );
}
