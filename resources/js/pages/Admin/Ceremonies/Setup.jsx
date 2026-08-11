import { Head, Link } from '@inertiajs/react';
import AdminLayout from '../../../components/Admin/AdminLayout';
import CeremonyContextHeader from '../../../components/Admin/CeremonyContextHeader';
import CeremonyForm from '../../../components/Admin/CeremonyForm';
import CeremonyWorkspaceNav from '../../../components/Admin/CeremonyWorkspaceNav';

export default function CeremonySetup({
    ceremony,
    presentationModeOptions,
    stageDesignTemplates,
    statusOptions,
}) {
    const formCeremony = {
        ...ceremony,
        status: ceremony.status.value,
        presentation_mode: ceremony.presentation_mode.value,
    };

    return (
        <AdminLayout>
            <Head title={`Setup · ${ceremony.name}`} />

            <CeremonyContextHeader
                actions={
                    <>
                        <Link
                            className="admin-button admin-button-secondary"
                            href={`/admin/ceremonies/${ceremony.id}`}
                        >
                            ← Ceremony overview
                        </Link>
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

            <section className="ceremony-workspace-panel">
                <div className="admin-section-heading">
                    <div>
                        <h2>Setup</h2>
                        <p>
                            Ceremony identity, lifecycle status, presentation mode, and stage appearance remain
                            audited and protected by the existing ceremony rules.
                        </p>
                    </div>
                </div>

                <CeremonyForm
                    action={`/admin/ceremonies/${ceremony.id}`}
                    ceremony={formCeremony}
                    presentationModeOptions={presentationModeOptions}
                    stageDesignTemplates={stageDesignTemplates}
                    statusOptions={statusOptions}
                    submitLabel="Save ceremony"
                />
            </section>
        </AdminLayout>
    );
}
