import { usePage } from '@inertiajs/react';
import AppShell from '../AppShell';

export default function AdminLayout({ children, mode = 'preparation' }) {
    const { flash } = usePage().props;

    return (
        <AppShell mode={mode}>
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
