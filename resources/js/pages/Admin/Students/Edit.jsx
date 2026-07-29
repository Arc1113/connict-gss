import { Head } from '@inertiajs/react';
import AdminLayout from '../../../components/Admin/AdminLayout';
import PageHeader from '../../../components/Admin/PageHeader';
import StudentForm from '../../../components/Admin/StudentForm';

export default function StudentEdit({ formOptions, student }) {
    return (
        <AdminLayout>
            <Head title={`Edit ${student.display_name}`} />
            <PageHeader
                description="Save reviewed changes and preview local media before ceremony use."
                eyebrow="Graduate records"
                title={`Edit ${student.display_name}`}
            />
            <StudentForm
                action={`/admin/students/${student.id}`}
                formOptions={formOptions}
                student={student}
                submitLabel="Save graduate"
            />
        </AdminLayout>
    );
}
