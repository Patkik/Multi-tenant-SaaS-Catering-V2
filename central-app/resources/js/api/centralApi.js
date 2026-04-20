import { http } from '../lib/http';
import { clearCentralToken, setCentralToken } from '../lib/http';

export async function loginCentralUser(payload) {
    const response = await http.post('/api/central/auth/login', payload);

    if (response.data?.data?.token) {
        setCentralToken(response.data.data.token);
    }

    return response.data.data;
}

export async function fetchCentralMe() {
    const response = await http.get('/api/central/auth/me');

    return response.data.data;
}

export async function logoutCentralUser() {
    try {
        await http.post('/api/central/auth/logout');
    } finally {
        clearCentralToken();
    }
}

export async function fetchCentralDashboard() {
    const response = await http.get('/api/central/dashboard');

    return response.data.data;
}

export async function fetchCentralPlans() {
    const response = await http.get('/api/central/plans');

    return response.data.data;
}

export async function fetchCentralPlansPricing() {
    const response = await http.get('/api/central/plans-pricing');

    return response.data.data;
}

export async function fetchCentralUsers(search = '') {
    const response = await http.get('/api/central/users', {
        params: {
            search: search || undefined,
        },
    });

    return response.data.data;
}

export async function updateCentralUser(userId, payload) {
    const response = await http.patch(`/api/central/users/${userId}`, payload);

    return response.data.data;
}

export async function fetchCentralRevenueAnalytics() {
    const response = await http.get('/api/central/revenue-analytics');

    return response.data.data;
}

export async function fetchCentralSystemHealth() {
    const response = await http.get('/api/central/system-health');

    return response.data.data;
}

export async function fetchCentralAppUpdates() {
    const response = await http.get('/api/central/app-updates');

    return response.data.data;
}

export async function applyCentralAppUpdate() {
    const response = await http.post('/api/central/app-updates/apply');

    return response.data.data;
}

export async function fetchCentralAuditLogs({ search = '', type = '', actor = '' } = {}) {
    const response = await http.get('/api/central/audit-logs', {
        params: {
            search: search || undefined,
            type: type || undefined,
            actor: actor || undefined,
        },
    });

    return response.data.data;
}

export async function fetchCentralTenants({ page = 1, perPage = 15, search = '', plan = '', status = '' } = {}) {
    const response = await http.get('/api/central/tenants', {
        params: {
            page,
            per_page: perPage,
            search: search || undefined,
            plan: plan || undefined,
            status: status || undefined,
        },
    });

    return response.data.data;
}

export async function fetchCentralTenantEditContext(tenantId) {
    const response = await http.get(`/api/central/tenants/${tenantId}`);

    return response.data.data;
}

export async function updateCentralTenant(tenantId, payload) {
    const response = await http.patch(`/api/central/tenants/${tenantId}`, payload);

    return response.data.data;
}

export async function fetchCentralTenantUsers(tenantId) {
    const response = await http.get(`/api/central/tenants/${tenantId}/users`);

    return response.data.data;
}

export async function updateCentralTenantUser(tenantId, userId, payload) {
    const response = await http.patch(`/api/central/tenants/${tenantId}/users/${userId}`, payload);

    return response.data.data;
}

export async function registerTenant(payload) {
    const response = await http.post('/api/tenants/register', payload);

    return response.data.data;
}

export async function updateTenantPlan(tenantId, plan) {
    const response = await http.patch(`/api/central/tenants/${tenantId}/plan`, {
        plan,
    });

    return response.data.data;
}

export async function updateTenantBranding(tenantId, payload) {
    const response = await http.patch(`/api/central/tenants/${tenantId}/branding`, payload);

    return response.data.data;
}

export async function updateTenantStatus(tenantId, isActive) {
    const response = await http.patch(`/api/central/tenants/${tenantId}/status`, {
        is_active: Boolean(isActive),
    });

    return response.data.data;
}

export async function checkCentralSubdomainAvailability(subdomain, tenantId = null) {
    const response = await http.get('/api/central/tenants/subdomain-availability', {
        params: {
            subdomain,
            tenant_id: tenantId || undefined,
        },
    });

    return response.data.data;
}
