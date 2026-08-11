import { useForm } from '@inertiajs/react';
import { useRef } from 'react';
import { FieldError, ValidationSummary } from './FormSupport';

export default function MediaPackageReplacement({ studentImport, workspace }) {
    const inputRef = useRef(null);
    const form = useForm({ media_package: null });

    function submit(event) {
        event.preventDefault();
        form.post(
            workspace?.urls.mediaPackageReplace
                ?? `/admin/students/import/${studentImport.token}/media-package`,
            {
                forceFormData: true,
                onSuccess: () => {
                    form.reset('media_package');
                    if (inputRef.current) {
                        inputRef.current.value = '';
                    }
                },
            },
        );
    }

    return (
        <form
            className="admin-source-replacement"
            encType="multipart/form-data"
            onSubmit={submit}
        >
            <ValidationSummary errors={form.errors} />
            <div>
                <h2>
                    {studentImport.has_media_package
                        ? 'Replace the photo and audio ZIP'
                        : 'Add a photo and audio ZIP'}
                </h2>
                <p>
                    Choose the corrected ZIP here. Your CSV and column mapping will be
                    kept, and no graduate records will be created yet.
                </p>
            </div>
            <div className="admin-field">
                <label className="sr-only" htmlFor="replacement_media_package">
                    Corrected photo and audio ZIP
                </label>
                <input
                    accept=".zip,application/zip"
                    aria-describedby="replacement_media_package-error"
                    aria-invalid={Boolean(form.errors.media_package)}
                    disabled={form.processing}
                    id="replacement_media_package"
                    onChange={(event) =>
                        form.setData(
                            'media_package',
                            event.target.files?.[0] ?? null,
                        )
                    }
                    ref={inputRef}
                    type="file"
                />
                <FieldError
                    id="replacement_media_package-error"
                    message={form.errors.media_package}
                />
            </div>
            <button
                className="admin-button admin-button-secondary"
                disabled={!form.data.media_package || form.processing}
                type="submit"
            >
                {form.processing ? 'Replacing ZIP…' : 'Use this corrected ZIP'}
            </button>
        </form>
    );
}
