import { Head, Link, useForm } from '@inertiajs/react';
import AdminLayout from '../../../../components/Admin/AdminLayout';
import { FieldError, ValidationSummary } from '../../../../components/Admin/FormSupport';
import ImportCounts from '../../../../components/Admin/ImportCounts';
import ImportProgress from '../../../../components/Admin/ImportProgress';
import ImportCeremonyRetarget from '../../../../components/Admin/ImportCeremonyRetarget';
import ImportSourceReplacements from '../../../../components/Admin/ImportSourceReplacements';
import PageHeader from '../../../../components/Admin/PageHeader';
import Pagination from '../../../../components/Admin/Pagination';

function RowMessages({ errors, existingRecord, generatedFields, warnings }) {
    const hasMessages =
        errors.length > 0 ||
        warnings.length > 0 ||
        generatedFields.length > 0 ||
        existingRecord;

    if (!hasMessages) {
        return <span className="admin-muted">No issues</span>;
    }

    return (
        <ul className="admin-import-row-messages">
            {existingRecord && (
                <li className="is-error">Existing graduate record matched.</li>
            )}
            {errors.map((message) => (
                <li className="is-error" key={`error-${message}`}>
                    Error: {message}
                </li>
            ))}
            {warnings.map((message) => (
                <li className="is-warning" key={`warning-${message}`}>
                    Warning: {message}
                </li>
            ))}
            {generatedFields.map((field) => (
                <li key={`generated-${field}`}>Generated: {field.replaceAll('_', ' ')}</li>
            ))}
        </ul>
    );
}

function RowState({ classification }) {
    const labels = {
        error: 'Blocked',
        valid: 'Ready',
        warning: 'Warning',
    };

    return (
        <span className={`admin-import-row-state is-${classification}`}>
            {labels[classification]}
        </span>
    );
}

