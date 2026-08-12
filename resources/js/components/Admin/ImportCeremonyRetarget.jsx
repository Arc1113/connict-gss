import { useForm } from '@inertiajs/react';

export default function ImportCeremonyRetarget({ ceremonies, studentImport, workspace }) {
    const form = useForm({ ceremony_id: String(studentImport.ceremony?.id ?? '') });

    function submit(event) {
        event.preventDefault();
        form.patch(
            workspace?.urls.ceremonyChange
                ?? `/admin/students/import/${studentImport.token}/ceremony`,
            { preserveScroll: true },
        );
    }

    return (
        <section aria-labelledby="change-import-ceremony-title" className="admin-import-retarget">
            <div className="admin-section-heading">
                <div>
                    <p className="admin-section-kicker">Wrong destination?</p>
                    <h2 id="change-import-ceremony-title">Change ceremony</h2>
                </div>
            </div>
            <form className="admin-inline-form" onSubmit={submit}>
                <label htmlFor="import_ceremony_id">Destination ceremony</label>
                <select
                    aria-describedby="import-ceremony-help import-ceremony-error"
                    aria-invalid={Boolean(form.errors.ceremony_id)}
                    disabled={form.processing}
                    id="import_ceremony_id"
                    onChange={(event) => form.setData('ceremony_id', event.target.value)}
                    value={form.data.ceremony_id}
                >
                    {ceremonies.map((ceremony) => (
                        <option key={ceremony.id} value={ceremony.id}>
                            {ceremony.name} — {ceremony.academic_year} ({ceremony.status.label})
                        </option>
                    ))}
                </select>
                <button
                    className="admin-button admin-button-secondary"
                    disabled={form.processing || form.data.ceremony_id === String(studentImport.ceremony?.id ?? '')}
                    type="submit"
                >
                    {form.processing ? 'Changing…' : 'Change and re-check'}
                </button>
            </form>
            <p className="admin-field-hint" id="import-ceremony-help">
                Your CSV, mapping, and media ZIP will be kept. Sequence and student-number conflicts will be checked again for the new ceremony.
            </p>
            {form.errors.ceremony_id && (
                <p className="admin-form-error" id="import-ceremony-error" role="alert">
                    {form.errors.ceremony_id}
                </p>
            )}
        </section>
    );
}
