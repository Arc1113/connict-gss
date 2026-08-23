import { Head } from '@inertiajs/react';
import AdminLayout from '../../../components/Admin/AdminLayout';
import PageHeader from '../../../components/Admin/PageHeader';
import UserForm from '../../../components/Admin/UserForm';

export default function UserEdit({ managedUser, roleOptions }) {
    return (
        <AdminLayout mode="technical">
            <Head title={`Edit ${managedUser.name}`} />
            <PageHeader
                backHref="/admin/users"
                backLabel="Operator accounts"
                description="Change role, account status, or replace the local password. Every saved change is audited."
                eyebrow="Operator accounts"
                title={`Edit ${managedUser.name}`}
            />
            <UserForm
                action={`/admin/users/${managedUser.id}`}
                managedUser={managedUser}
                roleOptions={roleOptions}
                submitLabel="Save account"
            />
        </AdminLayout>
    );
}
