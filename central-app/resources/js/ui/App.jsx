import { Navigate, Route, Routes } from 'react-router-dom';
import { useTenantContext } from '../providers/TenantProvider';
import { CentralWorkspaceLayout } from './layouts/CentralWorkspaceLayout';
import { TenantWorkspaceLayout } from './layouts/TenantWorkspaceLayout';
import { CentralAuditLogsPage } from './pages/central/CentralAuditLogsPage';
import { CentralDashboardPage } from './pages/central/CentralDashboardPage';
import { CentralLoginPage } from './pages/central/CentralLoginPage';
import { CentralPlansPricingPage } from './pages/central/CentralPlansPricingPage';
import { CentralRegistrationWizardPage } from './pages/central/CentralRegistrationWizardPage';
import { CentralRevenueAnalyticsPage } from './pages/central/CentralRevenueAnalyticsPage';
import { CentralSystemHealthPage } from './pages/central/CentralSystemHealthPage';
import { CentralTenantEditPage } from './pages/central/CentralTenantEditPage';
import { CentralTenantManagementPage } from './pages/central/CentralTenantManagementPage';
import { CentralUserManagementPage } from './pages/central/CentralUserManagementPage';
import { TenantBookingWizardPage } from './pages/tenant/TenantBookingWizardPage';
import { TenantAppearancePage } from './pages/tenant/TenantAppearancePage';
import { TenantBookingsPage } from './pages/tenant/TenantBookingsPage';
import { TenantCalendarPage } from './pages/tenant/TenantCalendarPage';
import { TenantClientsPage } from './pages/tenant/TenantClientsPage';
import { TenantDashboardPage } from './pages/tenant/TenantDashboardPage';
import { TenantLoginPage } from './pages/tenant/TenantLoginPage';
import { TenantMenuBuilderPage } from './pages/tenant/TenantMenuBuilderPage';
import { TenantPaymentsPage } from './pages/tenant/TenantPaymentsPage';
import { TenantSettingsPage } from './pages/tenant/TenantSettingsPage';
import { TenantStaffAssignmentsPage } from './pages/tenant/TenantStaffAssignmentsPage';
import { TenantAnalyticsPage } from './pages/tenant/TenantAnalyticsPage';
import { TenantUsersPage } from './pages/tenant/TenantUsersPage';

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
                <Route path="/central/user-management" element={<CentralUserManagementPage />} />
                <Route path="/central/revenue-analytics" element={<CentralRevenueAnalyticsPage />} />
                <Route path="/central/system-health" element={<CentralSystemHealthPage />} />
                <Route path="/central/audit-logs" element={<CentralAuditLogsPage />} />
            </Route>
            <Route path="*" element={<Navigate to="/central/dashboard" replace />} />
        </Routes>
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
            </Route>
            <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>
    );
}

function RequireTenantAuth({ children }) {
    const { isAuthenticated, isAuthLoading } = useTenantContext();

    if (isAuthLoading) {
        return <LoadingScreen />;
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
