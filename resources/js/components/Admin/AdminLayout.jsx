import { usePage } from '@inertiajs/react';
import AppShell from '../AppShell';

export default function AdminLayout({ children }) {
    const { flash } = usePage().props;

    return (
        <AppShell>
            {flash?.success && (
                <div className="admin-flash admin-flash-success" role="status">
                    {flash.success}
                </div>
            )}
            {flash?.warning && (
                <div className="admin-flash admin-flash-warning" role="status">
                    {flash.warning}
                </div>
            )}
            {children}
        </AppShell>
    );
}
