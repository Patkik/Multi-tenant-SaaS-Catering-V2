import { lazy, Suspense } from 'react';
import { Navigate, Route, Routes } from 'react-router-dom';
import { useTenantContext } from '../providers/TenantProvider';
import { CentralWorkspaceLayout } from './layouts/CentralWorkspaceLayout';
import { TenantWorkspaceLayout } from './layouts/TenantWorkspaceLayout';

const lazyNamed = (factory, exportName) => lazy(() => factory().then((module) => ({ default: module[exportName] })));

const CentralAuditLogsPage = lazyNamed(() => import('./pages/central/CentralAuditLogsPage'), 'CentralAuditLogsPage');
const CentralDashboardPage = lazyNamed(() => import('./pages/central/CentralDashboardPage'), 'CentralDashboardPage');
const CentralLoginPage = lazyNamed(() => import('./pages/central/CentralLoginPage'), 'CentralLoginPage');
const CentralPlanEditPage = lazyNamed(() => import('./pages/central/CentralPlanEditPage'), 'CentralPlanEditPage');
const CentralPlansPricingPage = lazyNamed(() => import('./pages/central/CentralPlansPricingPage'), 'CentralPlansPricingPage');
const CentralRegistrationWizardPage = lazyNamed(() => import('./pages/central/CentralRegistrationWizardPage'), 'CentralRegistrationWizardPage');
const CentralRevenueAnalyticsPage = lazyNamed(() => import('./pages/central/CentralRevenueAnalyticsPage'), 'CentralRevenueAnalyticsPage');
const CentralSystemHealthPage = lazyNamed(() => import('./pages/central/CentralSystemHealthPage'), 'CentralSystemHealthPage');
const CentralTenantEditPage = lazyNamed(() => import('./pages/central/CentralTenantEditPage'), 'CentralTenantEditPage');
const CentralTenantManagementPage = lazyNamed(() => import('./pages/central/CentralTenantManagementPage'), 'CentralTenantManagementPage');
const CentralUserManagementPage = lazyNamed(() => import('./pages/central/CentralUserManagementPage'), 'CentralUserManagementPage');
const TenantBookingWizardPage = lazyNamed(() => import('./pages/tenant/TenantBookingWizardPage'), 'TenantBookingWizardPage');
const TenantAppearancePage = lazyNamed(() => import('./pages/tenant/TenantAppearancePage'), 'TenantAppearancePage');
const TenantBookingsPage = lazyNamed(() => import('./pages/tenant/TenantBookingsPage'), 'TenantBookingsPage');
const TenantCalendarPage = lazyNamed(() => import('./pages/tenant/TenantCalendarPage'), 'TenantCalendarPage');
const TenantClientsPage = lazyNamed(() => import('./pages/tenant/TenantClientsPage'), 'TenantClientsPage');
const TenantDashboardPage = lazyNamed(() => import('./pages/tenant/TenantDashboardPage'), 'TenantDashboardPage');
const TenantLoginPage = lazyNamed(() => import('./pages/tenant/TenantLoginPage'), 'TenantLoginPage');
const TenantMenuBuilderPage = lazyNamed(() => import('./pages/tenant/TenantMenuBuilderPage'), 'TenantMenuBuilderPage');
const TenantPaymentsPage = lazyNamed(() => import('./pages/tenant/TenantPaymentsPage'), 'TenantPaymentsPage');
const TenantProfilePage = lazyNamed(() => import('./pages/tenant/TenantProfilePage'), 'TenantProfilePage');
const TenantSettingsPage = lazyNamed(() => import('./pages/tenant/TenantSettingsPage'), 'TenantSettingsPage');
const TenantStaffAssignmentsPage = lazyNamed(() => import('./pages/tenant/TenantStaffAssignmentsPage'), 'TenantStaffAssignmentsPage');
const TenantAnalyticsPage = lazyNamed(() => import('./pages/tenant/TenantAnalyticsPage'), 'TenantAnalyticsPage');
const TenantUsersPage = lazyNamed(() => import('./pages/tenant/TenantUsersPage'), 'TenantUsersPage');
const TenantSupportPage = lazyNamed(() => import('./pages/tenant/TenantSupportPage'), 'TenantSupportPage');

function LoadingScreen() {
    return (
        <div className="flex min-h-screen items-center justify-center px-4">
            <div className="app-shell-panel rounded-3xl px-6 py-5 text-sm font-semibold text-slate-700">
                Detecting tenant context and loading CaterPro workspace...
            </div>
        </div>
    );
}

function ErrorScreen({ message }) {
    return (
        <div className="flex min-h-screen items-center justify-center px-4">
            <div className="rounded-3xl border border-rose-300 bg-rose-50 px-6 py-5 text-sm text-rose-900">
                {message}
            </div>
        </div>
    );
}

function CentralRoutes() {
    return (
        <Suspense fallback={<LoadingScreen />}>
            <Routes>
                <Route path="/central/login" element={<CentralLoginPage />} />
                <Route
                    element={
                        <RequireCentralAuth>
                            <CentralWorkspaceLayout />
                        </RequireCentralAuth>
                    }
                >
                    <Route index element={<Navigate to="/central/dashboard" replace />} />
                    <Route path="/central/dashboard" element={<CentralDashboardPage />} />
                    <Route path="/central/tenants" element={<CentralTenantManagementPage />} />
                    <Route path="/central/tenants/:tenantId/edit" element={<CentralTenantEditPage />} />
                    <Route path="/central/new-tenant" element={<CentralRegistrationWizardPage />} />
                    <Route path="/central/plans-pricing" element={<CentralPlansPricingPage />} />
                    <Route path="/central/plans-pricing/:plan/edit" element={<CentralPlanEditPage />} />
                    <Route path="/central/user-management" element={<CentralUserManagementPage />} />
                    <Route path="/central/revenue-analytics" element={<CentralRevenueAnalyticsPage />} />
                    <Route path="/central/system-health" element={<CentralSystemHealthPage />} />
                    <Route path="/central/audit-logs" element={<CentralAuditLogsPage />} />
                    <Route path="/central/support" element={<TenantSupportPage />} />
                </Route>
                <Route path="*" element={<Navigate to="/central/dashboard" replace />} />
            </Routes>
        </Suspense>
    );
}

