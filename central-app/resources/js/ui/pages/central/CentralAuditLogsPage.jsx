import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { fetchCentralAuditLogs } from '../../../api/centralApi';

const typeStyles = {
    success: { backgroundColor: '#1D9E75', borderColor: '#1D9E75' },
    danger: { backgroundColor: '#D85A30', borderColor: '#D85A30' },
    info: { backgroundColor: '#378ADD', borderColor: '#378ADD' },
    warning: { backgroundColor: '#EF9F27', borderColor: '#EF9F27' },
};

export function CentralAuditLogsPage() {
    const [search, setSearch] = useState('');
    const [actionType, setActionType] = useState('all');
    const [actor, setActor] = useState('all');

    const logsQuery = useQuery({
        queryKey: ['central-audit-logs', { search, actionType, actor }],
        queryFn: () =>
            fetchCentralAuditLogs({
                search,
                type: actionType === 'all' ? '' : actionType,
                actor: actor === 'all' ? '' : actor,
            }),
        staleTime: 1000 * 15,
    });

    const logs = logsQuery.data?.entries ?? [];
    const availableUsers = logsQuery.data?.available_users ?? [];
    const total = logsQuery.data?.total ?? logs.length;

    return (
        <div className="space-y-4 pb-2">
            <section className="central-card p-4">
                <div className="flex flex-wrap items-center gap-2">
                    <input
                        type="search"
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder="Search audit log"
                        className="central-input h-9 min-w-[220px] flex-1 rounded-[var(--border-radius-md)] border px-3 text-[12px]"
                    />
                    <select
                        value={actionType}
                        onChange={(event) => setActionType(event.target.value)}
                        className="central-input h-9 rounded-[var(--border-radius-md)] border px-2.5 text-[12px]"
                    >
                        <option value="all">All actions</option>
                        <option value="success">success</option>
                        <option value="danger">danger</option>
                        <option value="info">info</option>
                        <option value="warning">warning</option>
                    </select>
                    <select
                        value={actor}
                        onChange={(event) => setActor(event.target.value)}
                        className="central-input h-9 rounded-[var(--border-radius-md)] border px-2.5 text-[12px]"
                    >
                        <option value="all">All users</option>
                        {availableUsers.map((user) => (
                            <option key={user} value={user}>
                                {user}
                            </option>
                        ))}
                    </select>
                </div>
            </section>

            <section className="central-card p-4">
                <div className="relative ml-2 border-l pl-4" style={{ borderColor: 'var(--color-border-tertiary)' }}>
                    {logsQuery.isPending ? (
                        <p className="text-[11px]" style={{ color: 'var(--color-text-secondary)' }}>
                            Loading audit logs...
                        </p>
                    ) : null}
                    {logsQuery.isError ? (
                        <p className="text-[11px]" style={{ color: 'var(--color-text-secondary)' }}>
                            Failed to load audit logs.
                        </p>
                    ) : null}
                    {logs.map((entry) => (
                        <article key={entry.id} className="relative mb-4 flex justify-between gap-3 last:mb-0">
                            <span className="absolute -left-[22px] top-1 inline-block h-3 w-3 rounded-full border" style={typeStyles[entry.type] ?? typeStyles.info} />
                            <div className="min-w-0">
                                <p className="text-[12px] font-semibold">{entry.action}</p>
                                <p className="text-[11px]" style={{ color: 'var(--color-text-secondary)' }}>
                                    {entry.detail}
                                </p>
                                <p className="mt-1 text-[10px]" style={{ color: 'var(--color-text-tertiary)' }}>
                                    {entry.user}
                                </p>
                            </div>
                            <span className="whitespace-nowrap text-[10px]" style={{ color: 'var(--color-text-tertiary)' }}>
                                {entry.timestamp ? String(entry.timestamp).replace('T', ' ').slice(0, 16) : '—'}
                            </span>
                        </article>
                    ))}
                    {!logsQuery.isPending && !logsQuery.isError && logs.length === 0 ? (
                        <p className="text-[11px]" style={{ color: 'var(--color-text-tertiary)' }}>
                            No audit entries found.
                        </p>
                    ) : null}
                </div>
                <p className="mt-3 text-[11px]" style={{ color: 'var(--color-text-tertiary)' }}>
                    Showing {logs.length} of {total} entries
                </p>
            </section>
        </div>
    );
}

