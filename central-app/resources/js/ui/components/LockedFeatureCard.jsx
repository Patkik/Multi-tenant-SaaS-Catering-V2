export function LockedFeatureCard({ title, description }) {
    return (
        <div className="rounded-2xl border border-amber-300 bg-amber-50 p-5 text-amber-900">
            <p className="text-sm font-semibold uppercase tracking-[0.14em]">Locked Feature</p>
            <h3 className="mt-2 text-lg font-semibold">{title}</h3>
            <p className="mt-1 text-sm text-amber-800">{description}</p>
        </div>
    );
}
