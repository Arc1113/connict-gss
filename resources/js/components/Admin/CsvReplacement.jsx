import { useForm } from '@inertiajs/react';
import { useRef } from 'react';
import { FieldError, ValidationSummary } from './FormSupport';

export default function CsvReplacement({ studentImport }) {
    const inputRef = useRef(null);
    const form = useForm({ csv_file: null });

    function submit(event) {
        event.preventDefault();
        form.post(`/admin/students/import/${studentImport.token}/csv`, {
            forceFormData: true,
            onSuccess: () => {
                form.reset('csv_file');
                if (inputRef.current) {
                    inputRef.current.value = '';
                }
            },
        });
    }

    return (
        <form
            className="admin-source-replacement"
            encType="multipart/form-data"
            onSubmit={submit}
        >
            <ValidationSummary errors={form.errors} />
            <div>
                <h2>Replace the CSV only</h2>
                <p>
                    Choose a corrected CSV. The current photo and audio ZIP will be
                    kept. If the columns change, you will review the mapping again.
                </p>
            </div>
            <div className="admin-field">
                <label className="sr-only" htmlFor="replacement_csv_file">
                    Corrected CSV file
                </label>
                <input
                    accept=".csv,.txt,text/csv,text/plain"
                    aria-describedby="replacement_csv_file-error"
                    aria-invalid={Boolean(form.errors.csv_file)}
                    disabled={form.processing}
                    id="replacement_csv_file"
                    onChange={(event) =>
                        form.setData('csv_file', event.target.files?.[0] ?? null)
                    }
                    ref={inputRef}
                    type="file"
                />
                <FieldError
                    id="replacement_csv_file-error"
                    message={form.errors.csv_file}
                />
            </div>
            <button
                className="admin-button admin-button-secondary"
                disabled={!form.data.csv_file || form.processing}
                type="submit"
            >
                {form.processing ? 'Replacing CSV…' : 'Use this corrected CSV'}
            </button>
        </form>
    );
}
