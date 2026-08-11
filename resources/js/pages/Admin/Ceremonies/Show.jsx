import { Head, Link, usePage } from '@inertiajs/react';
import AdminLayout from '../../../components/Admin/AdminLayout';
import CeremonyContextHeader from '../../../components/Admin/CeremonyContextHeader';
import CeremonyWorkspaceNav from '../../../components/Admin/CeremonyWorkspaceNav';

function announcementSummary(summary) {
    const parts = [`${summary.announcements_ready} ready`];

    if (summary.announcements_needs_review > 0) {
        parts.push(`${summary.announcements_needs_review} review`);
    }

    if (summary.announcements_missing > 0) {
        parts.push(`${summary.announcements_missing} missing`);
    }

    if (summary.announcements_rejected > 0) {
        parts.push(`${summary.announcements_rejected} rejected`);
    }

    return parts.join(' / ');
}

export default function CeremonyShow({ ceremony, overview }) {
    const { auth } = usePage().props;
    const summary = overview.summary;
    const nextAction = overview.next_action;

    return (
        <AdminLayout>
            <Head title={ceremony.name} />

            <CeremonyContextHeader
                actions={
                    <>
                        {auth.abilities.manage_ceremonies && (
                            <Link
                                className="admin-button admin-button-primary"
                                href={`/admin/ceremonies/${ceremony.id}/setup`}
                            >
                                Open setup
                            </Link>
                        )}
                        <Link
                            className="admin-button admin-button-secondary"
                            href="/admin/ceremonies"
                        >
                            Manage ceremonies
                        </Link>
                    </>
                }
                ceremony={ceremony}
            />

            <CeremonyWorkspaceNav ceremony={ceremony} />

            {nextAction ? (
                <section className="ceremony-workspace-panel">
                    <div className="admin-section-heading">
                        <div>
                            <h2>Next action</h2>
                            <p>Continue the most urgent preparation task for this ceremony.</p>
                        </div>
                    </div>
                    <div className="ceremony-workspace-next-action">
                        <div>
                            <strong>{nextAction.label}</strong>
                            <p>{nextAction.detail}</p>
                        </div>
                        <Link className="admin-button admin-button-primary" href={nextAction.href}>
                            Continue
                        </Link>
                    </div>
                </section>
            ) : null}

            <section className="ceremony-workspace-panel">
                <div className="admin-section-heading">
                    <div>
                        <h2>Needs attention</h2>
                        <p>
                            {overview.blockers.length === 0
                                ? 'No immediate preparation blockers were detected from the current ceremony data.'
                                : 'Resolve these concrete issues before moving into live ceremony operations.'}
                        </p>
                    </div>
                </div>

                {overview.blockers.length === 0 ? (
                    <p className="admin-inline-empty">
                        No graduate, photo, eligibility, or announcement blockers are currently flagged.
                    </p>
                ) : (
                    <ol className="ceremony-workspace-blockers">
                        {overview.blockers.map((blocker) => (
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
                        <h2>Preparation status</h2>
                        <p>Counts reflect the current records for this ceremony only.</p>
                    </div>
                </div>

                <dl className="ceremony-workspace-status-list">
                    <div>
                        <dt>Graduate records</dt>
                        <dd>{summary.graduates_total}</dd>
                    </div>
                    <div className={summary.eligibility_issues > 0 ? 'is-warning' : ''}>
                        <dt>Eligibility</dt>
                        <dd>
                            {summary.eligibility_ready} ready / {summary.eligibility_issues} issues
                        </dd>
                    </div>
                    <div className={summary.photos_missing > 0 ? 'is-warning' : ''}>
                        <dt>Photos</dt>
                        <dd>
                            {summary.photos_ready} ready / {summary.photos_missing} missing
                        </dd>
                    </div>
                    <div className={summary.qr_missing > 0 ? 'is-warning' : ''}>
                        <dt>QR</dt>
                        <dd>
                            {summary.qr_ready} ready / {summary.qr_missing} missing
                        </dd>
                    </div>
                    <div
                        className={
                            summary.announcements_needs_review > 0
                            || summary.announcements_missing > 0
                            || summary.announcements_rejected > 0
                                ? 'is-warning'
                                : ''
                        }
                    >
                        <dt>Announcements</dt>
                        <dd>{announcementSummary(summary)}</dd>
                    </div>
                </dl>
            </section>

            <section className="ceremony-workspace-panel">
                <div className="admin-section-heading">
                    <div>
                        <h2>Ceremony</h2>
                        <p>Configuration remains authoritative in the existing ceremony setup workflow.</p>
                    </div>
                </div>

                <dl className="admin-detail-list">
                    <div>
                        <dt>Event date</dt>
                        <dd>{overview.details.event_date || 'Not set'}</dd>
                    </div>
                    <div>
                        <dt>Venue</dt>
                        <dd>{overview.details.venue || 'Not set'}</dd>
                    </div>
                    <div>
                        <dt>Presentation mode</dt>
                        <dd>{overview.details.presentation_mode}</dd>
                    </div>
                    <div>
                        <dt>Live status</dt>
                        <dd>{overview.details.live_status}</dd>
                    </div>
                    <div>
                        <dt>Activated at</dt>
                        <dd>{ceremony.activated_at || 'Not activated'}</dd>
                    </div>
                    <div>
                        <dt>Completed at</dt>
                        <dd>{ceremony.completed_at || 'Not completed'}</dd>
                    </div>
                </dl>
            </section>
        </AdminLayout>
    );
}
