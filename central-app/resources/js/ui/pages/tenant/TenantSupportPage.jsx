import { useEffect, useMemo, useState } from 'react';
import { useLocation } from 'react-router-dom';
import { useMutation } from '@tanstack/react-query';
import { submitCentralSupportRequest } from '../../../api/centralApi';
import { submitTenantSupportRequest } from '../../../api/tenantApi';
import { useTenantContext } from '../../../providers/TenantProvider';

const categoryOptions = [
    {
        value: 'feedback',
        label: 'Feedback',
        description: 'Share product ideas, workflow suggestions, or general feedback.',
    },
    {
        value: 'bug',
        label: 'Bug report',
        description: 'Report a broken flow, error, or unexpected behavior.',
    },
];

const defaultFormState = {
    category: 'feedback',
    subject: '',
    message: '',
    contact_name: '',
    contact_email: '',
    page_path: '',
};

function buildSubject(category, isCentralMode = false) {
    if (category === 'bug') {
        return isCentralMode ? 'Bug report from central platform' : 'Bug report from tenant workspace';
    }

    return isCentralMode ? 'Central feedback' : 'Tenant feedback';
}

const supportActionButtonStyle = {
    borderColor: '#378ADD',
    backgroundColor: '#E6F1FB',
    color: '#0C447C',
};

const supportBadgeStyle = {
    borderColor: 'var(--color-border-tertiary)',
    backgroundColor: 'rgba(255, 255, 255, 0.9)',
    color: 'var(--color-text-secondary)',
};

function SupportStatusPill({ label, value }) {
    return (
        <div className="rounded-full border px-4 py-2 text-sm font-semibold" style={supportBadgeStyle}>
            {label}: {value}
        </div>
    );
}

