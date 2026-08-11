import { Head } from '@inertiajs/react';
import AdminLayout from '../../../components/Admin/AdminLayout';
import PageHeader from '../../../components/Admin/PageHeader';
import UserForm from '../../../components/Admin/UserForm';

export default function UserCreate({ roleOptions }) {
    return (
        <AdminLayout>
            <Head title="Add operator account" />
            <PageHeader
                backHref="/admin/users"
                backLabel="Operator accounts"
                description="Create one local account and assign only the role needed for ceremony duties."
                eyebrow="Operator accounts"
                title="Add operator account"
            />
            <UserForm
                action="/admin/users"
                roleOptions={roleOptions}
                submitLabel="Create account"
            />
        </AdminLayout>
    );
}
