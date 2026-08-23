import { Head, router, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import AdminLayout from '../../../components/Admin/AdminLayout';
import { FieldError, ValidationSummary } from '../../../components/Admin/FormSupport';
import PageHeader from '../../../components/Admin/PageHeader';
import StatusText from '../../../components/Admin/StatusText';
import { requestJson } from '../../../lib/http';

function emptyVariants(providers) {
    return providers.map((provider) => ({
        provider: provider.value,
        locale: '',
        voice: '',
        replacement_text: '',
        is_enabled: true,
    }));
}

function hasAdvancedVariants(rule, providers) {
    if (!rule) return false;

    const enabled = rule.variants.filter((variant) => variant.is_enabled);

    return enabled.length !== providers.length
        || enabled.some((variant) => variant.locale || variant.voice)
        || new Set(enabled.map((variant) => variant.replacement_text)).size > 1;
}

function sharedReplacement(rule) {
    const enabled = rule?.variants?.filter((variant) => variant.is_enabled) ?? [];

    return enabled.length > 0 && new Set(enabled.map((variant) => variant.replacement_text)).size === 1
        ? enabled[0].replacement_text
        : '';
}

function RuleEditor({ ceremonies, editing, onCancel, permissions, providers, sections }) {
    const canUseCommon = permissions.manage_common;
    const startsAdvanced = hasAdvancedVariants(editing, providers);
    const [advanced, setAdvanced] = useState(startsAdvanced);
    const form = useForm({
        ceremony_id: editing?.ceremony_id ?? (canUseCommon ? null : ceremonies[0]?.id ?? null),
        section: editing?.section ?? 'any',
        match_text: editing?.match_text ?? '',
        spoken_text: sharedReplacement(editing),
        is_enabled: editing?.is_enabled ?? true,
        variants: editing?.variants?.length ? editing.variants : emptyVariants(providers),
    });
    const [preview, setPreview] = useState(null);
    const [previewing, setPreviewing] = useState(false);
    const [previewError, setPreviewError] = useState(null);

    const selectedProvider = providers.find(
        (provider) => provider.value === form.data.variants[0]?.provider,
    );

    function updateVariant(index, field, value) {
        form.setData('variants', form.data.variants.map((variant, current) => (
            current === index ? { ...variant, [field]: value } : variant
        )));
    }

    function effectiveVariants() {
        return advanced
            ? form.data.variants
            : providers.map((provider) => ({
                provider: provider.value,
                locale: '',
                voice: '',
                replacement_text: form.data.spoken_text,
                is_enabled: true,
            }));
    }

    function submit(event) {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: onCancel };
        form.transform((data) => ({ ...data, variants: effectiveVariants() }));
        if (editing) form.put(`/admin/pronunciation-rules/${editing.id}`, options);
        else form.post('/admin/pronunciation-rules', options);
    }

    async function runPreview() {
        const variant = effectiveVariants().find((item) => item.is_enabled && item.replacement_text);
        if (!variant || !form.data.match_text) {
            setPreviewError('Enter a phrase and at least one replacement first.');
            return;
        }

        setPreviewing(true);
        setPreviewError(null);
        try {
            const voice = variant.voice || providers.find((item) => item.value === variant.provider)?.voices[0]?.value || '';
            const locale = variant.locale || providers.find((item) => item.value === variant.provider)?.voices.find((item) => item.value === voice)?.locale || 'en-US';
            const { response, data } = await requestJson('/admin/pronunciation-rules/preview', {
                method: 'POST',
                body: JSON.stringify({
                    text: form.data.match_text,
                    section: form.data.section,
                    ceremony_id: form.data.ceremony_id,
                    provider: variant.provider,
                    locale,
                    voice,
                    candidate_match_text: form.data.match_text,
                    candidate_replacement_text: variant.replacement_text,
                }),
            });
            if (!response.ok) throw new Error(data.message || 'Preview failed.');
            setPreview(data.preview);
        } catch (error) {
            setPreviewError(error.message || 'The preview could not be prepared.');
        } finally {
            setPreviewing(false);
        }
    }

    return (
        <form className="pronunciation-editor" onSubmit={submit} data-tour="pronunciation-editor">
            <div className="admin-section-heading">
                <div>
                    <p className="announcement-kicker">{editing ? `Revision ${editing.revision + 1}` : 'Simple pronunciation rule'}</p>
                    <h2>{editing ? 'Edit pronunciation' : 'Add pronunciation'}</h2>
                    <p>Keep the display spelling. Only generated speech uses your wording.</p>
                </div>
            </div>
            <ValidationSummary errors={form.errors} />
            <div className="admin-form-grid">
                <div className="admin-field admin-field-wide">
                    <label htmlFor="pronunciation_match">Phrase as written</label>
                    <input id="pronunciation_match" maxLength="255" onChange={(event) => form.setData('match_text', event.target.value)} required value={form.data.match_text} />
                    <small>Matching is case-insensitive and uses complete words or phrases.</small>
                    <FieldError message={form.errors.match_text} />
                </div>
                <div className="admin-field admin-field-wide">
                    <label htmlFor="pronunciation_spoken">How it should sound</label>
                    <input id="pronunciation_spoken" maxLength="255" onChange={(event) => form.setData('spoken_text', event.target.value)} required={!advanced} value={form.data.spoken_text} />
                    <small>This is applied to Azure Speech and Local Kokoro unless you use Advanced settings.</small>
                    <FieldError message={form.errors['variants.0.replacement_text']} />
                </div>
            </div>

            <details className="pronunciation-advanced" onToggle={(event) => setAdvanced(event.currentTarget.open)} open={advanced}>
                <summary>Advanced settings</summary>
                <p>Use these only when a rule should apply to one ceremony, section, provider, or voice.</p>
                <div className="admin-form-grid pronunciation-advanced-scope">
                    <div className="admin-field">
                        <label htmlFor="pronunciation_scope">Apply to</label>
                        <select id="pronunciation_scope" onChange={(event) => form.setData('ceremony_id', event.target.value === '' ? null : Number(event.target.value))} value={form.data.ceremony_id ?? ''}>
                            {canUseCommon && <option value="">Every ceremony</option>}
                            {ceremonies.map((ceremony) => <option key={ceremony.id} value={ceremony.id}>{ceremony.name}</option>)}
                        </select>
                        <FieldError message={form.errors.ceremony_id} />
                    </div>
                    <div className="admin-field">
                        <label htmlFor="pronunciation_section">Announcement section</label>
                        <select id="pronunciation_section" onChange={(event) => form.setData('section', event.target.value)} value={form.data.section}>
                            {sections.map((section) => <option key={section.value} value={section.value}>{section.label}</option>)}
                        </select>
                    </div>
                </div>
                <fieldset className="pronunciation-variants">
                    <legend>Provider-specific pronunciation</legend>
                    {form.data.variants.map((variant, index) => {
                    const provider = providers.find((item) => item.value === variant.provider) ?? selectedProvider;
                    return (
                        <div className="pronunciation-variant" key={`${variant.provider}-${index}`}>
                            <div className="admin-field">
                                <label htmlFor={`variant-provider-${index}`}>Provider</label>
                                <select id={`variant-provider-${index}`} onChange={(event) => updateVariant(index, 'provider', event.target.value)} value={variant.provider}>
                                    {providers.map((item) => <option key={item.value} value={item.value}>{item.label}</option>)}
                                </select>
                            </div>
                            <div className="admin-field pronunciation-replacement">
                                <label htmlFor={`variant-replacement-${index}`}>Spoken replacement</label>
                                <input id={`variant-replacement-${index}`} maxLength="255" onChange={(event) => updateVariant(index, 'replacement_text', event.target.value)} required value={variant.replacement_text} />
                                <FieldError message={form.errors[`variants.${index}.replacement_text`]} />
                            </div>
                            <div className="admin-field">
                                <label htmlFor={`variant-voice-${index}`}>Voice restriction</label>
                                <select id={`variant-voice-${index}`} onChange={(event) => {
                                    const voice = provider?.voices.find((item) => item.value === event.target.value);
                                    updateVariant(index, 'voice', event.target.value);
                                    updateVariant(index, 'locale', voice?.locale ?? '');
                                }} value={variant.voice}>
                                    <option value="">Every {provider?.label} voice</option>
                                    {provider?.voices.map((voice) => <option key={voice.value} value={voice.value}>{voice.value} · {voice.locale}</option>)}
                                </select>
                            </div>
                        </div>
                    );
                    })}
                </fieldset>

                <label className="admin-checkbox-row">
                    <input checked={form.data.is_enabled} onChange={(event) => form.setData('is_enabled', event.target.checked)} type="checkbox" />
                    Apply this rule to new previews and generation
                </label>
            </details>

            {(preview || previewError) && (
                <div className="pronunciation-before-after" aria-live="polite">
                    {previewError ? <p role="alert">{previewError}</p> : <>
                        <div><span>Display</span><strong>{preview.display_text}</strong></div>
                        <div><span>Speech</span><strong>{preview.speech_text}</strong></div>
                        {preview.audio_url && <audio controls preload="metadata" src={preview.audio_url}>Your browser cannot play this private preview.</audio>}
                        {preview.warnings.map((warning) => <small key={warning}>{warning}</small>)}
                    </>}
                </div>
            )}

            <div className="admin-form-actions">
                <button className="admin-button admin-button-secondary" disabled={previewing} onClick={runPreview} type="button">
                    {previewing ? 'Checking…' : 'Preview transformation'}
                </button>
                <button className="admin-button admin-button-primary" disabled={form.processing} type="submit">
                    {form.processing ? 'Saving…' : 'Save pronunciation'}
                </button>
                <button className="admin-button admin-button-text" onClick={onCancel} type="button">Cancel</button>
            </div>
        </form>
    );
}

