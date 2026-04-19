const statusTheme = {
    pending: 'bg-amber-100 text-amber-800 border-amber-300',
    confirmed: 'bg-sky-100 text-sky-800 border-sky-300',
    completed: 'bg-emerald-100 text-emerald-800 border-emerald-300',
    cancelled: 'bg-rose-100 text-rose-800 border-rose-300',
};

export function StatusBadge({ status }) {
    const normalizedStatus = String(status ?? 'pending').toLowerCase();
    const classes = statusTheme[normalizedStatus] ?? statusTheme.pending;

    return (
        <span className={`inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold capitalize tracking-wide ${classes}`}>
            {normalizedStatus}
        </span>
    );
}
