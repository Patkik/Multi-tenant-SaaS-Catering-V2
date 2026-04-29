import { useEffect, useMemo, useState } from 'react';
import { Link, Navigate, useNavigate, useParams } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { fetchCentralPlansPricing, updateCentralPlan } from '../../../api/centralApi';

const featureCatalog = [
    {
        key: 'event_management',
        label: 'Event management',
        description: 'Bookings, timelines, and event operations inside the tenant workspace.',
    },
    {
        key: 'client_portal',
        label: 'Client portal',
        description: 'Client-facing access for bookings, updates, and confirmations.',
    },
    {
        key: 'staff_assignment',
        label: 'Staff assignment',
        description: 'Assign staff to events and manage shift coverage.',
    },
    {
        key: 'advanced_analytics',
        label: 'Advanced analytics',
        description: 'Deeper reporting and insights for operational decisions.',
    },
    {
        key: 'branding_controls',
        label: 'Branding controls',
        description: 'Logo, color, and visual customization controls for the tenant.',
    },
];

function toPlanLabel(plan) {
    if (!plan) {
        return 'Plan';
    }

    return plan.label || String(plan.key || 'Plan');
}

function toDraftNumber(value) {
    return value === null || value === undefined ? '' : String(value);
}

function parseOptionalInteger(value) {
    const normalizedValue = String(value ?? '').trim();

    if (normalizedValue === '') {
        return null;
    }

    return Number.parseInt(normalizedValue, 10);
}

