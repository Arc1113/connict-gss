import { Head, Link } from '@inertiajs/react';
import AdminLayout from '../../../components/Admin/AdminLayout';
import CeremonyContextHeader from '../../../components/Admin/CeremonyContextHeader';
import CeremonyWorkspaceNav from '../../../components/Admin/CeremonyWorkspaceNav';

function formatDateTime(value) {
    if (!value) {
        return 'Not completed';
    }

    return new Intl.DateTimeFormat('en-PH', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

export default function CeremonyReadiness({ ceremony, readiness }) {
    return (
        <AdminLayout>
            <Head title={`Readiness · ${ceremony.name}`} />

            <CeremonyContextHeader ceremony={ceremony} />

            <CeremonyWorkspaceNav ceremony={ceremony} />

            <section className="ceremony-workspace-panel">
                <div className="admin-section-heading">
                    <div>
                        <h2>Readiness</h2>
                    </div>
                </div>

                {readiness.blockers.length === 0 ? (
                    <p className="admin-inline-empty">
                        No blocking readiness issues are currently flagged.
                    </p>
                ) : (
                    <ol className="ceremony-workspace-blockers">
                        {readiness.blockers.map((blocker) => (
                            <li key={blocker.label}>
                                <span>
                                    <strong>{blocker.label}</strong>
                                    <small>{blocker.detail}</small>
                                </span>
                                <Link href={blocker.href}>Review</Link>
                            </li>
                        ))}
                    </ol>
                )}
            </section>

            <section className="ceremony-workspace-panel">
                <div className="admin-section-heading">
                    <div>
                        <h2 className="sr-only">Preparation areas</h2>
                    </div>
                </div>

                <div className="readiness-task-links">
                    {readiness.sections.map((section) => (
                        <Link
                            className="admin-task-link"
                            href={section.action_href}
                            key={section.title}
                        >
                            <span>
                                <strong>{section.title}</strong>
                                <small>{section.detail}</small>
                            </span>
                            <span aria-hidden="true">{section.action_label}</span>
                        </Link>
                    ))}
                </div>
            </section>

            <details className="ceremony-workspace-panel readiness-backup">
                <summary>Backup status</summary>
                <div className="admin-section-heading">
                    <div>
                        <h2>Latest backup</h2>
                    </div>
                    <Link
                        className="admin-button admin-button-secondary"
                        href="/admin/backups"
                    >
                        Open backups
                    </Link>
                </div>

                {readiness.last_backup ? (
                    <dl className="admin-detail-list admin-detail-list-compact">
                        <div>
                            <dt>Latest backup</dt>
                            <dd>{readiness.last_backup.identifier}</dd>
                        </div>
                        <div>
                            <dt>Status</dt>
                            <dd>{readiness.last_backup.status}</dd>
                        </div>
                        <div>
                            <dt>Verification</dt>
                            <dd>{readiness.last_backup.verification_status}</dd>
                        </div>
                        <div>
                            <dt>Completed</dt>
                            <dd>{formatDateTime(readiness.last_backup.completed_at)}</dd>
                        </div>
                    </dl>
                ) : (
                    <p className="admin-inline-empty">
                        No local backup has been recorded yet.
                    </p>
                )}
            </details>
        </AdminLayout>
    );
}
