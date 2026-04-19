export const CENTRAL_PERMISSIONS = {
    DASHBOARD_VIEW: 'central.dashboard.view',
    PLANS_VIEW: 'central.plans.view',
    TENANTS_VIEW: 'central.tenants.view',
    TENANTS_MANAGE: 'central.tenants.manage',
};

export function hasCentralPermission(user, permission) {
    const permissions = user?.permissions ?? [];

    return permissions.includes(permission);
}