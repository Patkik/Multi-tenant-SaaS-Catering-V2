import { useMemo } from 'react';
import { useQuery } from '@tanstack/react-query';
import { fetchCentralDashboard } from '../../../api/centralApi';
import { formatCurrency, formatNumber } from '../../../lib/formatters';
import { CentralChartPanel } from '../../components/central/CentralChartPanel';
import { CentralMetricCard } from '../../components/central/CentralMetricCard';

function toDisplayPlanLabel(label) {
    const normalized = String(label ?? '').toLowerCase();

    if (normalized === 'starter') {
        return 'Basic';
    }

    if (normalized === 'business') {
        return 'Premium';
    }

    return label || 'Free';
}

function toDisplayStatus(status) {
    return String(status ?? '').toLowerCase() === 'active' ? 'Active' : 'Inactive';
}

const planBadgeStyles = {
    Free: {
        borderColor: '#73726c',
        backgroundColor: '#F1EFE8',
        color: '#444441',
    },
    Basic: {
        borderColor: '#378ADD',
        backgroundColor: '#E6F1FB',
        color: '#0C447C',
    },
    Premium: {
        borderColor: '#7F77DD',
        backgroundColor: '#E6F1FB',
        color: '#0C447C',
    },
    Enterprise: {
        borderColor: '#7F77DD',
        backgroundColor: '#E6F1FB',
        color: '#0C447C',
    },
};

const statusBadgeStyles = {
    Active: {
        borderColor: '#1D9E75',
        backgroundColor: '#E1F5EE',
        color: '#085041',
    },
    Inactive: {
        borderColor: '#D85A30',
        backgroundColor: '#FAECE7',
        color: '#712B13',
    },
};

export function CentralDashboardPage() {
    const dashboardQuery = useQuery({
        queryKey: ['central-dashboard'],
        queryFn: fetchCentralDashboard,
        staleTime: 1000 * 30,
    });

    const dashboard = dashboardQuery.data ?? {};
    const registrationSeries = dashboard.registration_series ?? [];
    const planDistribution = dashboard.plan_distribution ?? [];
    const activityRows = dashboard.recent_tenant_activity ?? [];

    const registrationConfig = useMemo(() => {
        return {
            type: 'bar',
            data: {
                labels: registrationSeries.map((item) => item.label),
                datasets: [
                    {
                        label: 'Registrations',
                        data: registrationSeries.map((item) => item.value),
                        backgroundColor: '#378ADD',
                        borderRadius: 4,
                    },
                ],
            },
            options: {
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { color: '#73726c', font: { size: 11 } },
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: '#F1EFE8' },
                        ticks: { color: '#73726c', font: { size: 11 } },
                    },
                },
            },
        };
    }, [registrationSeries]);

    const planDistributionConfig = useMemo(() => {
        return {
            type: 'doughnut',
            data: {
                labels: planDistribution.map((item) => toDisplayPlanLabel(item.label)),
                datasets: [
                    {
                        data: planDistribution.map((item) => item.count),
                        backgroundColor: planDistribution.map((item) => {
                            const label = toDisplayPlanLabel(item.label);
                            if (label === 'Basic') {
                                return '#378ADD';
                            }
                            if (label === 'Premium') {
                                return '#7F77DD';
                            }

                            return '#73726c';
                        }),
                        borderWidth: 0,
                    },
                ],
            },
            options: {
                cutout: '58%',
            },
        };
    }, [planDistribution]);

    if (dashboardQuery.isPending) {
        return (
            <div className="central-card p-4">
                <p className="text-[12px]" style={{ color: 'var(--color-text-secondary)' }}>
                    Loading dashboard data...
                </p>
            </div>
        );
    }

    if (dashboardQuery.isError) {
        return (
            <div className="central-card p-4">
                <p className="text-[12px]" style={{ color: 'var(--color-text-secondary)' }}>
                    Failed to load dashboard data.
                </p>
            </div>
        );
    }

    return (
        <div className="space-y-4 pb-2">
            <section className="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                <CentralMetricCard label="Total Tenants" value={formatNumber(dashboard.total_tenants)} />
                <CentralMetricCard label="Active" value={formatNumber(dashboard.active_tenants)} />
                <CentralMetricCard label="Monthly Revenue" value={formatCurrency(dashboard.estimated_monthly_revenue)} />
                <CentralMetricCard label="Avg Plan" value={toDisplayPlanLabel(dashboard.avg_plan_label)} />
            </section>

            <section className="grid gap-3 xl:grid-cols-2">
                <CentralChartPanel
                    title="Tenant registrations (last 6 months)"
                    ariaLabel="Bar chart showing tenant registrations for November to April with values 14, 18, 22, 17, 25, and 19"
                    height={260}
                    config={registrationConfig}
                    legendItems={[
                        {
                            key: 'registrations',
                            label: 'Registrations',
                            value: `${formatNumber(registrationSeries.reduce((sum, item) => sum + Number(item.value || 0), 0))} total`,
                            color: '#378ADD',
                        },
                    ]}
                />
                <CentralChartPanel
                    title="Plan distribution"
                    ariaLabel="Doughnut chart showing plan distribution with Free 32, Basic 64, and Premium 32"
                    height={260}
                    config={planDistributionConfig}
                    legendItems={planDistribution.map((item) => {
                        const label = toDisplayPlanLabel(item.label);

                        return {
                            key: item.key,
                            label,
                            value: formatNumber(item.count),
                            color: label === 'Basic' ? '#378ADD' : label === 'Premium' ? '#7F77DD' : '#73726c',
                        };
                    })}
                />
            </section>

            <section className="central-card p-4">
                <h3 className="text-[13px] font-semibold">Recent tenant activity</h3>
                <div className="mt-3 overflow-x-auto">
                    <table className="central-table w-full text-left text-[12px]" style={{ tableLayout: 'fixed' }}>
                        <colgroup>
                            <col style={{ width: '32%' }} />
                            <col style={{ width: '18%' }} />
                            <col style={{ width: '14%' }} />
                            <col style={{ width: '14%' }} />
                            <col style={{ width: '22%' }} />
                        </colgroup>
                        <thead>
                            <tr>
                                <th>Business Name</th>
                                <th>Subdomain</th>
                                <th>Plan</th>
                                <th>Status</th>
                                <th>Joined date</th>
                            </tr>
                        </thead>
                        <tbody>
                            {activityRows.map((row) => (
                                <tr key={row.tenant_id ?? row.subdomain}>
                                    <td className="truncate">{row.company_name}</td>
                                    <td className="truncate">{row.subdomain}</td>
                                    <td>
                                        <span
                                            className="inline-flex rounded-full border px-2 py-0.5 text-[10px] font-semibold"
                                            style={planBadgeStyles[toDisplayPlanLabel(row.plan_details?.label)] ?? planBadgeStyles.Free}
                                        >
                                            {toDisplayPlanLabel(row.plan_details?.label)}
                                        </span>
                                    </td>
                                    <td>
                                        <span
                                            className="inline-flex rounded-full border px-2 py-0.5 text-[10px] font-semibold"
                                            style={statusBadgeStyles[toDisplayStatus(row.status)]}
                                        >
                                            {toDisplayStatus(row.status)}
                                        </span>
                                    </td>
                                    <td>{row.created_at ? String(row.created_at).slice(0, 10) : '—'}</td>
                                </tr>
                            ))}
                            {activityRows.length === 0 ? (
                                <tr>
                                    <td colSpan={5} className="py-3 text-center" style={{ color: 'var(--color-text-tertiary)' }}>
                                        No tenant activity yet.
                                    </td>
                                </tr>
                            ) : null}
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    );
}

