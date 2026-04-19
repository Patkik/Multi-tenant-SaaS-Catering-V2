import { LockedFeatureCard } from '../../components/LockedFeatureCard';

export function TenantModulePlaceholderPage({ title, description, lockedReason = '' }) {
    return (
        <div className="space-y-5">
            <section className="app-shell-panel rounded-[2rem] p-6 md:p-8">
                <p className="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Module Workspace</p>
                <h1 className="hero-heading mt-2 text-3xl text-slate-900 md:text-4xl">{title}</h1>
                <p className="mt-3 max-w-2xl text-slate-700">{description}</p>
            </section>

            {lockedReason ? <LockedFeatureCard title={title} description={lockedReason} /> : null}
        </div>
    );
}
