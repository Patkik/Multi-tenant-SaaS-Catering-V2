import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { fetchCentralUsers, updateCentralUser } from '../../../api/centralApi';

const roleBadgeStyles = {
    'Super Admin': { borderColor: '#7F77DD', backgroundColor: '#E6F1FB', color: '#0C447C' },
    'Support Admin': { borderColor: '#378ADD', backgroundColor: '#E6F1FB', color: '#0C447C' },
    'Billing Admin': { borderColor: '#EF9F27', backgroundColor: '#FAEEDA', color: '#633806' },
};

const statusBadgeStyles = {
    Active: { borderColor: '#1D9E75', backgroundColor: '#E1F5EE', color: '#085041' },
    Inactive: { borderColor: '#73726c', backgroundColor: '#F1EFE8', color: '#444441' },
    Invited: { borderColor: '#73726c', backgroundColor: '#F1EFE8', color: '#444441' },
};

function toDisplayRole(role) {
    const normalized = String(role ?? '').toLowerCase();

    if (normalized.includes('support')) {
        return 'Support Admin';
    }
    if (normalized.includes('billing')) {
        return 'Billing Admin';
    }
    if (normalized.includes('super') || normalized.includes('admin')) {
        return 'Super Admin';
    }

    return 'Support Admin';
}

function initialsFromName(name) {
    return String(name ?? '')
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0])
        .join('')
        .toUpperCase();
}

