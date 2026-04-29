import { useQuery } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import { fetchCentralPlansPricing } from '../../../api/centralApi';
import { formatCurrency } from '../../../lib/formatters';

const displayPlanMap = {
    free: 'Free',
    starter: 'Basic',
    business: 'Premium',
};

const planBadgeStyles = {
    Free: { borderColor: '#73726c', backgroundColor: '#F1EFE8', color: '#444441' },
    Basic: { borderColor: '#378ADD', backgroundColor: '#E6F1FB', color: '#0C447C' },
    Premium: { borderColor: '#7F77DD', backgroundColor: '#E6F1FB', color: '#0C447C' },
};

function toPlanLabel(plan) {
    return displayPlanMap[String(plan?.key ?? '').toLowerCase()] ?? (plan?.label || 'Free');
}

export function CentralPlansPricingPage() {
    const plansPricingQuery = useQuery({
        queryKey: ['central-plans-pricing'],
        queryFn: fetchCentralPlansPricing,
        staleTime: 1000 * 60,
    });

    const plans = (plansPricingQuery.data?.plans ?? []).filter((plan) => ['free', 'starter', 'business'].includes(plan.key));
    const featureMatrix = plansPricingQuery.data?.feature_matrix ?? [];

    return (
        <div className="space-y-4 pb-2">
            <section className="grid gap-3 xl:grid-cols-3">
                {plansPricingQuery.isPending ? (
                    <article className="central-card p-4">
                        <p className="text-[12px]" style={{ color: 'var(--color-text-secondary)' }}>
                            Loading plans...
                        </p>
                    </article>
                ) : null}
                {plansPricingQuery.isError ? (
                    <article className="central-card p-4">
                        <p className="text-[12px]" style={{ color: 'var(--color-text-secondary)' }}>
                            Failed to load plans.
                        </p>
                    </article>
                ) : null}
                {plans.map((plan) => {
                    const label = toPlanLabel(plan);
                    const isBasic = label === 'Basic';
                    const userLimit = plan.user_limit ? `${plan.user_limit} users` : 'Unlimited users';
                    const eventLimit = plan.monthly_active_event_limit ? `${plan.monthly_active_event_limit} events/mo` : 'Unlimited events';

                    return (
                        <article key={plan.key} className="central-card p-4">
                            <div className="flex items-center justify-between">
                                <span className="rounded-full border px-2 py-0.5 text-[10px] font-semibold" style={planBadgeStyles[label] ?? planBadgeStyles.Free}>
                                    {label}
                                </span>
                                <Link
                                    to={`/central/plans-pricing/${plan.key}/edit`}
                                    className="rounded-[var(--border-radius-md)] border px-2 py-1 text-[11px]"
                                    style={{ borderColor: 'var(--color-border-tertiary)' }}
                                >
                                    Edit plan
                                </Link>
                            </div>
                            <p className="mt-2 text-[22px] font-semibold">{formatCurrency(plan.monthly_price)}/mo</p>
                            <p className="mt-2 text-[12px]">
                                {plan.tenant_count} tenants · Churn {Number(plan.churn_rate || 0).toFixed(1)}%
                            </p>
                            <p className="mt-1 text-[11px]" style={{ color: 'var(--color-text-secondary)' }}>
                                {userLimit} · {eventLimit}
                            </p>
                            {isBasic ? (
                                <p
                                    className="mt-2 inline-flex rounded-full border px-2 py-0.5 text-[10px] font-semibold"
                                    style={{ borderColor: '#378ADD', backgroundColor: '#E6F1FB', color: '#0C447C' }}
                                >
                                    Most popular
                                </p>
                            ) : null}
                            <ul className="mt-3 space-y-1 text-[11px]">
                                {plan.features.map((feature) => (
                                    <li key={feature} className="flex items-center gap-1.5">
                                        <span>✓</span>
                                        <span>{feature}</span>
                                    </li>
                                ))}
                            </ul>
                        </article>
                    );
                })}
            </section>

            <section className="central-card p-4">
                <h3 className="text-[13px] font-semibold">Feature gate matrix</h3>
                <div className="mt-3 overflow-x-auto">
                    <table className="central-table w-full text-left text-[12px]" style={{ tableLayout: 'fixed' }}>
                        <colgroup>
                            <col style={{ width: '46%' }} />
                            <col style={{ width: '18%' }} />
                            <col style={{ width: '18%' }} />
                            <col style={{ width: '18%' }} />
                        </colgroup>
                        <thead>
                            <tr>
                                <th>Feature</th>
                                <th>Free</th>
                                <th>Basic</th>
                                <th>Premium</th>
                            </tr>
                        </thead>
                        <tbody>
                            {featureMatrix.map((item) => (
                                <tr key={item.feature}>
                                    <td>{item.feature}</td>
                                    <td>{item.free ? '✓' : '✕'}</td>
                                    <td>{item.starter ? '✓' : '✕'}</td>
                                    <td>{item.business ? '✓' : '✕'}</td>
                                </tr>
                            ))}
                            {!plansPricingQuery.isPending && featureMatrix.length === 0 ? (
                                <tr>
                                    <td colSpan={4} className="py-3 text-center" style={{ color: 'var(--color-text-tertiary)' }}>
                                        No feature matrix available.
                                    </td>
                                </tr>
                            ) : null}
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    );
}

