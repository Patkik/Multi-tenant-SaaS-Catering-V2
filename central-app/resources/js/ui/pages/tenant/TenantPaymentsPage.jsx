import { useMemo, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
    createTenantPayment,
    fetchTenantEvents,
    fetchTenantPayments,
    updateTenantPayment,
} from '../../../api/tenantApi';
import { formatCurrency, formatNumber } from '../../../lib/formatters';
import { StatusBadge } from '../../components/StatusBadge';

const initialForm = {
    event_id: '',
    amount: '',
    payment_type: 'downpayment',
    status: 'pending',
    payment_method: 'bank transfer',
    reference: '',
};

export function TenantPaymentsPage() {
    const queryClient = useQueryClient();
    const [form, setForm] = useState(initialForm);

    const eventsQuery = useQuery({
        queryKey: ['tenant-events', { status: '', month: '' }],
        queryFn: () => fetchTenantEvents(),
        staleTime: 1000 * 30,
    });

    const paymentsQuery = useQuery({
        queryKey: ['tenant-payments'],
        queryFn: () => fetchTenantPayments(),
        staleTime: 1000 * 30,
    });

    const createPaymentMutation = useMutation({
        mutationFn: createTenantPayment,
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['tenant-payments'] });
            setForm(initialForm);
        },
    });

    const markPaidMutation = useMutation({
        mutationFn: ({ id }) =>
            updateTenantPayment(id, {
                status: 'paid',
                paid_at: new Date().toISOString(),
            }),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['tenant-payments'] });
        },
    });

    const events = useMemo(() => eventsQuery.data?.data ?? [], [eventsQuery.data]);
    const payments = useMemo(() => paymentsQuery.data?.data?.data ?? [], [paymentsQuery.data]);
    const paymentsMeta = paymentsQuery.data?.meta ?? { total_paid: 0, pending_collection: 0 };

    function updateField(key, value) {
        setForm((previous) => ({
            ...previous,
            [key]: value,
        }));
    }

    function submit(event) {
        event.preventDefault();

        createPaymentMutation.mutate({
            event_id: Number(form.event_id),
            amount: Number(form.amount),
            payment_type: form.payment_type,
            status: form.status,
            payment_method: form.payment_method,
            reference: form.reference || null,
            paid_at: form.status === 'paid' ? new Date().toISOString() : null,
        });
    }

    return (
        <div className="space-y-6">
            <section className="app-shell-panel rounded-3xl p-6">
                <p className="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Payments Workspace</p>
                <h1 className="hero-heading mt-2 text-3xl text-slate-900 md:text-4xl">Track downpayments, balances, and settlement status per event.</h1>

                <div className="mt-4 grid gap-3 md:grid-cols-2">
                    <div className="rounded-2xl border border-emerald-300 bg-emerald-50 px-4 py-3">
                        <p className="text-xs uppercase tracking-wide text-emerald-700">Total Paid</p>
                        <p className="mt-1 text-lg font-semibold text-emerald-900">{formatCurrency(paymentsMeta.total_paid)}</p>
                    </div>
                    <div className="rounded-2xl border border-amber-300 bg-amber-50 px-4 py-3">
                        <p className="text-xs uppercase tracking-wide text-amber-700">Pending Collection</p>
                        <p className="mt-1 text-lg font-semibold text-amber-900">{formatCurrency(paymentsMeta.pending_collection)}</p>
                    </div>
                </div>
            </section>

            <section className="grid gap-4 xl:grid-cols-[1fr_2fr]">
                <form onSubmit={submit} className="app-shell-panel rounded-3xl p-5">
                    <h2 className="text-lg font-semibold text-slate-900">Record Payment</h2>
                    <div className="mt-4 grid gap-3">
                        <select
                            className="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"
                            value={form.event_id}
                            onChange={(event) => updateField('event_id', event.target.value)}
                        >
                            <option value="">Select Event</option>
                            {events.map((tenantEvent) => (
                                <option key={tenantEvent.id} value={tenantEvent.id}>
                                    {tenantEvent.event_name} ({tenantEvent.event_date})
                                </option>
                            ))}
                        </select>

                        <input
                            type="number"
                            min={0}
                            className="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"
                            placeholder="Amount"
                            value={form.amount}
                            onChange={(event) => updateField('amount', event.target.value)}
                        />

                        <select
                            className="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"
                            value={form.payment_type}
                            onChange={(event) => updateField('payment_type', event.target.value)}
                        >
                            <option value="downpayment">Downpayment</option>
                            <option value="balance">Balance</option>
                            <option value="full">Full Payment</option>
                        </select>

                        <select
                            className="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"
                            value={form.status}
                            onChange={(event) => updateField('status', event.target.value)}
                        >
                            <option value="pending">Pending</option>
                            <option value="paid">Paid</option>
                            <option value="failed">Failed</option>
                            <option value="refunded">Refunded</option>
                        </select>

                        <input
                            className="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"
                            placeholder="Payment method"
                            value={form.payment_method}
                            onChange={(event) => updateField('payment_method', event.target.value)}
                        />

                        <input
                            className="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"
                            placeholder="Reference"
                            value={form.reference}
                            onChange={(event) => updateField('reference', event.target.value)}
                        />
                    </div>

                    {createPaymentMutation.isError ? (
                        <p className="mt-3 rounded-xl border border-rose-300 bg-rose-50 px-3 py-2 text-xs text-rose-900">
                            {createPaymentMutation.error?.response?.data?.message ?? 'Unable to record payment.'}
                        </p>
                    ) : null}

                    <button
                        type="submit"
                        disabled={createPaymentMutation.isPending}
                        className="mt-4 rounded-full bg-[var(--primary-color)] px-4 py-2 text-sm font-semibold text-white disabled:opacity-50"
                    >
                        {createPaymentMutation.isPending ? 'Saving...' : 'Record Payment'}
                    </button>
                </form>

                <div className="overflow-hidden rounded-3xl border border-slate-200 bg-white">
                    <div className="flex items-center justify-between border-b border-slate-100 px-4 py-3">
                        <h2 className="text-sm font-semibold text-slate-900">Payment Ledger</h2>
                        <span className="text-xs text-slate-500">{formatNumber(payments.length)} entries</span>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead className="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th className="px-4 py-3">Event</th>
                                    <th className="px-4 py-3">Client</th>
                                    <th className="px-4 py-3">Amount</th>
                                    <th className="px-4 py-3">Type</th>
                                    <th className="px-4 py-3">Status</th>
                                    <th className="px-4 py-3">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                {paymentsQuery.isPending ? (
                                    <tr>
                                        <td className="px-4 py-6 text-slate-600" colSpan={6}>
                                            Loading payment ledger...
                                        </td>
                                    </tr>
                                ) : paymentsQuery.isError ? (
                                    <tr>
                                        <td className="px-4 py-6 text-rose-700" colSpan={6}>
                                            Failed to load payments.
                                        </td>
                                    </tr>
                                ) : payments.length === 0 ? (
                                    <tr>
                                        <td className="px-4 py-6 text-slate-600" colSpan={6}>
                                            No payment records yet.
                                        </td>
                                    </tr>
                                ) : (
                                    payments.map((payment) => (
                                        <tr key={payment.id} className="border-t border-slate-100">
                                            <td className="px-4 py-3">
                                                <p className="font-semibold text-slate-900">{payment.event?.event_name || 'Unknown Event'}</p>
                                                <p className="text-xs text-slate-500">{payment.event?.event_date || 'No date'}</p>
                                            </td>
                                            <td className="px-4 py-3 text-slate-700">{payment.event?.client_name || 'N/A'}</td>
                                            <td className="px-4 py-3 text-slate-700">{formatCurrency(payment.amount)}</td>
                                            <td className="px-4 py-3 text-slate-700 capitalize">{payment.payment_type}</td>
                                            <td className="px-4 py-3">
                                                <StatusBadge status={payment.status} />
                                            </td>
                                            <td className="px-4 py-3">
                                                {payment.status !== 'paid' ? (
                                                    <button
                                                        type="button"
                                                        onClick={() => markPaidMutation.mutate({ id: payment.id })}
                                                        disabled={markPaidMutation.isPending}
                                                        className="rounded-lg border border-emerald-300 px-2.5 py-1 text-xs font-semibold text-emerald-700 disabled:opacity-50"
                                                    >
                                                        Mark Paid
                                                    </button>
                                                ) : (
                                                    <span className="text-xs text-slate-500">Settled</span>
                                                )}
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </div>
    );
}