export function CentralUserManagementPage() {
    const queryClient = useQueryClient();
    const [search, setSearch] = useState('');
    const [editingUser, setEditingUser] = useState(null);
    const [formValues, setFormValues] = useState({
        name: '',
        email: '',
    });
    const [formError, setFormError] = useState('');

    const usersQuery = useQuery({
        queryKey: ['central-users', { search }],
        queryFn: () => fetchCentralUsers(search),
        staleTime: 1000 * 30,
    });

    const updateUserMutation = useMutation({
        mutationFn: ({ userId, payload }) => updateCentralUser(userId, payload),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['central-users'] });
            setEditingUser(null);
            setFormError('');
        },
        onError: (error) => {
            const message = error?.response?.data?.message || 'Failed to update user details.';
            setFormError(String(message));
        },
    });

    const users = usersQuery.data?.users ?? [];

    const openEditDialog = (user) => {
        setEditingUser(user);
        setFormValues({
            name: user?.name ?? '',
            email: user?.email ?? '',
        });
        setFormError('');
    };

    const closeEditDialog = () => {
        if (updateUserMutation.isPending) {
            return;
        }

        setEditingUser(null);
        setFormError('');
    };

    const handleSaveUser = () => {
        if (!editingUser) {
            return;
        }

        const payload = {
            name: formValues.name.trim(),
            email: formValues.email.trim(),
        };

        setFormError('');
        updateUserMutation.mutate({ userId: editingUser.id, payload });
    };

    const isSaveDisabled = updateUserMutation.isPending || formValues.name.trim() === '' || formValues.email.trim() === '';

    return (
        <div className="space-y-4 pb-2">
            <section className="central-card p-4">
                <div className="flex flex-wrap items-center gap-2">
                    <input
                        type="search"
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder="Search admin user"
                        className="central-input h-9 min-w-[220px] flex-1 rounded-[var(--border-radius-md)] border px-3 text-[12px]"
                    />
                    <button type="button" className="central-button-muted px-3 py-2 text-[12px] font-semibold">
                        + Invite Admin
                    </button>
                </div>
            </section>

            <section className="central-card p-4">
                <div className="overflow-x-auto">
                    <table className="central-table w-full text-left text-[12px]" style={{ tableLayout: 'fixed' }}>
                        <colgroup>
                            <col style={{ width: '27%' }} />
                            <col style={{ width: '27%' }} />
                            <col style={{ width: '16%' }} />
                            <col style={{ width: '12%' }} />
                            <col style={{ width: '10%' }} />
                            <col style={{ width: '8%' }} />
                        </colgroup>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Added date</th>
                                <th>Edit</th>
                            </tr>
                        </thead>
                        <tbody>
                            {usersQuery.isPending ? (
                                <tr>
                                    <td className="py-3 text-center" colSpan={6} style={{ color: 'var(--color-text-secondary)' }}>
                                        Loading users...
                                    </td>
                                </tr>
                            ) : null}
                            {usersQuery.isError ? (
                                <tr>
                                    <td className="py-3 text-center" colSpan={6} style={{ color: 'var(--color-text-secondary)' }}>
                                        Failed to load users.
                                    </td>
                                </tr>
                            ) : null}
                            {!usersQuery.isPending && !usersQuery.isError && users.length === 0 ? (
                                <tr>
                                    <td className="py-3 text-center" colSpan={6} style={{ color: 'var(--color-text-tertiary)' }}>
                                        No users found.
                                    </td>
                                </tr>
                            ) : null}
                            {users.map((user) => {
                                const displayRole = toDisplayRole(user.role);
                                const displayStatus = user.status || 'Inactive';

                                return (
                                    <tr key={user.id}>
                                        <td>
                                            <div className="flex items-center gap-2">
                                                <span
                                                    className="inline-flex h-6 w-6 items-center justify-center rounded-full text-[10px] font-semibold"
                                                    style={{
                                                        backgroundColor: 'var(--color-background-secondary)',
                                                        color: 'var(--color-text-secondary)',
                                                    }}
                                                >
                                                    {initialsFromName(user.name)}
                                                </span>
                                                <span className="truncate">{user.name}</span>
                                            </div>
                                        </td>
                                        <td className="truncate">{user.email}</td>
                                        <td>
                                            <span className="inline-flex rounded-full border px-2 py-0.5 text-[10px] font-semibold" style={roleBadgeStyles[displayRole] ?? roleBadgeStyles['Support Admin']}>
                                                {displayRole}
                                            </span>
                                        </td>
                                        <td>
                                            <span className="inline-flex rounded-full border px-2 py-0.5 text-[10px] font-semibold" style={statusBadgeStyles[displayStatus] ?? statusBadgeStyles.Inactive}>
                                                {displayStatus}
                                            </span>
                                        </td>
                                        <td>{user.added_at || '—'}</td>
                                        <td>
                                            <button
                                                type="button"
                                                onClick={() => openEditDialog(user)}
                                                className="rounded-[var(--border-radius-md)] border px-2 py-1 text-[11px]"
                                                style={{ borderColor: 'var(--color-border-tertiary)' }}
                                            >
                                                Edit
                                            </button>
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
            </section>

            {editingUser ? (
                <section className="fixed inset-0 z-50 flex items-center justify-center bg-black/35 p-4">
                    <div className="central-card w-full max-w-[460px] space-y-3 p-4">
                        <header className="space-y-1">
                            <h2 className="text-[15px] font-semibold">Edit Admin Details</h2>
                            <p className="text-[12px]" style={{ color: 'var(--color-text-secondary)' }}>
                                Update the selected admin account information.
                            </p>
                        </header>

                        <label className="block space-y-1 text-[12px] font-medium">
                            <span>Name</span>
                            <input
                                type="text"
                                value={formValues.name}
                                onChange={(event) => setFormValues((previous) => ({ ...previous, name: event.target.value }))}
                                className="central-input h-9 w-full rounded-[var(--border-radius-md)] border px-3 text-[12px]"
                            />
                        </label>

                        <label className="block space-y-1 text-[12px] font-medium">
                            <span>Email</span>
                            <input
                                type="email"
                                value={formValues.email}
                                onChange={(event) => setFormValues((previous) => ({ ...previous, email: event.target.value }))}
                                className="central-input h-9 w-full rounded-[var(--border-radius-md)] border px-3 text-[12px]"
                            />
                        </label>

                        {formError ? (
                            <p className="text-[11px]" style={{ color: '#D85A30' }}>
                                {formError}
                            </p>
                        ) : null}

                        <div className="flex justify-end gap-2">
                            <button
                                type="button"
                                onClick={closeEditDialog}
                                disabled={updateUserMutation.isPending}
                                className="rounded-[var(--border-radius-md)] border px-3 py-2 text-[12px] font-medium disabled:opacity-60"
                                style={{ borderColor: 'var(--color-border-tertiary)' }}
                            >
                                Cancel
                            </button>
                            <button
                                type="button"
                                onClick={handleSaveUser}
                                disabled={isSaveDisabled}
                                className="central-button px-3 py-2 text-[12px] font-semibold disabled:opacity-60"
                            >
                                {updateUserMutation.isPending ? 'Saving...' : 'Save changes'}
                            </button>
                        </div>
                    </div>
                </section>
            ) : null}
        </div>
    );
}

