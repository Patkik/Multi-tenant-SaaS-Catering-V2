export function formatCurrency(value) {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
        maximumFractionDigits: 0,
    }).format(Number(value ?? 0));
}

export function formatNumber(value) {
    return new Intl.NumberFormat('en-US').format(Number(value ?? 0));
}

export function titleCase(value) {
    return String(value ?? '')
        .split(/[_\s-]+/)
        .filter(Boolean)
        .map((word) => word[0].toUpperCase() + word.slice(1))
        .join(' ');
}

export function isTenantContextFallbackError(error) {
    const status = error?.response?.status;

    return status === 400 || status === 403 || status === 404;
}
