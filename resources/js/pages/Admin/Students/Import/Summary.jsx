import { Head, Link } from '@inertiajs/react';
import AdminLayout from '../../../../components/Admin/AdminLayout';
import ImportProgress from '../../../../components/Admin/ImportProgress';
import PageHeader from '../../../../components/Admin/PageHeader';

function formatDateTime(value) {
    return new Intl.DateTimeFormat('en-PH', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

export default function StudentImportSummary({
    graduateListUrl,
    summary,
}) {
    const modeLabel =
        summary.mode === 'create_only'
            ? 'Create new graduates only'
            : summary.mode;

    return (
        <AdminLayout>
            <Head title="Graduate import complete" />
            <PageHeader
                description="The reviewed rows were committed together to PostgreSQL."
                eyebrow="Graduate import"
                title="Import complete"
            />
            <ImportProgress currentStep={4} />

            <section
                aria-labelledby="import-summary-title"
                className="admin-import-summary"
            >
                <div className="admin-import-summary-heading">
                    <div aria-hidden="true" className="admin-import-summary-mark">
                        ✓
                    </div>
                    <div>
                        <h2 id="import-summary-title">
                            {summary.created_count.toLocaleString()} graduate records
                            created
                        </h2>
                        <p>
                            {summary.ceremony_name}. Existing graduate records were not
                            changed.
                        </p>
                    </div>
                </div>

                <dl className="admin-detail-list admin-detail-list-compact">
                    <div>
                        <dt>Source file</dt>
                        <dd>{summary.original_filename}</dd>
                    </div>
                    <div>
                        <dt>Import mode</dt>
                        <dd>{modeLabel}</dd>
                    </div>
                    <div>
                        <dt>Rows reviewed</dt>
                        <dd>{summary.total_count.toLocaleString()}</dd>
                    </div>
                    <div>
                        <dt>Created</dt>
                        <dd>{summary.created_count.toLocaleString()}</dd>
                    </div>
                    <div>
                        <dt>Photos imported</dt>
                        <dd>{(summary.photo_count ?? 0).toLocaleString()}</dd>
                    </div>
                    <div>
                        <dt>Audio imported</dt>
                        <dd>{(summary.audio_count ?? 0).toLocaleString()}</dd>
                    </div>
                    <div>
                        <dt>Updated</dt>
                        <dd>{summary.updated_count.toLocaleString()}</dd>
                    </div>
                    <div>
                        <dt>Warnings reviewed</dt>
                        <dd>{summary.warning_count.toLocaleString()}</dd>
                    </div>
                    <div>
                        <dt>Skipped</dt>
                        <dd>{summary.skipped_count.toLocaleString()}</dd>
                    </div>
                    <div>
                        <dt>Started</dt>
                        <dd>{formatDateTime(summary.started_at)}</dd>
                    </div>
                    <div>
                        <dt>Completed</dt>
                        <dd>{formatDateTime(summary.completed_at)}</dd>
                    </div>
                </dl>

                <div className="admin-form-actions">
                    <Link
                        className="admin-button admin-button-primary"
                        href={graduateListUrl}
                    >
                        Review imported graduates
                    </Link>
                    <Link
                        className="admin-button admin-button-secondary"
                        href="/admin/students/import"
                    >
                        Import another CSV
                    </Link>
                </div>
            </section>

            <p className="admin-import-next-step">
                Imported audio remains in “Uploaded” status. Review and approve it in
                Audio Readiness before ceremony playback. QR printing remains a separate
                preparation step.
            </p>
        </AdminLayout>
    );
}
