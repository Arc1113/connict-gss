import { Head, Link, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import AdminLayout from '../../../components/Admin/AdminLayout';
import AudioPreview from '../../../components/Admin/AudioPreview';
import { FieldError, ValidationSummary } from '../../../components/Admin/FormSupport';
import PageHeader from '../../../components/Admin/PageHeader';
import StatusText from '../../../components/Admin/StatusText';
import ConfirmDialog from '../../../components/ConfirmDialog';
import { requestJson } from '../../../lib/http';

function formatDateTime(value) {
    if (!value) return 'Not recorded';
    return new Intl.DateTimeFormat('en-PH', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

const QUICK_STARTS = [
    {
        id: 'complete',
        label: 'Complete announcement',
        description: 'Name, program, major, honors, and awards.',
        values: { introduction_text: 'We proudly present', include_program: true, include_major: true, include_honors: true, include_awards: true, include_additional_text: false, awards_lead_in: 'recipient of', closing_text: '' },
    },
    {
        id: 'concise',
        label: 'Name and honors',
        description: 'A shorter reading for faster ceremonies.',
        values: { introduction_text: 'We proudly present', include_program: false, include_major: false, include_honors: true, include_awards: false, include_additional_text: false, awards_lead_in: 'recipient of', closing_text: '' },
    },
    {
        id: 'awards',
        label: 'Awards emphasis',
        description: 'Includes academic details and every reviewed award.',
        values: { introduction_text: 'Please welcome', include_program: true, include_major: true, include_honors: true, include_awards: true, include_additional_text: true, awards_lead_in: 'recognized as', closing_text: '' },
    },
];
const EMPTY_VOICES = [];

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
            <div className="announcement-pronunciation-comparison">
                <div><span>Display text</span><blockquote>{preview.display_text || preview.plain_text || 'The current settings cannot produce a valid preview.'}</blockquote></div>
                <div><span>Speech text</span><blockquote>{preview.speech_text || preview.plain_text || 'The current settings cannot produce a valid preview.'}</blockquote></div>
            </div>
            {preview.applied_rules?.length > 0 && <p className="admin-form-note">{preview.applied_rules.length} common pronunciation correction{preview.applied_rules.length === 1 ? '' : 's'} applied to this fictional preview.</p>}
            {preview.warnings.map((warning) => <p className="announcement-notice announcement-notice-warning" key={warning.code}>{warning.message}</p>)}
            {preview.blocking_errors.map((error) => <p className="announcement-notice announcement-notice-error" key={error.code} role="alert">{error.message}</p>)}
        </section>
    );
}

export default function AnnouncementSettings({ announcement }) {
    const { ceremony, limits, settings } = announcement;
    const [preview, setPreview] = useState(announcement.fictional_preview);
    const [previewing, setPreviewing] = useState(false);
    const [previewError, setPreviewError] = useState(null);
    const [confirmPublish, setConfirmPublish] = useState(false);
    const form = useForm({
        provider: settings.provider,
        name: settings.name,
        introduction_text: settings.introduction_text,
        introduction_frequency: settings.introduction_frequency ?? 'first_per_program',
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
    const [previewFingerprint, setPreviewFingerprint] = useState(
        JSON.stringify(form.data),
    );
    const currentFingerprint = useMemo(() => JSON.stringify(form.data), [form.data]);
    const previewIsCurrent = previewFingerprint === currentFingerprint;
    const selectedProvider = announcement.providers.find((item) => item.value === form.data.provider)
        ?? announcement.providers[0];
    const voices = selectedProvider?.voices ?? EMPTY_VOICES;
    const provider = selectedProvider?.readiness ?? { configured: false };
    const selectedVoice = voices.find((voice) => voice.value === form.data.default_voice);
    const isLocalProvider = form.data.provider === 'kokoro';
    const groupedVoices = useMemo(() => {
        if (!isLocalProvider) return [];

        return ['male', 'female'].map((gender) => ({
            gender,
            voices: voices.filter((voice) => voice.gender === gender),
        })).filter((group) => group.voices.length > 0);
    }, [isLocalProvider, voices]);
    const voiceLabels = useMemo(() => Object.fromEntries(
        announcement.providers.flatMap((item) => item.voices)
            .map((voice) => [voice.value, voice.label]),
    ), [announcement.providers]);

    function selectVoice(event) {
        const selected = voices.find((voice) => voice.value === event.target.value);
        form.setData((current) => ({
            ...current,
            default_voice: event.target.value,
            default_locale: selected?.locale ?? '',
        }));
    }

    function selectProvider(event) {
        const nextProvider = announcement.providers.find((item) => item.value === event.target.value);
        const nextVoice = nextProvider?.voices[0];
        form.setData((current) => ({
            ...current,
            provider: event.target.value,
            default_voice: nextVoice?.value ?? '',
            default_locale: nextVoice?.locale ?? '',
        }));
        comparison.setData('voices', (nextProvider?.voices ?? []).slice(0, 2).map((voice) => voice.value));
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
            setPreviewFingerprint(JSON.stringify(form.data));
        } catch {
            setPreviewError('The fictional preview could not be refreshed.');
        } finally {
            setPreviewing(false);
        }
    }

    function publish(event) {
        event.preventDefault();
        setConfirmPublish(true);
    }

    function generateComparison(event) {
        event.preventDefault();
        comparison.post(`/admin/ceremonies/${ceremony.id}/announcement-settings/voice-comparison`, { preserveScroll: true });
    }



    function applyQuickStart(values) {
        form.setData((current) => ({ ...current, ...values }));
    }

    return (
        <AdminLayout>
            <ConfirmDialog busy={form.processing} confirmLabel={`Publish version ${announcement.next_version}`} description="This becomes the ceremony default. Existing generated candidates may become outdated and still require review." onCancel={() => setConfirmPublish(false)} onConfirm={() => { setConfirmPublish(false); form.post(`/admin/ceremonies/${ceremony.id}/announcement-settings`, { preserveScroll: true }); }} open={confirmPublish} title={`Publish version ${announcement.next_version}?`} tone="warning" />
            <Head title={`Announcement settings · ${ceremony.name}`} />
            <PageHeader
                backHref={`/admin/ceremonies/${ceremony.id}/announcements`}
                backLabel="Announcements"
                actions={<Link className="admin-button admin-button-secondary" href={`/admin/ceremonies/${ceremony.id}/announcement-batches`}>Batch generation</Link>}
                description="Publish reviewed wording, voice, and pacing as a new immutable version."
                eyebrow="Ceremony announcements"
                title={ceremony.name}
            />

            <ValidationSummary errors={{ ...form.errors, ...comparison.errors }} />

            <nav className="announcement-setup-map" aria-label="Announcement template setup">
                <a href="#wording"><span>1</span><strong>Choose wording</strong></a>
                <a href="#spoken-sections"><span>2</span><strong>Choose details</strong></a>
                <a href="#voice-pacing"><span>3</span><strong>Select voice</strong></a>
                <a href="#review-publish"><span>4</span><strong>Review and publish</strong></a>
            </nav>

            <form className="admin-form announcement-settings-form" onSubmit={publish}>
                <fieldset className="admin-form-section admin-card" id="wording">
                    <div className="admin-card-header">
                        <legend>1. Choose a starting format</legend>
                    </div>
                    <div className="announcement-quick-starts">
                        {QUICK_STARTS.map((preset) => (
                            <button key={preset.id} onClick={() => applyQuickStart(preset.values)} type="button">
                                <strong>{preset.label}</strong><span>{preset.description}</span>
                            </button>
                        ))}
                    </div>
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
                        <div className="admin-field admin-field-wide">
                            <label htmlFor="introduction_frequency">Introduction cadence</label>
                            <select id="introduction_frequency" onChange={(event) => form.setData('introduction_frequency', event.target.value)} value={form.data.introduction_frequency}>
                                <option value="first_per_program">First graduate in each course/program</option>
                                <option value="every_student">Every graduate</option>
                                <option value="never">Never</option>
                            </select>
                            <p className="admin-field-hint">Course openers are selected by the lowest eligible sequence number.</p>
                            <FieldError id="introduction-frequency-error" message={form.errors.introduction_frequency} />
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

                <fieldset className="admin-form-section admin-card" id="spoken-sections">
                    <div className="admin-card-header">
                        <legend>2. Included graduate details</legend>
                    </div>
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
                                <span>{label}</span>
                            </label>
                        ))}
                    </div>
                </fieldset>

                <fieldset className="admin-form-section admin-card" id="voice-pacing">
                    <div className="admin-card-header">
                        <legend>3. Announcer voice and speed</legend>
                    </div>
                    <div className="announcement-provider-grid">
                        {announcement.providers.map((item) => (
                            <label className={`announcement-provider-card${form.data.provider === item.value ? ' is-selected' : ''}`} key={item.value}>
                                <input checked={form.data.provider === item.value} name="provider" onChange={selectProvider} type="radio" value={item.value} />
                                <strong>{item.label}</strong>
                                <span>{item.description}</span>
                                <small>{item.readiness.configured ? 'Ready' : 'Setup required'}</small>
                            </label>
                        ))}
                    </div>
                    <div className="admin-form-grid">
                        <div className="admin-field">
                            <label htmlFor="default_voice">Default voice</label>
                            <select id="default_voice" onChange={selectVoice} value={form.data.default_voice}>
                                {isLocalProvider ? groupedVoices.map((group) => (
                                    <optgroup key={group.gender} label={group.gender === 'male' ? 'Male voices' : 'Female voices'}>
                                        {group.voices.map((voice) => <option key={voice.value} value={voice.value}>{voice.label} - {voice.accent}</option>)}
                                    </optgroup>
                                )) : voices.map((voice) => <option key={voice.value} value={voice.value}>{voice.label} · {voice.locale}</option>)}
                            </select>
                            {selectedVoice?.description && <p className="admin-field-hint">{selectedVoice.description}</p>}
                        </div>
                        <div className="admin-field">
                            <label htmlFor="speaking_rate_percent">Speaking rate (%)</label>
                            <input id="speaking_rate_percent" max={limits.speaking_rate_maximum} min={limits.speaking_rate_minimum} onChange={(event) => form.setData('speaking_rate_percent', Number(event.target.value))} type="number" value={form.data.speaking_rate_percent} />
                            <p className="admin-field-hint">Allowed: {limits.speaking_rate_minimum}% to +{limits.speaking_rate_maximum}%.</p>
                        </div>
                    </div>
                    <details className="announcement-advanced-settings">
                        <summary>Advanced timing and length limits</summary>
                        <div className="admin-form-grid" style={{ marginTop: '1rem' }}>
                            {!isLocalProvider && <div className="admin-field"><label htmlFor="pause_after_name_ms">Pause after name (ms)</label><input id="pause_after_name_ms" max={limits.pause_maximum} min={limits.pause_minimum} onChange={(event) => form.setData('pause_after_name_ms', Number(event.target.value))} type="number" value={form.data.pause_after_name_ms} /></div>}
                            {!isLocalProvider && <div className="admin-field"><label htmlFor="pause_between_sections_ms">Pause between sections (ms)</label><input id="pause_between_sections_ms" max={limits.pause_maximum} min={limits.pause_minimum} onChange={(event) => form.setData('pause_between_sections_ms', Number(event.target.value))} type="number" value={form.data.pause_between_sections_ms} /></div>}
                            <div className="admin-field"><label htmlFor="recommended_character_limit">Recommended characters</label><input id="recommended_character_limit" min="100" onChange={(event) => form.setData('recommended_character_limit', Number(event.target.value))} type="number" value={form.data.recommended_character_limit} /></div>
                            <div className="admin-field"><label htmlFor="maximum_character_limit">Maximum characters</label><input id="maximum_character_limit" min={form.data.recommended_character_limit} onChange={(event) => form.setData('maximum_character_limit', Number(event.target.value))} type="number" value={form.data.maximum_character_limit} /></div>
                        </div>
                    </details>
                </fieldset>

                <section className="announcement-review-step" id="review-publish">
                    <div className="admin-section-heading">
                        <div>
                            <h2>4. Review and publish</h2>
                        </div>
                    </div>
                    {!previewIsCurrent && <p className="announcement-notice announcement-notice-warning">The preview is out of date. Refresh it before publishing.</p>}
                    <FictionalPreview preview={preview} />
                    <div className="admin-form-actions">
                        <button className="admin-button admin-button-secondary" disabled={previewing} onClick={refreshPreview} type="button">{previewing ? 'Refreshing…' : previewIsCurrent ? 'Refresh preview' : 'Review my changes'}</button>
                        <button className="admin-button admin-button-primary" disabled={form.processing || !preview.ready || !previewIsCurrent} type="submit">{form.processing ? 'Publishing…' : `Publish version ${announcement.next_version}`}</button>
                    </div>
                    {previewError && <p className="announcement-notice announcement-notice-error" role="alert">{previewError}</p>}
                </section>
            </form>

            <section className="admin-detail-section announcement-voice-comparison admin-card" id="voice-comparison">
                <div className="admin-section-heading admin-card-header">
                    <div>
                        <h2>Compare approved voices</h2>
                    </div>
                    <StatusText label={provider.configured ? 'Provider ready' : 'Provider disabled'} value={provider.configured ? 'approved' : 'missing'} />
                </div>
                <div className="admin-card-body">
                    <form onSubmit={generateComparison}>
                        <div className="announcement-settings-switches">
                            {voices.map((voice) => (
                                <label className="admin-checkbox-row" key={voice.value}>
                                    <input checked={comparison.data.voices.includes(voice.value)} onChange={() => toggleComparisonVoice(voice.value)} type="checkbox" />
                                    <span>{voice.label} · {voice.gender ? `${voice.gender === 'male' ? 'Male' : 'Female'} · ` : ''}{voice.locale}</span>
                                </label>
                            ))}
                        </div>
                        {comparison.errors.voice_comparison && <p className="announcement-notice announcement-notice-error" role="alert">{comparison.errors.voice_comparison}</p>}
                        <div className="admin-form-actions">
                            <button className="admin-button admin-button-primary" disabled={comparison.processing || !provider.configured || !settings.id || form.isDirty || comparison.data.voices.length === 0} type="submit">{comparison.processing ? 'Generating samples…' : 'Generate temporary samples'}</button>
                        </div>
                    </form>

                    {form.data.provider === 'azure' && <div className="announcement-usage" style={{ marginTop: '1.5rem' }}>
                        <strong>{announcement.usage.successful_characters.toLocaleString()} estimated characters used this month</strong>
                        <span>{announcement.usage.estimated_remaining.toLocaleString()} estimated remaining of {announcement.usage.monthly_budget.toLocaleString()}</span>
                        <small>{announcement.usage.label}</small>
                    </div>}

                    {announcement.samples.length === 0 ? <p className="admin-media-missing" style={{ marginTop: '1.5rem' }}>No unexpired comparison samples.</p> : (
                        <div className="announcement-voice-sample-grid">
                            {announcement.samples.map((sample) => (
                                <article key={sample.id}>
                                    <strong>{voiceLabels[sample.voice] ?? sample.voice}</strong>
                                    <span>{sample.provider === 'kokoro' ? 'Local offline' : 'Azure online'}</span>
                                    <span>{sample.locale} · template v{sample.template.version}</span>
                                    <small>Expires {formatDateTime(sample.expires_at)}</small>
                                    <AudioPreview label={`Fictional voice sample using ${sample.voice}`} src={sample.audio_url} />
                                </article>
                            ))}
                        </div>
                    )}
                </div>
            </section>

            <section className="admin-detail-section admin-card">
                <div className="admin-section-heading admin-card-header"><div><h2>Published versions</h2></div></div>
                <div className="admin-card-body">
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
                </div>
            </section>
        </AdminLayout>
    );
}
