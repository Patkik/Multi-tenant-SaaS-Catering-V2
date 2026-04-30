import { http, clearTenantToken, setTenantToken, tenantApiPrefix } from '../lib/http';

// pfx() returns '/api/tenant' on a proper subdomain, '/api/tenant-by-id' otherwise.
const pfx = () => tenantApiPrefix();

export async function fetchTenantCapabilities() {
    const response = await http.get(`${pfx()}/capabilities`);

    return response.data.data;
}

export async function loginTenantUser(payload) {
    const response = await http.post(`${pfx()}/auth/login`, payload);

    if (response.data?.data?.token) {
        setTenantToken(response.data.data.token);
    }

    return response.data.data;
}

export async function fetchTenantRegistrationPolicy() {
    const response = await http.get(`${pfx()}/auth/registration-policy`);

    return response.data.data;
}

export async function registerTenantAuthUser(payload) {
    const response = await http.post(`${pfx()}/auth/register`, payload);

    if (response.data?.data?.token) {
        setTenantToken(response.data.data.token);
    }

    return response.data.data;
}

export async function fetchTenantMe() {
    const response = await http.get(`${pfx()}/auth/me`);

    return response.data.data;
}

export async function updateTenantProfile(payload) {
    const requestConfig = payload instanceof FormData
        ? {
              headers: {
                  'Content-Type': 'multipart/form-data',
              },
          }
        : undefined;
    const response = await http.patch(`${pfx()}/auth/profile`, payload, requestConfig);

    return response.data.data;
}

export async function submitTenantSupportRequest(payload) {
    const response = await http.post(`${pfx()}/support`, payload);

    return response.data.data;
}

export async function logoutTenantUser() {
    try {
        await http.post(`${pfx()}/auth/logout`);
    } finally {
        clearTenantToken();
    }
}

export async function fetchTenantEvents({ status = '', month = '' } = {}) {
    const response = await http.get(`${pfx()}/events`, {
        params: {
            ...(status ? { status } : {}),
            ...(month ? { month } : {}),
        },
    });

    return response.data.data;
}

export async function createTenantEvent(payload) {
    const response = await http.post(`${pfx()}/events`, payload);

    return response.data.data;
}

export async function updateTenantEventStatus(id, status) {
    const response = await http.patch(`${pfx()}/events/${id}/status`, { status });

    return response.data.data;
}

export async function fetchTenantClients(params = {}) {
    const response = await http.get(`${pfx()}/clients`, { params });

    return response.data;
}

export async function createTenantClient(payload) {
    const response = await http.post(`${pfx()}/clients`, payload);

    return response.data.data;
}

export async function updateTenantClient(id, payload) {
    const response = await http.patch(`${pfx()}/clients/${id}`, payload);

    return response.data.data;
}

export async function deleteTenantClient(id) {
    const response = await http.delete(`${pfx()}/clients/${id}`);

    return response.data.data;
}

export async function fetchTenantPackages(params = {}) {
    const response = await http.get(`${pfx()}/packages`, { params });

    return response.data;
}

export async function createTenantPackage(payload) {
    const response = await http.post(`${pfx()}/packages`, payload);

    return response.data.data;
}

export async function updateTenantPackage(id, payload) {
    const response = await http.patch(`${pfx()}/packages/${id}`, payload);

    return response.data.data;
}

export async function deleteTenantPackage(id) {
    const response = await http.delete(`${pfx()}/packages/${id}`);

    return response.data.data;
}

export async function fetchTenantPayments(params = {}) {
    const response = await http.get(`${pfx()}/payments`, { params });

    return response.data;
}

export async function createTenantPayment(payload) {
    const response = await http.post(`${pfx()}/payments`, payload);

    return response.data.data;
}

export async function updateTenantPayment(id, payload) {
    const response = await http.patch(`${pfx()}/payments/${id}`, payload);

    return response.data.data;
}

export async function deleteTenantPayment(id) {
    const response = await http.delete(`${pfx()}/payments/${id}`);

    return response.data.data;
}

export async function fetchTenantStaff(params = {}) {
    const response = await http.get(`${pfx()}/staff`, { params });

    return response.data;
}

export async function createTenantStaff(payload) {
    const response = await http.post(`${pfx()}/staff`, payload);

    return response.data.data;
}

export async function updateTenantStaff(id, payload) {
    const response = await http.patch(`${pfx()}/staff/${id}`, payload);

    return response.data.data;
}

export async function deleteTenantStaff(id) {
    const response = await http.delete(`${pfx()}/staff/${id}`);

    return response.data.data;
}

export async function fetchTenantAssignments(params = {}) {
    const response = await http.get(`${pfx()}/assignments`, { params });

    return response.data;
}

export async function createTenantAssignment(payload) {
    const response = await http.post(`${pfx()}/assignments`, payload);

    return response.data.data;
}

export async function deleteTenantAssignment(id) {
    const response = await http.delete(`${pfx()}/assignments/${id}`);

    return response.data.data;
}

export async function fetchTenantAnalytics() {
    const response = await http.get(`${pfx()}/analytics`);

    return response.data.data;
}

export async function fetchTenantAppUpdates() {
    const response = await http.get(`${pfx()}/app-updates`);

    return response.data.data;
}

export async function applyTenantAppUpdate() {
    const response = await http.post(`${pfx()}/app-updates/apply`);

    return response.data.data;
}

export async function syncTenantAppVersion() {
    const response = await http.post(`${pfx()}/app-updates/sync-version`);

    return response.data.data;
}

export async function fetchTenantBranding() {
    const response = await http.get(`${pfx()}/branding`);

    return response.data.data;
}

export async function updateTenantBranding(payload) {
    const requestConfig = payload instanceof FormData
        ? {
              headers: {
                  'Content-Type': 'multipart/form-data',
              },
          }
        : undefined;
    const response = await http.patch(`${pfx()}/branding`, payload, requestConfig);

    return response.data.data;
}

export async function fetchTenantSettings() {
    const response = await http.get(`${pfx()}/settings`);

    return response.data.data;
}

export async function updateTenantSettings(payload) {
    const response = await http.patch(`${pfx()}/settings`, payload);

    return response.data.data;
}

export async function fetchTenantUsers(params = {}) {
    const response = await http.get(`${pfx()}/users`, { params });

    return response.data;
}

export async function createTenantUser(payload) {
    const response = await http.post(`${pfx()}/users`, payload);

    return response.data.data;
}

export async function updateTenantUser(id, payload) {
    const response = await http.patch(`${pfx()}/users/${id}`, payload);

    return response.data.data;
}

export async function deleteTenantUser(id) {
    const response = await http.delete(`${pfx()}/users/${id}`);

    return response.data.data;
}