export function TenantSupportPage() {
    const { pathname, search } = useLocation();
    const { mode, tenantProfile, authUser, centralAuthUser } = useTenantContext();
    const isCentralMode = mode === 'central';
    const [formState, setFormState] = useState(defaultFormState);
    const [successMessage, setSuccessMessage] = useState('');
    const trimmedSubject = formState.subject.trim();
    const trimmedMessage = formState.message.trim();
    const canSubmit = trimmedSubject.length > 0 && trimmedMessage.length >= 20;
    const activeUser = isCentralMode ? centralAuthUser : authUser;
    const supportInboxLabel = isCentralMode ? 'central team inbox' : 'tenant support inbox';
    const workspaceName = isCentralMode ? 'Central Platform' : tenantProfile?.company_name || 'Tenant Workspace';
    const workspaceId = isCentralMode ? 'central' : tenantProfile?.tenant_id || tenantProfile?.id || undefined;

    useEffect(() => {
        setFormState((previous) => ({
            ...previous,
            contact_name: previous.contact_name || activeUser?.display_name || activeUser?.name || '',
            contact_email: previous.contact_email || activeUser?.email || '',
            page_path: previous.page_path || `${pathname}${search}`,
            subject: previous.subject || buildSubject(previous.category),
        }));
    }, [activeUser?.display_name, activeUser?.email, activeUser?.name, pathname, search]);

    const supportMutation = useMutation({
        mutationFn: (payload) => (isCentralMode ? submitCentralSupportRequest(payload) : submitTenantSupportRequest(payload)),
        onSuccess: (result) => {
            setSuccessMessage(String(result?.message ?? 'Your support request has been sent.'));
            setFormState({
                ...defaultFormState,
                category: 'feedback',
                contact_name: activeUser?.display_name || activeUser?.name || '',
                contact_email: activeUser?.email || '',
                page_path: `${pathname}${search}`,
                subject: buildSubject('feedback', isCentralMode),
            });
        },
    });

    const workspaceLabel = useMemo(() => (isCentralMode ? 'Central Platform' : workspaceName), [isCentralMode, workspaceName]);
    const workspaceModeLabel = isCentralMode ? 'Central Console' : 'Tenant Workspace';

    function updateField(field, value) {
        setSuccessMessage('');
        setFormState((previous) => ({
            ...previous,
            [field]: value,
        }));
    }

    function handleCategoryChange(category) {
        setSuccessMessage('');
        setFormState((previous) => ({
            ...previous,
            category,
            subject: previous.subject.trim() === '' ? buildSubject(category, isCentralMode) : previous.subject,
        }));
    }

    function handleSubmit(event) {
        event.preventDefault();

        if (!canSubmit) {
            return;
        }

        supportMutation.mutate({
            category: formState.category,
            subject: trimmedSubject,
            message: trimmedMessage,
            contact_name: formState.contact_name.trim() || undefined,
            contact_email: formState.contact_email.trim() || undefined,
            workspace_name: workspaceLabel,
            workspace_id: workspaceId,
            page_path: formState.page_path.trim() || `${pathname}${search}`,
            app_version: activeUser?.app_version || tenantProfile?.app_version || undefined,
            user_role:
                activeUser?.role || activeUser?.roles?.[0] || tenantProfile?.active_role || undefined,
        });
    }

    return (
        <div className="space-y-4 pb-2">
            <section className="central-card p-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p className="text-[11px] uppercase tracking-[0.14em]" style={{ color: 'var(--color-text-tertiary)' }}>
                            Current Mode
                        </p>
                        <h1 className="hero-heading text-2xl" style={{ color: 'var(--color-text-primary)' }}>
                            Support Console
                        </h1>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <SupportStatusPill label="Workspace" value={workspaceModeLabel} />
                        <button
                            type="submit"
                            form="support-request-form"
                            disabled={supportMutation.isPending || !canSubmit}
                            className="cursor-pointer rounded-full border px-2.5 py-1 text-[10px] font-semibold uppercase leading-none transition disabled:cursor-not-allowed disabled:opacity-60"
                            style={supportActionButtonStyle}
                        >
                            {supportMutation.isPending ? 'Sending...' : 'Send Support'}
                        </button>
                    </div>
                </div>
                <p className="mt-3 max-w-3xl text-[12px]" style={{ color: 'var(--color-text-secondary)' }}>
                    Use this form to share product feedback, flag issues, or describe something that is not working as expected. Messages must be at least 20 characters long.
                </p>
            </section>

            <form id="support-request-form" onSubmit={handleSubmit} className="central-card p-4">
                <div className="grid gap-3 md:grid-cols-2">
                    {categoryOptions.map((option) => {
                        const checked = formState.category === option.value;

                        return (
                <p className="mt-4 text-[11px]" style={{ color: 'var(--color-text-tertiary)' }}>
                    The message is emailed to the {supportInboxLabel}.
                </p>
                                    <span className="block font-semibold" style={{ color: 'var(--color-text-primary)' }}>
                                        {option.label}
                                    </span>
                                    <span className="block text-[11px]" style={{ color: 'var(--color-text-tertiary)' }}>
                                        {option.description}
                                    </span>
                                </span>
                            </label>
                        );
                    })}
                </div>

                <div className="mt-4 grid gap-3 md:grid-cols-2">
                    <label className="space-y-1">
                        <span className="text-[11px] font-semibold" style={{ color: 'var(--color-text-secondary)' }}>
                            Subject
                        </span>
                        <input
                            value={formState.subject}
                            onChange={(event) => updateField('subject', event.target.value)}
                            className="central-input h-9 w-full rounded-[var(--border-radius-md)] border px-3 text-[12px]"
                            placeholder={buildSubject(formState.category, isCentralMode)}
                        />
                    </label>

                    <label className="space-y-1">
                        <span className="text-[11px] font-semibold" style={{ color: 'var(--color-text-secondary)' }}>
                            Contact name
                        </span>
                        <input
                            value={formState.contact_name}
                            onChange={(event) => updateField('contact_name', event.target.value)}
                            className="central-input h-9 w-full rounded-[var(--border-radius-md)] border px-3 text-[12px]"
                            placeholder="Your name"
                        />
                    </label>
                </div>

                <div className="mt-4 grid gap-3 md:grid-cols-2">
                    <label className="space-y-1">
                        <span className="text-[11px] font-semibold" style={{ color: 'var(--color-text-secondary)' }}>
                            Contact email
                        </span>
                        <input
                            type="email"
                            value={formState.contact_email}
                            onChange={(event) => updateField('contact_email', event.target.value)}
                            className="central-input h-9 w-full rounded-[var(--border-radius-md)] border px-3 text-[12px]"
                            placeholder="you@example.com"
                        />
                    </label>

                    <label className="space-y-1">
                        <span className="text-[11px] font-semibold" style={{ color: 'var(--color-text-secondary)' }}>
                            Affected page
                        </span>
                        <input
                            value={formState.page_path}
                            onChange={(event) => updateField('page_path', event.target.value)}
                            className="central-input h-9 w-full rounded-[var(--border-radius-md)] border px-3 text-[12px]"
                            placeholder="/bookings"
                        />
                    </label>
                </div>

                <label className="mt-4 block space-y-1">
                    <span className="text-[11px] font-semibold" style={{ color: 'var(--color-text-secondary)' }}>
                        Message
                    </span>
                    <textarea
                        value={formState.message}
                        onChange={(event) => updateField('message', event.target.value)}
                        className="central-input min-h-36 w-full rounded-[var(--border-radius-md)] border px-3 py-2 text-[12px]"
                        placeholder="Describe what happened, what you expected, and any steps to reproduce it."
                    />
                </label>

                {supportMutation.isError ? (
                    <p className="mt-3 rounded-[var(--border-radius-md)] border px-3 py-2 text-[11px]" style={{ borderColor: '#D85A30', color: '#712B13' }}>
                        We could not send your message. Please try again.
                    </p>
                ) : null}

                {successMessage ? (
                    <p className="mt-3 rounded-[var(--border-radius-md)] border px-3 py-2 text-[11px]" style={{ borderColor: '#1D9E75', backgroundColor: '#E1F5EE', color: '#085041' }}>
                        {successMessage}
                    </p>
                ) : null}

                <div className="mt-4 flex items-center gap-2">
                    <button
                        type="submit"
                        disabled={supportMutation.isPending || !canSubmit}
                        className="central-button-primary px-4 py-2 text-[12px] font-semibold disabled:opacity-60"
                    >
                        {supportMutation.isPending ? 'Sending...' : 'Send support request'}
                    </button>
                    <p className="text-[11px]" style={{ color: 'var(--color-text-tertiary)' }}>
                        The message is emailed to the {supportInboxLabel}.
                    </p>
                </div>
            </form>
        </div>
    );
}