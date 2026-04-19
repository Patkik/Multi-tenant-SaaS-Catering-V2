import { useMemo, useState } from 'react';
import { Navigate, useNavigate } from 'react-router-dom';
import { useMutation, useQuery } from '@tanstack/react-query';
import { fetchTenantRegistrationPolicy, loginTenantUser, registerTenantAuthUser } from '../../../api/tenantApi';
import { useTenantContext } from '../../../providers/TenantProvider';

const rolePreference = ['Admin', 'Manager', 'Staff', 'Cashier'];

const initialRegisterForm = {
    firstname: '',
    lastname: '',
    mi: '',
    businessName: '',
    email: '',
    password: '',
    password_confirmation: '',
    role: 'Staff',
};

export function TenantLoginPage() {
    const navigate = useNavigate();
    const { isAuthenticated, refreshAuth, updateAuthToken, clientAccess, tenantProfile } = useTenantContext();
    const [mode, setMode] = useState('login');
    const [identity, setIdentity] = useState('admin');
    const [password, setPassword] = useState('password123');
    const [registerForm, setRegisterForm] = useState(initialRegisterForm);

    const registrationPolicyQuery = useQuery({
        queryKey: ['tenant-registration-policy'],
        queryFn: fetchTenantRegistrationPolicy,
        staleTime: 1000 * 30,
    });

    const registrationPolicy = registrationPolicyQuery.data;
    const tenantIsSuspended = tenantProfile?.is_active === false || String(tenantProfile?.status ?? '').toLowerCase() === 'suspended';

    const availableRoles = useMemo(() => {
        const rolesFromPolicy = registrationPolicy?.available_roles ?? [];

        if (rolesFromPolicy.length === 0) {
            return ['Staff', 'Cashier'];
        }

        return rolePreference.filter((role) => rolesFromPolicy.includes(role));
    }, [registrationPolicy?.available_roles]);

    const firstAdminExists = Boolean(registrationPolicy?.first_admin_exists);

    const loginMutation = useMutation({
        mutationFn: loginTenantUser,
        onSuccess: async (payload) => {
            updateAuthToken(payload.token);
            await refreshAuth();
            navigate('/', { replace: true });
        },
    });

    const registerMutation = useMutation({
        mutationFn: registerTenantAuthUser,
        onSuccess: async (payload) => {
            if (payload?.token) {
                updateAuthToken(payload.token);
                await refreshAuth();
                navigate('/', { replace: true });
                return;
            }

            setMode('login');
            setIdentity(registerForm.email || `${registerForm.firstname}.${registerForm.lastname}`.toLowerCase());
            setPassword('');
            setRegisterForm(initialRegisterForm);
        },
    });

    if (isAuthenticated) {
        return <Navigate to="/" replace />;
    }

    function handleLoginSubmit(event) {
        event.preventDefault();

        loginMutation.mutate({
            identity,
            password,
        });
    }

    function updateRegisterField(key, value) {
        setRegisterForm((previous) => ({
            ...previous,
            [key]: value,
        }));
    }

    function handleRegisterSubmit(event) {
        event.preventDefault();

        const selectedRole = availableRoles.includes(registerForm.role) ? registerForm.role : availableRoles[0] ?? 'Staff';

        registerMutation.mutate({
            firstname: registerForm.firstname,
            lastname: registerForm.lastname,
            mi: registerForm.mi || undefined,
            email: registerForm.email || undefined,
            password: registerForm.password,
            password_confirmation: registerForm.password_confirmation,
            role: selectedRole,
        });
    }

    const registerMessage = firstAdminExists
        ? 'Register non-admin accounts from this page. Only the first admin can assign the Admin role.'
        : 'Register the first tenant admin account. After setup, only the first admin can assign Admin to other users.';

    return (
        <div className="mx-auto flex min-h-screen w-full max-w-5xl items-center px-4 py-8">
            <div className="app-shell-panel w-full rounded-[2rem] p-7 md:p-10">
                <p className="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Tenant Access</p>
                <h1 className="hero-heading mt-2 text-3xl text-slate-900 md:text-5xl">
                    {mode === 'login' ? 'Sign in to your catering command center.' : 'Register a tenant account.'}
                </h1>
                <p className="mt-2 max-w-2xl text-sm text-slate-600">
                    {mode === 'login'
                        ? 'Role isolation is enforced from your assigned account role. Contact your tenant admin if you need additional access.'
                        : registerMessage}
                </p>

                {tenantIsSuspended ? (
                    <p className="mt-3 rounded-xl border border-rose-300 bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-900">
                        Warning: This tenant account is currently suspended. Access is restricted until the central admin restores this tenant.
                    </p>
                ) : null}

                {clientAccess ? (
                    <p className="mt-2 rounded-xl border border-emerald-300 bg-emerald-50 px-3 py-2 text-xs text-emerald-900">
                        Client Portal Login is enabled for {tenantProfile?.company_name ?? 'this tenant'}.
                    </p>
                ) : null}

                <div className="mt-6 inline-flex overflow-hidden rounded-full border border-slate-300 bg-white p-1">
                    <button
                        type="button"
                        onClick={() => setMode('login')}
                        className={`rounded-full px-3 py-1.5 text-xs font-semibold transition ${
                            mode === 'login' ? 'bg-[var(--primary-color)] text-white' : 'text-slate-700'
                        }`}
                    >
                        Sign In
                    </button>
                    <button
                        type="button"
                        onClick={() => setMode('register')}
                        className={`rounded-full px-3 py-1.5 text-xs font-semibold transition ${
                            mode === 'register' ? 'bg-[var(--primary-color)] text-white' : 'text-slate-700'
                        }`}
                    >
                        Register
                    </button>
                </div>

                {mode === 'login' ? (
                    <form onSubmit={handleLoginSubmit} className="mt-7 grid gap-4 md:max-w-xl">
                        <label className="space-y-1 text-sm font-semibold text-slate-700">
                            Username or Email
                            <input
                                className="w-full rounded-xl border border-slate-300 bg-white px-3 py-2"
                                value={identity}
                                onChange={(event) => setIdentity(event.target.value)}
                                autoComplete="username"
                            />
                        </label>

                        <label className="space-y-1 text-sm font-semibold text-slate-700">
                            Password
                            <input
                                type="password"
                                className="w-full rounded-xl border border-slate-300 bg-white px-3 py-2"
                                value={password}
                                onChange={(event) => setPassword(event.target.value)}
                                autoComplete="current-password"
                            />
                        </label>

                        {loginMutation.isError ? (
                            <p className="rounded-xl border border-rose-300 bg-rose-50 px-3 py-2 text-sm text-rose-900">
                                {loginMutation.error?.response?.data?.message ?? 'Unable to sign in. Check your credentials.'}
                            </p>
                        ) : null}

                        <button
                            type="submit"
                            disabled={loginMutation.isPending}
                            className="mt-2 rounded-full bg-[var(--primary-color)] px-5 py-2 text-sm font-semibold text-white disabled:opacity-50"
                        >
                            {loginMutation.isPending ? 'Signing in...' : 'Sign In'}
                        </button>
                    </form>
                ) : (
                    <form onSubmit={handleRegisterSubmit} className="mt-7 grid gap-3">
                        <div className="grid gap-3 md:grid-cols-2">
                            <label className="text-sm font-semibold text-slate-700">
                                First name
                                <input
                                    className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2"
                                    value={registerForm.firstname}
                                    onChange={(event) => updateRegisterField('firstname', event.target.value)}
                                    required
                                />
                            </label>
                            <label className="text-sm font-semibold text-slate-700">
                                Last name
                                <input
                                    className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2"
                                    value={registerForm.lastname}
                                    onChange={(event) => updateRegisterField('lastname', event.target.value)}
                                    required
                                />
                            </label>
                            <label className="text-sm font-semibold text-slate-700">
                                Middle initial
                                <input
                                    className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2"
                                    value={registerForm.mi}
                                    maxLength={10}
                                    onChange={(event) => updateRegisterField('mi', event.target.value)}
                                />
                            </label>
                            <label className="text-sm font-semibold text-slate-700">
                                Business name
                                <input
                                    className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2"
                                    value={registerForm.businessName}
                                    onChange={(event) => updateRegisterField('businessName', event.target.value)}
                                    placeholder="Optional"
                                />
                            </label>
                            <label className="text-sm font-semibold text-slate-700 md:col-span-2">
                                Email
                                <input
                                    type="email"
                                    className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2"
                                    value={registerForm.email}
                                    onChange={(event) => updateRegisterField('email', event.target.value)}
                                />
                            </label>
                            <label className="text-sm font-semibold text-slate-700">
                                Password
                                <input
                                    type="password"
                                    className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2"
                                    value={registerForm.password}
                                    onChange={(event) => updateRegisterField('password', event.target.value)}
                                    minLength={8}
                                    required
                                />
                            </label>
                            <label className="text-sm font-semibold text-slate-700">
                                Verify password
                                <input
                                    type="password"
                                    className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2"
                                    value={registerForm.password_confirmation}
                                    onChange={(event) => updateRegisterField('password_confirmation', event.target.value)}
                                    minLength={8}
                                    required
                                />
                            </label>
                            <label className="text-sm font-semibold text-slate-700 md:col-span-2">
                                Role
                                <select
                                    className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2"
                                    value={registerForm.role}
                                    onChange={(event) => updateRegisterField('role', event.target.value)}
                                >
                                    {availableRoles.map((role) => (
                                        <option key={role} value={role}>
                                            {role}
                                        </option>
                                    ))}
                                </select>
                            </label>
                        </div>

                        {firstAdminExists ? (
                            <p className="rounded-xl border border-amber-300 bg-amber-50 px-3 py-2 text-xs text-amber-900">
                                Admin role is intentionally hidden here. Only the first admin can create or promote Admin users.
                            </p>
                        ) : null}

                        {registerMutation.isError ? (
                            <p className="rounded-xl border border-rose-300 bg-rose-50 px-3 py-2 text-sm text-rose-900">
                                {registerMutation.error?.response?.data?.message ?? 'Unable to register account.'}
                            </p>
                        ) : null}

                        <button
                            type="submit"
                            disabled={registerMutation.isPending || registrationPolicyQuery.isPending}
                            className="mt-1 rounded-full bg-[var(--primary-color)] px-5 py-2 text-sm font-semibold text-white disabled:opacity-50"
                        >
                            {registerMutation.isPending ? 'Registering...' : 'Register'}
                        </button>
                    </form>
                )}
            </div>
        </div>
    );
}
