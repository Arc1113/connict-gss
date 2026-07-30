import { Head, router } from '@inertiajs/react';
import AdminLayout from '../../../components/Admin/AdminLayout';
import EmptyState from '../../../components/Admin/EmptyState';
import PageHeader from '../../../components/Admin/PageHeader';
import Pagination from '../../../components/Admin/Pagination';
import StatusText from '../../../components/Admin/StatusText';

function formatDateTime(value) {
    if (!value) return 'Not recorded';

    return new Intl.DateTimeFormat('en-PH', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

function formatSize(value) {
    if (!value) return 'Not recorded';

    if (value < 1024 * 1024) {
        return `${Math.round(value / 1024)} KB`;
    }

    return `${(value / (1024 * 1024)).toFixed(1)} MB`;
}

export default function BackupIndex({ canCreate, destinationLabel, records }) {
    function createBackup() {
        router.post('/admin/backups', {}, { preserveScroll: true });
    }

    function verifyBackup(record) {
        router.post(`/admin/backups/${record.id}/verify`, {}, { preserveScroll: true });
    }

    return (
        <AdminLayout>
            <Head title="Backups" />
            <PageHeader
                actions={
                    canCreate && (
                        <button className="admin-button admin-button-primary" onClick={createBackup} type="button">
                            Create local backup
                        </button>
                    )
                }
                description={`Local PostgreSQL and media backups. Destination label: ${destinationLabel}.`}
                eyebrow="Operational recovery"
                title="Backups"
            />

            <section className="admin-detail-section">
                <h2>Restore safety</h2>
                <p>
                    Browser restore is intentionally unavailable. Use the documented
                    CLI restore-verification workflow before any destructive restore
                    escalation.
                </p>
                <p>
                    Run <code>php artisan gss:restore-verify --backup=/path/to/backup</code>{' '}
                    to test a backup in a disposable PostgreSQL database.
                </p>
            </section>

            {records.data.length === 0 ? (
                <EmptyState title="No backup records">
                    Create a local backup from this page or the approved CLI command.
                </EmptyState>
            ) : (
                <>
                    <div className="admin-table-wrap">
                        <table className="admin-table">
                            <caption className="sr-only">Local backup records</caption>
                            <thead>
                                <tr>
                                    <th scope="col">Started</th>
                                    <th scope="col">Backup</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Verification</th>
                                    <th scope="col">Size</th>
                                    <th scope="col">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                {records.data.map((record) => (
                                    <tr key={record.id}>
                                        <td>{formatDateTime(record.started_at)}</td>
                                        <td>
                                            <strong>{record.identifier}</strong>
                                            <small className="admin-table-note">
                                                {record.path_label}
                                            </small>
                                            {record.failure_summary && (
                                                <small className="admin-table-note">
                                                    {record.failure_summary}
                                                </small>
                                            )}
                                        </td>
                                        <td>
                                            <StatusText label={record.status} value={record.status} />
                                        </td>
                                        <td>
                                            <StatusText
                                                label={record.verification_status || 'pending'}
                                                value={record.verification_status || 'pending'}
                                            />
                                        </td>
                                        <td>{formatSize(record.file_size)}</td>
                                        <td>
                                            <button
                                                className="admin-button admin-button-secondary"
                                                disabled={record.status !== 'completed'}
                                                onClick={() => verifyBackup(record)}
                                                type="button"
                                            >
                                                Verify restore
                                            </button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <Pagination collection={records} itemLabel="backup records" />
                </>
            )}
        </AdminLayout>
    );
}
