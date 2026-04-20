import { useEffect, useMemo, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { createTenantPackage, fetchTenantPackages, updateTenantPackage } from '../../../api/tenantApi';
import { formatCurrency, formatNumber } from '../../../lib/formatters';
import { useTenantContext } from '../../../providers/TenantProvider';

const menuCategories = ['Appetizer', 'Main Course', 'Signature Rice', 'Dessert', 'Beverage', 'Add-On'];

const defaultMenuItemDraft = {
    name: '',
    category: 'Main Course',
    servings: 100,
    unit_cost: '',
    notes: '',
};

const initialPackageDraft = {
    name: '',
    description: '',
    pricing_mode: 'per_person',
    base_price: '',
    is_active: true,
};

function normalizeMenuItem(item) {
    return {
        name: String(item?.name ?? '').trim(),
        category: String(item?.category ?? 'Main Course').trim() || 'Main Course',
        servings: Math.max(Number(item?.servings ?? 1), 1),
        unit_cost: Math.max(Number(item?.unit_cost ?? 0), 0),
        notes: String(item?.notes ?? '').trim(),
    };
}

export function TenantMenuBuilderPage() {
    const queryClient = useQueryClient();
    const { authUser } = useTenantContext();
    const [selectedPackageId, setSelectedPackageId] = useState(null);
    const [menuItemDraft, setMenuItemDraft] = useState(defaultMenuItemDraft);
    const [packageDraft, setPackageDraft] = useState(initialPackageDraft);
    const [packageItems, setPackageItems] = useState([]);
    const [statusMessage, setStatusMessage] = useState('');

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
    const canManagePackages = Boolean(authUser?.permissions?.includes('packages.manage'));

    useEffect(() => {
        if (!selectedPackage) {
            setPackageItems([]);
            return;
        }

        const nextItems = Array.isArray(selectedPackage.menu_items) ? selectedPackage.menu_items.map(normalizeMenuItem) : [];
        setPackageItems(nextItems);
        setStatusMessage('');
    }, [selectedPackage?.id, selectedPackage?.menu_items]);

    const saveMenuMutation = useMutation({
        mutationFn: ({ packageId, payload }) => updateTenantPackage(packageId, payload),
        onSuccess: async (_response, variables) => {
            await queryClient.invalidateQueries({ queryKey: ['tenant-packages'] });

            setStatusMessage(
                variables.mode === 'publish'
                    ? `Menu template published at ${new Date().toLocaleString()}.`
                    : 'Menu template saved to package data.',
            );
        },
        onError: (error) => {
            setStatusMessage(error?.response?.data?.message ?? 'Unable to save menu template right now.');
        },
    });

    const createPackageMutation = useMutation({
        mutationFn: createTenantPackage,
        onSuccess: async (createdPackage) => {
            await queryClient.invalidateQueries({ queryKey: ['tenant-packages'] });
            setPackageDraft(initialPackageDraft);
            setSelectedPackageId(createdPackage?.id ?? null);
            setStatusMessage('Package created. You can now compose its menu template.');
        },
        onError: (error) => {
            setStatusMessage(error?.response?.data?.message ?? 'Unable to create package.');
        },
    });

    const ingredientCost = packageItems.reduce((carry, item) => carry + Number(item.unit_cost || 0) * Number(item.servings || 0), 0);
    const basePrice = Number(selectedPackage?.base_price ?? 0);
    const suggestedPrice = Math.max(basePrice, Math.round(ingredientCost * 1.5));

    const publishedAtLabel = selectedPackage?.menu_published_at ? new Date(selectedPackage.menu_published_at).toLocaleString() : null;

    function updateDraftField(key, value) {
        setMenuItemDraft((previous) => ({
            ...previous,
            [key]: value,
        }));
    }

    function updatePackageDraftField(key, value) {
        setPackageDraft((previous) => ({
            ...previous,
            [key]: value,
        }));
    }

    function createPackage(event) {
        event.preventDefault();

        if (!canManagePackages) {
            setStatusMessage('You do not have permission to create packages.');
            return;
        }

        if (!packageDraft.name.trim()) {
            setStatusMessage('Package name is required.');
            return;
        }

        const basePrice = Number(packageDraft.base_price);

        if (!Number.isFinite(basePrice) || basePrice < 0) {
            setStatusMessage('Base price must be zero or greater.');
            return;
        }

        createPackageMutation.mutate({
            name: packageDraft.name.trim(),
            description: packageDraft.description.trim() || null,
            pricing_mode: packageDraft.pricing_mode,
            base_price: basePrice,
            is_active: Boolean(packageDraft.is_active),
        });
    }

    function addItemToMenu(event) {
        event.preventDefault();

        if (!selectedPackage) {
            return;
        }

        const trimmedName = menuItemDraft.name.trim();

        if (!trimmedName) {
            setStatusMessage('Menu item name is required.');
            return;
        }


        setPackageItems((previous) => [
            ...previous,
            normalizeMenuItem({
                ...menuItemDraft,
                name: trimmedName,
            }),
        ]);

        setMenuItemDraft((previous) => ({
            ...defaultMenuItemDraft,
            category: previous.category,
            servings: previous.servings,
        }));
        setStatusMessage('');
    }

    function removeMenuItem(index) {
        setPackageItems((previous) => previous.filter((_, itemIndex) => itemIndex !== index));
        setStatusMessage('');
    }

    function persistMenuTemplate(mode) {
        if (!selectedPackage) {
            return;
        }

        const normalizedItems = packageItems.map(normalizeMenuItem);
        const payload = {
            menu_items: normalizedItems,
        };

        if (mode === 'publish') {
            payload.menu_published_at = new Date().toISOString();
        }

        saveMenuMutation.mutate({
            packageId: selectedPackage.id,
            payload,
            mode,
        });
    }

    function saveDraft() {
        persistMenuTemplate('draft');
    }

    function publishTemplate() {
        persistMenuTemplate('publish');
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
                            <div className="space-y-3 rounded-2xl border border-slate-200 bg-slate-50 p-3">
                                <p className="text-sm text-slate-700">No packages found. Create your first package to start building a menu.</p>
                                {canManagePackages ? (
                                    <form onSubmit={createPackage} className="space-y-2">
                                        <input
                                            value={packageDraft.name}
                                            onChange={(event) => updatePackageDraftField('name', event.target.value)}
                                            placeholder="Package name"
                                            className="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700"
                                        />
                                        <input
                                            value={packageDraft.description}
                                            onChange={(event) => updatePackageDraftField('description', event.target.value)}
                                            placeholder="Description"
                                            className="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700"
                                        />
                                        <div className="grid gap-2 sm:grid-cols-2">
                                            <select
                                                value={packageDraft.pricing_mode}
                                                onChange={(event) => updatePackageDraftField('pricing_mode', event.target.value)}
                                                className="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700"
                                            >
                                                <option value="per_person">Per Person</option>
                                                <option value="flat">Flat</option>
                                            </select>
                                            <input
                                                type="number"
                                                min={0}
                                                step="0.01"
                                                value={packageDraft.base_price}
                                                onChange={(event) => updatePackageDraftField('base_price', event.target.value)}
                                                placeholder="Base price"
                                                className="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700"
                                            />
                                        </div>
                                        <button
                                            type="submit"
                                            disabled={createPackageMutation.isPending}
                                            className="w-full rounded-xl bg-[var(--primary-color)] px-3 py-2 text-sm font-semibold text-white disabled:opacity-50"
                                        >
                                            {createPackageMutation.isPending ? 'Creating...' : 'Create Package'}
                                        </button>
                                    </form>
                                ) : (
                                    <p className="text-xs text-slate-600">Request packages.manage access from your tenant administrator.</p>
                                )}
                            </div>
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
                                <input
                                    value={menuItemDraft.name}
                                    onChange={(event) => updateDraftField('name', event.target.value)}
                                    placeholder="Example: Herb Roast Chicken"
                                    className="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700"
                                />
                            </label>

                            <label className="space-y-1 text-xs font-semibold uppercase tracking-wide text-slate-500">
                                Category
                                <select
                                    value={menuItemDraft.category}
                                    onChange={(event) => updateDraftField('category', event.target.value)}
                                    className="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700"
                                >
                                    {menuCategories.map((category) => (
                                        <option key={category} value={category}>
                                            {category}
                                        </option>
                                    ))}
                                </select>
                            </label>

                            <label className="space-y-1 text-xs font-semibold uppercase tracking-wide text-slate-500">
                                Serving Count
                                <input
                                    type="number"
                                    min={1}
                                    value={menuItemDraft.servings}
                                    onChange={(event) => updateDraftField('servings', event.target.value)}
                                    className="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700"
                                />
                            </label>

                            <label className="space-y-1 text-xs font-semibold uppercase tracking-wide text-slate-500">
                                Unit Cost
                                <input
                                    type="number"
                                    min={0}
                                    step="0.01"
                                    value={menuItemDraft.unit_cost}
                                    onChange={(event) => updateDraftField('unit_cost', event.target.value)}
                                    placeholder="0.00"
                                    className="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700"
                                />
                            </label>

                            <label className="space-y-1 text-xs font-semibold uppercase tracking-wide text-slate-500">
                                Notes
                                <input
                                    value={menuItemDraft.notes}
                                    onChange={(event) => updateDraftField('notes', event.target.value)}
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
                                packageItems.map((item, index) => (
                                    <div key={`${item.name}-${index}`} className="rounded-2xl border border-slate-200 bg-white px-3 py-3">
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
                                                onClick={() => removeMenuItem(index)}
                                                className="rounded-lg border border-rose-300 px-2 py-1 text-xs font-semibold text-rose-700"
                                            >
                                                Remove
                                            </button>
                                        </div>
                                        <p className="mt-2 text-xs font-semibold text-slate-700">
                                            Cost: {formatCurrency(Number(item.unit_cost || 0) * Number(item.servings || 0))}
                                        </p>
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
                            onClick={saveDraft}
                            disabled={!selectedPackage || saveMenuMutation.isPending}
                            className="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            {saveMenuMutation.isPending ? 'Saving...' : 'Save Draft'}
                        </button>

                        <button
                            type="button"
                            onClick={publishTemplate}
                            disabled={!selectedPackage || saveMenuMutation.isPending}
                            className="w-full rounded-xl bg-[var(--primary-color)] px-3 py-2 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            {saveMenuMutation.isPending ? 'Publishing...' : 'Publish Menu Template'}
                        </button>

                        {publishedAtLabel ? <p className="text-xs text-slate-600">Last published: {publishedAtLabel}</p> : null}

                        {statusMessage ? (
                            <p
                                className={`rounded-xl border px-3 py-2 text-xs ${
                                    saveMenuMutation.isError
                                        ? 'border-rose-300 bg-rose-50 text-rose-900'
                                        : 'border-emerald-300 bg-emerald-50 text-emerald-900'
                                }`}
                            >
                                {statusMessage}
                            </p>
                        ) : null}
                    </div>
                </article>
            </section>
        </div>
    );
}
