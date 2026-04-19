import axios from 'axios';

const TENANT_TOKEN_STORAGE_KEY = 'caterpro-tenant-token';
const CENTRAL_TOKEN_STORAGE_KEY = 'caterpro-central-token';

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

export const http = axios.create({
    headers: {
        'X-Requested-With': 'XMLHttpRequest',
        Accept: 'application/json',
    },
});

http.interceptors.request.use((config) => {
    const token = getTenantToken() ?? getCentralToken();

    if (token) {
        config.headers = {
            ...config.headers,
            Authorization: `Bearer ${token}`,
        };
    }

    return config;
});

http.interceptors.response.use(
    (response) => response,
    (error) => Promise.reject(error),
);
