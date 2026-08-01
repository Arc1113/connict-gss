import { Link, useForm } from '@inertiajs/react';
import { useEffect, useMemo } from 'react';
import { DEFAULT_STAGE_THEME } from '../../lib/stageTheme';
import { FieldError, RequiredNote, ValidationSummary } from './FormSupport';
import StageThemePreview from './StageThemePreview';

function describedBy(field, error, hint) {
    return [hint ? `${field}-hint` : null, error ? `${field}-error` : null]
        .filter(Boolean)
        .join(' ') || undefined;
}

function useObjectUrl(file) {
    const url = useMemo(() => (file ? URL.createObjectURL(file) : null), [file]);

    useEffect(() => () => {
        if (url) {
            URL.revokeObjectURL(url);
        }
    }, [url]);

    return url;
}

function editableTheme(theme) {
    return Object.fromEntries(
        Object.keys(DEFAULT_STAGE_THEME).map((key) => [
            key,
            theme?.[key] ?? DEFAULT_STAGE_THEME[key],
        ]),
    );
}

export default function CeremonyForm({
    action,
    ceremony,
    presentationModeOptions,
    statusOptions,
    submitLabel,
}) {
    const editing = Boolean(ceremony?.id);
    const { data, errors, processing, setData, post } = useForm({
        _method: editing ? 'put' : undefined,
        name: ceremony?.name ?? '',
        academic_year: ceremony?.academic_year ?? '',
        venue: ceremony?.venue ?? '',
        event_date: ceremony?.event_date ?? '',
        status: ceremony?.status ?? 'draft',
        presentation_mode: ceremony?.presentation_mode ?? 'controlled',
        stage_theme: editableTheme(ceremony?.stage_theme),
        stage_logo: null,
        stage_background: null,
    });
    const localLogoUrl = useObjectUrl(data.stage_logo);
    const localBackgroundUrl = useObjectUrl(data.stage_background);

    function submit(event) {
        event.preventDefault();

        const statusChanged = editing && data.status !== ceremony.status;
        const changesLiveContext =
            statusChanged &&
            (data.status === 'active' || ceremony.status === 'active');

        if (
            changesLiveContext &&
            !window.confirm(
                data.status === 'active'
                    ? `Activate ${data.name || 'this ceremony'} as the live ceremony used by Scanner and Stage Control?`
                    : `Remove ${data.name || 'this ceremony'} from the live Scanner and Stage Control context?`,
            )
        ) {
            return;
        }

        post(action, { forceFormData: true, preserveScroll: true });
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

            <fieldset className="admin-form-section stage-theme-editor">
                <legend>Audience stage design</legend>
                <p className="admin-form-note">
                    This ceremony-scoped design is stored locally and applied to the
                    projector display. Preview it here before ceremony day.
                </p>

                <StageThemePreview
                    backgroundUrl={localBackgroundUrl ?? ceremony?.stage_theme?.background_url}
                    logoUrl={localLogoUrl ?? ceremony?.stage_theme?.logo_url}
                    theme={data.stage_theme}
                />

                <div className="admin-form-grid stage-theme-controls">
                    <div className="admin-field">
                        <label htmlFor="stage_theme_preset">Visual preset</label>
                        <select
                            id="stage_theme_preset"
                            onChange={(event) => setData('stage_theme', {
                                ...data.stage_theme,
                                preset: event.target.value,
                            })}
                            value={data.stage_theme.preset}
                        >
                            <option value="institutional">Institutional</option>
                            <option value="modern">Modern minimal</option>
                            <option value="classic">Classic ceremony</option>
                        </select>
                    </div>
                    <div className="admin-field">
                        <label htmlFor="stage_theme_layout">Graduate layout</label>
                        <select
                            id="stage_theme_layout"
                            onChange={(event) => setData('stage_theme', {
                                ...data.stage_theme,
                                layout: event.target.value,
                            })}
                            value={data.stage_theme.layout}
                        >
                            <option value="centered">Centered</option>
                            <option value="split">Portrait split</option>
                            <option value="banner">Lower banner</option>
                        </select>
                    </div>

                    {[
                        ['background_color', 'Background'],
                        ['surface_color', 'Surface'],
                        ['text_color', 'Text'],
                        ['accent_color', 'Accent'],
                    ].map(([field, label]) => (
                        <div className="admin-field stage-theme-color" key={field}>
                            <label htmlFor={`stage_theme_${field}`}>{label} color</label>
                            <div>
                                <input
                                    id={`stage_theme_${field}`}
                                    onChange={(event) => setData('stage_theme', {
                                        ...data.stage_theme,
                                        [field]: event.target.value,
                                    })}
                                    type="color"
                                    value={data.stage_theme[field]}
                                />
                                <code>{data.stage_theme[field]}</code>
                            </div>
                        </div>
                    ))}

                    <div className="admin-field">
                        <label htmlFor="stage_logo">Ceremony logo</label>
                        <input
                            accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                            id="stage_logo"
                            onChange={(event) =>
                                setData('stage_logo', event.target.files?.[0] ?? null)
                            }
                            type="file"
                        />
                        <FieldError id="stage_logo-error" message={errors.stage_logo} />
                    </div>
                    <div className="admin-field">
                        <label htmlFor="stage_background">Background image</label>
                        <input
                            accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                            id="stage_background"
                            onChange={(event) =>
                                setData('stage_background', event.target.files?.[0] ?? null)
                            }
                            type="file"
                        />
                        <FieldError id="stage_background-error" message={errors.stage_background} />
                    </div>

                    <label className="stage-theme-checkbox">
                        <input
                            checked={Boolean(data.stage_theme.show_photo)}
                            onChange={(event) => setData('stage_theme', {
                                ...data.stage_theme,
                                show_photo: event.target.checked,
                            })}
                            type="checkbox"
                        />
                        Show graduate portrait when available
                    </label>
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
                        <p
                            className={`ceremony-live-context ceremony-live-context-${data.status === 'active' ? 'active' : 'inactive'}`}
                            role="status"
                        >
                            {data.status === 'active'
                                ? 'Live context: Scanner, Stage Control, and Audience Stage will use this ceremony.'
                                : 'Not live: activate this ceremony before opening Scanner or Stage Control for it.'}
                        </p>
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
