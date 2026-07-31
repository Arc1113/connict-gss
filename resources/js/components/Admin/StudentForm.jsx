import { Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AudioPreview from './AudioPreview';
import AnnouncementRecorder from './AnnouncementRecorder';
import { FieldError, RequiredNote, ValidationSummary } from './FormSupport';

function describedBy(field, error, hint = false) {
    return [hint ? `${field}-hint` : null, error ? `${field}-error` : null]
        .filter(Boolean)
        .join(' ') || undefined;
}

function TextField({
    data,
    errors,
    field,
    label,
    maxLength = 255,
    onChange,
    required = false,
    wide = false,
}) {
    return (
        <div className={`admin-field${wide ? ' admin-field-wide' : ''}`}>
            <label htmlFor={field}>
                {label} {required && <span aria-hidden="true">*</span>}
            </label>
            <input
                aria-describedby={describedBy(field, errors[field])}
                aria-invalid={Boolean(errors[field])}
                id={field}
                maxLength={maxLength}
                onChange={(event) => onChange(field, event.target.value)}
                required={required}
                type="text"
                value={data[field] ?? ''}
            />
            <FieldError id={`${field}-error`} message={errors[field]} />
        </div>
    );
}

export default function StudentForm({
    action,
    formOptions,
    student,
    submitLabel,
}) {
    const editing = Boolean(student?.id);
    const [isRecording, setIsRecording] = useState(false);
    const { data, errors, processing, setData, post } = useForm({
        _method: editing ? 'put' : undefined,
        ceremony_id: student?.ceremony_id ?? '',
        student_number: student?.student_number ?? '',
        first_name: student?.first_name ?? '',
        middle_name: student?.middle_name ?? '',
        last_name: student?.last_name ?? '',
        suffix: student?.suffix ?? '',
        display_name: student?.display_name ?? '',
        announcement_name: student?.announcement_name ?? '',
        pronunciation_guide: student?.pronunciation_guide ?? '',
        college: student?.college ?? '',
        program: student?.program ?? '',
        major: student?.major ?? '',
        honors: student?.honors ?? '',
        sequence_number: student?.sequence_number ?? '',
        eligibility_status: student?.eligibility_status ?? 'pending',
        photo: null,
        audio: null,
    });

    function submit(event) {
        event.preventDefault();

        if (isRecording) {
            return;
        }

        post(action, { forceFormData: true, preserveScroll: true });
    }

    function removeMedia(type) {
        const label = type === 'photo' ? 'graduate photo' : 'announcement audio';

        if (
            window.confirm(
                `Remove the current ${label}? Save any other form changes first.`,
            )
        ) {
            router.delete(`/admin/students/${student.id}/${type}`, {
                preserveScroll: true,
            });
        }
    }

    return (
        <form className="admin-form" onSubmit={submit}>
            <ValidationSummary errors={errors} />
            <RequiredNote />

            <fieldset className="admin-form-section">
                <legend>Record assignment</legend>
                <div className="admin-form-grid">
                    <div className="admin-field admin-field-wide">
                        <label htmlFor="ceremony_id">
                            Ceremony <span aria-hidden="true">*</span>
                        </label>
                        <select
                            aria-describedby={describedBy(
                                'ceremony_id',
                                errors.ceremony_id,
                            )}
                            aria-invalid={Boolean(errors.ceremony_id)}
                            autoFocus
                            id="ceremony_id"
                            onChange={(event) =>
                                setData('ceremony_id', event.target.value)
                            }
                            required
                            value={data.ceremony_id}
                        >
                            <option value="">Select a ceremony</option>
                            {formOptions.ceremonies.map((ceremony) => (
                                <option key={ceremony.id} value={ceremony.id}>
                                    {ceremony.name}
                                    {ceremony.academic_year
                                        ? ` — ${ceremony.academic_year}`
                                        : ''}{' '}
                                    ({ceremony.status})
                                </option>
                            ))}
                        </select>
                        <FieldError
                            id="ceremony_id-error"
                            message={errors.ceremony_id}
                        />
                    </div>

                    <TextField
                        data={data}
                        errors={errors}
                        field="student_number"
                        label="Student number"
                        maxLength={64}
                        onChange={setData}
                        required
                    />

                    <div className="admin-field">
                        <label htmlFor="sequence_number">
                            Sequence number <span aria-hidden="true">*</span>
                        </label>
                        <input
                            aria-describedby={describedBy(
                                'sequence_number',
                                errors.sequence_number,
                            )}
                            aria-invalid={Boolean(errors.sequence_number)}
                            id="sequence_number"
                            min="1"
                            onChange={(event) =>
                                setData('sequence_number', event.target.value)
                            }
                            required
                            type="number"
                            value={data.sequence_number}
                        />
                        <FieldError
                            id="sequence_number-error"
                            message={errors.sequence_number}
                        />
                    </div>

                    <p className="admin-form-note admin-field-wide">
                        A secure QR token is assigned by the server. Prepare and print
                        the card from the QR preparation workspace after saving this
                        record.
                    </p>
                </div>
            </fieldset>

            <fieldset className="admin-form-section">
                <legend>Graduate names</legend>
                <div className="admin-form-grid">
                    <TextField
                        data={data}
                        errors={errors}
                        field="first_name"
                        label="First name"
                        onChange={setData}
                        required
                    />
                    <TextField
                        data={data}
                        errors={errors}
                        field="middle_name"
                        label="Middle name"
                        onChange={setData}
                    />
                    <TextField
                        data={data}
                        errors={errors}
                        field="last_name"
                        label="Last name"
                        onChange={setData}
                        required
                    />
                    <TextField
                        data={data}
                        errors={errors}
                        field="suffix"
                        label="Suffix"
                        maxLength={32}
                        onChange={setData}
                    />
                    <TextField
                        data={data}
                        errors={errors}
                        field="display_name"
                        label="Stage display name"
                        onChange={setData}
                        required
                        wide
                    />
                    <TextField
                        data={data}
                        errors={errors}
                        field="announcement_name"
                        label="Announcement name"
                        onChange={setData}
                        required
                        wide
                    />
                    <TextField
                        data={data}
                        errors={errors}
                        field="pronunciation_guide"
                        label="Pronunciation guide"
                        onChange={setData}
                        wide
                    />
                </div>
                <p className="admin-form-note">
                    Display and announcement names are reviewed fields. They are not
                    overwritten automatically when separate name fields change.
                </p>
            </fieldset>

            <fieldset className="admin-form-section">
                <legend>Academic and eligibility data</legend>
                <div className="admin-form-grid">
                    <TextField
                        data={data}
                        errors={errors}
                        field="college"
                        label="College"
                        onChange={setData}
                        wide
                    />
                    <TextField
                        data={data}
                        errors={errors}
                        field="program"
                        label="Program"
                        onChange={setData}
                        required
                        wide
                    />
                    <TextField
                        data={data}
                        errors={errors}
                        field="major"
                        label="Major"
                        onChange={setData}
                        wide
                    />
                    <div className="admin-field admin-field-wide">
                        <label htmlFor="honors">Honors</label>
                        <textarea
                            aria-describedby={describedBy('honors', errors.honors)}
                            aria-invalid={Boolean(errors.honors)}
                            id="honors"
                            maxLength="1000"
                            onChange={(event) =>
                                setData('honors', event.target.value)
                            }
                            rows="3"
                            value={data.honors}
                        />
                        <FieldError id="honors-error" message={errors.honors} />
                    </div>
                    <div className="admin-field">
                        <label htmlFor="eligibility_status">
                            Eligibility <span aria-hidden="true">*</span>
                        </label>
                        <select
                            aria-describedby={describedBy(
                                'eligibility_status',
                                errors.eligibility_status,
                            )}
                            aria-invalid={Boolean(errors.eligibility_status)}
                            id="eligibility_status"
                            onChange={(event) =>
                                setData('eligibility_status', event.target.value)
                            }
                            required
                            value={data.eligibility_status}
                        >
                            {formOptions.eligibilityStatuses.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </select>
                        <FieldError
                            id="eligibility_status-error"
                            message={errors.eligibility_status}
                        />
                    </div>
                </div>
            </fieldset>

            <fieldset className="admin-form-section">
                <legend>Local presentation media</legend>
                <div className="admin-media-editor">
                    <section aria-labelledby="photo-heading">
                        <h2 id="photo-heading">Graduate photo</h2>
                        {student?.photo_url ? (
                            <img
                                alt={`Current approved photo for ${student.display_name}`}
                                className="admin-photo-preview"
                                src={student.photo_url}
                            />
                        ) : (
                            <p className="admin-media-missing">
                                No photo uploaded. The stage will use its no-photo
                                layout.
                            </p>
                        )}
                        <label htmlFor="photo">
                            {student?.photo_url ? 'Replace photo' : 'Upload photo'}
                        </label>
                        <input
                            accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                            aria-describedby={describedBy(
                                'photo',
                                errors.photo,
                                true,
                            )}
                            aria-invalid={Boolean(errors.photo)}
                            id="photo"
                            onChange={(event) =>
                                setData('photo', event.target.files?.[0] ?? null)
                            }
                            type="file"
                        />
                        <p className="admin-field-hint" id="photo-hint">
                            JPEG, PNG, or WebP; maximum 5 MB.
                        </p>
                        <FieldError id="photo-error" message={errors.photo} />
                        {editing && student.photo_url && (
                            <button
                                className="admin-text-action admin-text-action-danger"
                                onClick={() => removeMedia('photo')}
                                type="button"
                            >
                                Remove current photo
                            </button>
                        )}
                    </section>

                    <section aria-labelledby="audio-heading">
                        <h2 id="audio-heading">Announcement audio</h2>
                        <AudioPreview
                            label={`Current announcement audio for ${student?.display_name ?? 'graduate'}`}
                            src={student?.audio_url}
                        />
                        <AnnouncementRecorder
                            disabled={processing}
                            graduateName={data.announcement_name || data.display_name}
                            onRecorded={(file) => setData('audio', file)}
                            onRecordingChange={setIsRecording}
                        />
                        <label htmlFor="audio">
                            {student?.audio_url ? 'Replace audio' : 'Upload audio'}
                        </label>
                        <input
                            accept=".mp3,.wav,.ogg,.webm,audio/mpeg,audio/wav,audio/ogg,audio/webm"
                            aria-describedby={describedBy(
                                'audio',
                                errors.audio,
                                true,
                            )}
                            aria-invalid={Boolean(errors.audio)}
                            id="audio"
                            onChange={(event) =>
                                setData('audio', event.target.files?.[0] ?? null)
                            }
                            type="file"
                        />
                        <p className="admin-field-hint" id="audio-hint">
                            MP3, WAV, OGG, or WebM; maximum 15 MB. Replacing audio clears
                            approval and requires review before stage playback.
                        </p>
                        <FieldError id="audio-error" message={errors.audio} />
                        {editing && student.audio && (
                            <div className="admin-audio-readiness">
                                <strong>Readiness</strong>
                                <span>{student.audio.status.label}</span>
                                <small>
                                    {student.audio.approved_at
                                        ? `Approved ${new Date(student.audio.approved_at).toLocaleString('en-PH')}`
                                        : 'Not approved for automatic stage playback'}
                                </small>
                            </div>
                        )}
                        {editing && student.audio_url && (
                            <button
                                className="admin-text-action admin-text-action-danger"
                                onClick={() => removeMedia('audio')}
                                type="button"
                            >
                                Remove current audio
                            </button>
                        )}
                    </section>
                </div>
            </fieldset>

            <div className="admin-form-actions">
                <button
                    className="admin-button admin-button-primary"
                    disabled={processing || isRecording}
                    type="submit"
                >
                    {processing
                        ? 'Saving…'
                        : isRecording
                          ? 'Finish recording first'
                          : submitLabel}
                </button>
                <Link
                    className="admin-button admin-button-secondary"
                    href={editing ? `/admin/students/${student.id}` : '/admin/students'}
                >
                    Cancel
                </Link>
            </div>
        </form>
    );
}
