import { router, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { requestJson } from '../../lib/http';
import AudioPreview from './AudioPreview';
import StatusText from './StatusText';

function formatDateTime(value) {
    if (!value) return 'Not completed';

    return new Intl.DateTimeFormat('en-PH', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

function PreviewPanel({ preview }) {
    const lengthLabel = preview.length?.status?.replaceAll('_', ' ') ?? 'Unavailable';

    return (
        <div className="announcement-preview-panel" aria-live="polite">
            <div className="announcement-preview-meta">
                <span>{preview.character_count} characters</span>
                <span>{preview.award_count} spoken awards</span>
                <span>{lengthLabel}</span>
                {preview.duration && (
                    <span>
                        About {preview.duration.minimum_seconds}–
                        {preview.duration.maximum_seconds} seconds
                    </span>
                )}
            </div>
            <blockquote className="announcement-preview-copy">
                {preview.plain_text || 'No announcement can be composed yet.'}
            </blockquote>
            {preview.duration && (
                <p className="admin-field-hint">{preview.duration.label}</p>
            )}
            {preview.warnings.map((warning) => (
                <p className="announcement-notice announcement-notice-warning" key={warning.code}>
                    {warning.message}
                </p>
            ))}
            {preview.blocking_errors.map((error) => (
                <p className="announcement-notice announcement-notice-error" key={error.code} role="alert">
                    {error.message}
                </p>
            ))}
        </div>
    );
}

export default function AnnouncementWorkspace({ announcement, student }) {
    const [preview, setPreview] = useState(announcement.preview);
    const [templateId, setTemplateId] = useState(
        announcement.selected.template_id?.toString() ?? '',
    );
    const [voice, setVoice] = useState(announcement.selected.voice ?? '');
    const [previewing, setPreviewing] = useState(false);
    const [previewError, setPreviewError] = useState(null);
    const [editingAwardId, setEditingAwardId] = useState(null);
    const [reviewNotes, setReviewNotes] = useState({});
    const [reviewing, setReviewing] = useState(null);
    const awardForm = useForm({
        award_name: '',
        spoken_text: '',
        is_announcement_enabled: true,
    });
    const generationForm = useForm({
        announcement_template_id: announcement.selected.template_id ?? null,
        voice: announcement.selected.voice ?? null,
        force: false,
    });
    const selectedTemplate = useMemo(
        () => announcement.templates.find((item) => item.id.toString() === templateId),
        [announcement.templates, templateId],
    );

    function selectTemplate(event) {
        const nextId = event.target.value;
        const template = announcement.templates.find(
            (item) => item.id.toString() === nextId,
        );
        const nextVoice = template?.default_voice ?? voice;

        setTemplateId(nextId);
        setVoice(nextVoice);
        generationForm.setData((data) => ({
            ...data,
            announcement_template_id: nextId || null,
            voice: nextVoice || null,
        }));
    }

    function selectVoice(event) {
        setVoice(event.target.value);
        generationForm.setData('voice', event.target.value || null);
    }

    async function refreshPreview() {
        setPreviewing(true);
        setPreviewError(null);
        const query = new URLSearchParams();

        if (templateId) query.set('announcement_template_id', templateId);
        if (voice) query.set('voice', voice);

        try {
            const { response, data } = await requestJson(
                `/admin/students/${student.id}/announcement-preview?${query.toString()}`,
            );

            if (!response.ok) {
                setPreviewError(
                    data.message || 'The announcement preview could not be refreshed.',
                );
                return;
            }

            setPreview(data.preview);
        } catch {
            setPreviewError('The announcement preview could not be refreshed.');
        } finally {
            setPreviewing(false);
        }
    }

    function editAward(award) {
        setEditingAwardId(award.id);
        awardForm.setData({
            award_name: award.award_name,
            spoken_text: award.spoken_text ?? '',
            is_announcement_enabled: award.is_announcement_enabled,
        });
        awardForm.clearErrors();
    }

    function resetAwardForm() {
        setEditingAwardId(null);
        awardForm.reset();
        awardForm.clearErrors();
    }

    function submitAward(event) {
        event.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: resetAwardForm,
        };

        if (editingAwardId) {
            awardForm.put(
                `/admin/students/${student.id}/awards/${editingAwardId}`,
                options,
            );
            return;
        }

        awardForm.post(`/admin/students/${student.id}/awards`, options);
    }

    function moveAward(award, direction) {
        router.post(
            `/admin/students/${student.id}/awards/${award.id}/move`,
            { direction },
            { preserveScroll: true },
        );
    }

    function removeAward(award) {
        if (!window.confirm(`Remove “${award.award_name}” from this graduate?`)) return;

        router.delete(`/admin/students/${student.id}/awards/${award.id}`, {
            preserveScroll: true,
        });
    }

    function generate(force = false) {
        if (force && !window.confirm('Force a new Azure request even if an identical candidate exists?')) {
            return;
        }

        generationForm.transform((data) => ({ ...data, force })).post(
            `/admin/students/${student.id}/announcement-candidates`,
            {
                preserveScroll: true,
                onFinish: () => generationForm.transform((data) => data),
            },
        );
    }

    function reviewCandidate(generation, decision) {
        const notes = reviewNotes[generation.id] ?? '';

        if (decision === 'reject' && !notes.trim()) return;
        if (decision === 'approve' && !window.confirm(
            'Approve this candidate and replace the active stage audio?',
        )) return;

        setReviewing(`${generation.id}:${decision}`);
        router.post(
            `/admin/students/${student.id}/announcement-candidates/${generation.id}/${decision}`,
            { review_notes: notes },
            {
                preserveScroll: true,
                onFinish: () => setReviewing(null),
            },
        );
    }

    const generationDisabled =
        generationForm.processing ||
        previewing ||
        !preview.ready ||
        !announcement.provider?.configured;

    return (
        <section className="admin-detail-section announcement-workspace">
            <div className="admin-section-heading announcement-heading">
                <div>
                    <p className="announcement-kicker">Preparation only</p>
                    <h2>Automated announcement candidate</h2>
                    <p>
                        Compose and generate a private review candidate. This never
                        replaces the active stage audio or approves itself.
                    </p>
                </div>
                <StatusText
                    label={announcement.provider?.configured ? 'Provider ready' : 'Provider disabled'}
                    value={announcement.provider?.configured ? 'approved' : 'missing'}
                />
            </div>

            <div className="announcement-source-grid">
                <dl className="admin-detail-list admin-detail-list-compact">
                    <div><dt>Announcement name</dt><dd>{announcement.source.announcement_name}</dd></div>
                    <div><dt>TTS spoken name</dt><dd>{announcement.source.tts_spoken_name || 'Uses announcement name'}</dd></div>
                    <div><dt>Pronunciation guide</dt><dd>{announcement.source.pronunciation_guide || 'Not set'}</dd></div>
                    <div><dt>Program</dt><dd>{announcement.source.program}</dd></div>
                    <div><dt>Major</dt><dd>{announcement.source.major || 'Not set'}</dd></div>
                    <div><dt>Honors</dt><dd>{announcement.source.honors || 'Not set'}</dd></div>
                    <div><dt>Additional distinction</dt><dd>{announcement.source.additional_text || 'Not set'}</dd></div>
                    <div><dt>Active audio source</dt><dd>{announcement.source.active_audio_source?.replaceAll('_', ' ') || 'None'}</dd></div>
                </dl>
            </div>

            <div className="announcement-awards-editor">
                <div className="admin-section-heading">
                    <div>
                        <h3>Structured awards</h3>
                        <p>Only enabled awards enter the spoken announcement.</p>
                    </div>
                    <span>{announcement.awards.length} recorded</span>
                </div>

                {announcement.awards.length === 0 ? (
                    <p className="admin-media-missing">No structured awards recorded.</p>
                ) : (
                    <ol className="announcement-award-list">
                        {announcement.awards.map((award) => (
                            <li key={award.id}>
                                <div className="announcement-award-copy">
                                    <strong>{award.award_name}</strong>
                                    <span>{award.spoken_text || 'Uses the award name'}</span>
                                    <small>{award.is_announcement_enabled ? 'Included in speech' : 'Not spoken'}</small>
                                </div>
                                {announcement.permissions.manage_awards && (
                                    <div className="announcement-award-actions">
                                        <button aria-label={`Move ${award.award_name} up`} className="admin-button admin-button-secondary" disabled={!award.can_move_up} onClick={() => moveAward(award, 'up')} type="button">↑</button>
                                        <button aria-label={`Move ${award.award_name} down`} className="admin-button admin-button-secondary" disabled={!award.can_move_down} onClick={() => moveAward(award, 'down')} type="button">↓</button>
                                        <button className="admin-button admin-button-secondary" onClick={() => editAward(award)} type="button">Edit</button>
                                        <button className="admin-button admin-button-danger" onClick={() => removeAward(award)} type="button">Remove</button>
                                    </div>
                                )}
                            </li>
                        ))}
                    </ol>
                )}

                {announcement.permissions.manage_awards && (
                    <form className="announcement-award-form" onSubmit={submitAward}>
                        <div className="admin-field">
                            <label htmlFor="award_name">Award name</label>
                            <input id="award_name" maxLength="255" onChange={(event) => awardForm.setData('award_name', event.target.value)} required value={awardForm.data.award_name} />
                            {awardForm.errors.award_name && <p className="admin-field-error" role="alert">{awardForm.errors.award_name}</p>}
                        </div>
                        <div className="admin-field">
                            <label htmlFor="spoken_text">Spoken phrase</label>
                            <input id="spoken_text" maxLength="500" onChange={(event) => awardForm.setData('spoken_text', event.target.value)} placeholder="the Best in Research Award" value={awardForm.data.spoken_text} />
                            {awardForm.errors.spoken_text && <p className="admin-field-error" role="alert">{awardForm.errors.spoken_text}</p>}
                        </div>
                        <label className="admin-checkbox-row">
                            <input checked={awardForm.data.is_announcement_enabled} onChange={(event) => awardForm.setData('is_announcement_enabled', event.target.checked)} type="checkbox" />
                            Include in spoken announcement
                        </label>
                        <div className="admin-form-actions">
                            <button className="admin-button admin-button-primary" disabled={awardForm.processing} type="submit">
                                {editingAwardId ? 'Save award' : 'Add award'}
                            </button>
                            {editingAwardId && <button className="admin-button admin-button-secondary" onClick={resetAwardForm} type="button">Cancel edit</button>}
                        </div>
                    </form>
                )}
            </div>

            <div className="announcement-composer">
                <div className="announcement-selection-grid">
                    <div className="admin-field">
                        <label htmlFor="announcement_template">Template</label>
                        <select id="announcement_template" onChange={selectTemplate} value={templateId}>
                            <option value="">Use active ceremony template</option>
                            {announcement.templates.map((template) => (
                                <option key={template.id} value={template.id}>
                                    {template.name} · v{template.version}{template.is_active ? ' · active' : ''}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div className="admin-field">
                        <label htmlFor="announcement_voice">Voice</label>
                        <select id="announcement_voice" onChange={selectVoice} value={voice}>
                            {announcement.voices.map((item) => (
                                <option key={item.value} value={item.value}>{item.label} · {item.locale}</option>
                            ))}
                        </select>
                    </div>
                </div>
                {selectedTemplate && !selectedTemplate.is_active && (
                    <p className="announcement-notice announcement-notice-warning">
                        This is not the ceremony’s active template. The generated candidate will preserve this version for review.
                    </p>
                )}
                <div className="admin-form-actions">
                    <button className="admin-button admin-button-secondary" disabled={previewing} onClick={refreshPreview} type="button">
                        {previewing ? 'Refreshing preview…' : 'Preview announcement'}
                    </button>
                </div>
                {previewError && <p className="announcement-notice announcement-notice-error" role="alert">{previewError}</p>}
                <PreviewPanel preview={preview} />

                {announcement.usage && (
                    <div className="announcement-usage">
                        <strong>{announcement.usage.successful_characters.toLocaleString()} characters generated this month</strong>
                        <span>{announcement.usage.estimated_remaining.toLocaleString()} estimated remaining of {announcement.usage.monthly_budget.toLocaleString()}</span>
                        <small>{announcement.usage.label}</small>
                    </div>
                )}

                {generationForm.errors.announcement_generation && (
                    <p className="announcement-notice announcement-notice-error" role="alert">
                        {generationForm.errors.announcement_generation}
                    </p>
                )}
                {!announcement.provider?.configured && (
                    <p className="announcement-notice announcement-notice-warning">
                        Azure Speech is disabled or not configured. Text preview remains available and ceremony playback is unaffected.
                    </p>
                )}
                <div className="admin-form-actions">
                    <button className="admin-button admin-button-primary" disabled={generationDisabled} onClick={() => generate(false)} type="button">
                        {generationForm.processing ? 'Generating…' : 'Generate candidate'}
                    </button>
                    {announcement.permissions.force_generate && (
                        <button className="admin-button admin-button-secondary" disabled={generationDisabled} onClick={() => generate(true)} type="button">
                            Force regenerate
                        </button>
                    )}
                </div>
            </div>

            <div className="announcement-history">
                <div className="admin-section-heading">
                    <div><h3>Generation history</h3><p>Private candidates remain separate from active ceremony audio.</p></div>
                </div>
                {announcement.generations.length === 0 ? (
                    <p className="admin-media-missing">No candidates generated yet.</p>
                ) : (
                    <div className="announcement-generation-list">
                        {announcement.generations.map((generation) => (
                            <article key={generation.id}>
                                <div className="admin-section-heading">
                                    <div>
                                        <strong>Candidate #{generation.id}</strong>
                                        <p>{generation.template ? `${generation.template.name} · v${generation.template.version}` : 'Template unavailable'} · {generation.voice}</p>
                                        {generation.is_active_audio && <small>Current active audio</small>}
                                    </div>
                                    <StatusText label={generation.status_label} value={generation.status} />
                                </div>
                                <p>{generation.plain_text}</p>
                                <dl className="admin-detail-list admin-detail-list-compact">
                                    <div><dt>Characters</dt><dd>{generation.character_count ?? 'Not recorded'}</dd></div>
                                    <div><dt>Attempts</dt><dd>{generation.attempt_count}</dd></div>
                                    <div><dt>Generated</dt><dd>{formatDateTime(generation.generated_at || generation.created_at)}</dd></div>
                                    <div><dt>Requested by</dt><dd>{generation.generated_by || 'Deleted user'}</dd></div>
                                    <div><dt>Reviewed</dt><dd>{formatDateTime(generation.reviewed_at)}</dd></div>
                                    <div><dt>Reviewer</dt><dd>{generation.reviewed_by || 'Not reviewed'}</dd></div>
                                </dl>
                                {generation.review_notes && <p className="admin-field-hint">Review note: {generation.review_notes}</p>}
                                {generation.failure_message && <p className="announcement-notice announcement-notice-error">{generation.failure_message}</p>}
                                {generation.candidate_preview_url && (
                                    <AudioPreview label={`Private announcement candidate ${generation.id} for ${student.display_name}`} src={generation.candidate_preview_url} />
                                )}
                                {announcement.permissions.review && generation.can_review && (
                                    <div className="announcement-review-controls">
                                        <div className="admin-field">
                                            <label htmlFor={`candidate-review-${generation.id}`}>Review notes</label>
                                            <textarea
                                                id={`candidate-review-${generation.id}`}
                                                maxLength="1000"
                                                onChange={(event) => setReviewNotes((current) => ({
                                                    ...current,
                                                    [generation.id]: event.target.value,
                                                }))}
                                                placeholder="Required when rejecting"
                                                rows="3"
                                                value={reviewNotes[generation.id] ?? ''}
                                            />
                                        </div>
                                        <div className="admin-form-actions">
                                            <button
                                                className="admin-button admin-button-primary"
                                                disabled={reviewing !== null}
                                                onClick={() => reviewCandidate(generation, 'approve')}
                                                type="button"
                                            >
                                                {reviewing === `${generation.id}:approve` ? 'Approving…' : 'Approve candidate'}
                                            </button>
                                            <button
                                                className="admin-button admin-button-danger"
                                                disabled={reviewing !== null || !(reviewNotes[generation.id] ?? '').trim()}
                                                onClick={() => reviewCandidate(generation, 'reject')}
                                                type="button"
                                            >
                                                {reviewing === `${generation.id}:reject` ? 'Rejecting…' : 'Reject candidate'}
                                            </button>
                                        </div>
                                    </div>
                                )}
                            </article>
                        ))}
                    </div>
                )}
            </div>
        </section>
    );
}
