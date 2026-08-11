import { Head } from '@inertiajs/react';
import AdminLayout from '../../../components/Admin/AdminLayout';
import PageHeader from '../../../components/Admin/PageHeader';
import StudentForm from '../../../components/Admin/StudentForm';

export default function StudentCreate({ formOptions, workspace }) {
    return (
        <AdminLayout>
            <Head title="Add graduate" />
            <PageHeader
                backHref={workspace?.urls.graduates ?? '/admin/students'}
                backLabel="Graduates"
                description="Enter reviewed identity, academic, eligibility, and local media data."
                eyebrow={workspace ? 'Ceremony workspace' : 'Graduate records'}
                title="Add graduate"
            />
            <StudentForm
                action="/admin/students"
                formOptions={formOptions}
                submitLabel="Create graduate"
                workspace={workspace}
            />
        </AdminLayout>
    );
}
