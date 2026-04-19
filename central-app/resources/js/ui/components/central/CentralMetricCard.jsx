export function CentralMetricCard({ label, value, subtitle }) {
    return (
        <article className="central-card central-metric-card p-4">
            <p className="text-[11px] uppercase tracking-[0.08em]" style={{ color: 'var(--color-text-tertiary)' }}>
                {label}
            </p>
            <p className="mt-2 text-[22px] font-semibold leading-none">{value}</p>
            {subtitle ? (
                <p className="mt-2 text-[10px]" style={{ color: 'var(--color-text-secondary)' }}>
                    {subtitle}
                </p>
            ) : null}
        </article>
    );
}

