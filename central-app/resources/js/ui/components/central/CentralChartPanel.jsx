import { useEffect, useRef, useState } from 'react';
import { loadChartJs } from '../../../lib/loadChartJs';

export function CentralChartPanel({ title, ariaLabel, height, config, legendItems = [], emptyText = 'No legend data available.' }) {
    const canvasRef = useRef(null);
    const chartRef = useRef(null);
    const [error, setError] = useState('');

    useEffect(() => {
        let active = true;

        async function renderChart() {
            try {
                const Chart = await loadChartJs();
                if (!active || !canvasRef.current || !Chart) {
                    return;
                }

                if (chartRef.current) {
                    chartRef.current.destroy();
                }

                chartRef.current = new Chart(canvasRef.current, {
                    ...config,
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        ...config.options,
                        plugins: {
                            ...config.options?.plugins,
                            legend: {
                                display: false,
                            },
                        },
                    },
                });

                setError('');
            } catch (chartError) {
                if (active) {
                    setError(chartError.message || 'Unable to render chart.');
                }
            }
        }

        renderChart();

        return () => {
            active = false;
            if (chartRef.current) {
                chartRef.current.destroy();
                chartRef.current = null;
            }
        };
    }, [config]);

    return (
        <article className="central-card p-4">
            <h3 className="text-[13px] font-semibold">{title}</h3>
            <div className="relative mt-3" style={{ height: `${height}px` }}>
                <canvas ref={canvasRef} role="img" aria-label={ariaLabel}>
                    {ariaLabel}
                </canvas>
            </div>
            {error ? (
                <p className="mt-2 text-[11px]" style={{ color: 'var(--color-text-secondary)' }}>
                    {error}
                </p>
            ) : null}
            <div className="mt-3 grid gap-2">
                {legendItems.length > 0 ? (
                    legendItems.map((item) => (
                        <div key={item.key} className="flex items-center justify-between text-[11px]">
                            <div className="flex min-w-0 items-center gap-2">
                                <span
                                    className="inline-block h-2.5 w-2.5 rounded-[2px]"
                                    style={{
                                        backgroundColor: item.color,
                                    }}
                                />
                                <span className="truncate" style={{ color: 'var(--color-text-secondary)' }}>
                                    {item.label}
                                </span>
                            </div>
                            {item.value ? <span className="whitespace-nowrap font-medium">{item.value}</span> : null}
                        </div>
                    ))
                ) : (
                    <p className="text-[11px]" style={{ color: 'var(--color-text-tertiary)' }}>
                        {emptyText}
                    </p>
                )}
            </div>
        </article>
    );
}

