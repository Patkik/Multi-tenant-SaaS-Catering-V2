import { useState } from 'react';
import { Navigate, useNavigate } from 'react-router-dom';
import { useMutation } from '@tanstack/react-query';
import { loginTenantUser } from '../../../api/tenantApi';
import { useTenantContext } from '../../../providers/TenantProvider';

export function TenantLoginPage() {
    const navigate = useNavigate();
    const { isAuthenticated, refreshAuth, updateAuthToken, clientAccess, tenantProfile } = useTenantContext();
    const [identity, setIdentity] = useState('admin');
    const [password, setPassword] = useState('password123');

    const loginMutation = useMutation({
        mutationFn: loginTenantUser,
        onSuccess: async (payload) => {
            updateAuthToken(payload.token);
            await refreshAuth();
            navigate('/', { replace: true });
        },
    });

    if (isAuthenticated) {
        return <Navigate to="/" replace />;
    }

    function handleSubmit(event) {
        event.preventDefault();

        loginMutation.mutate({
            identity,
            password,
        });
    }

    return (
        <div className="mx-auto flex min-h-screen w-full max-w-5xl items-center px-4 py-8">
            <div className="app-shell-panel w-full rounded-[2rem] p-7 md:p-10">
                <p className="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Tenant Access</p>
                <h1 className="hero-heading mt-2 text-3xl text-slate-900 md:text-5xl">Sign in to your catering command center.</h1>
                <p className="mt-2 max-w-2xl text-sm text-slate-600">
                    Role isolation is enforced from your assigned account role. Contact your tenant admin if you need additional access.
                </p>

                {clientAccess ? (
                    <p className="mt-2 rounded-xl border border-emerald-300 bg-emerald-50 px-3 py-2 text-xs text-emerald-900">
                        Client Portal Login is enabled for {tenantProfile?.company_name ?? 'this tenant'}.
                    </p>
                ) : null}

                <form onSubmit={handleSubmit} className="mt-7 grid gap-4 md:max-w-xl">
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
            </div>
        </div>
    );
}
