import { Head, Link, usePage } from '@inertiajs/react';
import AdminLayout from '../../../components/Admin/AdminLayout';
import CeremonyContextHeader from '../../../components/Admin/CeremonyContextHeader';
import CeremonyWorkspaceNav from '../../../components/Admin/CeremonyWorkspaceNav';
import PreparationChecklist from '../../../components/Admin/PreparationChecklist';

export default function CeremonyShow({ ceremony, overview }) {
    const { auth, flash } = usePage().props;

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

            {flash?.ceremony_created && (
                <section aria-labelledby="ceremony-created-title" className="ceremony-created-panel">
                    <div>
                        <h2 id="ceremony-created-title">Your ceremony is ready to prepare</h2>
                        <p>Use guided setup if this is your first pass, or open the workspace and work at your own pace.</p>
                    </div>
                    <div className="ceremony-created-actions">
                        <Link className="admin-button admin-button-primary" href={`/admin/ceremonies/${ceremony.id}/guided-setup`}>
                            Start guided setup
                        </Link>
                        <Link className="admin-button admin-button-secondary" href={`/admin/ceremonies/${ceremony.id}`}>
                            Skip setup and open workspace
                        </Link>
                    </div>
                </section>
            )}

            <PreparationChecklist checklist={overview.checklist} />

            <section aria-labelledby="ceremony-day-actions-title" className="ceremony-workspace-panel ceremony-day-actions">
                <div className="admin-section-heading">
                    <div>
                        <h2 id="ceremony-day-actions-title">Ceremony day</h2>
                    </div>
                    <Link className="admin-button admin-button-secondary" href={`/admin/ceremonies/${ceremony.id}/run-ceremony`}>Readiness check</Link>
                </div>
                <div className="ceremony-day-actions-links">
                    {auth.abilities.operate_scanner && <Link className="admin-button admin-button-primary" href="/scanner">Open Scanner</Link>}
                    {auth.abilities.operate_stage && <Link className="admin-button admin-button-primary" href="/stage-control">Open Stage Control</Link>}
                    {auth.abilities.operate_stage && <a className="admin-button admin-button-secondary" href="/stage" target="_blank" rel="noreferrer">Open Stage Display</a>}
                </div>
            </section>
        </AdminLayout>
    );
}