function RequireCentralAuth({ children }) {
    const { isCentralAuthenticated, isCentralAuthLoading } = useTenantContext();

    if (isCentralAuthLoading) {
        return <LoadingScreen />;
    }

    if (!isCentralAuthenticated) {
        return <Navigate to="/central/login" replace />;
    }

    return children;
}

function TenantRoutes() {
    return (
        <Suspense fallback={<LoadingScreen />}>
            <Routes>
                <Route path="/login" element={<TenantLoginPage />} />
                <Route
                    element={
                        <RequireTenantAuth>
                            <TenantWorkspaceLayout />
                        </RequireTenantAuth>
                    }
                >
                    <Route
                        index
                        element={
                            <RequireTenantModule module="dashboard">
                                <TenantDashboardPage />
                            </RequireTenantModule>
                        }
                    />
                    <Route
                        path="/clients"
                        element={
                            <RequireTenantModule module="clients">
                                <TenantClientsPage />
                            </RequireTenantModule>
                        }
                    />
                    <Route
                        path="/bookings"
                        element={
                            <RequireTenantModule module="events" feature="event_management">
                                <TenantBookingsPage />
                            </RequireTenantModule>
                        }
                    />
                    <Route
                        path="/calendar"
                        element={
                            <RequireTenantModule module="events" feature="event_management">
                                <TenantCalendarPage />
                            </RequireTenantModule>
                        }
                    />
                    <Route path="/events" element={<Navigate to="/bookings" replace />} />
                    <Route
                        path="/menu-builder"
                        element={
                            <RequireTenantModule module="packages">
                                <TenantMenuBuilderPage />
                            </RequireTenantModule>
                        }
                    />
                    <Route path="/packages" element={<Navigate to="/menu-builder" replace />} />
                    <Route
                        path="/booking/new"
                        element={
                            <RequireTenantModule module="events" feature="event_management">
                                <TenantBookingWizardPage />
                            </RequireTenantModule>
                        }
                    />
                    <Route
                        path="/staff-scheduler"
                        element={
                            <RequireTenantModule module="assignments" feature="staff_assignment">
                                <TenantStaffAssignmentsPage />
                            </RequireTenantModule>
                        }
                    />
                    <Route path="/staff" element={<Navigate to="/staff-scheduler" replace />} />
                    <Route
                        path="/analytics"
                        element={
                            <RequireTenantModule module="analytics" feature="advanced_analytics">
                                <TenantAnalyticsPage />
                            </RequireTenantModule>
                        }
                    />
                    <Route
                        path="/appearance"
                        element={
                            <RequireTenantModule module="branding" feature="branding_controls">
                                <TenantAppearancePage />
                            </RequireTenantModule>
                        }
                    />
                    <Route path="/branding" element={<Navigate to="/appearance" replace />} />
                    <Route
                        path="/invoices"
                        element={
                            <RequireTenantModule module="payments">
                                <TenantPaymentsPage />
                            </RequireTenantModule>
                        }
                    />
                    <Route path="/payments" element={<Navigate to="/invoices" replace />} />
                    <Route path="/profile" element={<TenantProfilePage />} />
                    <Route
                        path="/settings"
                        element={
                            <RequireTenantModule module="users">
                                <TenantSettingsPage />
                            </RequireTenantModule>
                        }
                    />
                    <Route
                        path="/users"
                        element={
                            <RequireTenantModule module="users">
                                <TenantUsersPage />
                            </RequireTenantModule>
                        }
                    />
                    <Route path="/support" element={<TenantSupportPage />} />
                </Route>
                <Route path="*" element={<Navigate to="/" replace />} />
            </Routes>
        </Suspense>
    );
}

function RequireTenantAuth({ children }) {
    const { isAuthenticated, isAuthLoading, tenantProfile } = useTenantContext();
    const tenantIsSuspended =
        tenantProfile?.is_active === false || String(tenantProfile?.status ?? '').toLowerCase() === 'suspended';

    if (isAuthLoading) {
        return <LoadingScreen />;
    }

    if (tenantIsSuspended) {
        return <Navigate to="/login" replace />;
    }

    if (!isAuthenticated) {
        return <Navigate to="/login" replace />;
    }

    return children;
}

function RequireTenantModule({ module, feature, children }) {
    const { tenantProfile, authUser } = useTenantContext();

    const allowedModules = authUser?.modules ?? [];
    const enabledFeatures = tenantProfile?.enabled_features ?? [];

    if (!allowedModules.includes(module)) {
        return <ErrorScreen message="You are not authorized to access this module." />;
    }

    if (feature && !enabledFeatures.includes(feature)) {
        return <ErrorScreen message="This module is not available on your current subscription tier." />;
    }

    return children;
}

export default function App() {
    const { mode, isLoading, isError, error } = useTenantContext();

    if (isLoading) {
        return <LoadingScreen />;
    }

    if (isError) {
        return <ErrorScreen message={`Unable to load workspace context: ${error?.message ?? 'Unknown error'}`} />;
    }

    return mode === 'tenant' ? <TenantRoutes /> : <CentralRoutes />;
}
