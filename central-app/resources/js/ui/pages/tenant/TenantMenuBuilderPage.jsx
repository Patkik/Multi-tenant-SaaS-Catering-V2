import { useEffect, useMemo, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { fetchTenantPackages } from '../../../api/tenantApi';
import { formatCurrency, formatNumber } from '../../../lib/formatters';

const menuLibrary = [
    { id: 'beef-salpicado', name: 'Beef Salpicado', category: 'Main Course', unitCost: 95 },
    { id: 'chicken-galantina', name: 'Chicken Galantina', category: 'Main Course', unitCost: 88 },
    { id: 'seafood-paella', name: 'Seafood Paella', category: 'Signature Rice', unitCost: 110 },
    { id: 'caesar-salad', name: 'Classic Caesar Salad', category: 'Appetizer', unitCost: 45 },
    { id: 'mango-panna-cotta', name: 'Mango Panna Cotta', category: 'Dessert', unitCost: 42 },
    { id: 'brew-coffee-bar', name: 'Premium Brew Coffee Bar', category: 'Beverage', unitCost: 36 },
];

export function TenantMenuBuilderPage() {
    const [selectedPackageId, setSelectedPackageId] = useState(null);
    const [selectedItemId, setSelectedItemId] = useState(menuLibrary[0].id);
    const [servings, setServings] = useState(100);
    const [notes, setNotes] = useState('');
    const [compositions, setCompositions] = useState({});
    const [publishState, setPublishState] = useState({});

    const packagesQuery = useQuery({
        queryKey: ['tenant-packages'],
        queryFn: () => fetchTenantPackages(),
        staleTime: 1000 * 30,
    });

    const packages = useMemo(() => packagesQuery.data?.data?.data ?? [], [packagesQuery.data]);

    useEffect(() => {
        if (packages.length && !selectedPackageId) {
            setSelectedPackageId(packages[0].id);
        }
    }, [packages, selectedPackageId]);

    const selectedPackage = packages.find((pkg) => pkg.id === selectedPackageId) ?? null;
    const packageItems = selectedPackage ? compositions[selectedPackage.id] ?? [] : [];

    const ingredientCost = packageItems.reduce((carry, item) => carry + item.totalCost, 0);
    const basePrice = Number(selectedPackage?.base_price ?? 0);
    const suggestedPrice = Math.max(basePrice, Math.round(ingredientCost * 1.5));

    function addItemToMenu(event) {
        event.preventDefault();

        if (!selectedPackage) {
            return;
        }

        const template = menuLibrary.find((item) => item.id === selectedItemId);

        if (!template) {
            return;
        }

        const safeServings = Math.max(Number(servings || 0), 1);
        const entry = {
            id: `${template.id}-${Date.now()}`,
            name: template.name,
            category: template.category,
            unitCost: template.unitCost,
            servings: safeServings,
            totalCost: template.unitCost * safeServings,
            notes: notes.trim(),
        };

        setCompositions((previous) => ({
            ...previous,
            [selectedPackage.id]: [...(previous[selectedPackage.id] ?? []), entry],
        }));

        setNotes('');
    }

    function removeMenuItem(itemId) {
        if (!selectedPackage) {
            return;
        }

        setCompositions((previous) => ({
            ...previous,
            [selectedPackage.id]: (previous[selectedPackage.id] ?? []).filter((item) => item.id !== itemId),
        }));
    }

    function publishTemplate() {
        if (!selectedPackage) {
            return;
        }

        setPublishState((previous) => ({
            ...previous,
            [selectedPackage.id]: new Date().toLocaleString(),
        }));
    }

    return (
        <div className="space-y-6">
            <section className="app-shell-panel rounded-[2rem] p-6">
                <p className="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Menu Builder</p>
                <h1 className="hero-heading mt-2 text-3xl text-slate-900 md:text-4xl">Compose per-package menus with cost awareness and publishing controls.</h1>
            </section>

            <section className="grid gap-4 xl:grid-cols-[1fr_1.3fr_1fr]">
                <article className="overflow-hidden rounded-3xl border border-slate-200 bg-white">
                    <header className="border-b border-slate-100 px-4 py-3">
                        <h2 className="text-sm font-semibold text-slate-900">Package Catalog</h2>
                    </header>

                    <div className="space-y-2 p-3">
                        {packagesQuery.isPending ? (
                            <p className="text-sm text-slate-600">Loading packages...</p>
                        ) : null}

                        {packagesQuery.isError ? (
                            <p className="text-sm text-rose-700">Unable to load package catalog.</p>
                        ) : null}

                        {!packagesQuery.isPending && !packagesQuery.isError && packages.length === 0 ? (
                            <p className="text-sm text-slate-600">No packages found. Create one in package management first.</p>
                        ) : null}

                        {packages.map((pkg) => {
                            const isActive = selectedPackageId === pkg.id;

                            return (
                                <button
                                    key={pkg.id}
                                    type="button"
                                    onClick={() => setSelectedPackageId(pkg.id)}
                                    className={`w-full rounded-2xl border px-3 py-3 text-left transition ${
                                        isActive
                                            ? 'border-[var(--primary-color)] bg-[color-mix(in_oklab,var(--primary-color)_14%,white_86%)]'
                                            : 'border-slate-200 bg-slate-50 hover:border-slate-300'
                                    }`}
                                >
                                    <p className="text-sm font-semibold text-slate-900">{pkg.name}</p>
                                    <p className="mt-0.5 text-xs text-slate-600">{pkg.description || 'No description'}</p>
                                    <p className="mt-2 text-xs text-slate-500">
                                        Base: {formatCurrency(pkg.base_price)} · {pkg.pricing_mode === 'per_person' ? 'Per person' : 'Flat'}
                                    </p>
                                </button>
                            );
                        })}
                    </div>
                </article>

                <article className="overflow-hidden rounded-3xl border border-slate-200 bg-white">
                    <header className="border-b border-slate-100 px-4 py-3">
                        <h2 className="text-sm font-semibold text-slate-900">Builder Canvas</h2>
                        <p className="text-xs text-slate-500">{selectedPackage ? `Editing ${selectedPackage.name}` : 'Select a package to begin'}</p>
                    </header>

                    <div className="space-y-4 p-4">
                        <form onSubmit={addItemToMenu} className="grid gap-3 rounded-2xl border border-slate-200 bg-slate-50 p-3">
                            <label className="space-y-1 text-xs font-semibold uppercase tracking-wide text-slate-500">
                                Menu Item
                                <select
                                    value={selectedItemId}
                                    onChange={(event) => setSelectedItemId(event.target.value)}
                                    className="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700"
                                >
                                    {menuLibrary.map((item) => (
                                        <option key={item.id} value={item.id}>
                                            {item.name} ({item.category})
                                        </option>
                                    ))}
                                </select>
                            </label>

                            <label className="space-y-1 text-xs font-semibold uppercase tracking-wide text-slate-500">
                                Serving Count
                                <input
                                    type="number"
                                    min={1}
                                    value={servings}
                                    onChange={(event) => setServings(event.target.value)}
                                    className="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700"
                                />
                            </label>

                            <label className="space-y-1 text-xs font-semibold uppercase tracking-wide text-slate-500">
                                Notes
                                <input
                                    value={notes}
                                    onChange={(event) => setNotes(event.target.value)}
                                    placeholder="Preparation notes or dietary tags"
                                    className="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700"
                                />
                            </label>

                            <button
                                type="submit"
                                disabled={!selectedPackage}
                                className="rounded-xl bg-[var(--primary-color)] px-3 py-2 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                Add To Menu
                            </button>
                        </form>

                        <div className="space-y-2">
                            <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">Current Composition</p>
                            {packageItems.length === 0 ? (
                                <div className="rounded-2xl border border-dashed border-slate-300 bg-white px-3 py-4 text-sm text-slate-600">
                                    No items added yet.
                                </div>
                            ) : (
                                packageItems.map((item) => (
                                    <div key={item.id} className="rounded-2xl border border-slate-200 bg-white px-3 py-3">
                                        <div className="flex items-start justify-between gap-2">
                                            <div>
                                                <p className="text-sm font-semibold text-slate-900">{item.name}</p>
                                                <p className="text-xs text-slate-500">
                                                    {item.category} · {formatNumber(item.servings)} servings
                                                </p>
                                                {item.notes ? <p className="mt-1 text-xs text-slate-600">{item.notes}</p> : null}
                                            </div>
                                            <button
                                                type="button"
                                                onClick={() => removeMenuItem(item.id)}
                                                className="rounded-lg border border-rose-300 px-2 py-1 text-xs font-semibold text-rose-700"
                                            >
                                                Remove
                                            </button>
                                        </div>
                                        <p className="mt-2 text-xs font-semibold text-slate-700">Cost: {formatCurrency(item.totalCost)}</p>
                                    </div>
                                ))
                            )}
                        </div>
                    </div>
                </article>

                <article className="overflow-hidden rounded-3xl border border-slate-200 bg-white">
                    <header className="border-b border-slate-100 px-4 py-3">
                        <h2 className="text-sm font-semibold text-slate-900">Pricing + Publish</h2>
                    </header>

                    <div className="space-y-3 p-4">
                        <div className="rounded-2xl border border-slate-200 bg-slate-50 px-3 py-3">
                            <p className="text-xs uppercase tracking-wide text-slate-500">Package</p>
                            <p className="mt-1 text-sm font-semibold text-slate-900">{selectedPackage?.name || 'No package selected'}</p>
                        </div>

                        <div className="rounded-2xl border border-slate-200 bg-white px-3 py-3">
                            <p className="text-xs uppercase tracking-wide text-slate-500">Ingredient Cost</p>
                            <p className="mt-1 text-lg font-semibold text-slate-900">{formatCurrency(ingredientCost)}</p>
                        </div>

                        <div className="rounded-2xl border border-slate-200 bg-white px-3 py-3">
                            <p className="text-xs uppercase tracking-wide text-slate-500">Suggested Selling Price</p>
                            <p className="mt-1 text-lg font-semibold text-slate-900">{formatCurrency(suggestedPrice)}</p>
                            <p className="mt-1 text-xs text-slate-500">Based on food cost x 1.5 with base package floor.</p>
                        </div>

                        <button
                            type="button"
                            onClick={publishTemplate}
                            disabled={!selectedPackage}
                            className="w-full rounded-xl bg-[var(--primary-color)] px-3 py-2 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            Publish Menu Template
                        </button>

                        {selectedPackage && publishState[selectedPackage.id] ? (
                            <p className="rounded-xl border border-emerald-300 bg-emerald-50 px-3 py-2 text-xs text-emerald-900">
                                Published at {publishState[selectedPackage.id]}.
                            </p>
                        ) : null}
                    </div>
                </article>
            </section>
        </div>
    );
}
