import { useEffect, useMemo, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
    checkCentralSubdomainAvailability,
    fetchCentralTenantEditContext,
    fetchCentralTenantUsers,
    updateCentralTenant,
    updateCentralTenantUser,
} from '../../../api/centralApi';

function toUserDraft(user) {
    return {
        username: user.username || '',
        firstname: user.firstname || '',
        lastname: user.lastname || '',
        email: user.email || '',
        role: user.role || 'Staff',
        is_active: Boolean(user.is_active),
        password: '',
    };
}

function toDisplayPlanLabel(plan) {
    const normalized = String(plan ?? '').toLowerCase();

    if (normalized === 'starter') {
        return 'Basic';
    }

    if (normalized === 'business') {
        return 'Premium';
    }

    return String(plan || 'Free');
}

export function CentralTenantEditPage() {
    const { tenantId } = useParams();
    const navigate = useNavigate();
    const queryClient = useQueryClient();
    const [tenantForm, setTenantForm] = useState({
        company_name: '',
        subdomain: '',
        plan: 'free',
        enabled_features: [],
        client_access: false,
        is_active: true,
    });
    const [userDrafts, setUserDrafts] = useState({});

    const tenantContextQuery = useQuery({
        queryKey: ['central-tenant-edit-context', tenantId],
        queryFn: () => fetchCentralTenantEditContext(tenantId),
        enabled: Boolean(tenantId),
        staleTime: 1000 * 15,
    });

    const usersQuery = useQuery({
        queryKey: ['central-tenant-users', tenantId],
        queryFn: () => fetchCentralTenantUsers(tenantId),
        enabled: Boolean(tenantId),
        staleTime: 1000 * 15,
    });

    const tenantMutation = useMutation({
        mutationFn: (payload) => updateCentralTenant(tenantId, payload),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['central-tenants'] });
            queryClient.invalidateQueries({ queryKey: ['central-dashboard'] });
            queryClient.invalidateQueries({ queryKey: ['central-tenant-edit-context', tenantId] });
        },
    });

    const userMutation = useMutation({
        mutationFn: ({ userId, payload }) => updateCentralTenantUser(tenantId, userId, payload),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['central-tenant-users', tenantId] });
        },
    });

    useEffect(() => {
        const tenant = tenantContextQuery.data?.tenant;

        if (!tenant) {
            return;
        }

        setTenantForm({
            company_name: tenant.company_name || '',
            subdomain: tenant.subdomain || '',
            plan: tenant.plan || 'free',
            enabled_features: Array.isArray(tenant.enabled_features) ? tenant.enabled_features : [],
            client_access: Boolean(tenant.client_access),
            is_active: Boolean(tenant.is_active),
        });
    }, [tenantContextQuery.data?.tenant]);

    useEffect(() => {
        const users = usersQuery.data ?? [];

        if (!Array.isArray(users)) {
            return;
        }

        setUserDrafts((previous) => {
            const next = {};

            users.forEach((user) => {
                next[user.id] = previous[user.id] ?? toUserDraft(user);
            });

            return next;
        });
    }, [usersQuery.data]);

    const availablePlans = tenantContextQuery.data?.available_plans ?? [];
    const featureCatalog = tenantContextQuery.data?.feature_catalog ?? [];
    const availableRoles = tenantContextQuery.data?.available_roles ?? ['Admin', 'Manager', 'Staff', 'Cashier'];
    const users = usersQuery.data ?? [];

    const planByKey = useMemo(() => {
        return new Map(availablePlans.map((plan) => [plan.key, plan]));
    }, [availablePlans]);

    const currentSubdomain = String(tenantContextQuery.data?.tenant?.subdomain ?? '').toLowerCase();
    const nextSubdomain = String(tenantForm.subdomain ?? '').trim().toLowerCase();
    const shouldCheckSubdomain = nextSubdomain.length >= 3 && nextSubdomain !== currentSubdomain;

    const subdomainAvailabilityQuery = useQuery({
        queryKey: ['central-subdomain-availability', tenantId, nextSubdomain],
        queryFn: () => checkCentralSubdomainAvailability(nextSubdomain, tenantId),
        enabled: shouldCheckSubdomain,
        staleTime: 1000 * 10,
    });

    function updateTenantField(field, value) {
        setTenantForm((previous) => ({
            ...previous,
            [field]: value,
        }));
    }

    function toggleFeature(featureKey) {
        setTenantForm((previous) => {
            const hasFeature = previous.enabled_features.includes(featureKey);

            return {
                ...previous,
                enabled_features: hasFeature
                    ? previous.enabled_features.filter((item) => item !== featureKey)
                    : [...previous.enabled_features, featureKey],
            };
        });
    }

    function onPlanChange(planKey) {
        const selectedPlan = planByKey.get(planKey);

        setTenantForm((previous) => ({
            ...previous,
            plan: planKey,
            enabled_features: Array.isArray(selectedPlan?.default_features) ? selectedPlan.default_features : previous.enabled_features,
        }));
    }

    function onSaveTenant(event) {
        event.preventDefault();

        tenantMutation.mutate({
            company_name: tenantForm.company_name.trim(),
            subdomain: tenantForm.subdomain.trim().toLowerCase(),
            plan: tenantForm.plan,
            enabled_features: tenantForm.enabled_features,
            client_access: Boolean(tenantForm.client_access),
            is_active: Boolean(tenantForm.is_active),
        });
    }

    function updateUserDraft(userId, field, value) {
        setUserDrafts((previous) => ({
            ...previous,
            [userId]: {
                ...(previous[userId] ?? {}),
                [field]: value,
            },
        }));
    }

    function saveUser(userId) {
        const draft = userDrafts[userId];

        if (!draft) {
            return;
        }

        userMutation.mutate({
            userId,
            payload: {
                username: draft.username,
                firstname: draft.firstname,
                lastname: draft.lastname,
                email: draft.email || null,
                role: draft.role,
                is_active: Boolean(draft.is_active),
                password: draft.password || undefined,
            },
        });
    }

    if (tenantContextQuery.isPending) {
        return (
            <section className="central-card p-4">
                <p className="text-[12px]" style={{ color: 'var(--color-text-secondary)' }}>
                    Loading tenant details...
                </p>
            </section>
        );
    }

    if (tenantContextQuery.isError) {
        return (
            <section className="central-card p-4">
                <p className="text-[12px]" style={{ color: 'var(--color-text-secondary)' }}>
                    Failed to load tenant details.
                </p>
            </section>
        );
    }

    return (
        <div className="space-y-4 pb-2">
            <section className="central-card p-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <p className="text-[11px] uppercase tracking-[0.08em]" style={{ color: 'var(--color-text-tertiary)' }}>
                            Tenant Administration
                        </p>
                        <h1 className="text-lg font-semibold">Edit {tenantContextQuery.data?.tenant?.company_name || 'Tenant'}</h1>
                    </div>
                    <div className="flex items-center gap-2">
                        <Link
                            to="/central/tenants"
                            className="rounded-[var(--border-radius-md)] border px-3 py-2 text-[12px] font-medium"
                            style={{ borderColor: 'var(--color-border-tertiary)' }}
                        >
                            Back to Tenants
                        </Link>
                        <button
                            type="button"
                            onClick={() => navigate('/central/tenants')}
                            className="central-button-muted px-3 py-2 text-[12px] font-semibold"
                        >
                            Done
                        </button>
                    </div>
                </div>
            </section>

            <form onSubmit={onSaveTenant} className="central-card p-4">
                <div className="grid gap-3 md:grid-cols-2">
                    <label className="space-y-1">
                        <span className="text-[11px] font-semibold" style={{ color: 'var(--color-text-secondary)' }}>
                            Business Name
                        </span>
                        <input
                            value={tenantForm.company_name}
                            onChange={(event) => updateTenantField('company_name', event.target.value)}
                            className="central-input h-9 w-full rounded-[var(--border-radius-md)] border px-3 text-[12px]"
                            placeholder="Business name"
                        />
                    </label>

                    <label className="space-y-1">
                        <span className="text-[11px] font-semibold" style={{ color: 'var(--color-text-secondary)' }}>
                            Subdomain
                        </span>
                        <input
                            value={tenantForm.subdomain}
                            onChange={(event) => updateTenantField('subdomain', event.target.value.toLowerCase().replace(/[^a-z0-9-]/g, ''))}
                            className="central-input h-9 w-full rounded-[var(--border-radius-md)] border px-3 text-[12px]"
                            placeholder="tenant-subdomain"
                        />
                    </label>
                </div>

                {shouldCheckSubdomain ? (
                    <p className="mt-2 text-[11px]" style={{ color: subdomainAvailabilityQuery.data?.available ? '#085041' : '#712B13' }}>
                        {subdomainAvailabilityQuery.isPending
                            ? 'Checking subdomain availability...'
                            : subdomainAvailabilityQuery.data?.available
                                ? 'Subdomain available.'
                                : 'Subdomain unavailable.'}
                    </p>
                ) : null}

                <div className="mt-4 grid gap-3 md:grid-cols-3">
                    <label className="space-y-1">
                        <span className="text-[11px] font-semibold" style={{ color: 'var(--color-text-secondary)' }}>
                            Plan
                        </span>
                        <select
                            value={tenantForm.plan}
                            onChange={(event) => onPlanChange(event.target.value)}
                            className="central-input h-9 w-full rounded-[var(--border-radius-md)] border px-2.5 text-[12px]"
                        >
                            {availablePlans.map((plan) => (
                                <option key={plan.key} value={plan.key}>
                                    {toDisplayPlanLabel(plan.label)}
                                </option>
                            ))}
                        </select>
                    </label>

                    <label className="inline-flex items-center gap-2 pt-6 text-[12px] font-medium" style={{ color: 'var(--color-text-primary)' }}>
                        <input
                            type="checkbox"
                            checked={Boolean(tenantForm.client_access)}
                            onChange={(event) => updateTenantField('client_access', event.target.checked)}
                        />
                        Client portal access enabled
                    </label>

                    <label className="inline-flex items-center gap-2 pt-6 text-[12px] font-medium" style={{ color: 'var(--color-text-primary)' }}>
                        <input
                            type="checkbox"
                            checked={Boolean(tenantForm.is_active)}
                            onChange={(event) => updateTenantField('is_active', event.target.checked)}
                        />
                        Tenant is active
                    </label>
                </div>

                <div className="mt-4 space-y-2">
                    <p className="text-[11px] font-semibold" style={{ color: 'var(--color-text-secondary)' }}>
                        Feature Access
                    </p>
                    <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        {featureCatalog.map((feature) => {
                            const checked = tenantForm.enabled_features.includes(feature.key);

                            return (
                                <label
                                    key={feature.key}
                                    className="inline-flex items-center gap-2 rounded-[var(--border-radius-md)] border px-3 py-2 text-[12px]"
                                    style={{ borderColor: 'var(--color-border-tertiary)' }}
                                >
                                    <input type="checkbox" checked={checked} onChange={() => toggleFeature(feature.key)} />
                                    <span>{feature.label}</span>
                                </label>
                            );
                        })}
                    </div>
                    <p className="text-[11px]" style={{ color: 'var(--color-text-tertiary)' }}>
                        Disabling a feature removes its related tenant pages after the tenant workspace refreshes capabilities.
                    </p>
                </div>

                {tenantMutation.isError ? (
                    <p className="mt-3 rounded-[var(--border-radius-md)] border px-3 py-2 text-[11px]" style={{ borderColor: '#D85A30', color: '#712B13' }}>
                        {tenantMutation.error?.response?.data?.message ?? 'Unable to save tenant details.'}
                    </p>
                ) : null}

                <div className="mt-4 flex items-center gap-2">
                    <button type="submit" disabled={tenantMutation.isPending} className="central-button px-3 py-2 text-[12px] font-semibold disabled:opacity-50">
                        {tenantMutation.isPending ? 'Saving Tenant...' : 'Save Tenant Changes'}
                    </button>
                </div>
            </form>

            <section className="central-card p-4">
                <div className="mb-2 flex items-center justify-between">
                    <h2 className="text-sm font-semibold">Tenant Users</h2>
                    <p className="text-[11px]" style={{ color: 'var(--color-text-tertiary)' }}>
                        {Array.isArray(users) ? users.length : 0} users
                    </p>
                </div>

                <div className="overflow-x-auto">
                    <table className="central-table w-full text-left text-[12px]" style={{ tableLayout: 'fixed' }}>
                        <colgroup>
                            <col style={{ width: '14%' }} />
                            <col style={{ width: '15%' }} />
                            <col style={{ width: '15%' }} />
                            <col style={{ width: '19%' }} />
                            <col style={{ width: '13%' }} />
                            <col style={{ width: '8%' }} />
                            <col style={{ width: '16%' }} />
                        </colgroup>
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>First Name</th>
                                <th>Last Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Active</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {usersQuery.isPending ? (
                                <tr>
                                    <td colSpan={7} className="py-3 text-center" style={{ color: 'var(--color-text-secondary)' }}>
                                        Loading tenant users...
                                    </td>
                                </tr>
                            ) : null}
                            {usersQuery.isError ? (
                                <tr>
                                    <td colSpan={7} className="py-3 text-center" style={{ color: 'var(--color-text-secondary)' }}>
                                        Failed to load tenant users.
                                    </td>
                                </tr>
                            ) : null}
                            {!usersQuery.isPending && !usersQuery.isError && users.length === 0 ? (
                                <tr>
                                    <td colSpan={7} className="py-3 text-center" style={{ color: 'var(--color-text-tertiary)' }}>
                                        No users found for this tenant.
                                    </td>
                                </tr>
                            ) : null}
                            {users.map((user) => {
                                const draft = userDrafts[user.id] ?? toUserDraft(user);
                                const isSavingThisUser = userMutation.isPending && userMutation.variables?.userId === user.id;

                                return (
                                    <tr key={user.id}>
                                        <td>
                                            <input
                                                value={draft.username}
                                                onChange={(event) => updateUserDraft(user.id, 'username', event.target.value)}
                                                className="central-input h-8 w-full rounded-[var(--border-radius-md)] border px-2 text-[11px]"
                                            />
                                        </td>
                                        <td>
                                            <input
                                                value={draft.firstname}
                                                onChange={(event) => updateUserDraft(user.id, 'firstname', event.target.value)}
                                                className="central-input h-8 w-full rounded-[var(--border-radius-md)] border px-2 text-[11px]"
                                            />
                                        </td>
                                        <td>
                                            <input
                                                value={draft.lastname}
                                                onChange={(event) => updateUserDraft(user.id, 'lastname', event.target.value)}
                                                className="central-input h-8 w-full rounded-[var(--border-radius-md)] border px-2 text-[11px]"
                                            />
                                        </td>
                                        <td>
                                            <input
                                                value={draft.email}
                                                onChange={(event) => updateUserDraft(user.id, 'email', event.target.value)}
                                                className="central-input h-8 w-full rounded-[var(--border-radius-md)] border px-2 text-[11px]"
                                            />
                                        </td>
                                        <td>
                                            <select
                                                value={draft.role}
                                                onChange={(event) => updateUserDraft(user.id, 'role', event.target.value)}
                                                className="central-input h-8 w-full rounded-[var(--border-radius-md)] border px-2 text-[11px]"
                                            >
                                                {availableRoles.map((role) => (
                                                    <option key={role} value={role}>
                                                        {role}
                                                    </option>
                                                ))}
                                            </select>
                                        </td>
                                        <td>
                                            <label className="inline-flex items-center justify-center gap-1 text-[11px]">
                                                <input
                                                    type="checkbox"
                                                    checked={Boolean(draft.is_active)}
                                                    onChange={(event) => updateUserDraft(user.id, 'is_active', event.target.checked)}
                                                />
                                            </label>
                                        </td>
                                        <td>
                                            <button
                                                type="button"
                                                onClick={() => saveUser(user.id)}
                                                disabled={isSavingThisUser}
                                                className="central-button px-2 py-1 text-[11px] font-medium disabled:opacity-50"
                                            >
                                                {isSavingThisUser ? 'Saving...' : 'Save User'}
                                            </button>
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>

                {userMutation.isError ? (
                    <p className="mt-3 rounded-[var(--border-radius-md)] border px-3 py-2 text-[11px]" style={{ borderColor: '#D85A30', color: '#712B13' }}>
                        {userMutation.error?.response?.data?.message ?? 'Unable to save tenant user.'}
                    </p>
                ) : null}
            </section>
        </div>
    );
}
