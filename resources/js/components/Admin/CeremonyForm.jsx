import { Link, useForm } from '@inertiajs/react';
import { FieldError, RequiredNote, ValidationSummary } from './FormSupport';

function describedBy(field, error, hint) {
    return [hint ? `${field}-hint` : null, error ? `${field}-error` : null]
        .filter(Boolean)
        .join(' ') || undefined;
}

export default function CeremonyForm({
    action,
    ceremony,
    presentationModeOptions,
    statusOptions,
    submitLabel,
}) {
    const editing = Boolean(ceremony?.id);
    const { data, errors, processing, setData, post, put } = useForm({
        name: ceremony?.name ?? '',
        academic_year: ceremony?.academic_year ?? '',
        venue: ceremony?.venue ?? '',
        event_date: ceremony?.event_date ?? '',
        status: ceremony?.status ?? 'draft',
        presentation_mode: ceremony?.presentation_mode ?? 'controlled',
    });

    function submit(event) {
        event.preventDefault();

        if (editing) {
            put(action, { preserveScroll: true });
            return;
        }

        post(action, { preserveScroll: true });
    }

    return (
        <form className="admin-form" onSubmit={submit}>
            <ValidationSummary errors={errors} />
            <RequiredNote />

            <fieldset className="admin-form-section">
                <legend>Ceremony identity</legend>
                <div className="admin-form-grid">
                    <div className="admin-field admin-field-wide">
                        <label htmlFor="name">
                            Ceremony name <span aria-hidden="true">*</span>
                        </label>
                        <input
                            aria-describedby={describedBy('name', errors.name)}
                            aria-invalid={Boolean(errors.name)}
                            autoFocus
                            id="name"
                            maxLength="255"
                            onChange={(event) => setData('name', event.target.value)}
                            required
                            type="text"
                            value={data.name}
                        />
                        <FieldError id="name-error" message={errors.name} />
                    </div>

                    <div className="admin-field">
                        <label htmlFor="academic_year">Academic year</label>
                        <input
                            aria-describedby={describedBy(
                                'academic_year',
                                errors.academic_year,
                                true,
                            )}
                            aria-invalid={Boolean(errors.academic_year)}
                            id="academic_year"
                            inputMode="numeric"
                            maxLength="9"
                            onChange={(event) =>
                                setData('academic_year', event.target.value)
                            }
                            placeholder="2026-2027"
                            type="text"
                            value={data.academic_year}
                        />
                        <p className="admin-field-hint" id="academic_year-hint">
                            Use consecutive years in YYYY-YYYY format.
                        </p>
                        <FieldError
                            id="academic_year-error"
                            message={errors.academic_year}
                        />
                    </div>

                    <div className="admin-field">
                        <label htmlFor="event_date">Event date</label>
                        <input
                            aria-describedby={describedBy(
                                'event_date',
                                errors.event_date,
                            )}
                            aria-invalid={Boolean(errors.event_date)}
                            id="event_date"
                            onChange={(event) =>
                                setData('event_date', event.target.value)
                            }
                            type="date"
                            value={data.event_date}
                        />
                        <FieldError
                            id="event_date-error"
                            message={errors.event_date}
                        />
                    </div>

                    <div className="admin-field admin-field-wide">
                        <label htmlFor="venue">Venue</label>
                        <input
                            aria-describedby={describedBy('venue', errors.venue)}
                            aria-invalid={Boolean(errors.venue)}
                            id="venue"
                            maxLength="255"
                            onChange={(event) => setData('venue', event.target.value)}
                            type="text"
                            value={data.venue}
                        />
                        <FieldError id="venue-error" message={errors.venue} />
                    </div>
                </div>
            </fieldset>

            <fieldset className="admin-form-section">
                <legend>Operational status</legend>
                <div className="admin-form-grid">
                    <div className="admin-field">
                        <label htmlFor="status">
                            Status <span aria-hidden="true">*</span>
                        </label>
                        <select
                            aria-describedby={describedBy(
                                'status',
                                errors.status,
                                true,
                            )}
                            aria-invalid={Boolean(errors.status)}
                            id="status"
                            onChange={(event) => setData('status', event.target.value)}
                            required
                            value={data.status}
                        >
                            {statusOptions.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </select>
                        <p className="admin-field-hint" id="status-hint">
                            Activation is explicit and is blocked if another ceremony
                            is active.
                        </p>
                        <FieldError id="status-error" message={errors.status} />
                    </div>

                    <div className="admin-field">
                        <label htmlFor="presentation_mode">
                            Presentation mode <span aria-hidden="true">*</span>
                        </label>
                        <select
                            aria-describedby={describedBy(
                                'presentation_mode',
                                errors.presentation_mode,
                                true,
                            )}
                            aria-invalid={Boolean(errors.presentation_mode)}
                            id="presentation_mode"
                            onChange={(event) =>
                                setData('presentation_mode', event.target.value)
                            }
                            required
                            value={data.presentation_mode}
                        >
                            {presentationModeOptions.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </select>
                        <p
                            className="admin-field-hint"
                            id="presentation_mode-hint"
                        >
                            {data.presentation_mode === 'automatic'
                                ? 'Assisted automatic presents a new scan only while the stage is idle and no graduate is ahead. An operator still completes and advances the queue.'
                                : 'Controlled mode keeps every scan waiting until a stage operator presents a graduate.'}
                        </p>
                        <FieldError
                            id="presentation_mode-error"
                            message={errors.presentation_mode}
                        />
                    </div>
                </div>

                <p className="admin-form-warning">
                    Status or configuration cannot change while a graduate is
                    actively presented. Dates never activate a ceremony
                    automatically.
                </p>
            </fieldset>

            <div className="admin-form-actions">
                <button
                    className="admin-button admin-button-primary"
                    disabled={processing}
                    type="submit"
                >
                    {processing ? 'Saving…' : submitLabel}
                </button>
                <Link
                    className="admin-button admin-button-secondary"
                    href={
                        editing
                            ? `/admin/ceremonies/${ceremony.id}`
                            : '/admin/ceremonies'
                    }
                >
                    Cancel
                </Link>
            </div>
        </form>
    );
}
