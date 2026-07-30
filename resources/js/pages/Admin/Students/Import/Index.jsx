import { Head, Link, useForm } from '@inertiajs/react';
import AdminLayout from '../../../../components/Admin/AdminLayout';
import { FieldError, ValidationSummary } from '../../../../components/Admin/FormSupport';
import ImportProgress from '../../../../components/Admin/ImportProgress';
import PageHeader from '../../../../components/Admin/PageHeader';

export default function StudentImportIndex({ ceremonies, limits, templateUrl }) {
    const form = useForm({
        ceremony_id: ceremonies[0]?.id ?? '',
        csv_file: null,
    });
    const hasCeremonies = ceremonies.length > 0;

    function submit(event) {
        event.preventDefault();
        form.post('/admin/students/import/inspect', {
            forceFormData: true,
        });
    }

    return (
        <AdminLayout>
            <Head title="Import graduate records" />
            <PageHeader
                actions={
                    <Link
                        className="admin-button admin-button-secondary"
                        href="/admin/students"
                    >
                        Return to graduate records
                    </Link>
                }
                description="Inspect a prepared CSV before any graduate records are created."
                eyebrow="Graduate data preparation"
                title="Import graduate records"
            />
            <ImportProgress currentStep={1} />

            <div className="admin-import-layout">
                <form
                    className="admin-form admin-import-form"
                    encType="multipart/form-data"
                    onSubmit={submit}
                >
                    <ValidationSummary errors={form.errors} />
                    <fieldset className="admin-form-section">
                        <legend>Destination and source file</legend>
                        <div className="admin-form-grid">
                            <div className="admin-field admin-field-wide">
                                <label htmlFor="ceremony_id">
                                    Destination ceremony <span aria-hidden="true">*</span>
                                </label>
                                <select
                                    aria-describedby="ceremony_id-hint ceremony_id-error"
                                    aria-invalid={Boolean(form.errors.ceremony_id)}
                                    disabled={!hasCeremonies || form.processing}
                                    id="ceremony_id"
                                    onChange={(event) =>
                                        form.setData('ceremony_id', event.target.value)
                                    }
                                    value={form.data.ceremony_id}
                                >
                                    {!hasCeremonies && (
                                        <option value="">No available ceremonies</option>
                                    )}
                                    {ceremonies.map((ceremony) => (
                                        <option key={ceremony.id} value={ceremony.id}>
                                            {ceremony.name} — {ceremony.academic_year} (
                                            {ceremony.status.label})
                                        </option>
                                    ))}
                                </select>
                                <p className="admin-field-hint" id="ceremony_id-hint">
                                    The import creates new graduates in this ceremony only.
                                </p>
                                <FieldError
                                    id="ceremony_id-error"
                                    message={form.errors.ceremony_id}
                                />
                            </div>

                            <div className="admin-field admin-field-wide">
                                <label htmlFor="csv_file">
                                    CSV file <span aria-hidden="true">*</span>
                                </label>
                                <input
                                    accept=".csv,.txt,text/csv,text/plain"
                                    aria-describedby="csv_file-hint csv_file-error"
                                    aria-invalid={Boolean(form.errors.csv_file)}
                                    disabled={!hasCeremonies || form.processing}
                                    id="csv_file"
                                    onChange={(event) =>
                                        form.setData(
                                            'csv_file',
                                            event.target.files?.[0] ?? null,
                                        )
                                    }
                                    type="file"
                                />
                                <p className="admin-field-hint" id="csv_file-hint">
                                    UTF-8 CSV, up to {limits.max_file_megabytes} MiB and{' '}
                                    {limits.max_rows.toLocaleString()} data rows. Comma,
                                    semicolon, and tab delimiters are accepted.
                                </p>
                                <FieldError
                                    id="csv_file-error"
                                    message={form.errors.csv_file}
                                />
                            </div>
                        </div>
                    </fieldset>

                    {!hasCeremonies && (
                        <p className="admin-blocking-message" role="status">
                            Add a planned, ready, active, or paused ceremony before
                            importing graduate records.
                        </p>
                    )}

                    <div className="admin-form-actions">
                        <button
                            className="admin-button admin-button-primary"
                            disabled={
                                !hasCeremonies ||
                                !form.data.csv_file ||
                                form.processing
                            }
                            type="submit"
                        >
                            {form.processing ? 'Inspecting CSV…' : 'Inspect CSV'}
                        </button>
                    </div>
                </form>

                <aside aria-labelledby="import-preparation-title" className="admin-import-help">
                    <h2 id="import-preparation-title">Prepare the file</h2>
                    <ol>
                        <li>Use one header row and one graduate per following row.</li>
                        <li>
                            Include student number, first and last name, program,
                            sequence number, and eligibility.
                        </li>
                        <li>
                            Keep sequence and student numbers unique within the
                            destination ceremony.
                        </li>
                    </ol>
                    <a className="admin-text-link" href={templateUrl}>
                        Download the local CSV template
                    </a>
                    <p>
                        This phase creates new records only. Existing graduate records
                        are never overwritten by an import.
                    </p>
                </aside>
            </div>
        </AdminLayout>
    );
}
