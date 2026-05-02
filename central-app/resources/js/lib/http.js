import axios from 'axios';

const TENANT_TOKEN_STORAGE_KEY = 'caterpro-tenant-token';
const CENTRAL_TOKEN_STORAGE_KEY = 'caterpro-central-token';
const TENANT_ID_SESSION_KEY = 'caterpro-tenant-id';

// ─── Tenant-ID resolution ─────────────────────────────────────────────────────
// Priority: URL ?tenant= param → sessionStorage → subdomain (extracted from host)

function extractSubdomainFromHost() {
    const host = window.location.hostname; // e.g. "jollibee.localhost"
    const parts = host.split('.');
    // Valid tenant subdomain: at least 2 parts and the first part is not "www"
    if (parts.length >= 2 && parts[0] !== 'www' && parts[0] !== 'localhost') {
        return parts[0];
    }
    return null;
}

function readTenantIdFromUrl() {
    const params = new URLSearchParams(window.location.search);
    return params.get('tenant') ?? null;
}

/**
 * Returns the active tenant ID (subdomain slug), or null if we are on the
 * central domain without a ?tenant= param.
 */
export function getActiveTenantId() {
    const fromUrl = readTenantIdFromUrl();
    if (fromUrl) {
        // Persist for the session so page navigations don't lose the tenant.
        window.sessionStorage.setItem(TENANT_ID_SESSION_KEY, fromUrl);
        return fromUrl;
    }

    const fromSession = window.sessionStorage.getItem(TENANT_ID_SESSION_KEY);
    if (fromSession) {
        return fromSession;
    }

    return extractSubdomainFromHost();
}

/**
 * Returns true when the browser is already on the correct tenant subdomain
 * (i.e. "jollibee.localhost"), so we can use the normal /api/tenant/* routes.
 * When false, we must use /api/tenant-by-id/* with the X-Tenant-ID header.
 */
export function isOnTenantSubdomain() {
    return extractSubdomainFromHost() !== null;
}

/**
 * Returns the correct API prefix depending on the routing mode.
 * Use this in every tenantApi.js call instead of the hardcoded '/api/tenant'.
 */
export function tenantApiPrefix() {
    return isOnTenantSubdomain() ? '/api/tenant' : '/api/tenant-by-id';
}

/** Clear the persisted tenant ID (e.g. on logout). */
export function clearActiveTenantId() {
    window.sessionStorage.removeItem(TENANT_ID_SESSION_KEY);
}

// ─── Token helpers ────────────────────────────────────────────────────────────

export function getTenantToken() {
    return window.localStorage.getItem(TENANT_TOKEN_STORAGE_KEY);
}

export function setTenantToken(token) {
    if (token) {
        window.localStorage.setItem(TENANT_TOKEN_STORAGE_KEY, token);
    }
}

export function clearTenantToken() {
    window.localStorage.removeItem(TENANT_TOKEN_STORAGE_KEY);
}

export function getCentralToken() {
    return window.localStorage.getItem(CENTRAL_TOKEN_STORAGE_KEY);
}

export function setCentralToken(token) {
    if (token) {
        window.localStorage.setItem(CENTRAL_TOKEN_STORAGE_KEY, token);
    }
}

export function clearCentralToken() {
    window.localStorage.removeItem(CENTRAL_TOKEN_STORAGE_KEY);
}

// ─── Axios instance ───────────────────────────────────────────────────────────

export const http = axios.create({
    headers: {
        'X-Requested-With': 'XMLHttpRequest',
        Accept: 'application/json',
    },
});

http.interceptors.request.use((config) => {
    // Auth token
    const token = getTenantToken() ?? getCentralToken();
    if (token) {
        config.headers = {
            ...config.headers,
            Authorization: `Bearer ${token}`,
        };
    }

    // Tenant identification header — injected when not on a native subdomain
    if (!isOnTenantSubdomain()) {
        const tenantId = getActiveTenantId();
        if (tenantId) {
            config.headers = {
                ...config.headers,
                'X-Tenant': tenantId,
            };
        }
    }

    return config;
});

http.interceptors.response.use(
    (response) => response,
    (error) => Promise.reject(error),
);
