import { Head, Link } from '@inertiajs/react';
import AdminLayout from '../../../components/Admin/AdminLayout';
import CeremonyContextHeader from '../../../components/Admin/CeremonyContextHeader';
import CeremonyWorkspaceNav from '../../../components/Admin/CeremonyWorkspaceNav';

export default function WorkspaceBridge({ bridge, ceremony }) {
    return (
        <AdminLayout>
            <Head title={`${bridge.title} · ${ceremony.name}`} />

            <CeremonyContextHeader ceremony={ceremony} />
            <CeremonyWorkspaceNav ceremony={ceremony} />

            <section className="ceremony-workspace-panel">
                <div className="admin-section-heading">
                    <div>
                        <h2>{bridge.title}</h2>
                        <p>{bridge.message}</p>
                    </div>
                </div>

                <div className="ceremony-workspace-bridge-actions">
                    {bridge.actions.map((action) => (
                        <Link
                            className={`admin-button admin-button-${action.tone}`}
                            href={action.href}
                            key={action.href}
                        >
                            {action.label}
                        </Link>
                    ))}
                </div>
            </section>
        </AdminLayout>
    );
}
