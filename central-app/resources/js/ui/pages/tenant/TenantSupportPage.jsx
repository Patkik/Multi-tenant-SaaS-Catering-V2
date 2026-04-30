import { useEffect, useMemo, useState } from 'react';
import { useLocation } from 'react-router-dom';
import { useMutation } from '@tanstack/react-query';
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
    subject: buildSubject('feedback'),
    message: '',
    contact_name: '',
    contact_email: '',
    page_path: '',
};

function buildSubject(category) {
    return category === 'bug' ? 'Bug report from tenant workspace' : 'Tenant feedback';
}

export function TenantSupportPage() {
    const { pathname, search } = useLocation();
    const { tenantProfile, authUser } = useTenantContext();
    const [formState, setFormState] = useState(defaultFormState);
    const [successMessage, setSuccessMessage] = useState('');
    const trimmedSubject = formState.subject.trim();
    const trimmedMessage = formState.message.trim();
    const canSubmit = trimmedSubject.length > 0 && trimmedMessage.length >= 20;

    useEffect(() => {
        setFormState((previous) => ({
            ...previous,
            contact_name: previous.contact_name || authUser?.display_name || authUser?.name || '',
            contact_email: previous.contact_email || authUser?.email || '',
            page_path: previous.page_path || `${pathname}${search}`,
            subject: previous.subject || buildSubject(previous.category),
        }));
    }, [authUser?.display_name, authUser?.email, authUser?.name, pathname, search]);

    const supportMutation = useMutation({
        mutationFn: submitTenantSupportRequest,
        onSuccess: (result) => {
            setSuccessMessage(String(result?.message ?? 'Your support request has been sent.'));
            setFormState({
                ...defaultFormState,
                category: 'feedback',
                contact_name: authUser?.display_name || authUser?.name || '',
                contact_email: authUser?.email || '',
                page_path: `${pathname}${search}`,
                subject: buildSubject('feedback'),
            });
        },
    });

    const workspaceName = useMemo(() => tenantProfile?.company_name || 'Tenant Workspace', [tenantProfile?.company_name]);

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
            subject: previous.subject.trim() === '' ? buildSubject(category) : previous.subject,
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
            workspace_name: workspaceName,
            workspace_id: tenantProfile?.tenant_id || tenantProfile?.id || undefined,
            page_path: formState.page_path.trim() || `${pathname}${search}`,
            app_version: tenantProfile?.app_version || undefined,
            user_role: authUser?.role || tenantProfile?.active_role || undefined,
        });
    }

    return (
        <div className="space-y-4 pb-2">
            <section className="central-card p-4">
                <div className="max-w-3xl">
                    <p className="text-[11px] uppercase tracking-[0.08em]" style={{ color: 'var(--color-text-tertiary)' }}>
                        Support
                    </p>
                    <h1 className="mt-1 text-lg font-semibold">Send feedback or report a bug</h1>
                    <p className="mt-1 text-[12px]" style={{ color: 'var(--color-text-secondary)' }}>
                        Use this form to share product feedback, flag issues, or describe something that is not working as expected. Messages must be at least 20 characters long.
                    </p>
                </div>
            </section>

            <form onSubmit={handleSubmit} className="central-card p-4">
                <div className="grid gap-3 md:grid-cols-2">
                    {categoryOptions.map((option) => {
                        const checked = formState.category === option.value;

                        return (
                            <label
                                key={option.value}
                                className={`flex cursor-pointer gap-3 rounded-[var(--border-radius-md)] border px-3 py-3 text-[12px] transition ${checked ? 'border-[var(--primary-color)] bg-white' : ''}`}
                                style={{ borderColor: checked ? 'var(--primary-color)' : 'var(--color-border-tertiary)' }}
                            >
                                <input
                                    type="radio"
                                    name="category"
                                    checked={checked}
                                    onChange={() => handleCategoryChange(option.value)}
                                />
                                <span className="space-y-0.5">
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
                            placeholder={buildSubject(formState.category)}
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
                        The message is emailed to the tenant support inbox.
                    </p>
                </div>
            </form>
        </div>
    );
}