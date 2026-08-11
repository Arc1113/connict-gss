import { Head } from '@inertiajs/react';
import AdminLayout from '../../../components/Admin/AdminLayout';
import PageHeader from '../../../components/Admin/PageHeader';
import StudentForm from '../../../components/Admin/StudentForm';

export default function StudentEdit({ formOptions, student, workspace }) {
    return (
        <AdminLayout>
            <Head title={`Edit ${student.display_name}`} />
            <PageHeader
                backHref={workspace ? `${workspace.urls.graduates}/${student.id}` : `/admin/students/${student.id}`}
                backLabel="Graduate"
                description="Save reviewed changes and preview local media before ceremony use."
                eyebrow={workspace ? 'Ceremony workspace' : 'Graduate records'}
                title={`Edit ${student.display_name}`}
            />
            <StudentForm
                action={workspace?.locked ? `${workspace.urls.graduates}/${student.id}` : `/admin/students/${student.id}`}
                formOptions={formOptions}
                student={student}
                submitLabel="Save graduate"
                workspace={workspace}
            />
        </AdminLayout>
    );
}
