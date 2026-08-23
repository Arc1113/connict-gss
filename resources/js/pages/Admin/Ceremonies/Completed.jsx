import { Head, Link } from '@inertiajs/react';
import AdminLayout from '../../../components/Admin/AdminLayout';

export default function CompletedCeremony({ ceremony, summary, urls }) {
    return (
        <AdminLayout>
            <Head title={`Completed · ${ceremony.name}`} />
            <header className="workspace-page-header">
                <div>
                    <p>Historical ceremony</p>
                    <h1>{ceremony.name}</h1>
                    <span>Completed {ceremony.completed_at || 'date not recorded'}</span>
                </div>
                <Link className="admin-button admin-button-secondary" href="/admin/ceremonies">All ceremonies</Link>
            </header>
            <section aria-labelledby="completed-summary-title" className="completed-ceremony-summary">
                <div>
                    <p className="completed-ceremony-kicker">Review workspace</p>
                    <h2 id="completed-summary-title">Ceremony completed</h2>
                    <p>Preparation controls are no longer the focus. Review the event record or open its report.</p>
                </div>
                <div className="completed-ceremony-actions">
                    <Link className="admin-button admin-button-primary" href={urls.reports}>Open report</Link>
                    <a className="admin-button admin-button-secondary" href={urls.export}>Export report</a>
                </div>
            </section>
            <dl className="completed-ceremony-metrics">
                <div><dt>Graduates</dt><dd>{summary.graduate_count}</dd></div>
                <div><dt>Announcements ready</dt><dd>{summary.announcements_ready}</dd></div>
                <div><dt>Scan activity</dt><dd>{summary.scan_events}</dd></div>
                <div><dt>Stage activity</dt><dd>{summary.stage_events}</dd></div>
            </dl>
            <section className="completed-ceremony-activity">
                <h2>Activity</h2>
                <p>The ceremony record, audit history, and operational report remain available according to your account permissions.</p>
                <Link href={urls.workspace}>Open ceremony record</Link>
            </section>
        </AdminLayout>
    );
}
