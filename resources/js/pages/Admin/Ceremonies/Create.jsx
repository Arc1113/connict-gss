import { Head } from '@inertiajs/react';
import AdminLayout from '../../../components/Admin/AdminLayout';
import CeremonyForm from '../../../components/Admin/CeremonyForm';
import PageHeader from '../../../components/Admin/PageHeader';

export default function CeremonyCreate({
    presentationModeOptions,
    statusOptions,
}) {
    return (
        <AdminLayout>
            <Head title="Add ceremony" />
            <PageHeader
                description="Create the ceremony record and choose its initial operational state."
                eyebrow="Ceremonies"
                title="Add ceremony"
            />
            <CeremonyForm
                action="/admin/ceremonies"
                presentationModeOptions={presentationModeOptions}
                statusOptions={statusOptions}
                submitLabel="Create ceremony"
            />
        </AdminLayout>
    );
}
