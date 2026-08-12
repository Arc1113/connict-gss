import { Head, Link, useForm } from '@inertiajs/react';
import AdminLayout from '../../../../components/Admin/AdminLayout';
import { FieldError, ValidationSummary } from '../../../../components/Admin/FormSupport';
import ImportProgress from '../../../../components/Admin/ImportProgress';
import ImportCeremonyRetarget from '../../../../components/Admin/ImportCeremonyRetarget';
import ImportSourceReplacements from '../../../../components/Admin/ImportSourceReplacements';
import PageHeader from '../../../../components/Admin/PageHeader';

export default function StudentImportMapping({
    mappingRows,
    ceremonies,
    studentImport,
    targetOptions,
    workspace,
}) {
    const form = useForm({
        mapping: mappingRows.map((row) => row.selected_target ?? ''),
    });

    function changeMapping(index, value) {
        const mapping = [...form.data.mapping];
        mapping[index] = value;
        form.setData('mapping', mapping);
    }

    function submit(event) {
        event.preventDefault();
        form.post(
            workspace?.urls.previewStore
                ?? `/admin/students/import/${studentImport.token}/preview`,
        );
    }

    return (
        <AdminLayout>
            <Head title="Map CSV columns" />
            <PageHeader
                backHref={workspace?.urls.import ?? '/admin/students/import'}
                backLabel="Import source"
                actions={
                    <Link
                        className="admin-button admin-button-secondary"
                        href={workspace?.urls.import ?? '/admin/students/import'}
                    >
                        Start over
                    </Link>
                }
                description="Match each source column to one approved graduate field."
                eyebrow="Graduate import"
                title="Map CSV columns"
            />
            <ImportProgress currentStep={2} />

            <section aria-labelledby="import-source-title" className="admin-import-context">
                <h2 id="import-source-title">Import source</h2>
                <dl>
                    <div>
                        <dt>File</dt>
                        <dd>{studentImport.original_filename}</dd>
                    </div>
                    <div>
                        <dt>Ceremony</dt>
                        <dd>{studentImport.ceremony?.name ?? 'Ceremony unavailable'}</dd>
                    </div>
                    <div>
                        <dt>Rows found</dt>
                        <dd>{studentImport.row_count.toLocaleString()}</dd>
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

            <form className="admin-import-mapping-form" onSubmit={submit}>
                <ValidationSummary errors={form.errors} />
                <p className="admin-form-note" id="mapping">
                    Required targets are marked below. Choose “Ignore this column” for
                    source data that does not belong in the approved graduate record.
                </p>

                <div className="admin-table-wrap">
                    <table className="admin-table admin-import-mapping-table">
                        <caption className="sr-only">
                            CSV source columns and selected graduate fields
                        </caption>
                        <thead>
                            <tr>
                                <th scope="col">CSV column</th>
                                <th scope="col">Example values</th>
                                <th scope="col">Graduate field</th>
                            </tr>
                        </thead>
                        <tbody>
                            {mappingRows.map((row) => {
                                const error = form.errors[`mapping.${row.index}`];
                                const fieldId = `mapping.${row.index}`;

                                return (
                                    <tr key={row.index}>
                                        <th scope="row">{row.header}</th>
                                        <td>
                                            {row.samples.length > 0 ? (
                                                <ul className="admin-import-samples">
                                                    {row.samples.map((sample) => (
                                                        <li key={sample}>{sample}</li>
                                                    ))}
                                                </ul>
                                            ) : (
                                                <span className="admin-muted">
                                                    No sample value
                                                </span>
                                            )}
                                        </td>
                                        <td>
                                            <div className="admin-field">
                                                <label className="sr-only" htmlFor={fieldId}>
                                                    Graduate field for {row.header}
                                                </label>
                                                <select
                                                    aria-describedby={`${fieldId}-error`}
                                                    aria-invalid={Boolean(error)}
                                                    disabled={form.processing}
                                                    id={fieldId}
                                                    onChange={(event) =>
                                                        changeMapping(
                                                            row.index,
                                                            event.target.value,
                                                        )
                                                    }
                                                    value={
                                                        form.data.mapping[row.index] ?? ''
                                                    }
                                                >
                                                    <option value="">
                                                        Ignore this column
                                                    </option>
                                                    {targetOptions.map((option) => (
                                                        <option
                                                            key={option.value}
                                                            value={option.value}
                                                        >
                                                            {option.label}
                                                            {option.required
                                                                ? ' — required'
                                                                : ''}
                                                        </option>
                                                    ))}
                                                </select>
                                                {row.suggested_target && (
                                                    <p className="admin-field-hint">
                                                        Suggested from the exact header
                                                        name.
                                                    </p>
                                                )}
                                                <FieldError
                                                    id={`${fieldId}-error`}
                                                    message={error}
                                                />
                                            </div>
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
                <FieldError id="mapping-error" message={form.errors.mapping} />

                <div className="admin-form-actions admin-import-actions">
                    <button
                        className="admin-button admin-button-primary"
                        disabled={form.processing}
                        type="submit"
                    >
                        {form.processing ? 'Preparing preview…' : 'Review imported rows'}
                    </button>
                </div>
            </form>
        </AdminLayout>
    );
}
