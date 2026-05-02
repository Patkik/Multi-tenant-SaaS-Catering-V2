import { useMemo, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { checkCentralSubdomainAvailability, fetchCentralPlans, registerTenant } from '../../../api/centralApi';
import { formatCurrency } from '../../../lib/formatters';

const steps = ['Business Info', 'Subdomain', 'Plan', 'Confirm'];
const preferredPlanOrder = ['free', 'starter', 'business'];

const initialForm = {
    firstName: '',
    lastName: '',
    middleInitial: '',
    businessName: '',
    email: '',
    password: '',
    verifyPassword: '',
    subdomain: '',
    plan: 'starter',
};

function toDisplayPlanLabel(planKey) {
    if (planKey === 'starter') {
        return 'Basic';
    }
    if (planKey === 'business') {
        return 'Premium';
    }
    return 'Free';
}

function toUsername(form) {
    const fromEmail = String(form.email || '').split('@')[0];
    const raw = fromEmail || `${form.firstName}${form.lastName}` || 'owner';
    return raw.toLowerCase().replace(/[^a-z0-9._-]/g, '').slice(0, 50) || 'owner';
}

export function CentralRegistrationWizardPage() {
    const queryClient = useQueryClient();
    const [step, setStep] = useState(0);
    const [form, setForm] = useState(initialForm);

    const plansQuery = useQuery({
        queryKey: ['central-plans'],
        queryFn: fetchCentralPlans,
        staleTime: 1000 * 60,
    });

    const availablePlans = useMemo(() => {
        const plans = plansQuery.data ?? [];
        const byKey = new Map(plans.map((plan) => [plan.key, plan]));

        return preferredPlanOrder.map((planKey) => byKey.get(planKey)).filter(Boolean);
    }, [plansQuery.data]);

    const subdomainQuery = useQuery({
        queryKey: ['central-subdomain-availability', form.subdomain],
        queryFn: () => checkCentralSubdomainAvailability(form.subdomain),
        enabled: step >= 1 && form.subdomain.length >= 3,
        staleTime: 1000 * 10,
    });

    const registerMutation = useMutation({
        mutationFn: registerTenant,
        onSuccess: async () => {
            await Promise.all([
                queryClient.invalidateQueries({ queryKey: ['central-tenants'] }),
                queryClient.invalidateQueries({ queryKey: ['central-dashboard'] }),
                queryClient.invalidateQueries({ queryKey: ['central-revenue-analytics'] }),
            ]);
        },
    });

    const selectedPlan = useMemo(() => {
        return availablePlans.find((plan) => plan.key === form.plan) ?? availablePlans[0];
    }, [availablePlans, form.plan]);

    function updateField(key, value) {
        setForm((previous) => ({
            ...previous,
            [key]: value,
        }));
    }

    function moveForward() {
        setStep((previous) => Math.min(previous + 1, 3));
    }

    function moveBack() {
        setStep((previous) => Math.max(previous - 1, 0));
    }

    function handleProvision() {
        registerMutation.mutate({
            company_name: form.businessName,
            subdomain: form.subdomain,
            plan: form.plan,
            admin: {
                username: toUsername(form),
                lastname: form.lastName,
                mi: form.middleInitial || null,
                firstname: form.firstName,
                password: form.password,
                password_confirmation: form.verifyPassword,
            },
        });
    }

    const subdomainAvailable = Boolean(subdomainQuery.data?.available);
    const showSubdomainAvailability = form.subdomain.length >= 3 && !subdomainQuery.isPending;

    return (
        <div className="space-y-4 pb-2">
            <section className="central-card p-4">
                <div className="grid gap-2 md:grid-cols-4">
                    {steps.map((label, index) => {
                        const isCompleted = index < step;
                        const isCurrent = index === step;

                        return (
                            <div
                                key={label}
                                className="flex items-center gap-2 rounded-[var(--border-radius-md)] border px-2.5 py-2 text-[11px]"
                                style={{
                                    borderColor: 'var(--color-border-tertiary)',
                                    backgroundColor: isCurrent ? 'var(--color-background-secondary)' : 'var(--color-background-primary)',
                                    color: isCurrent ? 'var(--color-text-primary)' : isCompleted ? 'var(--color-text-primary)' : 'var(--color-text-tertiary)',
                                }}
                            >
                                <span
                                    className="inline-flex h-4 w-4 items-center justify-center rounded-full border text-[10px]"
                                    style={{
                                        borderColor: 'var(--color-border-tertiary)',
                                        backgroundColor: isCompleted ? 'var(--color-background-secondary)' : 'var(--color-background-primary)',
                                        color: isCompleted ? 'var(--color-text-primary)' : 'var(--color-text-tertiary)',
                                    }}
                                >
                                    {isCompleted ? '✓' : index + 1}
                                </span>
                                <span className="font-semibold">{label}</span>
                            </div>
                        );
                    })}
                </div>
            </section>

            <section className="central-card p-4">
                {step === 0 ? (
                    <div className="grid gap-3 md:grid-cols-2">
                        <label className="text-[12px]">
                            First name
                            <input value={form.firstName} onChange={(event) => updateField('firstName', event.target.value)} className="central-input mt-1 h-9 w-full rounded-[var(--border-radius-md)] border px-3" />
                        </label>
                        <label className="text-[12px]">
                            Last name
                            <input value={form.lastName} onChange={(event) => updateField('lastName', event.target.value)} className="central-input mt-1 h-9 w-full rounded-[var(--border-radius-md)] border px-3" />
                        </label>
                        <label className="text-[12px]">
                            Middle initial
                            <input value={form.middleInitial} onChange={(event) => updateField('middleInitial', event.target.value)} className="central-input mt-1 h-9 w-full rounded-[var(--border-radius-md)] border px-3" />
                        </label>
                        <label className="text-[12px]">
                            Business name
                            <input value={form.businessName} onChange={(event) => updateField('businessName', event.target.value)} className="central-input mt-1 h-9 w-full rounded-[var(--border-radius-md)] border px-3" />
                        </label>
                        <label className="text-[12px]">
                            Email
                            <input type="email" value={form.email} onChange={(event) => updateField('email', event.target.value)} className="central-input mt-1 h-9 w-full rounded-[var(--border-radius-md)] border px-3" />
                        </label>
                        <label className="text-[12px]">
                            Password
                            <input type="password" value={form.password} onChange={(event) => updateField('password', event.target.value)} className="central-input mt-1 h-9 w-full rounded-[var(--border-radius-md)] border px-3" />
                        </label>
                        <label className="text-[12px] md:col-span-2">
                            Verify password
                            <input type="password" value={form.verifyPassword} onChange={(event) => updateField('verifyPassword', event.target.value)} className="central-input mt-1 h-9 w-full rounded-[var(--border-radius-md)] border px-3" />
                        </label>
                    </div>
                ) : null}

                {step === 1 ? (
                    <div className="space-y-3">
                        <label className="text-[12px]">
                            Subdomain
                            <div className="mt-1 flex rounded-[var(--border-radius-md)] border" style={{ borderColor: 'var(--color-border-tertiary)' }}>
                                <input
                                    value={form.subdomain}
                                    onChange={(event) => updateField('subdomain', event.target.value.toLowerCase().replace(/[^a-z0-9-]/g, ''))}
                                    className="central-input h-9 w-full rounded-l-[var(--border-radius-md)] border-none px-3"
                                    placeholder="northfeast"
                                />
                                <span
                                    className="inline-flex items-center rounded-r-[var(--border-radius-md)] border-l px-3 text-[11px]"
                                    style={{
                                        borderColor: 'var(--color-border-tertiary)',
                                        backgroundColor: 'var(--color-background-secondary)',
                                        color: 'var(--color-text-secondary)',
                                    }}
                                >
                                    .caterpro.ph
                                </span>
                            </div>
                        </label>
                        {showSubdomainAvailability ? (
                            <p
                                className="inline-flex rounded-full border px-2.5 py-1 text-[10px] font-semibold"
                                style={{
                                    borderColor: subdomainAvailable ? '#1D9E75' : '#D85A30',
                                    backgroundColor: subdomainAvailable ? '#E1F5EE' : '#FAECE7',
                                    color: subdomainAvailable ? '#085041' : '#712B13',
                                }}
                            >
                                {subdomainAvailable ? 'Subdomain available' : 'Subdomain unavailable'}
                            </p>
                        ) : null}
                    </div>
                ) : null}

                {step === 2 ? (
                    <div className="grid gap-3 md:grid-cols-3">
                        {availablePlans.map((plan) => {
                            const isSelected = form.plan === plan.key;
                            const isFeatured = plan.key === 'starter';
                            return (
                                <button
                                    key={plan.key}
                                    type="button"
                                    onClick={() => updateField('plan', plan.key)}
                                    className="rounded-[var(--border-radius-lg)] p-4 text-left"
                                    style={{
                                        borderStyle: 'solid',
                                        borderWidth: isFeatured ? '2px' : '0.5px',
                                        borderColor: isFeatured ? '#378ADD' : isSelected ? '#1D9E75' : 'var(--color-border-tertiary)',
                                        backgroundColor: 'var(--color-background-primary)',
                                    }}
                                >
                                    <div className="flex items-center justify-between">
                                        <h3 className="text-[14px] font-semibold">{toDisplayPlanLabel(plan.key)}</h3>
                                        {isFeatured ? (
                                            <span
                                                className="rounded-full border px-2 py-0.5 text-[10px] font-semibold"
                                                style={{
                                                    borderColor: '#378ADD',
                                                    backgroundColor: '#E6F1FB',
                                                    color: '#0C447C',
                                                }}
                                            >
                                                Most popular
                                            </span>
                                        ) : null}
                                    </div>
                                    <p className="mt-2 text-[21px] font-semibold">{formatCurrency(plan.monthly_price)}/mo</p>
                                </button>
                            );
                        })}
                    </div>
                ) : null}

                {step === 3 ? (
                    <div className="space-y-3">
                        <table className="central-table w-full text-left text-[12px]" style={{ tableLayout: 'fixed' }}>
                            <colgroup>
                                <col style={{ width: '28%' }} />
                                <col style={{ width: '72%' }} />
                            </colgroup>
                            <tbody>
                                <tr>
                                    <th>Business</th>
                                    <td>{form.businessName || '—'}</td>
                                </tr>
                                <tr>
                                    <th>Owner</th>
                                    <td>{[form.firstName, form.middleInitial, form.lastName].filter(Boolean).join(' ') || '—'}</td>
                                </tr>
                                <tr>
                                    <th>Subdomain</th>
                                    <td>{form.subdomain ? `${form.subdomain}.caterpro.ph` : '—'}</td>
                                </tr>
                                <tr>
                                    <th>Plan</th>
                                    <td>{selectedPlan ? toDisplayPlanLabel(selectedPlan.key) : '—'}</td>
                                </tr>
                                <tr>
                                    <th>DB note</th>
                                    <td>Tenant schema and queue channels are provisioned after confirmation.</td>
                                </tr>
                            </tbody>
                        </table>

                        <div
                            className="rounded-[var(--border-radius-md)] border px-3 py-2 text-[11px]"
                            style={{
                                borderColor: '#EF9F27',
                                backgroundColor: '#FAEEDA',
                                color: '#633806',
                            }}
                        >
                            Provisioning creates tenant records, database allocation, default roles, and feature gates. This can take a few seconds.
                        </div>
                    </div>
                ) : null}

                {registerMutation.isError ? (
                    <div
                        className="mt-3 rounded-[var(--border-radius-md)] border px-3 py-2 text-[11px]"
                        style={{
                            borderColor: '#D85A30',
                            backgroundColor: '#FAECE7',
                            color: '#712B13',
                        }}
                    >
                        {registerMutation.error?.response?.data?.message ?? 'Unable to provision tenant.'}
                    </div>
                ) : null}

                {registerMutation.isSuccess ? (
                    <div
                        className="mt-3 rounded-[var(--border-radius-md)] border px-3 py-2 text-[11px]"
                        style={{
                            borderColor: '#1D9E75',
                            backgroundColor: '#E1F5EE',
                            color: '#085041',
                        }}
                    >
                        Tenant provisioning started for {form.businessName || 'new tenant'}.
                    </div>
                ) : null}

                <div className="mt-4 flex items-center justify-between">
                    <button type="button" onClick={moveBack} disabled={step === 0} className="central-button px-3 py-2 text-[12px] font-semibold disabled:opacity-50">
                        Back
                    </button>
                    {step < 3 ? (
                        <button type="button" onClick={moveForward} className="central-button-muted px-3 py-2 text-[12px] font-semibold">
                            Continue
                        </button>
                    ) : (
                        <button
                            type="button"
                            onClick={handleProvision}
                            disabled={registerMutation.isPending || !subdomainAvailable}
                            className="central-button-muted px-3 py-2 text-[12px] font-semibold disabled:opacity-50"
                        >
                            {registerMutation.isPending ? 'Provisioning...' : 'Provision'}
                        </button>
                    )}
                </div>
            </section>
        </div>
    );
}

