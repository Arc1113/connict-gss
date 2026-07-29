import { Head, Link, usePage } from '@inertiajs/react';
import AdminLayout from '../../components/Admin/AdminLayout';
import PageHeader from '../../components/Admin/PageHeader';
import StatusText from '../../components/Admin/StatusText';

export default function AdminIndex({
    activeCeremonyState,
    referenceCeremony,
    readiness,
}) {
    const { auth } = usePage().props;
    const abilities = auth.abilities;
    const activeStatus = {
        none: ['No active ceremony', 'warning'],
        ready: ['One active ceremony', 'active'],
        conflict: ['Activation conflict', 'conflict'],
    }[activeCeremonyState];

    return (
        <AdminLayout>
            <Head title="Administration" />
            <PageHeader
                description="Review ceremony readiness and open the data task that needs attention."
                eyebrow="Phase 4"
                title="Ceremony preparation"
            />

            <section
                aria-labelledby="current-ceremony-heading"
                className="admin-readiness"
            >
                <div className="admin-section-heading">
                    <div>
                        <h2 id="current-ceremony-heading">Current ceremony context</h2>
                        <StatusText label={activeStatus[0]} value={activeStatus[1]} />
                    </div>
                    {referenceCeremony && (
                        <Link href={`/admin/ceremonies/${referenceCeremony.id}`}>
                            Review ceremony
                        </Link>
                    )}
                </div>

                {referenceCeremony ? (
                    <>
                        <div className="admin-current-ceremony">
                            <strong>{referenceCeremony.name}</strong>
                            <span>
                                {referenceCeremony.academic_year ||
                                    'Academic year not set'}
                                {' · '}
                                {referenceCeremony.event_date ||
                                    'Event date not set'}
                            </span>
                        </div>

                        {readiness && (
                            <dl className="admin-readiness-list">
                                <div>
                                    <dt>Graduate records</dt>
                                    <dd>{readiness.total}</dd>
                                </div>
                                <div>
                                    <dt>Eligible</dt>
                                    <dd>{readiness.eligible}</dd>
                                </div>
                                <div>
                                    <dt>Needs eligibility review</dt>
                                    <dd>{readiness.needs_review}</dd>
                                </div>
                                <div>
                                    <dt>Missing approved photo</dt>
                                    <dd>{readiness.missing_photo}</dd>
                                </div>
                                <div>
                                    <dt>Missing announcement audio</dt>
                                    <dd>{readiness.missing_audio}</dd>
                                </div>
                            </dl>
                        )}
                    </>
                ) : (
                    <p className="admin-inline-empty">
                        No ceremony exists yet. An administrator must create the first
                        ceremony before graduate records can be assigned.
                    </p>
                )}

                {activeCeremonyState === 'conflict' && (
                    <p className="admin-blocking-message" role="alert">
                        More than one ceremony is active. Scanner operations remain
                        safely blocked until an administrator corrects the statuses.
                    </p>
                )}
            </section>

            <section aria-labelledby="tasks-heading" className="admin-task-list">
                <h2 id="tasks-heading">Preparation tasks</h2>
                {abilities.view_ceremonies && (
                    <Link href="/admin/ceremonies">
                        <span>
                            <strong>Ceremonies</strong>
                            <small>
                                Review identity, schedule, mode, and lifecycle status.
                            </small>
                        </span>
                        <span aria-hidden="true">→</span>
                    </Link>
                )}
                {abilities.view_students && (
                    <Link href="/admin/students">
                        <span>
                            <strong>Graduate records</strong>
                            <small>
                                Search, validate, and prepare local presentation media.
                            </small>
                        </span>
                        <span aria-hidden="true">→</span>
                    </Link>
                )}
                {abilities.view_audit_logs && (
                    <Link href="/admin/audit-logs">
                        <span>
                            <strong>Administrative audit history</strong>
                            <small>
                                Review important ceremony, graduate, and media changes.
                            </small>
                        </span>
                        <span aria-hidden="true">→</span>
                    </Link>
                )}
            </section>
        </AdminLayout>
    );
}
