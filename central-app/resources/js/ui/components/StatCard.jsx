import { formatCurrency, formatNumber } from '../../lib/formatters';

export function StatCard({ title, value, trend, kind = 'number' }) {
    const formattedValue =
        kind === 'currency' ? formatCurrency(value) : kind === 'number' ? formatNumber(value) : String(value ?? '');

    return (
        <article className="app-shell-panel rounded-3xl p-5 shadow-[0_18px_45px_-28px_rgba(24,24,27,0.48)]">
            <p className="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{title}</p>
            <p className="mt-3 text-3xl font-bold text-slate-900">{formattedValue}</p>
            {trend ? <p className="mt-2 text-sm text-slate-600">{trend}</p> : null}
        </article>
    );
}