export function CentralPlanEditPage() {
    const { plan: planKey } = useParams();
    const navigate = useNavigate();
    const queryClient = useQueryClient();
    const [formState, setFormState] = useState({
        monthly_price: '0',
        user_limit: '',
        monthly_active_event_limit: '',
        features: [],
    });

    const plansPricingQuery = useQuery({
        queryKey: ['central-plans-pricing'],
        queryFn: fetchCentralPlansPricing,
        staleTime: 1000 * 60,
    });

    const plan = useMemo(() => {
        const plans = plansPricingQuery.data?.plans ?? [];

        return plans.find((entry) => entry.key === planKey) ?? null;
    }, [planKey, plansPricingQuery.data?.plans]);

    useEffect(() => {
        if (!plan) {
            return;
        }

        setFormState({
            monthly_price: toDraftNumber(plan.monthly_price),
            user_limit: toDraftNumber(plan.user_limit),
            monthly_active_event_limit: toDraftNumber(plan.monthly_active_event_limit),
            features: Array.isArray(plan.features) ? plan.features : [],
        });
    }, [plan]);

    const updatePlanMutation = useMutation({
        mutationFn: (payload) => updateCentralPlan(planKey, payload),
        onSuccess: async () => {
            await Promise.all([
                queryClient.invalidateQueries({ queryKey: ['central-plans-pricing'] }),
                queryClient.invalidateQueries({ queryKey: ['central-plans'] }),
                queryClient.invalidateQueries({ queryKey: ['central-dashboard'] }),
            ]);

            navigate('/central/plans-pricing');
        },
    });

    function updateField(field, value) {
        setFormState((previous) => ({
            ...previous,
            [field]: value,
        }));
    }

    function toggleFeature(featureKey) {
        setFormState((previous) => ({
            ...previous,
            features: previous.features.includes(featureKey)
                ? previous.features.filter((item) => item !== featureKey)
                : [...previous.features, featureKey],
        }));
    }

    function handleSubmit(event) {
        event.preventDefault();

        updatePlanMutation.mutate({
            monthly_price: Number.parseInt(formState.monthly_price || '0', 10),
            user_limit: parseOptionalInteger(formState.user_limit),
            monthly_active_event_limit: parseOptionalInteger(formState.monthly_active_event_limit),
            features: formState.features,
        });
    }

    if (plansPricingQuery.isPending) {
        return (
            <section className="central-card p-4">
                <p className="text-[12px]" style={{ color: 'var(--color-text-secondary)' }}>
                    Loading plan details...
                </p>
            </section>
        );
    }

    if (plansPricingQuery.isError) {
        return (
            <section className="central-card p-4">
                <p className="text-[12px]" style={{ color: 'var(--color-text-secondary)' }}>
                    Failed to load plan details.
                </p>
            </section>
        );
    }

    if (!plan) {
        return <Navigate to="/central/plans-pricing" replace />;
    }

    const selectedFeatures = new Set(formState.features);

    return (
        <div className="space-y-4 pb-2">
            <section className="central-card p-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <p className="text-[11px] uppercase tracking-[0.08em]" style={{ color: 'var(--color-text-tertiary)' }}>
                            Plan Administration
                        </p>
                        <h1 className="text-lg font-semibold">Edit {toPlanLabel(plan)}</h1>
                        <p className="mt-1 text-[12px]" style={{ color: 'var(--color-text-secondary)' }}>
                            Update pricing, limits, and feature access for this plan.
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        <Link
                            to="/central/plans-pricing"
                            className="rounded-[var(--border-radius-md)] border px-3 py-2 text-[12px] font-medium"
                            style={{ borderColor: 'var(--color-border-tertiary)' }}
                        >
                            Back to Pricing
                        </Link>
                    </div>
                </div>
            </section>

            <form onSubmit={handleSubmit} className="central-card p-4">
                <div className="grid gap-3 md:grid-cols-3">
                    <label className="space-y-1">
                        <span className="text-[11px] font-semibold" style={{ color: 'var(--color-text-secondary)' }}>
                            Monthly Price
                        </span>
                        <input
                            type="number"
                            min="0"
                            step="1"
                            value={formState.monthly_price}
                            onChange={(event) => updateField('monthly_price', event.target.value)}
                            className="central-input h-9 w-full rounded-[var(--border-radius-md)] border px-3 text-[12px]"
                            placeholder="0"
                        />
                    </label>

                    <label className="space-y-1">
                        <span className="text-[11px] font-semibold" style={{ color: 'var(--color-text-secondary)' }}>
                            User Limit
                        </span>
                        <input
                            type="number"
                            min="1"
                            step="1"
                            value={formState.user_limit}
                            onChange={(event) => updateField('user_limit', event.target.value)}
                            className="central-input h-9 w-full rounded-[var(--border-radius-md)] border px-3 text-[12px]"
                            placeholder="Unlimited"
                        />
                    </label>

                    <label className="space-y-1">
                        <span className="text-[11px] font-semibold" style={{ color: 'var(--color-text-secondary)' }}>
                            Monthly Active Event Limit
                        </span>
                        <input
                            type="number"
                            min="1"
                            step="1"
                            value={formState.monthly_active_event_limit}
                            onChange={(event) => updateField('monthly_active_event_limit', event.target.value)}
                            className="central-input h-9 w-full rounded-[var(--border-radius-md)] border px-3 text-[12px]"
                            placeholder="Unlimited"
                        />
                    </label>
                </div>

                <div className="mt-4 space-y-2">
                    <div className="flex items-center justify-between gap-2">
                        <p className="text-[11px] font-semibold" style={{ color: 'var(--color-text-secondary)' }}>
                            Feature Access
                        </p>
                        <p className="text-[11px]" style={{ color: 'var(--color-text-tertiary)' }}>
                            {selectedFeatures.size} selected
                        </p>
                    </div>
                    <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        {featureCatalog.map((feature) => {
                            const checked = selectedFeatures.has(feature.key);

                            return (
                                <label
                                    key={feature.key}
                                    className="flex gap-3 rounded-[var(--border-radius-md)] border px-3 py-2 text-[12px]"
                                    style={{ borderColor: 'var(--color-border-tertiary)' }}
                                >
                                    <input type="checkbox" className="mt-0.5" checked={checked} onChange={() => toggleFeature(feature.key)} />
                                    <span className="space-y-0.5">
                                        <span className="block font-medium" style={{ color: 'var(--color-text-primary)' }}>
                                            {feature.label}
                                        </span>
                                        <span className="block text-[11px]" style={{ color: 'var(--color-text-tertiary)' }}>
                                            {feature.description}
                                        </span>
                                    </span>
                                </label>
                            );
                        })}
                    </div>
                    <p className="text-[11px]" style={{ color: 'var(--color-text-tertiary)' }}>
                        Remove a feature here to disable it for tenants on this plan.
                    </p>
                </div>

                {updatePlanMutation.isError ? (
                    <p className="mt-3 rounded-[var(--border-radius-md)] border px-3 py-2 text-[11px]" style={{ borderColor: '#D85A30', color: '#712B13' }}>
                        Failed to update the plan. Please verify the values and try again.
                    </p>
                ) : null}

                <div className="mt-4 flex items-center gap-2">
                    <button
                        type="submit"
                        disabled={updatePlanMutation.isPending}
                        className="central-button-primary px-4 py-2 text-[12px] font-semibold disabled:opacity-60"
                    >
                        {updatePlanMutation.isPending ? 'Saving...' : 'Save changes'}
                    </button>
                    <Link
                        to="/central/plans-pricing"
                        className="rounded-[var(--border-radius-md)] border px-4 py-2 text-[12px] font-medium"
                        style={{ borderColor: 'var(--color-border-tertiary)' }}
                    >
                        Cancel
                    </Link>
                </div>
            </form>
        </div>
    );
}