export default function PronunciationRuleIndex({ ceremonies, filters, permissions, providers, rules, sections }) {
    const [editing, setEditing] = useState(null);
    const [adding, setAdding] = useState(false);
    const [search, setSearch] = useState(filters.search ?? '');
    const enabledCount = useMemo(() => rules.filter((rule) => rule.is_enabled).length, [rules]);
    const filterVoices = useMemo(() => providers
        .filter((provider) => !filters.provider || provider.value === filters.provider)
        .flatMap((provider) => provider.voices.map((voice) => ({ ...voice, provider: provider.value }))), [filters.provider, providers]);

    function filter(field, value) {
        const nextFilters = { ...filters, search, [field]: value || undefined };
        if (field === 'provider') {
            nextFilters.voice = undefined;
            nextFilters.locale = undefined;
        }
        router.get('/admin/pronunciation-rules', nextFilters, { preserveState: true, replace: true });
    }

    return (
        <AdminLayout>
            <Head title="Pronunciation dictionary" />
            <PageHeader
                actions={(permissions.manage_common || (permissions.manage_ceremony && ceremonies.length > 0)) && <button className="admin-button admin-button-primary" onClick={() => { setEditing(null); setAdding(true); }} type="button">Add pronunciation</button>}
                eyebrow="Voice preparation"
                title="Pronunciation dictionary"
            />

            <p className="pronunciation-count">{enabledCount} active rules</p>

            {(adding || editing) && <RuleEditor ceremonies={ceremonies} editing={editing} key={editing?.id ?? 'new'} onCancel={() => { setAdding(false); setEditing(null); }} permissions={permissions} providers={providers} sections={sections} />}

            <section className="admin-detail-section" data-tour="pronunciation-rule-list">
                <div className="pronunciation-filters">
                    <div className="admin-field pronunciation-search">
                        <label htmlFor="pronunciation_search">Search dictionary</label>
                        <input data-page-search id="pronunciation_search" onChange={(event) => setSearch(event.target.value)} onKeyDown={(event) => { if (event.key === 'Enter') filter('search', search); }} placeholder="Phrase or spoken replacement" type="search" value={search} />
                    </div>
                    <div className="admin-field">
                        <label htmlFor="pronunciation_scope_filter">Scope</label>
                        <select id="pronunciation_scope_filter" onChange={(event) => filter('ceremony', event.target.value)} value={filters.ceremony ?? ''}>
                            <option value="">All scopes</option><option value="common">Common</option>
                            {ceremonies.map((ceremony) => <option key={ceremony.id} value={ceremony.id}>{ceremony.name}</option>)}
                        </select>
                    </div>
                    <details className="pronunciation-filter-more">
                        <summary>More filters</summary>
                        <div>
                            <div className="admin-field">
                                <label htmlFor="pronunciation_section_filter">Section</label>
                                <select id="pronunciation_section_filter" onChange={(event) => filter('section', event.target.value)} value={filters.section ?? ''}>
                                    <option value="">All sections</option>
                                    {sections.map((section) => <option key={section.value} value={section.value}>{section.label}</option>)}
                                </select>
                            </div>
                            <div className="admin-field">
                                <label htmlFor="pronunciation_provider_filter">Provider</label>
                                <select id="pronunciation_provider_filter" onChange={(event) => filter('provider', event.target.value)} value={filters.provider ?? ''}>
                                    <option value="">All providers</option>
                                    {providers.map((provider) => <option key={provider.value} value={provider.value}>{provider.label}</option>)}
                                </select>
                            </div>
                            <div className="admin-field">
                                <label htmlFor="pronunciation_voice_filter">Voice</label>
                                <select id="pronunciation_voice_filter" onChange={(event) => filter('voice', event.target.value)} value={filters.voice ?? ''}>
                                    <option value="">All voice restrictions</option>
                                    {filterVoices.map((voice) => <option key={`${voice.provider}-${voice.value}`} value={voice.value}>{voice.value}</option>)}
                                </select>
                            </div>
                            <div className="admin-field">
                                <label htmlFor="pronunciation_status_filter">Status</label>
                                <select id="pronunciation_status_filter" onChange={(event) => filter('status', event.target.value)} value={filters.status ?? ''}>
                                    <option value="">All statuses</option><option value="enabled">Enabled</option><option value="disabled">Disabled</option>
                                </select>
                            </div>
                        </div>
                    </details>
                </div>

                {rules.length === 0 ? (
                    <div className="admin-empty-state"><h2>No matching pronunciation rules</h2><p>Clear the filters or add a reviewed ceremony pronunciation.</p></div>
                ) : (
                    <div className="pronunciation-rule-list">
                        {rules.map((rule) => {
                            const canEdit = rule.scope === 'common' ? permissions.manage_common : permissions.manage_ceremony;
                            const replacement = sharedReplacement(rule);
                            return (
                                <article className="pronunciation-rule-row" key={rule.id}>
                                    <div className="pronunciation-rule-copy">
                                        <span>{rule.scope === 'common' ? 'Common' : rule.ceremony} · {sections.find((section) => section.value === rule.section)?.label}</span>
                                        <h2>{rule.match_text}</h2>
                                        <p>{replacement ? `Speaks as ${replacement}` : rule.variants.filter((variant) => variant.is_enabled).map((variant) => `${variant.provider}: ${variant.replacement_text}`).join(' · ')}</p>
                                    </div>
                                    <div className="pronunciation-rule-state">
                                        <StatusText label={rule.is_enabled ? 'Enabled' : 'Disabled'} value={rule.is_enabled ? 'approved' : 'outdated'} />
                                        {rule.usage_count > 0 && <small>{rule.usage_count} used</small>}
                                    </div>
                                    {canEdit && <button className="admin-button admin-button-secondary" onClick={() => { setAdding(false); setEditing(rule); window.scrollTo({ top: 0, behavior: 'smooth' }); }} type="button">Edit</button>}
                                </article>
                            );
                        })}
                    </div>
                )}
            </section>
        </AdminLayout>
    );
}
