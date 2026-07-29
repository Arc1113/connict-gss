import { Head } from '@inertiajs/react';
import AdminLayout from '../../../components/Admin/AdminLayout';
import PageHeader from '../../../components/Admin/PageHeader';
import StudentForm from '../../../components/Admin/StudentForm';

export default function StudentCreate({ formOptions }) {
    return (
        <AdminLayout>
            <Head title="Add graduate" />
            <PageHeader
                description="Enter reviewed identity, academic, eligibility, and local media data."
                eyebrow="Graduate records"
                title="Add graduate"
            />
            <StudentForm
                action="/admin/students"
                formOptions={formOptions}
                submitLabel="Create graduate"
            />
        </AdminLayout>
    );
}