export default function StudentImportPreview({ ceremonies, preview, studentImport, workspace }) {
    const form = useForm({ confirmed: false });

    function submit(event) {
        event.preventDefault();
        form.post(
            workspace?.urls.confirm
                ?? `/admin/students/import/${studentImport.token}/confirm`,
        );
    }

    return (
        <AdminLayout>
            <Head title="Review import" />
            <PageHeader
                backHref={workspace?.urls.mapping ?? `/admin/students/import/${studentImport.token}/mapping`}
                backLabel="Mapping"
                actions={
                    <Link
                        className="admin-button admin-button-secondary"
                        href={
                            workspace?.urls.mapping
                            ?? `/admin/students/import/${studentImport.token}/mapping`
                        }
                    >
                        Change mapping
                    </Link>
                }
                description="Resolve every blocked row before creating graduate records."
                eyebrow="Graduate import"
                title="Review import"
            />
            <ImportProgress currentStep={3} />

            <section aria-labelledby="preview-context-title" className="admin-import-context">
                <h2 id="preview-context-title">Destination check</h2>
                <dl>
                    <div>
                        <dt>Ceremony</dt>
                        <dd>{studentImport.ceremony?.name ?? 'Ceremony unavailable'}</dd>
                    </div>
                    <div>
                        <dt>Academic year</dt>
                        <dd>{studentImport.ceremony?.academic_year ?? 'Not available'}</dd>
                    </div>
                    <div>
                        <dt>Source file</dt>
                        <dd>{studentImport.original_filename}</dd>
                    </div>
                    <div>
                        <dt>Mode</dt>
                        <dd>{studentImport.mode.label}</dd>
                    </div>
                </dl>
            </section>

            <ImportSourceReplacements
                studentImport={studentImport}
                workspace={workspace}
            />

            <ImportCeremonyRetarget
                ceremonies={ceremonies}
                studentImport={studentImport}
                workspace={workspace}
            />

            <ImportCounts counts={preview} />

            {preview.error_count > 0 && (
                <p className="admin-blocking-message" role="alert">
                    Import is blocked. Correct the source file, mapping, or media ZIP,
                    then review the rows again. No graduate records have been created.
                </p>
            )}
            {preview.error_count === 0 && preview.warning_count > 0 && (
                <p className="admin-form-warning" role="status">
                    Warnings do not block this create-only import. Review every warning
                    before confirming.
                </p>
            )}

            <div className="admin-table-wrap admin-import-preview-table">
                <table className="admin-table">
                    <caption className="sr-only">
                        Server-normalized graduate rows and import findings
                    </caption>
                    <thead>
                        <tr>
                            <th scope="col">CSV row</th>
                            <th scope="col">State</th>
                            <th scope="col">Graduate</th>
                            <th scope="col">Program</th>
                            <th scope="col">Sequence</th>
                            <th scope="col">Eligibility</th>
                            <th scope="col">Findings</th>
                        </tr>
                    </thead>
                    <tbody>
                        {preview.rows.data.map((row) => (
                            <tr
                                className={`admin-import-row is-${row.classification}`}
                                key={row.row_number}
                            >
                                <th scope="row">{row.row_number}</th>
                                <td>
                                    <RowState classification={row.classification} />
                                </td>
                                <td>
                                    <span className="admin-cell-primary">
                                        {row.normalized.display_name || 'Name unavailable'}
                                    </span>
                                    <small>
                                        {row.normalized.student_number ||
                                            'Student number missing'}
                                    </small>
                                </td>
                                <td>
                                    <span className="admin-cell-primary">
                                        {row.normalized.program || 'Program missing'}
                                    </span>
                                    <small>
                                        {row.normalized.major || 'No major provided'}
                                    </small>
                                </td>
                                <td>{row.normalized.sequence_number ?? 'Missing'}</td>
                                <td>
                                    {row.normalized.eligibility_status || 'Missing'}
                                </td>
                                <td>
                                    <RowMessages
                                        errors={row.errors}
                                        existingRecord={row.existing_record}
                                        generatedFields={row.generated_fields}
                                        warnings={row.warnings}
                                    />
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            <Pagination collection={preview.rows} itemLabel="CSV rows" />

            <details className="admin-import-details">
                <summary>Review saved mapping and ignored columns</summary>
                <dl>
                    {preview.mapping.map((item) => (
                        <div key={item.source}>
                            <dt>{item.source}</dt>
                            <dd>{item.target_label}</dd>
                        </div>
                    ))}
                </dl>
                <p>
                    Ignored:{' '}
                    {preview.ignored_columns.length > 0
                        ? preview.ignored_columns.join(', ')
                        : 'No columns'}
                </p>
            </details>

            {preview.can_confirm && (
                <form className="admin-import-confirm" onSubmit={submit}>
                    <ValidationSummary errors={form.errors} />
                    <h2>Confirm atomic import</h2>
                    <p>
                        This will create {preview.total_count.toLocaleString()} new
                        graduate records in {studentImport.ceremony?.name}. If any
                        record cannot be saved, none will be created.
                    </p>
                    <label className="admin-checkbox-row" htmlFor="confirmed">
                        <input
                            aria-describedby="confirmed-error"
                            aria-invalid={Boolean(form.errors.confirmed)}
                            checked={form.data.confirmed}
                            disabled={form.processing}
                            id="confirmed"
                            onChange={(event) =>
                                form.setData('confirmed', event.target.checked)
                            }
                            type="checkbox"
                        />
                        I reviewed the ceremony, mapping, warnings, and row count.
                    </label>
                    <FieldError
                        id="confirmed-error"
                        message={form.errors.confirmed ?? form.errors.confirm}
                    />
                    <button
                        className="admin-button admin-button-primary"
                        disabled={!form.data.confirmed || form.processing}
                        id="confirm"
                        type="submit"
                    >
                        {form.processing
                            ? 'Importing graduate records…'
                            : `Import ${preview.total_count.toLocaleString()} graduates`}
                    </button>
                </form>
            )}
        </AdminLayout>
    );
}
