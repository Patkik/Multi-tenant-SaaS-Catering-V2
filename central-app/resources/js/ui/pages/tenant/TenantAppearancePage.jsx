import { useEffect, useMemo, useRef, useState } from 'react';
import { useMutation, useQuery } from '@tanstack/react-query';
import { fetchTenantBranding, updateTenantBranding } from '../../../api/tenantApi';
import { useTenantContext } from '../../../providers/TenantProvider';

const fallbackCustomizer = {
    company_name: '',
    primary_color: '#0B8F66',
    logo_url: '',
    heading_font: 'Sora',
    body_font: 'Plus Jakarta Sans',
    layout_density: 'comfortable',
    card_radius: 18,
    hero_message: 'Craft memorable event experiences your clients love.',
};

const panelTabs = [
    { key: 'brand', label: 'Brand Identity' },
    { key: 'sections', label: 'Homepage Sections' },
    { key: 'typography', label: 'Typography' },
    { key: 'styles', label: 'Global Styles' },
];

const defaultSections = [
    { id: 'hero', label: 'Hero Banner', enabled: true },
    { id: 'featured-packages', label: 'Featured Packages', enabled: true },
    { id: 'testimonials', label: 'Client Testimonials', enabled: true },
    { id: 'cta', label: 'Call To Action', enabled: true },
];

const LOGO_PNG_MIME_TYPE = 'image/png';
const MAX_LOGO_DIMENSION_PX = 600;

function normalizeSections(sections) {
    if (!Array.isArray(sections) || sections.length === 0) {
        return defaultSections;
    }

    return sections.map((section, index) => ({
        id: String(section?.id || `section-${index + 1}`),
        label: String(section?.label || `Section ${index + 1}`),
        enabled: Boolean(section?.enabled),
    }));
}

function mapBrandingToCustomizer(payload) {
    return {
        ...fallbackCustomizer,
        company_name: payload?.company_name || '',
        primary_color: payload?.primary_color || '#0B8F66',
        logo_url: payload?.logo_url || '',
        heading_font: payload?.heading_font || fallbackCustomizer.heading_font,
        body_font: payload?.body_font || fallbackCustomizer.body_font,
        layout_density: payload?.layout_density || fallbackCustomizer.layout_density,
        card_radius: Number(payload?.card_radius ?? fallbackCustomizer.card_radius),
        hero_message: payload?.hero_message || fallbackCustomizer.hero_message,
    };
}

function loadImageDimensions(file) {
    return new Promise((resolve, reject) => {
        const previewUrl = URL.createObjectURL(file);
        const image = new Image();

        image.onload = () => {
            resolve({
                previewUrl,
                width: image.width,
                height: image.height,
            });
        };

        image.onerror = () => {
            URL.revokeObjectURL(previewUrl);
            reject(new Error('Unable to read image metadata.'));
        };

        image.src = previewUrl;
    });
}

