import { Head, Link, usePage } from '@inertiajs/react';
import AdminLayout from '../../components/Admin/AdminLayout';
import PageHeader from '../../components/Admin/PageHeader';
import StatusText from '../../components/Admin/StatusText';

export default function AdminIndex({
    activeCeremonyState,
    lastBackup,
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
    const blockers = [
        activeCeremonyState === 'conflict' && {
            label: 'Multiple ceremonies are active',
            detail: 'Scanner and stage operations require exactly one active ceremony.',
            href: '/admin/ceremonies',
        },
        activeCeremonyState === 'none' && {
            label: 'No ceremony is active',
            detail: 'Activate the ceremony before opening arrival scanning.',
            href: '/admin/ceremonies',
        },
        readiness?.needs_review > 0 && {
            label: `${readiness.needs_review} graduate records need eligibility review`,
            detail: 'Resolve eligibility before graduates arrive.',
            href: '/admin/students?eligibility=pending',
        },
        readiness?.audio_needs_review > 0 && {
            label: `${readiness.audio_needs_review} announcements need review`,
            detail: 'Approve or reject pronunciation audio before stage operation.',
            href: '/admin/students?media=audio_needs_review',
        },
        readiness?.audio_missing > 0 && {
            label: `${readiness.audio_missing} graduates have no announcement audio`,
            detail: 'Prepare audio or confirm the fallback procedure.',
            href: '/admin/students?media=missing_audio',
        },
    ].filter(Boolean);

    return (
        <AdminLayout>
            <Head title="Administration" />
            <PageHeader
                description="Resolve readiness exceptions, verify the active ceremony, and open the next preparation task."
                eyebrow="Overview"
                title="Ceremony readiness"
            />

            <section className="admin-blockers" aria-labelledby="blockers-heading">
                <div className="admin-section-heading">
                    <div>
                        <h2 id="blockers-heading">Action required</h2>
                        <p>
                            {blockers.length === 0
                                ? 'No blocking readiness issues were detected.'
                                : `${blockers.length} readiness issue${blockers.length === 1 ? '' : 's'} need attention.`}
                        </p>
                    </div>
                    <StatusText
                        label={blockers.length === 0 ? 'Ready' : 'Review required'}
                        value={blockers.length === 0 ? 'active' : 'warning'}
                    />
                </div>
                {blockers.length > 0 && (
                    <ol>
                        {blockers.map((blocker) => (
                            <li key={blocker.label}>
                                <span>
                                    <strong>{blocker.label}</strong>
                                    <small>{blocker.detail}</small>
                                </span>
                                <Link href={blocker.href}>Resolve</Link>
                            </li>
                        ))}
                    </ol>
                )}
            </section>

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
                                    <dt>Total graduates</dt>
                                    <dd>{readiness.total}</dd>
                                </div>
                                <div>
                                    <dt>Eligible</dt>
                                    <dd>{readiness.eligible}</dd>
                                </div>
                                <div>
                                    <dt>Waiting in queue</dt>
                                    <dd>{readiness.waiting}</dd>
                                </div>
                                <div>
                                    <dt>Presenting</dt>
                                    <dd>{readiness.presenting}</dd>
                                </div>
                                <div>
                                    <dt>Completed</dt>
                                    <dd>{readiness.completed}</dd>
                                </div>
                                <div className={readiness.needs_review > 0 ? 'is-warning' : undefined}>
                                    <dt>Needs eligibility review</dt>
                                    <dd>{readiness.needs_review}</dd>
                                </div>
                                <div>
                                    <dt>Audio approved</dt>
                                    <dd>{readiness.audio_approved}</dd>
                                </div>
                                <div className={readiness.audio_needs_review > 0 ? 'is-warning' : undefined}>
                                    <dt>Audio needs review</dt>
                                    <dd>
                                        <Link href="/admin/students?media=audio_needs_review">
                                            {readiness.audio_needs_review}
                                        </Link>
                                    </dd>
                                </div>
                                <div>
                                    <dt>Audio uploaded</dt>
                                    <dd>
                                        <Link href="/admin/students?media=audio_uploaded">
                                            {readiness.audio_uploaded}
                                        </Link>
                                    </dd>
                                </div>
                                <div className={readiness.audio_missing > 0 ? 'is-warning' : undefined}>
                                    <dt>Audio missing</dt>
                                    <dd>
                                        <Link href="/admin/students?media=missing_audio">
                                            {readiness.audio_missing}
                                        </Link>
                                    </dd>
                                </div>
                                <div className={readiness.audio_rejected > 0 ? 'is-danger' : undefined}>
                                    <dt>Audio rejected</dt>
                                    <dd>
                                        <Link href="/admin/students?media=audio_rejected">
                                            {readiness.audio_rejected}
                                        </Link>
                                    </dd>
                                </div>
                                <div className={readiness.missing_photo > 0 ? 'is-warning' : undefined}>
                                    <dt>Missing photo</dt>
                                    <dd>{readiness.missing_photo}</dd>
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
                <h2 id="tasks-heading">Operational tasks</h2>
                <div className="admin-task-links">
                    {abilities.view_ceremonies && (
                        <Link className="admin-task-link" href="/admin/ceremonies">
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
                        <Link className="admin-task-link" href="/admin/students">
                            <span>
                                <strong>Graduate records</strong>
                                <small>
                                    Search, validate, and prepare local presentation media.
                                </small>
                            </span>
                            <span aria-hidden="true">→</span>
                        </Link>
                    )}
                    {abilities.import_students && (
                        <Link className="admin-task-link" href="/admin/students/import">
                            <span>
                                <strong>Import graduate CSV</strong>
                                <small>
                                    Inspect, map, preview, and atomically create prepared
                                    records.
                                </small>
                            </span>
                            <span aria-hidden="true">→</span>
                        </Link>
                    )}
                    {abilities.manage_users && (
                        <Link className="admin-task-link" href="/admin/users">
                            <span>
                                <strong>Operator accounts</strong>
                                <small>
                                    Create local accounts, assign duties, and
                                    deactivate access safely.
                                </small>
                            </span>
                            <span aria-hidden="true">→</span>
                        </Link>
                    )}
                    {abilities.view_audit_logs && (
                        <Link className="admin-task-link" href="/admin/audit-logs">
                            <span>
                                <strong>Administrative audit history</strong>
                                <small>
                                    Review important ceremony, graduate, and media changes.
                                </small>
                            </span>
                            <span aria-hidden="true">→</span>
                        </Link>
                    )}
                    {abilities.view_operational_logs && (
                        <Link className="admin-task-link" href="/admin/scan-logs">
                            <span>
                                <strong>Operational logs</strong>
                                <small>
                                    Review scanner attempts and stage-event history with
                                    safe filters.
                                </small>
                            </span>
                            <span aria-hidden="true">→</span>
                        </Link>
                    )}
                    {abilities.view_ceremony_reports && (
                        <Link className="admin-task-link" href="/admin/reports/ceremony-summary">
                            <span>
                                <strong>Ceremony summary</strong>
                                <small>
                                    Review completion, readiness, media, and exception
                                    counts.
                                </small>
                            </span>
                            <span aria-hidden="true">→</span>
                        </Link>
                    )}
                    {abilities.view_backups && (
                        <Link className="admin-task-link" href="/admin/backups">
                            <span>
                                <strong>Backups and recovery</strong>
                                <small>
                                    Last backup:{' '}
                                    {lastBackup
                                        ? `${lastBackup.status} · ${lastBackup.verification_status || 'pending'}`
                                        : 'none recorded'}
                                </small>
                            </span>
                            <span aria-hidden="true">→</span>
                        </Link>
                    )}
                </div>
            </section>
        </AdminLayout>
    );
}
