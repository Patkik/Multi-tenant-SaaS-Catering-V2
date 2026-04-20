import { http } from '../lib/http';
import { clearTenantToken, setTenantToken } from '../lib/http';

export async function fetchTenantCapabilities() {
    const response = await http.get('/api/tenant/capabilities');

    return response.data.data;
}

export async function loginTenantUser(payload) {
    const response = await http.post('/api/tenant/auth/login', payload);

    if (response.data?.data?.token) {
        setTenantToken(response.data.data.token);
    }

    return response.data.data;
}

export async function fetchTenantRegistrationPolicy() {
    const response = await http.get('/api/tenant/auth/registration-policy');

    return response.data.data;
}

export async function registerTenantAuthUser(payload) {
    const response = await http.post('/api/tenant/auth/register', payload);

    if (response.data?.data?.token) {
        setTenantToken(response.data.data.token);
    }

    return response.data.data;
}

export async function fetchTenantMe() {
    const response = await http.get('/api/tenant/auth/me');

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
    const response = await http.patch('/api/tenant/auth/profile', payload, requestConfig);

    return response.data.data;
}

export async function logoutTenantUser() {
    try {
        await http.post('/api/tenant/auth/logout');
    } finally {
        clearTenantToken();
    }
}

export async function fetchTenantEvents({ status = '', month = '' } = {}) {
    const response = await http.get('/api/tenant/events', {
        params: {
            ...(status ? { status } : {}),
            ...(month ? { month } : {}),
        },
    });

    return response.data.data;
}

export async function createTenantEvent(payload) {
    const response = await http.post('/api/tenant/events', payload);

    return response.data.data;
}

export async function updateTenantEventStatus(id, status) {
    const response = await http.patch(`/api/tenant/events/${id}/status`, { status });

    return response.data.data;
}

export async function fetchTenantClients(params = {}) {
    const response = await http.get('/api/tenant/clients', { params });

    return response.data;
}

export async function createTenantClient(payload) {
    const response = await http.post('/api/tenant/clients', payload);

    return response.data.data;
}

export async function updateTenantClient(id, payload) {
    const response = await http.patch(`/api/tenant/clients/${id}`, payload);

    return response.data.data;
}

export async function deleteTenantClient(id) {
    const response = await http.delete(`/api/tenant/clients/${id}`);

    return response.data.data;
}

export async function fetchTenantPackages(params = {}) {
    const response = await http.get('/api/tenant/packages', { params });

    return response.data;
}

export async function createTenantPackage(payload) {
    const response = await http.post('/api/tenant/packages', payload);

    return response.data.data;
}

export async function updateTenantPackage(id, payload) {
    const response = await http.patch(`/api/tenant/packages/${id}`, payload);

    return response.data.data;
}

export async function deleteTenantPackage(id) {
    const response = await http.delete(`/api/tenant/packages/${id}`);

    return response.data.data;
}

export async function fetchTenantPayments(params = {}) {
    const response = await http.get('/api/tenant/payments', { params });

    return response.data;
}

export async function createTenantPayment(payload) {
    const response = await http.post('/api/tenant/payments', payload);

    return response.data.data;
}

export async function updateTenantPayment(id, payload) {
    const response = await http.patch(`/api/tenant/payments/${id}`, payload);

    return response.data.data;
}

export async function deleteTenantPayment(id) {
    const response = await http.delete(`/api/tenant/payments/${id}`);

    return response.data.data;
}

export async function fetchTenantStaff(params = {}) {
    const response = await http.get('/api/tenant/staff', { params });

    return response.data;
}

export async function createTenantStaff(payload) {
    const response = await http.post('/api/tenant/staff', payload);

    return response.data.data;
}

export async function updateTenantStaff(id, payload) {
    const response = await http.patch(`/api/tenant/staff/${id}`, payload);

    return response.data.data;
}

export async function deleteTenantStaff(id) {
    const response = await http.delete(`/api/tenant/staff/${id}`);

    return response.data.data;
}

export async function fetchTenantAssignments(params = {}) {
    const response = await http.get('/api/tenant/assignments', { params });

    return response.data;
}

export async function createTenantAssignment(payload) {
    const response = await http.post('/api/tenant/assignments', payload);

    return response.data.data;
}

export async function deleteTenantAssignment(id) {
    const response = await http.delete(`/api/tenant/assignments/${id}`);

    return response.data.data;
}

export async function fetchTenantAnalytics() {
    const response = await http.get('/api/tenant/analytics');

    return response.data.data;
}

export async function fetchTenantAppUpdates() {
    const response = await http.get('/api/tenant/app-updates');

    return response.data.data;
}

export async function applyTenantAppUpdate() {
    const response = await http.post('/api/tenant/app-updates/apply');

    return response.data.data;
}

export async function fetchTenantBranding() {
    const response = await http.get('/api/tenant/branding');

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
    const response = await http.patch('/api/tenant/branding', payload, requestConfig);

    return response.data.data;
}

export async function fetchTenantSettings() {
    const response = await http.get('/api/tenant/settings');

    return response.data.data;
}

export async function updateTenantSettings(payload) {
    const response = await http.patch('/api/tenant/settings', payload);

    return response.data.data;
}

export async function fetchTenantUsers(params = {}) {
    const response = await http.get('/api/tenant/users', { params });

    return response.data;
}

export async function createTenantUser(payload) {
    const response = await http.post('/api/tenant/users', payload);

    return response.data.data;
}

export async function updateTenantUser(id, payload) {
    const response = await http.patch(`/api/tenant/users/${id}`, payload);

    return response.data.data;
}

export async function deleteTenantUser(id) {
    const response = await http.delete(`/api/tenant/users/${id}`);

    return response.data.data;
}
