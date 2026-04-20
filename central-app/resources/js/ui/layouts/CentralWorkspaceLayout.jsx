import { useMemo, useState } from 'react';
import { useMutation, useQuery } from '@tanstack/react-query';
import { NavLink, Outlet, useLocation } from 'react-router-dom';
import { applyCentralAppUpdate, fetchCentralAppUpdates } from '../../api/centralApi';
import { useTenantContext } from '../../providers/TenantProvider';

const links = [
    { to: '/central/dashboard', label: 'Dashboard' },
    { to: '/central/tenants', label: 'Tenants' },
    { to: '/central/new-tenant', label: 'New Tenant' },
    { to: '/central/plans-pricing', label: 'Plans & Pricing' },
    { to: '/central/user-management', label: 'User Management' },
    { to: '/central/revenue-analytics', label: 'Revenue Analytics' },
    { to: '/central/system-health', label: 'System Health' },
    { to: '/central/audit-logs', label: 'Audit Logs' },
];

const buildTimeVersion = import.meta.env.VITE_APP_VERSION || '0.0.0';

export function CentralWorkspaceLayout() {
    const location = useLocation();
    const { centralAuthUser, centralSignOut } = useTenantContext();
    const [hasCheckedUpdates, setHasCheckedUpdates] = useState(false);
    const [updateFeedback, setUpdateFeedback] = useState(null);
    const appUpdatesQuery = useQuery({
        queryKey: ['central-app-updates'],
        queryFn: fetchCentralAppUpdates,
        retry: false,
        enabled: false,
    });
    const applyUpdateMutation = useMutation({
        mutationFn: applyCentralAppUpdate,
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

    const pageTitle = useMemo(() => {
        const activeRoute = links.find((entry) => entry.to === location.pathname);
        return activeRoute?.label ?? 'Dashboard';
    }, [location.pathname]);

    const adminName = useMemo(() => {
        if (!centralAuthUser) {
            return 'Central Admin';
        }

        const fullName = [centralAuthUser.firstname, centralAuthUser.lastname].filter(Boolean).join(' ').trim();
        return fullName || centralAuthUser.email || 'Central Admin';
    }, [centralAuthUser]);

    const adminInitials = useMemo(() => {
        return adminName
            .split(' ')
            .filter(Boolean)
            .slice(0, 2)
            .map((part) => part[0]?.toUpperCase())
            .join('') || 'CA';
    }, [adminName]);

    const updateInfo = hasCheckedUpdates ? appUpdatesQuery.data : null;
    const hasAvailableUpdate = Boolean(updateInfo?.enabled && updateInfo?.update_available);
    const latestVersionLabel = updateInfo?.latest_tag || updateInfo?.latest_version;
    const displayedVersion = updateInfo?.current_version || centralAuthUser?.app_version || buildTimeVersion;
    const canApplyAutomatically = Boolean(updateInfo?.can_apply);
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

    const handleApplyUpdate = () => {
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
        <div
            className="central-console flex h-screen w-full overflow-hidden"
            style={{
                color: 'var(--color-text-primary)',
                backgroundColor: 'var(--color-background-tertiary)',
            }}
        >
            <aside
                className="flex w-48 flex-col border-r px-4 py-4"
                style={{
                    borderColor: 'var(--color-border-tertiary)',
                    backgroundColor: 'var(--color-background-primary)',
                }}
            >
                <div>
                    <p className="text-[15px] font-semibold tracking-[-0.01em]">CaterPro</p>
                    <p className="text-[10px] uppercase tracking-[0.12em]" style={{ color: 'var(--color-text-tertiary)' }}>
                        Central Platform
                    </p>
                </div>

                <nav className="mt-6 flex flex-1 flex-col gap-1.5">
                    {links.map((link) => (
                        <NavLink
                            key={link.to}
                            to={link.to}
                            className={({ isActive }) =>
                                `flex items-center justify-between rounded-[var(--border-radius-md)] border px-2.5 py-2 text-[12px] font-medium ${
                                    isActive ? 'central-nav-link-active' : 'central-nav-link'
                                }`
                            }
                        >
                            <span>{link.label}</span>
                            {link.isNew ? (
                                <span
                                    className="rounded-full border px-1.5 py-0.5 text-[10px] font-semibold uppercase leading-none"
                                    style={{
                                        borderColor: '#378ADD',
                                        backgroundColor: '#E6F1FB',
                                        color: '#0C447C',
                                    }}
                                >
                                    New
                                </span>
                            ) : null}
                        </NavLink>
                    ))}
                </nav>

                <div className="mt-4 rounded-[var(--border-radius-md)] border p-2.5" style={{ borderColor: 'var(--color-border-tertiary)' }}>
                    <div className="flex items-center gap-2">
                            <span
                                className="inline-flex h-7 w-7 items-center justify-center rounded-full text-[10px] font-semibold"
                                style={{
                                    backgroundColor: 'var(--color-background-secondary)',
                                    color: 'var(--color-text-secondary)',
                                }}
                            >
                                {adminInitials}
                            </span>
                        <div className="min-w-0">
                            <p className="truncate text-[11px] font-semibold">{adminName}</p>
                            <p className="truncate text-[10px]" style={{ color: 'var(--color-text-tertiary)' }}>
                                {centralAuthUser?.email ?? 'central@caterpro.ph'}
                            </p>
                        </div>
                    </div>
                    <button
                        type="button"
                        onClick={centralSignOut}
                        className="central-button mt-2.5 w-full px-2 py-1.5 text-[11px] font-medium"
                    >
                        Sign out
                    </button>
                </div>
            </aside>

            <section className="flex min-w-0 flex-1 flex-col">
                <header
                    className="flex items-center justify-between border-b px-6 py-4"
                    style={{
                        borderColor: 'var(--color-border-tertiary)',
                        backgroundColor: 'var(--color-background-primary)',
                    }}
                >
                    <h1 className="text-[15px] font-semibold tracking-[-0.01em]">{pageTitle}</h1>
                    <div className="flex items-center gap-2">
                        <span
                            className="rounded-full border px-2.5 py-1 text-[10px] font-semibold uppercase leading-none"
                            style={{ borderColor: 'var(--color-border-tertiary)' }}
                        >
                            v{displayedVersion}
                        </span>
                        <button
                            type="button"
                            onClick={handleCheckForUpdates}
                            disabled={isCheckingUpdates || isApplyingUpdate}
                            className="rounded-full border px-2.5 py-1 text-[10px] font-semibold uppercase leading-none transition disabled:cursor-not-allowed disabled:opacity-60"
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
                            <span
                                className="rounded-full border px-2.5 py-1 text-[10px] font-semibold uppercase leading-none"
                                style={{
                                    borderColor: '#D85A30',
                                    backgroundColor: '#FAECE7',
                                    color: '#712B13',
                                }}
                                title={latestVersionLabel ? `Latest release: ${latestVersionLabel}` : 'A newer release is available'}
                            >
                                Update Available
                            </span>
                        ) : null}
                        {hasAvailableUpdate ? (
                            <button
                                type="button"
                                onClick={handleApplyUpdate}
                                disabled={isApplyingUpdate}
                                className="rounded-full border px-2.5 py-1 text-[10px] font-semibold uppercase leading-none transition disabled:cursor-not-allowed disabled:opacity-60"
                                style={{
                                    borderColor: '#378ADD',
                                    backgroundColor: '#E6F1FB',
                                    color: '#0C447C',
                                }}
                                title={canApplyAutomatically ? 'Apply update command now' : 'Open release instructions for manual update'}
                            >
                                {isApplyingUpdate ? 'Updating...' : canApplyAutomatically ? 'Update System' : 'Open Release'}
                            </button>
                        ) : null}
                        <span
                            className="inline-flex h-7 w-7 items-center justify-center rounded-full text-[10px] font-semibold"
                            style={{
                                backgroundColor: 'var(--color-background-secondary)',
                                color: 'var(--color-text-secondary)',
                            }}
                        >
                            {adminInitials}
                        </span>
                    </div>
                </header>

                {updateFeedback ? (
                    <div
                        className="border-b px-6 py-2 text-[11px]"
                        style={{
                            borderColor: 'var(--color-border-tertiary)',
                            ...(updateFeedbackStyle ?? {}),
                        }}
                    >
                        {updateFeedback.message}
                    </div>
                ) : null}

                <main className="min-h-0 flex-1 overflow-y-auto px-6 py-5">
                    <h2 className="sr-only">Central platform workspace content</h2>
                    <Outlet />
                </main>
            </section>
        </div>
    );
}
