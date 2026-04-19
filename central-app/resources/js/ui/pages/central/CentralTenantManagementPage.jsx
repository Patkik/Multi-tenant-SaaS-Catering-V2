import { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { fetchCentralTenants, updateTenantStatus } from '../../../api/centralApi';

const planBadgeStyles = {
    Free: {
        borderColor: '#73726c',
        backgroundColor: '#F1EFE8',
        color: '#444441',
    },
    Basic: {
        borderColor: '#378ADD',
        backgroundColor: '#E6F1FB',
        color: '#0C447C',
    },
    Premium: {
        borderColor: '#7F77DD',
        backgroundColor: '#E6F1FB',
        color: '#0C447C',
    },
    Enterprise: {
        borderColor: '#7F77DD',
        backgroundColor: '#E6F1FB',
        color: '#0C447C',
    },
};

const statusBadgeStyles = {
    Active: {
        borderColor: '#1D9E75',
        backgroundColor: '#E1F5EE',
        color: '#085041',
    },
    Suspended: {
        borderColor: '#D85A30',
        backgroundColor: '#FAECE7',
        color: '#712B13',
    },
};

function toDisplayPlanLabel(label) {
    const normalized = String(label ?? '').toLowerCase();

    if (normalized === 'starter') {
        return 'Basic';
    }

    if (normalized === 'business') {
        return 'Premium';
    }

    return label || 'Free';
}

function toDisplayStatus(status) {
    return String(status ?? '').toLowerCase() === 'active' ? 'Active' : 'Suspended';
}

function normalizeTenantHost(candidate) {
    const raw = String(candidate ?? '').trim();

    if (!raw) {
        return '';
    }

    const withoutProtocol = raw.replace(/^https?:\/\//i, '');
    const hostWithOptionalPort = withoutProtocol.split('/')[0].trim().toLowerCase();
    const host = hostWithOptionalPort.split(':')[0];

    return host.replace(/\.+/g, '.').replace(/^\.+|\.+$/g, '');
}

function resolveTenantHost(tenant) {
    const subdomainHost = normalizeTenantHost(tenant?.subdomain);
    const fullDomainHost = normalizeTenantHost(tenant?.full_domain);

    if (subdomainHost.includes('.')) {
        return subdomainHost;
    }

    if (fullDomainHost !== '') {
        return fullDomainHost;
    }

    if (subdomainHost !== '') {
        return `${subdomainHost}.localhost`;
    }

    return '';
}

export function CentralTenantManagementPage() {
    const navigate = useNavigate();
    const queryClient = useQueryClient();
    const [search, setSearch] = useState('');
    const [planFilter, setPlanFilter] = useState('all');
    const [statusFilter, setStatusFilter] = useState('all');

    const tenantsQuery = useQuery({
        queryKey: ['central-tenants', { search, planFilter, statusFilter }],
        queryFn: () =>
            fetchCentralTenants({
                perPage: 50,
                search,
                plan: planFilter === 'all' ? '' : planFilter,
                status: statusFilter === 'all' ? '' : statusFilter,
            }),
        staleTime: 1000 * 15,
    });

    const statusMutation = useMutation({
        mutationFn: ({ tenantId, isActive }) => updateTenantStatus(tenantId, isActive),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['central-tenants'] });
            queryClient.invalidateQueries({ queryKey: ['central-dashboard'] });
            queryClient.invalidateQueries({ queryKey: ['central-revenue-analytics'] });
        },
    });

    const rows = tenantsQuery.data?.data ?? [];
    const total = tenantsQuery.data?.total ?? rows.length;

    const handleViewTenant = (tenantHost) => {
        if (!tenantHost) {
            return;
        }

        const protocol = window.location.protocol === 'https:' ? 'https:' : 'http:';
        const currentPort = window.location.port;
        const includePort = currentPort !== '' && currentPort !== '80' && currentPort !== '443';
        const portSuffix = includePort ? `:${currentPort}` : '';

        window.location.assign(`${protocol}//${tenantHost}${portSuffix}/login`);
    };

    return (
        <div className="space-y-4 pb-2">
            <section className="central-card p-4">
                <div className="flex flex-wrap items-center gap-2">
                    <input
                        type="search"
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder="Search business, subdomain, or DB name"
                        className="central-input h-9 min-w-[220px] flex-1 rounded-[var(--border-radius-md)] border px-3 text-[12px]"
                    />
                    <select
                        value={planFilter}
                        onChange={(event) => setPlanFilter(event.target.value)}
                        className="central-input h-9 rounded-[var(--border-radius-md)] border px-2.5 text-[12px]"
                    >
                        <option value="all">All plans</option>
                        <option value="free">Free</option>
                        <option value="starter">Basic</option>
                        <option value="business">Premium</option>
                    </select>
                    <select
                        value={statusFilter}
                        onChange={(event) => setStatusFilter(event.target.value)}
                        className="central-input h-9 rounded-[var(--border-radius-md)] border px-2.5 text-[12px]"
                    >
                        <option value="all">All statuses</option>
                        <option value="active">Active</option>
                        <option value="suspended">Suspended</option>
                    </select>
                    <button
                        type="button"
                        onClick={() => navigate('/central/new-tenant')}
                        className="central-button-muted px-3 py-2 text-[12px] font-semibold"
                    >
                        + New Tenant
                    </button>
                </div>
            </section>

            <section className="central-card p-4">
                <div className="overflow-x-auto">
                    <table className="central-table w-full text-left text-[12px]" style={{ tableLayout: 'fixed' }}>
                        <colgroup>
                            <col style={{ width: '22%' }} />
                            <col style={{ width: '12%' }} />
                            <col style={{ width: '18%' }} />
                            <col style={{ width: '9%' }} />
                            <col style={{ width: '10%' }} />
                            <col style={{ width: '11%' }} />
                            <col style={{ width: '18%' }} />
                        </colgroup>
                        <thead>
                            <tr>
                                <th>Business Name</th>
                                <th>Subdomain</th>
                                <th>DB Name</th>
                                <th>Plan</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {tenantsQuery.isPending ? (
                                <tr>
                                    <td colSpan={7} className="py-3 text-center" style={{ color: 'var(--color-text-secondary)' }}>
                                        Loading tenants...
                                    </td>
                                </tr>
                            ) : null}
                            {tenantsQuery.isError ? (
                                <tr>
                                    <td colSpan={7} className="py-3 text-center" style={{ color: 'var(--color-text-secondary)' }}>
                                        Failed to load tenants.
                                    </td>
                                </tr>
                            ) : null}
                            {!tenantsQuery.isPending && !tenantsQuery.isError && rows.length === 0 ? (
                                <tr>
                                    <td colSpan={7} className="py-3 text-center" style={{ color: 'var(--color-text-tertiary)' }}>
                                        No tenants found.
                                    </td>
                                </tr>
                            ) : null}
                            {rows.map((tenant) => {
                                const displayPlan = toDisplayPlanLabel(tenant.plan_details?.label);
                                const displayStatus = toDisplayStatus(tenant.status);
                                const tenantHost = resolveTenantHost(tenant);
                                const canViewTenant = tenantHost !== '';

                                return (
                                    <tr key={tenant.tenant_id}>
                                        <td className="truncate">{tenant.company_name}</td>
                                        <td className="truncate">{tenant.subdomain}</td>
                                        <td className="truncate">{tenant.db_name || '—'}</td>
                                        <td>
                                            <span
                                                className="inline-flex rounded-full border px-2 py-0.5 text-[10px] font-semibold"
                                                style={planBadgeStyles[displayPlan] ?? planBadgeStyles.Free}
                                            >
                                                {displayPlan}
                                            </span>
                                        </td>
                                        <td>
                                            <span className="inline-flex rounded-full border px-2 py-0.5 text-[10px] font-semibold" style={statusBadgeStyles[displayStatus]}>
                                                {displayStatus}
                                            </span>
                                        </td>
                                        <td>{tenant.created_at ? String(tenant.created_at).slice(0, 10) : '—'}</td>
                                        <td>
                                            <div className="flex flex-wrap gap-1.5">
                                                <button
                                                    type="button"
                                                    onClick={() => handleViewTenant(tenantHost)}
                                                    disabled={!canViewTenant}
                                                    className="rounded-[var(--border-radius-md)] border px-2 py-1 text-[11px]"
                                                    style={{ borderColor: 'var(--color-border-tertiary)' }}
                                                >
                                                    View
                                                </button>
                                                <Link
                                                    to={`/central/tenants/${tenant.tenant_id}/edit`}
                                                    className="rounded-[var(--border-radius-md)] border px-2 py-1 text-[11px]"
                                                    style={{ borderColor: 'var(--color-border-tertiary)' }}
                                                >
                                                    Edit
                                                </Link>
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        statusMutation.mutate({
                                                            tenantId: tenant.tenant_id,
                                                            isActive: !tenant.is_active,
                                                        })
                                                    }
                                                    disabled={statusMutation.isPending}
                                                    className="central-button px-2 py-1 text-[11px] font-medium disabled:opacity-50"
                                                >
                                                    {tenant.is_active ? 'Suspend' : 'Restore'}
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
                <p className="mt-3 text-[11px]" style={{ color: 'var(--color-text-tertiary)' }}>
                    Showing {rows.length} of {total} tenants
                </p>
            </section>
        </div>
    );
}

