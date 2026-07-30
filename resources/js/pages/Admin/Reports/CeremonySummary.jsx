import { Head, Link, router, useForm } from '@inertiajs/react';
import AdminLayout from '../../../components/Admin/AdminLayout';
import EmptyState from '../../../components/Admin/EmptyState';
import PageHeader from '../../../components/Admin/PageHeader';
import StatusText from '../../../components/Admin/StatusText';

function formatDateTime(value) {
    if (!value) return 'Not recorded';

    return new Intl.DateTimeFormat('en-PH', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

function exportHref(filters) {
    const parameters = new URLSearchParams(
        Object.entries(filters).filter(([, value]) => value),
    );

    return `/admin/reports/ceremony-summary/export?${parameters.toString()}`;
}

export default function CeremonySummary({ canExport, ceremonies, filters, summary }) {
    const resetForm = useForm({ confirmation: '' });

    function submitReset(event) {
        event.preventDefault();

        if (!summary) return;

        router.post(
            `/admin/ceremonies/${summary.ceremony.id}/operational-reset`,
            resetForm.data,
            { preserveScroll: true },
        );
    }

    return (
        <AdminLayout>
            <Head title="Ceremony completion summary" />
            <PageHeader
                actions={
                    canExport && (
                        <a className="admin-button admin-button-primary" href={exportHref(filters)}>
                            Export CSV
                        </a>
                    )
                }
                description="PostgreSQL-derived ceremony counts, timing, readiness, and safe reset policy."
                eyebrow="Reports"
                title="Ceremony completion summary"
            />

            <form action="/admin/reports/ceremony-summary" className="admin-filters" method="get">
                <div>
                    <label htmlFor="ceremony_id">Ceremony</label>
                    <select defaultValue={filters.ceremony_id} id="ceremony_id" name="ceremony_id">
                        {ceremonies.map((ceremony) => (
                            <option key={ceremony.id} value={ceremony.id}>
                                {ceremony.name} ({ceremony.status})
                            </option>
                        ))}
                    </select>
                </div>
                <div className="admin-filter-actions">
                    <button className="admin-button admin-button-primary" type="submit">
                        Review summary
                    </button>
                </div>
            </form>

            {!summary ? (
                <EmptyState title="No ceremony available">
                    Create a ceremony before reviewing operational reports.
                </EmptyState>
            ) : (
                <>
                    <section className="admin-detail-section">
                        <div className="admin-section-heading">
                            <div>
                                <h2>{summary.ceremony.name}</h2>
                                <p>
                                    {summary.ceremony.academic_year || 'Academic year not set'}
                                </p>
                            </div>
                            <StatusText label={summary.ceremony.status} value={summary.ceremony.status} />
                        </div>
                        <dl className="admin-detail-list admin-detail-list-compact">
                            <div>
                                <dt>Ceremony start</dt>
                                <dd>{formatDateTime(summary.timing.ceremony_start)}</dd>
                            </div>
                            <div>
                                <dt>First presentation</dt>
                                <dd>{formatDateTime(summary.timing.first_presentation)}</dd>
                            </div>
                            <div>
                                <dt>Last presentation</dt>
                                <dd>{formatDateTime(summary.timing.last_presentation)}</dd>
                            </div>
                            <div>
                                <dt>Completion timestamp</dt>
                                <dd>{formatDateTime(summary.timing.completion_timestamp)}</dd>
                            </div>
                        </dl>
                    </section>

                    <section className="admin-readiness">
                        <h2>Operational counts</h2>
                        <dl className="admin-readiness-list">
                            {Object.entries(summary.counts).map(([metric, value]) => (
                                <div key={metric}>
                                    <dt>{metric.replaceAll('_', ' ')}</dt>
                                    <dd>{value}</dd>
                                </div>
                            ))}
                        </dl>
                    </section>

                    <section className="admin-detail-section">
                        <h2>Metric definitions</h2>
                        <dl className="admin-detail-list">
                            {Object.entries(summary.definitions).map(([metric, definition]) => (
                                <div key={metric}>
                                    <dt>{metric.replaceAll('_', ' ')}</dt>
                                    <dd>{definition}</dd>
                                </div>
                            ))}
                        </dl>
                    </section>

                    <section className="admin-detail-section admin-danger-zone">
                        <h2>Safe operational reset</h2>
                        <p>
                            Reset cancels waiting and ready queue rows, clears the stage
                            state, and preserves graduate records, completed history, scan
                            logs, stage events, and audit logs.
                        </p>
                        <form className="admin-inline-form" onSubmit={submitReset}>
                            <label htmlFor="confirmation">
                                Type RESET CURRENT OPERATIONS to confirm
                            </label>
                            <input
                                id="confirmation"
                                name="confirmation"
                                onChange={(event) =>
                                    resetForm.setData('confirmation', event.target.value)
                                }
                                value={resetForm.data.confirmation}
                            />
                            <button
                                className="admin-button admin-button-danger"
                                disabled={resetForm.processing}
                                type="submit"
                            >
                                Reset current operations
                            </button>
                        </form>
                        <Link href="/admin/stage-events?event_type=operational_reset">
                            Review reset events
                        </Link>
                    </section>
                </>
            )}
        </AdminLayout>
    );
}
