import { Head, Link } from '@inertiajs/react';
import { usePage } from '@inertiajs/react';
import AdminLayout from '../../components/Admin/AdminLayout';

export default function RunCeremony({ ceremony, preparation, actions }) {
    const readyCount = preparation.steps.filter((step) => step.status === 'ready').length;
    const { auth } = usePage().props;

    return (
        <AdminLayout>
            <Head title={`Run ceremony · ${ceremony.name}`} />
            <header className="workspace-page-header">
                <div>
                    <p>Working ceremony</p>
                    <h1>Run ceremony</h1>
                    <span>{ceremony.name} · {ceremony.status}</span>
                </div>
                <Link className="admin-button admin-button-secondary" href={`/admin/ceremonies/${ceremony.id}`}>
                    Back to workspace
                </Link>
            </header>
            <section aria-labelledby="run-readiness-title" className="run-ceremony-readiness">
                <div>
                    <p className="run-ceremony-kicker">Readiness summary</p>
                    <h2 id="run-readiness-title">{preparation.ready ? 'Ready for a final operational check' : 'Preparation still needs attention'}</h2>
                    <p>{readyCount} of {preparation.steps.length} preparation areas are ready. Opening an operational screen does not change ceremony state.</p>
                </div>
                <Link className="admin-button admin-button-secondary" href={`/admin/ceremonies/${ceremony.id}/preparation`}>Review preparation</Link>
            </section>
            <section aria-labelledby="run-actions-title" className="run-ceremony-actions">
                <h2 id="run-actions-title">Open ceremony operations</h2>
                <div>
                    {actions.scanner && <Link className="admin-button admin-button-primary" href={actions.scanner}>Open Scanner</Link>}
                    {actions.stage_control && <Link className="admin-button admin-button-primary" href={actions.stage_control}>Open Stage Control</Link>}
                    {actions.stage_display && <a className="admin-button admin-button-secondary" href={actions.stage_display} target="_blank" rel="noreferrer">Open Stage Display</a>}
                    {!actions.scanner && !actions.stage_control && !actions.stage_display && <p className="admin-inline-empty">Your account is not authorized for ceremony-day operations.</p>}
                </div>
            </section>
            {auth.abilities.manage_ceremonies && <p className="run-ceremony-note">Live ceremony activation remains an explicit administrator action in ceremony setup.</p>}
        </AdminLayout>
    );
}
