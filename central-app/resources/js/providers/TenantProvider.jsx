import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { fetchCentralMe, logoutCentralUser } from '../api/centralApi';
import { fetchTenantCapabilities, fetchTenantMe, logoutTenantUser } from '../api/tenantApi';
import { isTenantContextFallbackError } from '../lib/formatters';
import { clearCentralToken, clearTenantToken, getCentralToken, getTenantToken, clearActiveTenantId } from '../lib/http';

const DEFAULT_PRIMARY_COLOR = '#0B8F66';

const TenantContext = createContext(null);

export function TenantProvider({ children }) {
    const [authToken, setAuthTokenState] = useState(() => getTenantToken());
    const [centralAuthToken, setCentralAuthTokenState] = useState(() => getCentralToken());

    const tenantQuery = useQuery({
        queryKey: ['tenant-capabilities'],
        queryFn: fetchTenantCapabilities,
        retry: false,
        staleTime: 1000 * 30,
        refetchOnWindowFocus: true,
        refetchInterval: (query) => (query.state.status === 'success' ? 1000 * 15 : false),
        refetchIntervalInBackground: true,
    });

    const centralModeFallback = tenantQuery.isError && isTenantContextFallbackError(tenantQuery.error);
    const isTenantMode = tenantQuery.isSuccess;

    const meQuery = useQuery({
        queryKey: ['tenant-auth-me', authToken],
        queryFn: fetchTenantMe,
        enabled: isTenantMode && Boolean(authToken),
        retry: false,
        staleTime: 1000 * 30,
    });

    const centralMeQuery = useQuery({
        queryKey: ['central-auth-me', centralAuthToken],
        queryFn: fetchCentralMe,
        enabled: !isTenantMode && Boolean(centralAuthToken),
        retry: false,
        staleTime: 1000 * 30,
    });

    useEffect(() => {
        if (meQuery.isError && meQuery.error?.response?.status === 401) {
            clearTenantToken();
            setAuthTokenState(null);
        }
    }, [meQuery.error, meQuery.isError]);

    useEffect(() => {
        if (centralMeQuery.isError && centralMeQuery.error?.response?.status === 401) {
            clearCentralToken();
            setCentralAuthTokenState(null);
        }
    }, [centralMeQuery.error, centralMeQuery.isError]);

    const tenantProfile = useMemo(() => {
        if (!isTenantMode) {
            return null;
        }

        return {
            ...tenantQuery.data,
            client_access: Boolean(tenantQuery.data?.client_access),
        };
    }, [isTenantMode, tenantQuery.data]);

    const authUser = meQuery.data?.user ?? null;
    const isAuthenticated = isTenantMode ? Boolean(authUser) : false;
    const centralAuthUser = centralMeQuery.data?.user ?? null;
    const isCentralAuthenticated = !isTenantMode ? Boolean(centralAuthUser) : false;
    const clientAccess = Boolean(tenantProfile?.client_access);

    const primaryColor = tenantProfile?.branding?.primary_color ?? DEFAULT_PRIMARY_COLOR;

    useEffect(() => {
        document.documentElement.style.setProperty('--primary-color', primaryColor);
    }, [primaryColor]);

    const signOut = useCallback(async () => {
        try {
            await logoutTenantUser();
        } finally {
            clearActiveTenantId();
            setAuthTokenState(null);
        }
    }, []);

    const updateAuthToken = useCallback((token) => {
        setAuthTokenState(token ?? null);
    }, []);

    const centralSignOut = useCallback(async () => {
        try {
            await logoutCentralUser();
        } finally {
            setCentralAuthTokenState(null);
        }
    }, []);

    const updateCentralAuthToken = useCallback((token) => {
        setCentralAuthTokenState(token ?? null);
    }, []);

    const value = useMemo(() => {
        return {
            mode: isTenantMode ? 'tenant' : 'central',
            tenantProfile,
            clientAccess,
            authUser,
            centralAuthUser,
            isAuthenticated,
            isCentralAuthenticated,
            isLoading: tenantQuery.isLoading,
            isError: tenantQuery.isError && !centralModeFallback,
            error: tenantQuery.isError && !centralModeFallback ? tenantQuery.error : null,
            refreshProfile: tenantQuery.refetch,
            isAuthLoading: meQuery.isLoading,
            refreshAuth: meQuery.refetch,
            isCentralAuthLoading: centralMeQuery.isLoading,
            refreshCentralAuth: centralMeQuery.refetch,
            updateAuthToken,
            updateCentralAuthToken,
            signOut,
            centralSignOut,
        };
    }, [
        authUser,
        centralAuthUser,
        centralMeQuery.isLoading,
        centralMeQuery.refetch,
        centralSignOut,
        centralModeFallback,
        isAuthenticated,
        isCentralAuthenticated,
        isTenantMode,
        meQuery.isLoading,
        meQuery.refetch,
        tenantProfile,
        clientAccess,
        tenantQuery.error,
        tenantQuery.isError,
        tenantQuery.isLoading,
        tenantQuery.refetch,
        signOut,
        updateAuthToken,
        updateCentralAuthToken,
    ]);

    return <TenantContext.Provider value={value}>{children}</TenantContext.Provider>;
}

export function useTenantContext() {
    const context = useContext(TenantContext);

    if (!context) {
        throw new Error('useTenantContext must be used inside TenantProvider.');
    }

    return context;
}
