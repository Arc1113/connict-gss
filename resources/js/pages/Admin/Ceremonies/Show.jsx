import { Head, Link, router, usePage } from '@inertiajs/react';
import AdminLayout from '../../../components/Admin/AdminLayout';
import { ValidationSummary } from '../../../components/Admin/FormSupport';
import PageHeader from '../../../components/Admin/PageHeader';
import StatusText from '../../../components/Admin/StatusText';

export default function CeremonyShow({ ceremony }) {
    const { auth, errors } = usePage().props;

    function deleteCeremony() {
        if (
            window.confirm(
                `Delete "${ceremony.name}"? Only unused preparation records can be deleted.`,
            )
        ) {
            router.delete(`/admin/ceremonies/${ceremony.id}`, {
                preserveScroll: true,
            });
        }
    }

    return (
        <AdminLayout>
            <Head title={ceremony.name} />
            <PageHeader
                actions={
                    <>
                        <Link
                            className="admin-button admin-button-secondary"
                            href={`/admin/students?ceremony_id=${ceremony.id}`}
                        >
                            View graduates
                        </Link>
                        {auth.abilities.manage_ceremonies && (
                            <Link
                                className="admin-button admin-button-primary"
                                href={`/admin/ceremonies/${ceremony.id}/edit`}
                            >
                                Edit ceremony
                            </Link>
                        )}
                        {auth.abilities.manage_announcement_settings && (
                            <Link
                                className="admin-button admin-button-secondary"
                                href={`/admin/ceremonies/${ceremony.id}/announcement-settings`}
                            >
                                Announcement settings
                            </Link>
                        )}
                        {auth.abilities.manage_announcement_batches && (
                            <Link
                                className="admin-button admin-button-secondary"
                                href={`/admin/ceremonies/${ceremony.id}/announcement-batches`}
                            >
                                Announcement batches
                            </Link>
                        )}
                    </>
                }
                description="Ceremony configuration and protected operational history."
                eyebrow="Ceremonies"
                title={ceremony.name}
            />

            <ValidationSummary errors={errors} />

            <section className="admin-detail-section">
                <div className="admin-section-heading">
                    <h2>Configuration</h2>
                    <StatusText
                        label={ceremony.status.label}
                        value={ceremony.status.value}
                    />
                </div>
                <dl className="admin-detail-list">
                    <div>
                        <dt>Academic year</dt>
                        <dd>{ceremony.academic_year || 'Not set'}</dd>
                    </div>
                    <div>
                        <dt>Event date</dt>
                        <dd>{ceremony.event_date || 'Not set'}</dd>
                    </div>
                    <div>
                        <dt>Venue</dt>
                        <dd>{ceremony.venue || 'Not set'}</dd>
                    </div>
                    <div>
                        <dt>Presentation mode</dt>
                        <dd>{ceremony.presentation_mode.label}</dd>
                    </div>
                    <div>
                        <dt>Activated at</dt>
                        <dd>{ceremony.activated_at || 'Not activated'}</dd>
                    </div>
                    <div>
                        <dt>Completed at</dt>
                        <dd>{ceremony.completed_at || 'Not completed'}</dd>
                    </div>
                </dl>
            </section>

            <section className="admin-detail-section">
                <h2>Operational history protection</h2>
                <dl className="admin-detail-list admin-detail-list-compact">
                    <div>
                        <dt>Graduate records</dt>
                        <dd>{ceremony.students_count}</dd>
                    </div>
                    <div>
                        <dt>Queue items</dt>
                        <dd>{ceremony.queue_items_count}</dd>
                    </div>
                    <div>
                        <dt>Scan logs</dt>
                        <dd>{ceremony.scan_logs_count}</dd>
                    </div>
                    <div>
                        <dt>Stage events</dt>
                        <dd>{ceremony.stage_events_count}</dd>
                    </div>
                    <div>
                        <dt>Stage state</dt>
                        <dd>
                            {ceremony.has_stage_state
                                ? `${ceremony.stage_mode} · version ${ceremony.stage_version}`
                                : 'Not initialized'}
                        </dd>
                    </div>
                </dl>
                <p className="admin-form-note">
                    Any graduate or operational history protects this ceremony from
                    deletion.
                </p>
            </section>

            {auth.abilities.manage_ceremonies && (
                <section className="admin-danger-zone">
                    <div>
                        <h2>Delete unused ceremony</h2>
                        <p>
                            Available only for a non-operational ceremony with no
                            graduate, stage, queue, scan, or event records.
                        </p>
                    </div>
                    <button
                        className="admin-button admin-button-danger"
                        onClick={deleteCeremony}
                        type="button"
                    >
                        Delete ceremony
                    </button>
                </section>
            )}
        </AdminLayout>
    );
}
