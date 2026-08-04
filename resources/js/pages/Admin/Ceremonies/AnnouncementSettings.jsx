import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AdminLayout from '../../../components/Admin/AdminLayout';
import AudioPreview from '../../../components/Admin/AudioPreview';
import { FieldError, ValidationSummary } from '../../../components/Admin/FormSupport';
import PageHeader from '../../../components/Admin/PageHeader';
import StatusText from '../../../components/Admin/StatusText';
import { requestJson } from '../../../lib/http';

function formatDateTime(value) {
    if (!value) return 'Not recorded';
    return new Intl.DateTimeFormat('en-PH', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

function FictionalPreview({ preview }) {
    return (
        <section className="admin-detail-section announcement-settings-preview" aria-live="polite">
            <div className="admin-section-heading">
                <div>
                    <p className="announcement-kicker">Fictional data only</p>
                    <h2>Announcement preview</h2>
                    <p>No graduate record or provider request is used for this preview.</p>
                </div>
                <StatusText label={preview.ready ? 'Ready' : 'Blocked'} value={preview.ready ? 'approved' : 'rejected'} />
            </div>
            <div className="announcement-preview-meta">
                <span>{preview.character_count} characters</span>
                <span>{preview.award_count} sample award</span>
                {preview.duration && <span>About {preview.duration.minimum_seconds}–{preview.duration.maximum_seconds} seconds</span>}
            </div>
            <blockquote className="announcement-preview-copy">
                {preview.plain_text || 'The current settings cannot produce a valid preview.'}
            </blockquote>
            {preview.warnings.map((warning) => <p className="announcement-notice announcement-notice-warning" key={warning.code}>{warning.message}</p>)}
            {preview.blocking_errors.map((error) => <p className="announcement-notice announcement-notice-error" key={error.code} role="alert">{error.message}</p>)}
        </section>
    );
}

export default function AnnouncementSettings({ announcement }) {
    const { ceremony, limits, provider, settings } = announcement;
    const [preview, setPreview] = useState(announcement.fictional_preview);
    const [previewing, setPreviewing] = useState(false);
    const [previewError, setPreviewError] = useState(null);
    const form = useForm({
        name: settings.name,
        introduction_text: settings.introduction_text,
        include_program: settings.include_program,
        include_major: settings.include_major,
        include_honors: settings.include_honors,
        include_awards: settings.include_awards,
        include_additional_text: settings.include_additional_text,
        awards_lead_in: settings.awards_lead_in ?? '',
        closing_text: settings.closing_text ?? '',
        default_voice: settings.default_voice,
        default_locale: settings.default_locale,
        speaking_rate_percent: settings.speaking_rate_percent,
        pause_after_name_ms: settings.pause_after_name_ms,
        pause_between_sections_ms: settings.pause_between_sections_ms,
        recommended_character_limit: settings.recommended_character_limit,
        maximum_character_limit: settings.maximum_character_limit,
    });
    const comparison = useForm({
        voices: announcement.voices.slice(0, 2).map((voice) => voice.value),
    });

    function selectVoice(event) {
        const selected = announcement.voices.find((voice) => voice.value === event.target.value);
        form.setData((current) => ({
            ...current,
            default_voice: event.target.value,
            default_locale: selected?.locale ?? '',
        }));
    }

    function toggleComparisonVoice(voice) {
        comparison.setData(
            'voices',
            comparison.data.voices.includes(voice)
                ? comparison.data.voices.filter((item) => item !== voice)
                : [...comparison.data.voices, voice],
        );
    }

    async function refreshPreview() {
        setPreviewing(true);
        setPreviewError(null);

        try {
            const { response, data } = await requestJson(
                `/admin/ceremonies/${ceremony.id}/announcement-settings/preview`,
                { method: 'POST', body: JSON.stringify(form.data) },
            );
            if (!response.ok) {
                setPreviewError(data.message || 'The fictional preview could not be refreshed.');
                return;
            }
            setPreview(data.preview);
        } catch {
            setPreviewError('The fictional preview could not be refreshed.');
        } finally {
            setPreviewing(false);
        }
    }

    function publish(event) {
        event.preventDefault();
        if (!window.confirm(`Publish version ${announcement.next_version} and make it the ceremony default? Existing generated candidates may become outdated.`)) return;
        form.post(`/admin/ceremonies/${ceremony.id}/announcement-settings`, { preserveScroll: true });
    }

    function generateComparison(event) {
        event.preventDefault();
        comparison.post(`/admin/ceremonies/${ceremony.id}/announcement-settings/voice-comparison`, { preserveScroll: true });
    }

    const comparisonCharacters = preview.character_count * comparison.data.voices.length;

    return (
        <AdminLayout>
            <Head title={`Announcement settings · ${ceremony.name}`} />
            <PageHeader
                actions={<><Link className="admin-button admin-button-secondary" href={`/admin/ceremonies/${ceremony.id}/announcement-batches`}>Batch generation</Link><Link className="admin-button admin-button-secondary" href={`/admin/ceremonies/${ceremony.id}`}>Back to ceremony</Link></>}
                description="Publish reviewed wording, voice, and pacing as a new immutable version."
                eyebrow="Ceremony announcements"
                title={ceremony.name}
            />

            <ValidationSummary errors={{ ...form.errors, ...comparison.errors }} />

            <form className="admin-form announcement-settings-form" onSubmit={publish}>
                <fieldset className="admin-form-section">
                    <legend>Template wording</legend>
                    <p className="admin-form-note">Saving never rewrites a published template. It creates active version {announcement.next_version}.</p>
                    <div className="admin-form-grid">
                        <div className="admin-field">
                            <label htmlFor="template_name">Template name</label>
                            <input id="template_name" maxLength="255" onChange={(event) => form.setData('name', event.target.value)} required value={form.data.name} />
                            <FieldError id="template-name-error" message={form.errors.name} />
                        </div>
                        <div className="admin-field admin-field-wide">
                            <label htmlFor="introduction_text">Introduction</label>
                            <input id="introduction_text" maxLength="500" onChange={(event) => form.setData('introduction_text', event.target.value)} required value={form.data.introduction_text} />
                            <FieldError id="introduction-text-error" message={form.errors.introduction_text} />
                        </div>
                        <div className="admin-field">
                            <label htmlFor="awards_lead_in">Awards lead-in</label>
                            <input id="awards_lead_in" maxLength="255" onChange={(event) => form.setData('awards_lead_in', event.target.value)} value={form.data.awards_lead_in} />
                        </div>
                        <div className="admin-field admin-field-wide">
                            <label htmlFor="closing_text">Closing text</label>
                            <input id="closing_text" maxLength="500" onChange={(event) => form.setData('closing_text', event.target.value)} value={form.data.closing_text} />
                        </div>
                    </div>
                </fieldset>

                <fieldset className="admin-form-section">
                    <legend>Spoken sections</legend>
                    <div className="announcement-settings-switches">
                        {[
                            ['include_program', 'Include program'],
                            ['include_major', 'Include major'],
                            ['include_honors', 'Include honors'],
                            ['include_awards', 'Include awards'],
                            ['include_additional_text', 'Include additional distinction'],
                        ].map(([field, label]) => (
                            <label className="admin-checkbox-row" key={field}>
                                <input checked={form.data[field]} onChange={(event) => form.setData(field, event.target.checked)} type="checkbox" />
                                {label}
                            </label>
                        ))}
                    </div>
                </fieldset>

                <fieldset className="admin-form-section">
                    <legend>Voice and pacing</legend>
                    <div className="admin-form-grid">
                        <div className="admin-field">
                            <label htmlFor="default_voice">Default voice</label>
                            <select id="default_voice" onChange={selectVoice} value={form.data.default_voice}>
                                {announcement.voices.map((voice) => <option key={voice.value} value={voice.value}>{voice.label} · {voice.locale}</option>)}
                            </select>
                        </div>
                        <div className="admin-field">
                            <label htmlFor="speaking_rate_percent">Speaking rate (%)</label>
                            <input id="speaking_rate_percent" max={limits.speaking_rate_maximum} min={limits.speaking_rate_minimum} onChange={(event) => form.setData('speaking_rate_percent', Number(event.target.value))} type="number" value={form.data.speaking_rate_percent} />
                            <p className="admin-field-hint">Allowed: {limits.speaking_rate_minimum}% to +{limits.speaking_rate_maximum}%.</p>
                        </div>
                        <div className="admin-field">
                            <label htmlFor="pause_after_name_ms">Pause after name (ms)</label>
                            <input id="pause_after_name_ms" max={limits.pause_maximum} min={limits.pause_minimum} onChange={(event) => form.setData('pause_after_name_ms', Number(event.target.value))} type="number" value={form.data.pause_after_name_ms} />
                        </div>
                        <div className="admin-field">
                            <label htmlFor="pause_between_sections_ms">Pause between sections (ms)</label>
                            <input id="pause_between_sections_ms" max={limits.pause_maximum} min={limits.pause_minimum} onChange={(event) => form.setData('pause_between_sections_ms', Number(event.target.value))} type="number" value={form.data.pause_between_sections_ms} />
                        </div>
                        <div className="admin-field">
                            <label htmlFor="recommended_character_limit">Recommended characters</label>
                            <input id="recommended_character_limit" min="100" onChange={(event) => form.setData('recommended_character_limit', Number(event.target.value))} type="number" value={form.data.recommended_character_limit} />
                        </div>
                        <div className="admin-field">
                            <label htmlFor="maximum_character_limit">Maximum characters</label>
                            <input id="maximum_character_limit" min={form.data.recommended_character_limit} onChange={(event) => form.setData('maximum_character_limit', Number(event.target.value))} type="number" value={form.data.maximum_character_limit} />
                        </div>
                    </div>
                </fieldset>

                <div className="admin-form-actions">
                    <button className="admin-button admin-button-secondary" disabled={previewing} onClick={refreshPreview} type="button">{previewing ? 'Refreshing…' : 'Preview fictional announcement'}</button>
                    <button className="admin-button admin-button-primary" disabled={form.processing || !preview.ready} type="submit">{form.processing ? 'Publishing…' : `Publish version ${announcement.next_version}`}</button>
                </div>
                {previewError && <p className="announcement-notice announcement-notice-error" role="alert">{previewError}</p>}
            </form>

            <FictionalPreview preview={preview} />

            <section className="admin-detail-section announcement-voice-comparison">
                <div className="admin-section-heading">
                    <div>
                        <p className="announcement-kicker">Explicit preparation action</p>
                        <h2>Compare approved voices</h2>
                        <p>Samples use the fixed fictional preview, expire after one hour, and can never become active audio.</p>
                    </div>
                    <StatusText label={provider.configured ? 'Provider ready' : 'Provider disabled'} value={provider.configured ? 'approved' : 'missing'} />
                </div>
                <form onSubmit={generateComparison}>
                    <div className="announcement-settings-switches">
                        {announcement.voices.map((voice) => (
                            <label className="admin-checkbox-row" key={voice.value}>
                                <input checked={comparison.data.voices.includes(voice.value)} onChange={() => toggleComparisonVoice(voice.value)} type="checkbox" />
                                {voice.label} · {voice.locale}
                            </label>
                        ))}
                    </div>
                    <p className="admin-form-note">This request will synthesize {comparison.data.voices.length} sample(s), approximately {comparisonCharacters.toLocaleString()} billable characters. A pleasant sample does not prove a graduate name will be pronounced correctly.</p>
                    {comparison.errors.voice_comparison && <p className="announcement-notice announcement-notice-error" role="alert">{comparison.errors.voice_comparison}</p>}
                    <button className="admin-button admin-button-primary" disabled={comparison.processing || !provider.configured || !settings.id || comparison.data.voices.length === 0} type="submit">{comparison.processing ? 'Generating samples…' : 'Generate temporary samples'}</button>
                </form>

                <div className="announcement-usage">
                    <strong>{announcement.usage.successful_characters.toLocaleString()} estimated characters used this month</strong>
                    <span>{announcement.usage.estimated_remaining.toLocaleString()} estimated remaining of {announcement.usage.monthly_budget.toLocaleString()}</span>
                    <small>{announcement.usage.label}</small>
                </div>

                {announcement.samples.length === 0 ? <p className="admin-media-missing">No unexpired comparison samples.</p> : (
                    <div className="announcement-voice-sample-grid">
                        {announcement.samples.map((sample) => (
                            <article key={sample.id}>
                                <strong>{sample.voice}</strong>
                                <span>{sample.locale} · template v{sample.template.version}</span>
                                <small>Expires {formatDateTime(sample.expires_at)}</small>
                                <AudioPreview label={`Fictional voice sample using ${sample.voice}`} src={sample.audio_url} />
                            </article>
                        ))}
                    </div>
                )}
            </section>

            <section className="admin-detail-section">
                <div className="admin-section-heading"><div><h2>Published versions</h2><p>Historical versions remain attached to their generation records.</p></div></div>
                {announcement.history.length === 0 ? <p className="admin-media-missing">No announcement settings published yet.</p> : (
                    <div className="announcement-template-history">
                        {announcement.history.map((template) => (
                            <article key={template.id}>
                                <div><strong>{template.name} · version {template.version}</strong>{template.is_active && <span>Active default</span>}</div>
                                <p>{template.default_voice} · {template.default_locale} · rate {template.speaking_rate_percent}%</p>
                                <small>{template.generations_count} generation(s) · published {formatDateTime(template.created_at)}</small>
                            </article>
                        ))}
                    </div>
                )}
            </section>
        </AdminLayout>
    );
}
