import { useEffect, useMemo, useRef, useState } from 'react';
import { useMutation } from '@tanstack/react-query';
import { updateTenantProfile } from '../../../api/tenantApi';
import { useTenantContext } from '../../../providers/TenantProvider';

const MAX_AVATAR_FILE_SIZE_BYTES = 2 * 1024 * 1024;
const ALLOWED_AVATAR_MIME_TYPES = ['image/png', 'image/jpeg', 'image/webp'];

function buildProfileForm(user) {
    return {
        firstname: user?.firstname || '',
        lastname: user?.lastname || '',
        mi: user?.mi || '',
        email: user?.email || '',
        password: '',
        passwordConfirmation: '',
    };
}

function resolveInitials(displayName) {
    const words = String(displayName || '')
        .split(' ')
        .map((word) => word.trim())
        .filter(Boolean)
        .slice(0, 2);

    if (words.length === 0) {
        return 'GU';
    }

    return words.map((word) => word[0]?.toUpperCase() || '').join('');
}

export function TenantProfilePage() {
    const { authUser, refreshAuth } = useTenantContext();
    const [profileForm, setProfileForm] = useState(() => buildProfileForm(authUser));
    const [removeAvatar, setRemoveAvatar] = useState(false);
    const [pendingAvatarFile, setPendingAvatarFile] = useState(null);
    const [pendingAvatarPreviewUrl, setPendingAvatarPreviewUrl] = useState('');
    const [avatarError, setAvatarError] = useState('');
    const [formError, setFormError] = useState('');
    const [successMessage, setSuccessMessage] = useState('');
    const avatarFileInputRef = useRef(null);

    const saveProfileMutation = useMutation({
        mutationFn: updateTenantProfile,
        onSuccess: async (payload) => {
            const nextUser = payload?.user || authUser;

            setProfileForm(buildProfileForm(nextUser));
            clearPendingAvatarSelection();
            setRemoveAvatar(false);
            setFormError('');
            setSuccessMessage('Your profile has been updated.');
            await refreshAuth();
        },
        onError: (error) => {
            setSuccessMessage('');
            setFormError(error?.response?.data?.message || 'Unable to update profile right now.');
        },
    });

    useEffect(() => {
        setProfileForm(buildProfileForm(authUser));
        setFormError('');
    }, [authUser]);

    useEffect(() => {
        return () => {
            if (pendingAvatarPreviewUrl) {
                URL.revokeObjectURL(pendingAvatarPreviewUrl);
            }
        };
    }, [pendingAvatarPreviewUrl]);

    const displayName = authUser?.display_name || 'Guest';
    const avatarInitials = resolveInitials(displayName);

    const activeAvatarUrl = useMemo(() => {
        if (pendingAvatarPreviewUrl) {
            return pendingAvatarPreviewUrl;
        }

        if (removeAvatar) {
            return '';
        }

        return authUser?.avatar_url || '';
    }, [authUser?.avatar_url, pendingAvatarPreviewUrl, removeAvatar]);

    function clearPendingAvatarSelection() {
        setPendingAvatarFile(null);
        setAvatarError('');
        setPendingAvatarPreviewUrl((current) => {
            if (current) {
                URL.revokeObjectURL(current);
            }

            return '';
        });

        if (avatarFileInputRef.current) {
            avatarFileInputRef.current.value = '';
        }
    }

    function updateField(key, value) {
        setProfileForm((previous) => ({
            ...previous,
            [key]: value,
        }));
    }

    function handleRemoveAvatarChange(event) {
        const checked = event.target.checked;

        setRemoveAvatar(checked);

        if (checked) {
            clearPendingAvatarSelection();
        }
    }

    function handleAvatarFileChange(event) {
        const file = event.target.files?.[0];

        if (!file) {
            clearPendingAvatarSelection();
            return;
        }

        if (!ALLOWED_AVATAR_MIME_TYPES.includes(file.type)) {
            clearPendingAvatarSelection();
            setAvatarError('Avatar must be PNG, JPG, JPEG, or WEBP.');
            return;
        }

        if (file.size > MAX_AVATAR_FILE_SIZE_BYTES) {
            clearPendingAvatarSelection();
            setAvatarError('Avatar must be 2MB or smaller.');
            return;
        }

        const previewUrl = URL.createObjectURL(file);

        setPendingAvatarPreviewUrl((current) => {
            if (current) {
                URL.revokeObjectURL(current);
            }

            return previewUrl;
        });

        setPendingAvatarFile(file);
        setRemoveAvatar(false);
        setAvatarError('');
    }

    function buildPayload() {
        const basePayload = {
            firstname: profileForm.firstname.trim(),
            lastname: profileForm.lastname.trim(),
            mi: profileForm.mi.trim(),
            email: profileForm.email.trim(),
            remove_avatar: removeAvatar,
        };

        if (profileForm.password.trim() !== '') {
            basePayload.password = profileForm.password;
            basePayload.password_confirmation = profileForm.passwordConfirmation;
        }

        if (!pendingAvatarFile) {
            return basePayload;
        }

        const formData = new FormData();

        formData.append('firstname', basePayload.firstname);
        formData.append('lastname', basePayload.lastname);
        formData.append('mi', basePayload.mi);
        formData.append('email', basePayload.email);
        formData.append('remove_avatar', basePayload.remove_avatar ? '1' : '0');

        if (basePayload.password) {
            formData.append('password', basePayload.password);
            formData.append('password_confirmation', basePayload.password_confirmation || '');
        }

        formData.append('avatar_file', pendingAvatarFile);

        return formData;
    }

    function handleSubmit(event) {
        event.preventDefault();

        if (profileForm.password !== profileForm.passwordConfirmation) {
            setFormError('Password confirmation does not match.');
            setSuccessMessage('');
            return;
        }

        setFormError('');
        setSuccessMessage('');
        saveProfileMutation.mutate(buildPayload());
    }

    return (
        <div className="space-y-6">
            <section className="app-shell-panel rounded-[2rem] p-6">
                <p className="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">My Account</p>
                <h1 className="hero-heading mt-2 text-3xl text-slate-900 md:text-4xl">Update your account details and profile avatar.</h1>
            </section>

            <section className="grid gap-4 xl:grid-cols-[1.3fr_1fr]">
                <form onSubmit={handleSubmit} className="overflow-hidden rounded-3xl border border-slate-200 bg-white">
                    <header className="border-b border-slate-100 px-4 py-3">
                        <h2 className="text-sm font-semibold text-slate-900">Account Information</h2>
                        <p className="text-xs text-slate-500">Use your updated email or username at the next login.</p>
                    </header>

                    <div className="grid gap-4 p-4 md:grid-cols-2">
                        <label className="space-y-1 text-xs font-semibold uppercase tracking-wide text-slate-500">
                            First Name
                            <input
                                value={profileForm.firstname}
                                onChange={(event) => updateField('firstname', event.target.value)}
                                className="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700"
                            />
                        </label>

                        <label className="space-y-1 text-xs font-semibold uppercase tracking-wide text-slate-500">
                            Last Name
                            <input
                                value={profileForm.lastname}
                                onChange={(event) => updateField('lastname', event.target.value)}
                                className="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700"
                            />
                        </label>

                        <label className="space-y-1 text-xs font-semibold uppercase tracking-wide text-slate-500">
                            Middle Initial
                            <input
                                value={profileForm.mi}
                                onChange={(event) => updateField('mi', event.target.value)}
                                className="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700"
                            />
                        </label>

                        <label className="space-y-1 text-xs font-semibold uppercase tracking-wide text-slate-500">
                            Username
                            <input
                                value={authUser?.username || ''}
                                disabled
                                className="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-600"
                            />
                        </label>

                        <label className="md:col-span-2 space-y-1 text-xs font-semibold uppercase tracking-wide text-slate-500">
                            Email
                            <input
                                type="email"
                                value={profileForm.email}
                                onChange={(event) => updateField('email', event.target.value)}
                                className="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700"
                            />
                        </label>

                        <label className="space-y-1 text-xs font-semibold uppercase tracking-wide text-slate-500">
                            New Password
                            <input
                                type="password"
                                value={profileForm.password}
                                onChange={(event) => updateField('password', event.target.value)}
                                placeholder="Leave blank to keep current password"
                                className="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700"
                            />
                        </label>

                        <label className="space-y-1 text-xs font-semibold uppercase tracking-wide text-slate-500">
                            Confirm Password
                            <input
                                type="password"
                                value={profileForm.passwordConfirmation}
                                onChange={(event) => updateField('passwordConfirmation', event.target.value)}
                                placeholder="Repeat new password"
                                className="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700"
                            />
                        </label>
                    </div>

                    <div className="flex items-center justify-end border-t border-slate-100 px-4 py-3">
                        <button
                            type="submit"
                            disabled={saveProfileMutation.isPending}
                            className="rounded-full bg-[var(--primary-color)] px-4 py-2 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            {saveProfileMutation.isPending ? 'Saving...' : 'Save Profile'}
                        </button>
                    </div>

                    {formError ? (
                        <p className="mx-4 mb-4 rounded-xl border border-rose-300 bg-rose-50 px-3 py-2 text-xs text-rose-900">{formError}</p>
                    ) : null}

                    {successMessage ? (
                        <p className="mx-4 mb-4 rounded-xl border border-emerald-300 bg-emerald-50 px-3 py-2 text-xs text-emerald-900">{successMessage}</p>
                    ) : null}
                </form>

                <article className="space-y-4 rounded-3xl border border-slate-200 bg-white p-4">
                    <h2 className="text-sm font-semibold text-slate-900">Profile Avatar</h2>

                    <div className="flex items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 p-3">
                        {activeAvatarUrl ? (
                            <img
                                src={activeAvatarUrl}
                                alt={`${displayName} avatar`}
                                className="h-14 w-14 rounded-full border border-slate-200 object-cover"
                            />
                        ) : (
                            <div className="flex h-14 w-14 items-center justify-center rounded-full bg-[var(--primary-color)] text-sm font-semibold text-white">
                                {avatarInitials}
                            </div>
                        )}
                        <div>
                            <p className="text-sm font-semibold text-slate-900">{displayName}</p>
                            <p className="text-xs text-slate-500">Role: {authUser?.role || 'Guest'}</p>
                        </div>
                    </div>

                    <label className="space-y-1 text-xs font-semibold uppercase tracking-wide text-slate-500">
                        Upload Avatar
                        <input
                            ref={avatarFileInputRef}
                            type="file"
                            accept="image/png,image/jpeg,image/webp"
                            onChange={handleAvatarFileChange}
                            className="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700"
                        />
                    </label>

                    <p className="text-[11px] text-slate-500">PNG, JPG, JPEG, or WEBP. Maximum size: 2MB.</p>

                    <label className="inline-flex items-center gap-2 text-sm font-semibold text-slate-700">
                        <input
                            type="checkbox"
                            checked={removeAvatar}
                            onChange={handleRemoveAvatarChange}
                        />
                        Remove current avatar
                    </label>

                    {pendingAvatarFile ? (
                        <p className="rounded-xl border border-emerald-300 bg-emerald-50 px-3 py-2 text-xs text-emerald-900">
                            Selected avatar: {pendingAvatarFile.name}
                        </p>
                    ) : null}

                    {avatarError ? (
                        <p className="rounded-xl border border-rose-300 bg-rose-50 px-3 py-2 text-xs text-rose-900">{avatarError}</p>
                    ) : null}
                </article>
            </section>
        </div>
    );
}
