import { Head, Link } from '@inertiajs/react';
import AdminLayout from '../../../components/Admin/AdminLayout';
import EmptyState from '../../../components/Admin/EmptyState';
import PageHeader from '../../../components/Admin/PageHeader';
import Pagination from '../../../components/Admin/Pagination';

function formatDateTime(value) {
    return new Intl.DateTimeFormat('en-PH', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

function ChangeValues({ title, values }) {
    if (!values || Object.keys(values).length === 0) {
        return null;
    }

    return (
        <div>
            <dt>{title}</dt>
            <dd>
                {Object.entries(values).map(([field, value]) => (
                    <span className="admin-audit-value" key={field}>
                        <strong>{field.replaceAll('_', ' ')}</strong>
                        {Array.isArray(value)
                            ? value.join(', ') || 'None'
                            : String(value ?? 'Not set')}
                    </span>
                ))}
            </dd>
        </div>
    );
}

export default function AuditLogIndex({ filters, logs }) {
    const hasFilters = Object.values(filters).some(Boolean);

    return (
        <AdminLayout>
            <Head title="Administrative audit history" />
            <PageHeader
                description="Important local data changes with safe, redacted values."
                eyebrow="Administrator only"
                title="Administrative audit history"
            />

            <form action="/admin/audit-logs" className="admin-filters" method="get">
                <div className="admin-filter-search">
                    <label htmlFor="action">Action contains</label>
                    <input
                        defaultValue={filters.action}
                        id="action"
                        name="action"
                        placeholder="student.updated"
                        type="search"
                    />
                </div>
                <div>
                    <label htmlFor="entity_type">Record type</label>
                    <select
                        defaultValue={filters.entity_type}
                        id="entity_type"
                        name="entity_type"
                    >
                        <option value="">All record types</option>
                        <option value="ceremony">Ceremony</option>
                        <option value="student">Graduate</option>
                    </select>
                </div>
                <div className="admin-filter-actions">
                    <button className="admin-button admin-button-primary" type="submit">
                        Apply filters
                    </button>
                    {hasFilters && (
                        <Link
                            className="admin-button admin-button-secondary"
                            href="/admin/audit-logs"
                        >
                            Clear
                        </Link>
                    )}
                </div>
            </form>

            {logs.data.length === 0 ? (
                <EmptyState title="No audit entries">
                    {hasFilters
                        ? 'No important changes match the current filters.'
                        : 'Important Phase 4 changes will appear here.'}
                </EmptyState>
            ) : (
                <>
                    <div className="admin-table-wrap">
                        <table className="admin-table">
                            <caption className="sr-only">
                                Administrative audit history matching the current filters
                            </caption>
                            <thead>
                                <tr>
                                    <th scope="col">Time</th>
                                    <th scope="col">Actor</th>
                                    <th scope="col">Action</th>
                                    <th scope="col">Record</th>
                                    <th scope="col">Safe change summary</th>
                                </tr>
                            </thead>
                            <tbody>
                                {logs.data.map((log) => (
                                    <tr key={log.id}>
                                        <td>{formatDateTime(log.created_at)}</td>
                                        <td>{log.actor}</td>
                                        <td>
                                            <code>{log.action}</code>
                                        </td>
                                        <td>
                                            {log.entity_type} #{log.entity_id ?? '—'}
                                        </td>
                                        <td>
                                            <details className="admin-audit-details">
                                                <summary>Review changes</summary>
                                                <dl>
                                                    <ChangeValues
                                                        title="Before"
                                                        values={log.old_values}
                                                    />
                                                    <ChangeValues
                                                        title="After"
                                                        values={log.new_values}
                                                    />
                                                </dl>
                                            </details>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <Pagination collection={logs} itemLabel="audit entries" />
                </>
            )}
        </AdminLayout>
    );
}
