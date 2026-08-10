import { Head } from '@inertiajs/react';
import AdminLayout from '../../../components/Admin/AdminLayout';
import CeremonyForm from '../../../components/Admin/CeremonyForm';
import PageHeader from '../../../components/Admin/PageHeader';

export default function CeremonyEdit({
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
            <Head title={`Edit ${ceremony.name}`} />
            <PageHeader
                description="Changes are audited and blocked while a graduate is actively presented."
                eyebrow="Ceremonies"
                title={`Edit ${ceremony.name}`}
            />
            <CeremonyForm
                action={`/admin/ceremonies/${ceremony.id}`}
                ceremony={formCeremony}
                presentationModeOptions={presentationModeOptions}
                stageDesignTemplates={stageDesignTemplates}
                statusOptions={statusOptions}
                submitLabel="Save ceremony"
            />
        </AdminLayout>
    );
}
