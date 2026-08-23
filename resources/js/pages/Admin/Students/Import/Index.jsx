import { Head, Link, useForm } from '@inertiajs/react';
import AdminLayout from '../../../../components/Admin/AdminLayout';
import { FieldError, ValidationSummary } from '../../../../components/Admin/FormSupport';
import ImportProgress from '../../../../components/Admin/ImportProgress';
import PageHeader from '../../../../components/Admin/PageHeader';

export default function StudentImportIndex({
    ceremonies,
    limits,
    templateUrl,
    workspace,
}) {
    const form = useForm({
        ceremony_id: workspace?.ceremony?.id ?? ceremonies[0]?.id ?? '',
        csv_file: null,
        media_package: null,
    });
    const hasCeremonies = ceremonies.length > 0;

    function submit(event) {
        event.preventDefault();
        form.post(workspace?.urls.inspect ?? '/admin/students/import/inspect', {
            forceFormData: true,
        });
    }

    return (
        <AdminLayout>
            <Head title="Import graduates" />
            <PageHeader
                actions={
                    <Link
                        className="admin-button admin-button-secondary"
                        href={workspace?.urls.graduates ?? '/admin/students'}
                    >
                        Return to graduate records
                    </Link>
                }
                eyebrow={workspace ? 'Ceremony workspace' : 'Graduate data preparation'}
                title="Import graduates"
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
                        <legend>Upload files</legend>
                        <div className="admin-form-grid">
                            {workspace?.locked ? (
                                <input name="ceremony_id" type="hidden" value={form.data.ceremony_id} />
                            ) : (
                                <div className="admin-field admin-field-wide">
                                    <label htmlFor="ceremony_id">
                                        Add graduates to <span aria-hidden="true">*</span>
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
                                        New records will be added to this ceremony only.
                                    </p>
                                    <FieldError
                                        id="ceremony_id-error"
                                        message={form.errors.ceremony_id}
                                    />
                                </div>
                            )}

                            <div className="admin-field admin-field-wide">
                                <label htmlFor="media_package">
                                    Media package <span>Optional</span>
                                </label>
                                <input
                                    accept=".zip,application/zip"
                                    aria-describedby="media_package-hint media_package-error"
                                    aria-invalid={Boolean(form.errors.media_package)}
                                    disabled={!hasCeremonies || form.processing}
                                    id="media_package"
                                    onChange={(event) =>
                                        form.setData(
                                            'media_package',
                                            event.target.files?.[0] ?? null,
                                        )
                                    }
                                    type="file"
                                />
                                <p className="admin-field-hint" id="media_package-hint">
                                    ZIP with matching photos or audio.
                                </p>
                                <FieldError
                                    id="media_package-error"
                                    message={form.errors.media_package}
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
                                    One graduate per row.
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
                            {form.processing ? 'Checking file…' : 'Continue'}
                        </button>
                    </div>
                </form>

                <aside aria-labelledby="import-preparation-title" className="admin-import-help">
                    <h2 id="import-preparation-title">Template</h2>
                    <a className="admin-text-link" href={templateUrl}>
                        Download the CSV template
                    </a>
                    <details className="admin-import-requirements">
                        <summary>File requirements</summary>
                    <ol>
                        <li>
                            Include student number, first and last name, program,
                            sequence number, and eligibility.
                        </li>
                        <li>
                            Keep sequence and student numbers unique within the
                            destination ceremony.
                        </li>
                        <li>
                            For media, add <code>photo_filename</code> and
                            <code>audio_filename</code> columns and upload one ZIP with
                            matching files in its <code>photos/</code> and <code>audio/</code>
                            folders.
                        </li>
                    </ol>
                        <p>
                            CSV files may be comma, semicolon, or tab delimited, up to{' '}
                            {limits.max_file_megabytes} MiB and {limits.max_rows.toLocaleString()}{' '}
                            data rows. ZIP files may be up to {limits.max_media_package_megabytes} MiB.
                        </p>
                    </details>
                </aside>
            </div>
        </AdminLayout>
    );
}
