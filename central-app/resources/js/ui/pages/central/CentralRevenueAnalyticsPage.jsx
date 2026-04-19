import { useMemo } from 'react';
import { useQuery } from '@tanstack/react-query';
import { fetchCentralRevenueAnalytics } from '../../../api/centralApi';
import { formatCurrency } from '../../../lib/formatters';
import { CentralChartPanel } from '../../components/central/CentralChartPanel';
import { CentralMetricCard } from '../../components/central/CentralMetricCard';

function toPlanLabel(label) {
    const normalized = String(label ?? '').toLowerCase();
    if (normalized === 'starter') {
        return 'Basic';
    }
    if (normalized === 'business') {
        return 'Premium';
    }

    return label || 'Misc';
}

function toPlanColor(label) {
    if (label === 'Basic') {
        return '#378ADD';
    }
    if (label === 'Premium') {
        return '#7F77DD';
    }
    if (label === 'Misc') {
        return '#EF9F27';
    }

    return '#73726c';
}

export function CentralRevenueAnalyticsPage() {
    const revenueQuery = useQuery({
        queryKey: ['central-revenue-analytics'],
        queryFn: fetchCentralRevenueAnalytics,
        staleTime: 1000 * 30,
    });

    const analytics = revenueQuery.data ?? {};
    const metrics = analytics.metrics ?? {};
    const mrrTrend = analytics.mrr_trend ?? [];
    const revenueByPlan = analytics.revenue_by_plan ?? [];
    const newVsChurned = analytics.new_vs_churned ?? [];

    const mappedRevenue = useMemo(() => {
        return revenueByPlan.map((item) => {
            const label = toPlanLabel(item.label);
            return {
                ...item,
                label,
                color: toPlanColor(label),
            };
        });
    }, [revenueByPlan]);

    const mrrTrendConfig = useMemo(() => {
        return {
            type: 'line',
            data: {
                labels: mrrTrend.map((item) => item.label),
                datasets: [
                    {
                        label: 'MRR',
                        data: mrrTrend.map((item) => item.value),
                        borderColor: '#7F77DD',
                        backgroundColor: '#7F77DD',
                        borderWidth: 2,
                        pointRadius: 2.5,
                        tension: 0.35,
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
                        grid: { color: '#F1EFE8' },
                        ticks: {
                            color: '#73726c',
                            font: { size: 11 },
                            callback: (value) => `₱${(Number(value) / 1000).toFixed(0)}k`,
                        },
                    },
                },
            },
        };
    }, [mrrTrend]);

    const revenueByPlanConfig = useMemo(() => {
        return {
            type: 'doughnut',
            data: {
                labels: mappedRevenue.map((item) => item.label),
                datasets: [
                    {
                        data: mappedRevenue.map((item) => item.value),
                        backgroundColor: mappedRevenue.map((item) => item.color),
                        borderWidth: 0,
                    },
                ],
            },
            options: {
                cutout: '60%',
            },
        };
    }, [mappedRevenue]);

    const churnVsNewConfig = useMemo(() => {
        return {
            type: 'bar',
            data: {
                labels: newVsChurned.map((item) => item.label),
                datasets: [
                    {
                        label: 'New tenants',
                        data: newVsChurned.map((item) => item.new),
                        backgroundColor: '#378ADD',
                        borderRadius: 4,
                    },
                    {
                        label: 'Churned tenants',
                        data: newVsChurned.map((item) => item.churned),
                        backgroundColor: '#D85A30',
                        borderRadius: 4,
                    },
                ],
            },
            options: {
                scales: {
                    x: {
                        stacked: false,
                        grid: { display: false },
                        ticks: { color: '#73726c', font: { size: 11 } },
                    },
                    y: {
                        beginAtZero: true,
                        ticks: { color: '#73726c', font: { size: 11 } },
                        grid: { color: '#F1EFE8' },
                    },
                },
            },
        };
    }, [newVsChurned]);

    const revenueTotal = mappedRevenue.reduce((total, item) => total + Number(item.value || 0), 0);
    const revenueLegend = mappedRevenue.map((item) => ({
        key: item.key,
        label: item.label,
        value: `${formatCurrency(item.value)} · ${revenueTotal ? ((item.value / revenueTotal) * 100).toFixed(1) : '0.0'}%`,
        color: item.color,
    }));

    if (revenueQuery.isPending) {
        return (
            <div className="central-card p-4">
                <p className="text-[12px]" style={{ color: 'var(--color-text-secondary)' }}>
                    Loading revenue analytics...
                </p>
            </div>
        );
    }

    if (revenueQuery.isError) {
        return (
            <div className="central-card p-4">
                <p className="text-[12px]" style={{ color: 'var(--color-text-secondary)' }}>
                    Failed to load revenue analytics.
                </p>
            </div>
        );
    }

    return (
        <div className="space-y-4 pb-2">
            <section className="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                <CentralMetricCard label="MRR" value={formatCurrency(metrics.mrr)} />
                <CentralMetricCard label="ARR" value={formatCurrency(metrics.arr)} />
                <CentralMetricCard label="Avg churn rate" value={`${Number(metrics.avg_churn_rate || 0).toFixed(1)}%`} />
                <CentralMetricCard label="ARPU" value={formatCurrency(metrics.arpu)} />
            </section>

            <section className="grid gap-3 xl:grid-cols-2">
                <CentralChartPanel
                    title="12-month MRR trend"
                    ariaLabel="Line chart showing MRR growth over the last 12 months"
                    height={260}
                    config={mrrTrendConfig}
                    legendItems={[{ key: 'mrr', label: 'Current MRR', value: formatCurrency(metrics.mrr), color: '#7F77DD' }]}
                />
                <CentralChartPanel
                    title="Revenue by plan"
                    ariaLabel="Doughnut chart with revenue by plan"
                    height={260}
                    config={revenueByPlanConfig}
                    legendItems={revenueLegend}
                />
            </section>

            <CentralChartPanel
                title="New vs churned tenants (last 6 months)"
                ariaLabel="Grouped bar chart comparing new and churned tenants for recent months"
                height={280}
                config={churnVsNewConfig}
                legendItems={[
                    { key: 'new', label: 'New tenants', value: `${newVsChurned.reduce((sum, item) => sum + Number(item.new || 0), 0)}`, color: '#378ADD' },
                    { key: 'churn', label: 'Churned tenants', value: `${newVsChurned.reduce((sum, item) => sum + Number(item.churned || 0), 0)}`, color: '#D85A30' },
                ]}
            />
        </div>
    );
}

