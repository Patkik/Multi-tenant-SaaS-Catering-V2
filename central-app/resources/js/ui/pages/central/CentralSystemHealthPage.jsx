import { useMemo } from 'react';
import { useQuery } from '@tanstack/react-query';
import { fetchCentralSystemHealth } from '../../../api/centralApi';
import { CentralChartPanel } from '../../components/central/CentralChartPanel';
import { CentralMetricCard } from '../../components/central/CentralMetricCard';

const serviceHealthyStyle = {
    borderColor: '#1D9E75',
    backgroundColor: '#E1F5EE',
    color: '#085041',
};

const serviceDegradedStyle = {
    borderColor: '#EF9F27',
    backgroundColor: '#FAEEDA',
    color: '#633806',
};

export function CentralSystemHealthPage() {
    const healthQuery = useQuery({
        queryKey: ['central-system-health'],
        queryFn: fetchCentralSystemHealth,
        staleTime: 1000 * 15,
    });

    const health = healthQuery.data ?? {};
    const metrics = health.metrics ?? {};
    const services = health.service_health ?? [];
    const resourceBars = health.resource_usage ?? [];
    const recentJobs = health.recent_job_events ?? [];
    const apiSeries = health.api_response_series ?? [];

    const latencyConfig = useMemo(() => {
        return {
            type: 'line',
            data: {
                labels: apiSeries.map((item) => item.label),
                datasets: [
                    {
                        label: 'API latency',
                        data: apiSeries.map((item) => item.value),
                        borderColor: '#7F77DD',
                        backgroundColor: '#7F77DD',
                        borderWidth: 2,
                        pointRadius: 2,
                        tension: 0.3,
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
                        ticks: {
                            color: '#73726c',
                            font: { size: 11 },
                            callback: (value) => `${value}ms`,
                        },
                    },
                },
            },
        };
    }, [apiSeries]);

    if (healthQuery.isPending) {
        return (
            <div className="central-card p-4">
                <p className="text-[12px]" style={{ color: 'var(--color-text-secondary)' }}>
                    Loading system health...
                </p>
            </div>
        );
    }

    if (healthQuery.isError) {
        return (
            <div className="central-card p-4">
                <p className="text-[12px]" style={{ color: 'var(--color-text-secondary)' }}>
                    Failed to load system health.
                </p>
            </div>
        );
    }

    return (
        <div className="space-y-4 pb-2">
            <section className="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                <CentralMetricCard label="Tenant databases" value={metrics.tenant_databases ?? 0} />
                <CentralMetricCard label="Pending jobs" value={metrics.pending_jobs ?? 0} />
                <CentralMetricCard label="Failed jobs 24h" value={metrics.failed_jobs_24h ?? 0} />
                <CentralMetricCard label="Avg API latency" value={`${metrics.avg_api_latency ?? 0}ms`} />
            </section>

            <section className="grid gap-3 xl:grid-cols-2">
                <article className="central-card p-4">
                    <h3 className="text-[13px] font-semibold">Service health</h3>
                    <div className="mt-3 space-y-2.5">
                        {services.map((service) => (
                            <div
                                key={service.name}
                                className="flex items-center justify-between rounded-[var(--border-radius-md)] border px-2.5 py-2"
                                style={{ borderColor: 'var(--color-border-tertiary)' }}
                            >
                                <div className="flex items-center gap-2">
                                    <span
                                        className="inline-block h-2.5 w-2.5 rounded-full"
                                        style={{
                                            backgroundColor: service.status === 'Healthy' ? '#1D9E75' : '#EF9F27',
                                        }}
                                    />
                                    <div>
                                        <p className="text-[12px] font-medium">{service.name}</p>
                                        <p className="text-[10px]" style={{ color: 'var(--color-text-secondary)' }}>
                                            {service.latency} · {service.detail ?? service.uptime}
                                        </p>
                                    </div>
                                </div>
                                <span
                                    className="rounded-full border px-2 py-0.5 text-[10px] font-semibold"
                                    style={service.status === 'Healthy' ? serviceHealthyStyle : serviceDegradedStyle}
                                >
                                    {service.status}
                                </span>
                            </div>
                        ))}
                    </div>
                </article>

                <article className="central-card p-4">
                    <h3 className="text-[13px] font-semibold">Resource usage</h3>
                    <div className="mt-3 space-y-2.5">
                        {resourceBars.map((item) => {
                            const hasNumericValue = Number.isFinite(Number(item.value));
                            const metricValue = hasNumericValue ? Math.max(0, Math.min(100, Number(item.value))) : null;

                            return (
                                <div key={item.label}>
                                    <div className="mb-1 flex items-center justify-between text-[11px]">
                                        <span>{item.label}</span>
                                        <span>{hasNumericValue ? `${metricValue}%` : 'N/A'}</span>
                                    </div>
                                    <div className="h-2 overflow-hidden rounded-full" style={{ backgroundColor: 'var(--color-background-secondary)' }}>
                                        <div
                                            className="h-full rounded-full"
                                            style={{
                                                width: `${metricValue ?? 0}%`,
                                                backgroundColor:
                                                    metricValue === null
                                                        ? 'var(--color-border-tertiary)'
                                                        : metricValue >= 80
                                                            ? 'var(--color-text-tertiary)'
                                                            : 'var(--color-text-secondary)',
                                            }}
                                        />
                                    </div>
                                </div>
                            );
                        })}
                    </div>

                    <h4 className="mt-4 text-[12px] font-semibold">Recent job events</h4>
                    <table className="central-table mt-2 w-full text-left text-[11px]" style={{ tableLayout: 'fixed' }}>
                        <colgroup>
                            <col style={{ width: '33%' }} />
                            <col style={{ width: '37%' }} />
                            <col style={{ width: '30%' }} />
                        </colgroup>
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Job</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            {recentJobs.map((job) => (
                                <tr key={`${job.time}-${job.job}`}>
                                    <td>{job.time}</td>
                                    <td>{job.job}</td>
                                    <td>{job.status}</td>
                                </tr>
                            ))}
                            {recentJobs.length === 0 ? (
                                <tr>
                                    <td colSpan={3} className="py-2 text-center" style={{ color: 'var(--color-text-tertiary)' }}>
                                        No recent job events.
                                    </td>
                                </tr>
                            ) : null}
                        </tbody>
                    </table>
                </article>
            </section>

            <CentralChartPanel
                title="24-hour API response time"
                ariaLabel="Line chart showing API response times over 24 hours"
                height={270}
                config={latencyConfig}
                legendItems={[{ key: 'latency', label: 'Average latency', value: `${metrics.avg_api_latency ?? 0}ms`, color: '#7F77DD' }]}
            />
        </div>
    );
}