export function TenantAppearancePage() {
    const { refreshProfile } = useTenantContext();
    const [panel, setPanel] = useState('brand');
    const [customizer, setCustomizer] = useState(fallbackCustomizer);
    const [savedSnapshot, setSavedSnapshot] = useState(fallbackCustomizer);
    const [sections, setSections] = useState(defaultSections);
    const [savedSections, setSavedSections] = useState(defaultSections);
    const [publishedAt, setPublishedAt] = useState('');
    const [logoUploadFile, setLogoUploadFile] = useState(null);
    const [logoUploadPreviewUrl, setLogoUploadPreviewUrl] = useState('');
    const [logoUploadError, setLogoUploadError] = useState('');
    const logoFileInputRef = useRef(null);

    const brandingQuery = useQuery({
        queryKey: ['tenant-branding'],
        queryFn: fetchTenantBranding,
        staleTime: 1000 * 60,
    });

    const saveCustomizerMutation = useMutation({
        mutationFn: updateTenantBranding,
        onSuccess: async (payload) => {
            const next = mapBrandingToCustomizer(payload);

            setCustomizer(next);
            setSavedSnapshot(next);
            const normalizedSections = normalizeSections(payload?.homepage_sections);
            setSections(normalizedSections);
            setSavedSections(normalizedSections);
            clearPendingLogoUpload();
            await refreshProfile();
        },
    });

    useEffect(() => {
        if (brandingQuery.data) {
            const next = mapBrandingToCustomizer(brandingQuery.data);

            setCustomizer(next);
            setSavedSnapshot(next);
            const normalizedSections = normalizeSections(brandingQuery.data?.homepage_sections);
            setSections(normalizedSections);
            setSavedSections(normalizedSections);
            clearPendingLogoUpload();
        }
    }, [brandingQuery.data]);

    useEffect(() => {
        return () => {
            if (logoUploadPreviewUrl) {
                URL.revokeObjectURL(logoUploadPreviewUrl);
            }
        };
    }, [logoUploadPreviewUrl]);

    const enabledSectionCount = sections.filter((section) => section.enabled).length;

    const previewStyle = useMemo(() => {
        return {
            '--preview-accent': customizer.primary_color,
            '--preview-radius': `${customizer.card_radius}px`,
            fontFamily: customizer.body_font,
        };
    }, [customizer.body_font, customizer.card_radius, customizer.primary_color]);

    const previewLogoUrl = logoUploadPreviewUrl || customizer.logo_url;

    function updateField(key, value) {
        setCustomizer((previous) => ({
            ...previous,
            [key]: value,
        }));
    }

    function clearPendingLogoUpload() {
        setLogoUploadFile(null);
        setLogoUploadError('');
        setLogoUploadPreviewUrl((current) => {
            if (current) {
                URL.revokeObjectURL(current);
            }

            return '';
        });

        if (logoFileInputRef.current) {
            logoFileInputRef.current.value = '';
        }
    }

    async function handleLogoFileChange(event) {
        const file = event.target.files?.[0];

        if (!file) {
            clearPendingLogoUpload();
            return;
        }

        if (file.type !== LOGO_PNG_MIME_TYPE) {
            clearPendingLogoUpload();
            setLogoUploadError('Only PNG files are allowed.');
            return;
        }

        try {
            const { previewUrl, width, height } = await loadImageDimensions(file);

            if (width > MAX_LOGO_DIMENSION_PX || height > MAX_LOGO_DIMENSION_PX) {
                URL.revokeObjectURL(previewUrl);
                clearPendingLogoUpload();
                setLogoUploadError(`Logo must be ${MAX_LOGO_DIMENSION_PX}x${MAX_LOGO_DIMENSION_PX}px or smaller.`);
                return;
            }

            setLogoUploadPreviewUrl((current) => {
                if (current) {
                    URL.revokeObjectURL(current);
                }

                return previewUrl;
            });
            setLogoUploadFile(file);
            setLogoUploadError('');
        } catch {
            clearPendingLogoUpload();
            setLogoUploadError('Could not process the selected logo file.');
        }
    }

    function buildCustomizerPayload() {
        const payload = {
            company_name: customizer.company_name,
            primary_color: customizer.primary_color,
            logo_url: customizer.logo_url,
            logo_path: '',
            heading_font: customizer.heading_font,
            body_font: customizer.body_font,
            layout_density: customizer.layout_density,
            card_radius: customizer.card_radius,
            hero_message: customizer.hero_message,
            homepage_sections: sections,
        };

        if (!logoUploadFile) {
            return payload;
        }

        const formData = new FormData();
        formData.append('company_name', payload.company_name || '');
        formData.append('primary_color', payload.primary_color || '');
        formData.append('logo_url', payload.logo_url || '');
        formData.append('logo_path', '');
        formData.append('heading_font', payload.heading_font || '');
        formData.append('body_font', payload.body_font || '');
        formData.append('layout_density', payload.layout_density || '');
        formData.append('card_radius', String(payload.card_radius));
        formData.append('hero_message', payload.hero_message || '');
        formData.append('logo_file', logoUploadFile);

        payload.homepage_sections.forEach((section, index) => {
            formData.append(`homepage_sections[${index}][id]`, section.id);
            formData.append(`homepage_sections[${index}][label]`, section.label);
            formData.append(`homepage_sections[${index}][enabled]`, section.enabled ? '1' : '0');
        });

        return formData;
    }

    function toggleSection(sectionId) {
        setSections((previous) =>
            previous.map((section) =>
                section.id === sectionId
                    ? {
                          ...section,
                          enabled: !section.enabled,
                      }
                    : section,
            ),
        );
    }

    function moveSection(sectionId, direction) {
        setSections((previous) => {
            const currentIndex = previous.findIndex((section) => section.id === sectionId);

            if (currentIndex < 0) {
                return previous;
            }

            const nextIndex = direction === 'up' ? currentIndex - 1 : currentIndex + 1;

            if (nextIndex < 0 || nextIndex >= previous.length) {
                return previous;
            }

            const clone = [...previous];
            const [moved] = clone.splice(currentIndex, 1);
            clone.splice(nextIndex, 0, moved);
            return clone;
        });
    }

    function saveDraft(event) {
        event.preventDefault();

        saveCustomizerMutation.mutate(buildCustomizerPayload());
    }

    function publishTheme() {
        saveCustomizerMutation.mutate(
            buildCustomizerPayload(),
            {
                onSuccess: () => {
                    setPublishedAt(new Date().toLocaleString());
                },
            },
        );
    }

    function resetToSaved() {
        setCustomizer(savedSnapshot);
        setSections(savedSections);
        clearPendingLogoUpload();
    }

    return (
        <div className="space-y-6">
            <section className="app-shell-panel rounded-[2rem] p-6">
                <p className="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Appearance Customizer</p>
                <h1 className="hero-heading mt-2 text-3xl text-slate-900 md:text-4xl">WordPress-style design controls with instant preview and publish workflow.</h1>
            </section>

            <section className="grid gap-4 xl:grid-cols-[1.05fr_1.95fr]">
                <form onSubmit={saveDraft} className="overflow-hidden rounded-3xl border border-slate-200 bg-white">
                    <header className="border-b border-slate-100 px-4 py-3">
                        <h2 className="text-sm font-semibold text-slate-900">Customizer Controls</h2>
                        <p className="text-xs text-slate-500">Live controls synchronized with workspace branding tokens.</p>
                    </header>

                    <div className="flex flex-wrap gap-2 border-b border-slate-100 px-4 py-3">
                        {panelTabs.map((tab) => (
                            <button
                                key={tab.key}
                                type="button"
                                onClick={() => setPanel(tab.key)}
                                className={`rounded-full px-3 py-1.5 text-xs font-semibold ${
                                    panel === tab.key
                                        ? 'bg-[var(--primary-color)] text-white'
                                        : 'border border-slate-300 bg-white text-slate-700'
                                }`}
                            >
                                {tab.label}
                            </button>
                        ))}
                    </div>

                    <div className="space-y-3 p-4">
                        {panel === 'brand' ? (
                            <>
                                <label className="space-y-1 text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    Company Name
                                    <input
                                        value={customizer.company_name}
                                        onChange={(event) => updateField('company_name', event.target.value)}
                                        className="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-900"
                                    />
                                </label>

                                <label className="space-y-1 text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    Primary Color
                                    <input
                                        type="color"
                                        value={customizer.primary_color}
                                        onChange={(event) => updateField('primary_color', event.target.value)}
                                        className="h-10 w-full rounded-xl border border-slate-300 bg-white px-1 py-1"
                                    />
                                </label>

                                <label className="space-y-1 text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    Logo Upload (PNG)
                                    <input
                                        ref={logoFileInputRef}
                                        type="file"
                                        accept="image/png"
                                        onChange={handleLogoFileChange}
                                        className="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700"
                                    />
                                </label>

                                <p className="text-[11px] text-slate-500">PNG only. Max dimensions: 600x600 px.</p>

                                {logoUploadFile ? (
                                    <p className="rounded-xl border border-emerald-300 bg-emerald-50 px-3 py-2 text-xs text-emerald-900">
                                        Selected logo: {logoUploadFile.name}
                                    </p>
                                ) : null}

                                {logoUploadError ? (
                                    <p className="rounded-xl border border-rose-300 bg-rose-50 px-3 py-2 text-xs text-rose-900">{logoUploadError}</p>
                                ) : null}

                                <label className="space-y-1 text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    Hero Message
                                    <textarea
                                        rows={3}
                                        value={customizer.hero_message}
                                        onChange={(event) => updateField('hero_message', event.target.value)}
                                        className="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700"
                                    />
                                </label>
                            </>
                        ) : null}

                        {panel === 'sections' ? (
                            <div className="space-y-2">
                                {sections.map((section, index) => (
                                    <div key={section.id} className="rounded-2xl border border-slate-200 bg-slate-50 px-3 py-3">
                                        <div className="flex items-center justify-between gap-2">
                                            <div>
                                                <p className="text-sm font-semibold text-slate-900">{section.label}</p>
                                                <p className="text-xs text-slate-500">Position {index + 1}</p>
                                            </div>
                                            <button
                                                type="button"
                                                onClick={() => toggleSection(section.id)}
                                                className={`rounded-full px-2.5 py-1 text-xs font-semibold ${
                                                    section.enabled
                                                        ? 'bg-emerald-100 text-emerald-800'
                                                        : 'bg-slate-200 text-slate-700'
                                                }`}
                                            >
                                                {section.enabled ? 'Enabled' : 'Hidden'}
                                            </button>
                                        </div>
                                        <div className="mt-2 flex gap-2">
                                            <button
                                                type="button"
                                                onClick={() => moveSection(section.id, 'up')}
                                                className="rounded-lg border border-slate-300 bg-white px-2 py-1 text-xs font-semibold text-slate-700"
                                            >
                                                Move Up
                                            </button>
                                            <button
                                                type="button"
                                                onClick={() => moveSection(section.id, 'down')}
                                                className="rounded-lg border border-slate-300 bg-white px-2 py-1 text-xs font-semibold text-slate-700"
                                            >
                                                Move Down
                                            </button>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        ) : null}

                        {panel === 'typography' ? (
                            <>
                                <label className="space-y-1 text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    Heading Font
                                    <select
                                        value={customizer.heading_font}
                                        onChange={(event) => updateField('heading_font', event.target.value)}
                                        className="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700"
                                    >
                                        <option value="Sora">Sora</option>
                                        <option value="Plus Jakarta Sans">Plus Jakarta Sans</option>
                                        <option value="Poppins">Poppins</option>
                                        <option value="Manrope">Manrope</option>
                                    </select>
                                </label>

                                <label className="space-y-1 text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    Body Font
                                    <select
                                        value={customizer.body_font}
                                        onChange={(event) => updateField('body_font', event.target.value)}
                                        className="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700"
                                    >
                                        <option value="Plus Jakarta Sans">Plus Jakarta Sans</option>
                                        <option value="Inter">Inter</option>
                                        <option value="Nunito Sans">Nunito Sans</option>
                                        <option value="Work Sans">Work Sans</option>
                                    </select>
                                </label>
                            </>
                        ) : null}

                        {panel === 'styles' ? (
                            <>
                                <label className="space-y-1 text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    Layout Density
                                    <select
                                        value={customizer.layout_density}
                                        onChange={(event) => updateField('layout_density', event.target.value)}
                                        className="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700"
                                    >
                                        <option value="comfortable">Comfortable</option>
                                        <option value="compact">Compact</option>
                                        <option value="airy">Airy</option>
                                    </select>
                                </label>

                                <label className="space-y-1 text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    Card Radius ({customizer.card_radius}px)
                                    <input
                                        type="range"
                                        min={8}
                                        max={28}
                                        value={customizer.card_radius}
                                        onChange={(event) => updateField('card_radius', Number(event.target.value))}
                                        className="w-full"
                                    />
                                </label>
                            </>
                        ) : null}

                        {saveCustomizerMutation.isError ? (
                            <p className="rounded-xl border border-rose-300 bg-rose-50 px-3 py-2 text-xs text-rose-900">
                                {saveCustomizerMutation.error?.response?.data?.message ?? 'Unable to save appearance profile.'}
                            </p>
                        ) : null}

                        {saveCustomizerMutation.isSuccess ? (
                            <p className="rounded-xl border border-emerald-300 bg-emerald-50 px-3 py-2 text-xs text-emerald-900">
                                Appearance draft saved successfully.
                            </p>
                        ) : null}

                        {publishedAt ? (
                            <p className="rounded-xl border border-sky-300 bg-sky-50 px-3 py-2 text-xs text-sky-900">Published at {publishedAt}</p>
                        ) : null}

                        <div className="flex flex-wrap gap-2 pt-1">
                            <button
                                type="submit"
                                disabled={saveCustomizerMutation.isPending}
                                className="rounded-full bg-[var(--primary-color)] px-4 py-2 text-sm font-semibold text-white disabled:opacity-50"
                            >
                                {saveCustomizerMutation.isPending ? 'Saving...' : 'Save Draft'}
                            </button>
                            <button
                                type="button"
                                onClick={publishTheme}
                                disabled={saveCustomizerMutation.isPending}
                                className="rounded-full border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 disabled:opacity-50"
                            >
                                Publish Theme
                            </button>
                            <button
                                type="button"
                                onClick={resetToSaved}
                                className="rounded-full border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700"
                            >
                                Reset
                            </button>
                        </div>
                    </div>
                </form>

                <article className="overflow-hidden rounded-3xl border border-slate-200 bg-white">
                    <header className="border-b border-slate-100 px-4 py-3">
                        <h2 className="text-sm font-semibold text-slate-900">Live Preview</h2>
                        <p className="text-xs text-slate-500">Enabled sections: {enabledSectionCount} / {sections.length}</p>
                    </header>

                    <div className="p-4">
                        <div
                            className="rounded-3xl border border-slate-200 bg-[color-mix(in_oklab,var(--preview-accent)_8%,white_92%)] p-5"
                            style={previewStyle}
                        >
                            <div className="flex items-center justify-between gap-3">
                                <div className="flex items-center gap-3">
                                    {previewLogoUrl ? (
                                        <img src={previewLogoUrl} alt="Preview logo" className="h-11 w-11 rounded-xl border border-slate-200 object-cover" />
                                    ) : (
                                        <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-[var(--preview-accent)] text-sm font-bold text-white">
                                            {(customizer.company_name || 'C')[0]?.toUpperCase()}
                                        </div>
                                    )}
                                    <div>
                                        <p className="text-xs uppercase tracking-wide text-slate-500">Tenant Brand</p>
                                        <p className="text-base font-semibold text-slate-900">{customizer.company_name || 'CaterPro Tenant'}</p>
                                    </div>
                                </div>
                                <button
                                    type="button"
                                    className="rounded-full bg-[var(--preview-accent)] px-3 py-1.5 text-xs font-semibold text-white"
                                >
                                    Book Event
                                </button>
                            </div>

                            <div className="mt-5 rounded-[var(--preview-radius)] border border-slate-200 bg-white p-4">
                                <h3 className="text-lg font-semibold text-slate-900" style={{ fontFamily: customizer.heading_font }}>
                                    {customizer.hero_message}
                                </h3>
                                <p className="mt-1 text-sm text-slate-600">
                                    This preview reflects your live appearance configuration before publishing.
                                </p>

                                <div className="mt-4 grid gap-2 md:grid-cols-2">
                                    {sections
                                        .filter((section) => section.enabled)
                                        .map((section) => (
                                            <div key={section.id} className="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                                                <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">Section</p>
                                                <p className="text-sm font-semibold text-slate-900">{section.label}</p>
                                            </div>
                                        ))}
                                </div>
                            </div>
                        </div>
                    </div>
                </article>
            </section>
        </div>
    );
}
