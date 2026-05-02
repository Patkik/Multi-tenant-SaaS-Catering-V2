import { useMemo, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { createTenantEvent, fetchTenantPackages } from '../../../api/tenantApi';
import { formatCurrency } from '../../../lib/formatters';

const initialState = {
    event_name: '',
    event_date: '',
    location: '',
    guest_count: 50,
    status: 'pending',
    quoted_total: 35000,
    catering_package_id: null,
    client: {
        first_name: '',
        last_name: '',
        email: '',
        phone: '',
    },
};

export function TenantBookingWizardPage() {
    const [step, setStep] = useState(0);
    const [form, setForm] = useState(initialState);
    const queryClient = useQueryClient();

    const createEventMutation = useMutation({
        mutationFn: createTenantEvent,
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['tenant-events'] });
        },
    });

    const packagesQuery = useQuery({
        queryKey: ['tenant-packages'],
        queryFn: () => fetchTenantPackages(),
        staleTime: 1000 * 60,
    });

    const packageOptions = useMemo(() => {
        const items = packagesQuery.data?.data?.data ?? [];

        return [
            { id: null, name: 'Custom Package', basePrice: 0 },
            ...items.map((item) => ({
                id: item.id,
                name: item.name,
                basePrice: Number(item.base_price ?? 0),
            })),
        ];
    }, [packagesQuery.data]);

    const selectedPackage = useMemo(
        () => packageOptions.find((option) => option.id === form.catering_package_id) ?? packageOptions[0],
        [form.catering_package_id],
    );

    function updateEventField(key, value) {
        setForm((previous) => ({
            ...previous,
            [key]: value,
        }));
    }

    function updateClientField(key, value) {
        setForm((previous) => ({
            ...previous,
            client: {
                ...previous.client,
                [key]: value,
            },
        }));
    }

    function submit(event) {
        event.preventDefault();

        const payload = {
            ...form,
            guest_count: Number(form.guest_count),
            quoted_total: Number(form.quoted_total),
            catering_package_id: form.catering_package_id || null,
        };

        createEventMutation.mutate(payload);
    }

    const canMoveNext =
        step === 0
            ? form.event_name && form.event_date && form.location
            : step === 1
              ? form.client.first_name && form.client.last_name
              : true;

    return (
        <div className="space-y-6">
            <section className="app-shell-panel rounded-[2rem] p-6 md:p-8">
                <p className="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Booking Wizard</p>
                <h1 className="hero-heading mt-2 text-3xl text-slate-900 md:text-4xl">Build a booking from schedule to package review in four guided steps.</h1>
                <div className="mt-5 grid gap-3 md:grid-cols-4">
                    {['Schedule', 'Client', 'Package', 'Review'].map((label, index) => (
                        <div
                            key={label}
                            className={`rounded-2xl border px-3 py-2 text-sm font-semibold ${
                                index === step
                                    ? 'border-[var(--primary-color)] bg-[color-mix(in_oklab,var(--primary-color)_20%,white_80%)] text-slate-900'
                                    : index < step
                                      ? 'border-emerald-300 bg-emerald-50 text-emerald-800'
                                      : 'border-slate-200 bg-white text-slate-500'
                            }`}
                        >
                            {label}
                        </div>
                    ))}
                </div>
            </section>

            <form className="app-shell-panel rounded-[2rem] p-6 md:p-8" onSubmit={submit}>
                {step === 0 ? (
                    <div className="grid gap-4 md:grid-cols-2">
                        <label className="space-y-1 text-sm font-semibold text-slate-700">
                            Event Name
                            <input
                                className="w-full rounded-xl border border-slate-300 bg-white px-3 py-2"
                                value={form.event_name}
                                onChange={(event) => updateEventField('event_name', event.target.value)}
                            />
                        </label>
                        <label className="space-y-1 text-sm font-semibold text-slate-700">
                            Event Date
                            <input
                                type="date"
                                className="w-full rounded-xl border border-slate-300 bg-white px-3 py-2"
                                value={form.event_date}
                                onChange={(event) => updateEventField('event_date', event.target.value)}
                            />
                        </label>
                        <label className="space-y-1 text-sm font-semibold text-slate-700 md:col-span-2">
                            Location
                            <input
                                className="w-full rounded-xl border border-slate-300 bg-white px-3 py-2"
                                value={form.location}
                                onChange={(event) => updateEventField('location', event.target.value)}
                                placeholder="Ortigas, Pasig"
                            />
                        </label>
                        <label className="space-y-1 text-sm font-semibold text-slate-700">
                            Guest Count
                            <input
                                type="number"
                                min={1}
                                className="w-full rounded-xl border border-slate-300 bg-white px-3 py-2"
                                value={form.guest_count}
                                onChange={(event) => updateEventField('guest_count', event.target.value)}
                            />
                        </label>
                    </div>
                ) : null}

                {step === 1 ? (
                    <div className="grid gap-4 md:grid-cols-2">
                        <label className="space-y-1 text-sm font-semibold text-slate-700">
                            Client First Name
                            <input
                                className="w-full rounded-xl border border-slate-300 bg-white px-3 py-2"
                                value={form.client.first_name}
                                onChange={(event) => updateClientField('first_name', event.target.value)}
                            />
                        </label>
                        <label className="space-y-1 text-sm font-semibold text-slate-700">
                            Client Last Name
                            <input
                                className="w-full rounded-xl border border-slate-300 bg-white px-3 py-2"
                                value={form.client.last_name}
                                onChange={(event) => updateClientField('last_name', event.target.value)}
                            />
                        </label>
                        <label className="space-y-1 text-sm font-semibold text-slate-700">
                            Email
                            <input
                                type="email"
                                className="w-full rounded-xl border border-slate-300 bg-white px-3 py-2"
                                value={form.client.email}
                                onChange={(event) => updateClientField('email', event.target.value)}
                            />
                        </label>
                        <label className="space-y-1 text-sm font-semibold text-slate-700">
                            Phone
                            <input
                                className="w-full rounded-xl border border-slate-300 bg-white px-3 py-2"
                                value={form.client.phone}
                                onChange={(event) => updateClientField('phone', event.target.value)}
                            />
                        </label>
                    </div>
                ) : null}

                {step === 2 ? (
                    <div className="space-y-4">
                        {packagesQuery.isError ? (
                            <p className="rounded-xl border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-900">
                                Unable to load package catalog. You can still proceed with a custom quote.
                            </p>
                        ) : null}

                        <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                            {packageOptions.map((option) => {
                                const selected = option.id === form.catering_package_id;

                                return (
                                    <button
                                        key={option.name}
                                        type="button"
                                        onClick={() => {
                                            updateEventField('catering_package_id', option.id);
                                            if (option.basePrice > 0) {
                                                updateEventField('quoted_total', option.basePrice);
                                            }
                                        }}
                                        className={`rounded-2xl border p-4 text-left ${
                                            selected
                                                ? 'border-[var(--primary-color)] bg-[color-mix(in_oklab,var(--primary-color)_20%,white_80%)]'
                                                : 'border-slate-200 bg-white hover:border-slate-400'
                                        }`}
                                    >
                                        <p className="font-semibold text-slate-900">{option.name}</p>
                                        <p className="mt-1 text-sm text-slate-600">Base {formatCurrency(option.basePrice)}</p>
                                    </button>
                                );
                            })}
                        </div>

                        <label className="space-y-1 text-sm font-semibold text-slate-700">
                            Quoted Total
                            <input
                                type="number"
                                min={0}
                                className="w-full rounded-xl border border-slate-300 bg-white px-3 py-2"
                                value={form.quoted_total}
                                onChange={(event) => updateEventField('quoted_total', event.target.value)}
                            />
                        </label>
                    </div>
                ) : null}

                {step === 3 ? (
                    <div className="space-y-3 rounded-2xl border border-slate-200 bg-white p-4 text-sm text-slate-700">
                        <p>
                            <span className="font-semibold">Event:</span> {form.event_name}
                        </p>
                        <p>
                            <span className="font-semibold">Date:</span> {form.event_date}
                        </p>
                        <p>
                            <span className="font-semibold">Location:</span> {form.location}
                        </p>
                        <p>
                            <span className="font-semibold">Guests:</span> {form.guest_count}
                        </p>
                        <p>
                            <span className="font-semibold">Client:</span> {form.client.first_name} {form.client.last_name}
                        </p>
                        <p>
                            <span className="font-semibold">Package:</span> {selectedPackage.name}
                        </p>
                        <p>
                            <span className="font-semibold">Quote:</span> {formatCurrency(form.quoted_total)}
                        </p>
                    </div>
                ) : null}

                {createEventMutation.isError ? (
                    <p className="mt-4 rounded-xl border border-rose-300 bg-rose-50 px-3 py-2 text-sm text-rose-900">
                        {createEventMutation.error?.response?.data?.message ?? 'Unable to create event. Verify all required fields.'}
                    </p>
                ) : null}

                {createEventMutation.isSuccess ? (
                    <p className="mt-4 rounded-xl border border-emerald-300 bg-emerald-50 px-3 py-2 text-sm text-emerald-900">
                        Booking created: {createEventMutation.data.event_name} ({createEventMutation.data.event_date})
                    </p>
                ) : null}

                <div className="mt-6 flex items-center justify-between">
                    <button
                        type="button"
                        onClick={() => setStep((current) => Math.max(current - 1, 0))}
                        disabled={step === 0}
                        className="rounded-full border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 disabled:opacity-40"
                    >
                        Back
                    </button>
                    {step < 3 ? (
                        <button
                            type="button"
                            onClick={() => setStep((current) => Math.min(current + 1, 3))}
                            disabled={!canMoveNext}
                            className="rounded-full bg-[var(--primary-color)] px-5 py-2 text-sm font-semibold text-white disabled:opacity-40"
                        >
                            Continue
                        </button>
                    ) : (
                        <button
                            type="submit"
                            disabled={createEventMutation.isPending}
                            className="rounded-full bg-[var(--primary-color)] px-5 py-2 text-sm font-semibold text-white disabled:opacity-40"
                        >
                            {createEventMutation.isPending ? 'Submitting...' : 'Create Booking'}
                        </button>
                    )}
                </div>
            </form>
        </div>
    );
}
