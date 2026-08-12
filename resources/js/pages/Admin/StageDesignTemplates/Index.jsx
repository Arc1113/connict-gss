import { Head, Link, useForm } from '@inertiajs/react';
import { useRef, useState } from 'react';
import ConfirmDialog from '../../../components/ConfirmDialog';
import AdminLayout from '../../../components/Admin/AdminLayout';
import { FieldError, ValidationSummary } from '../../../components/Admin/FormSupport';
import PageHeader from '../../../components/Admin/PageHeader';
import StageThemePreview from '../../../components/Admin/StageThemePreview';
import { DEFAULT_STAGE_THEME } from '../../../lib/stageTheme';

function blankDesign() {
    return {
        name: 'New Custom Design',
        description: '',
        theme: { ...DEFAULT_STAGE_THEME },
    };
}

export default function StageDesignTemplateIndex({ templates }) {
    const [selectedId, setSelectedId] = useState(null);
    const editorRef = useRef(null);
    const form = useForm(blankDesign());
    const editing = selectedId !== null;
    const [confirmDelete, setConfirmDelete] = useState(false);

    function selectTemplate(template) {
        setSelectedId(template.id);
        form.clearErrors();
        form.setData({
            name: template.name,
            description: template.description ?? '',
            theme: { ...DEFAULT_STAGE_THEME, ...template.theme },
        });
        editorRef.current?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function startNew() {
        setSelectedId(null);
        form.clearErrors();
        form.setData(blankDesign());
        editorRef.current?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        setTimeout(() => {
            document.getElementById('stage_design_name')?.focus();
        }, 100);
    }

    function setTheme(field, value) {
        form.setData('theme', { ...form.data.theme, [field]: value });
    }

    function save(event) {
        event.preventDefault();
        if (editing) {
            form.put(`/admin/stage-designs/${selectedId}`, { preserveScroll: true });
            return;
        }
        form.post('/admin/stage-designs', {
            preserveScroll: true,
            onSuccess: () => startNew(),
        });
    }

    function remove() {
        if (!editing) return;
        form.delete(`/admin/stage-designs/${selectedId}`, {
            preserveScroll: true,
            onSuccess: () => startNew(),
        });
    }

    return (
        <AdminLayout>
            <ConfirmDialog busy={form.processing} confirmLabel="Delete design" description={`This removes “${form.data.name}” from the reusable library. Ceremonies that already used it keep their saved copy.`} onCancel={() => setConfirmDelete(false)} onConfirm={() => { setConfirmDelete(false); remove(); }} open={confirmDelete} title={`Delete ${form.data.name}?`} tone="danger" />
            <Head title="Stage designs" />
            <PageHeader
                actions={
                    <div className="admin-page-actions-group">
                        <button className="admin-button admin-button-primary" onClick={startNew} type="button">
                            + Create new design
                        </button>
                        <Link className="admin-button admin-button-secondary" href="/admin/ceremonies">Open ceremonies</Link>
                    </div>
                }
                description="Manage and customize audience display templates."
                eyebrow="Reusable templates"
                title="Stage designs"
            />

            <div className="stage-design-workspace">
                <aside className="stage-design-library" aria-label="Saved stage designs">
                    <header>
                        <div><strong>Design library</strong><span>{templates.length} saved</span></div>
                        <button className="admin-button admin-button-primary admin-button-sm" onClick={startNew} type="button">+ New design</button>
                    </header>
                    {templates.length === 0 && <p className="admin-media-missing">No saved designs yet. Create your first stage design above.</p>}
                    {templates.map((template) => (
                        <button
                            className={`stage-design-library-item${selectedId === template.id ? ' is-selected' : ''}`}
                            key={template.id}
                            onClick={() => selectTemplate(template)}
                            type="button"
                        >
                            <span className="stage-design-swatch" style={{ background: template.theme.background_color, borderColor: template.theme.accent_color }} />
                            <span><strong>{template.name}</strong><small>{template.description || 'No description'}</small></span>
                        </button>
                    ))}
                </aside>

                <main className="stage-design-editor" ref={editorRef}>
                    <ValidationSummary errors={form.errors} />
                    <div className="admin-section-heading">
                        <div>
                            <span className={`admin-status-badge ${editing ? 'admin-status-info' : 'admin-status-success'}`}>
                                {editing ? 'Editing template' : 'Creating new design'}
                            </span>
                            <h2>{editing ? form.data.name : (form.data.name || 'New Stage Design')}</h2>
                        </div>
                    </div>

                    <div className="stage-design-preview-container">
                        <StageThemePreview theme={form.data.theme} />
                    </div>

                    <form className="admin-form" onSubmit={save}>
                        <div className="admin-form-grid">
                            <div className="admin-field">
                                <label htmlFor="stage_design_name">Design name</label>
                                <input id="stage_design_name" maxLength="255" onChange={(event) => form.setData('name', event.target.value)} required value={form.data.name} />
                                <FieldError id="stage-design-name-error" message={form.errors.name} />
                            </div>
                            <div className="admin-field admin-field-wide">
                                <label htmlFor="stage_design_description">Description / Usage note</label>
                                <input id="stage_design_description" maxLength="500" onChange={(event) => form.setData('description', event.target.value)} placeholder="e.g. Commencement stage display for main campus" value={form.data.description} />
                                <FieldError id="stage-design-description-error" message={form.errors.description} />
                            </div>
                            <div className="admin-field">
                                <label htmlFor="stage_design_preset">Typography treatment</label>
                                <select id="stage_design_preset" onChange={(event) => setTheme('preset', event.target.value)} value={form.data.theme.preset}><option value="institutional">Institutional</option><option value="modern">Modern minimal</option><option value="classic">Classic ceremony</option></select>
                            </div>
                            <div className="admin-field">
                                <label htmlFor="stage_design_layout">Graduate layout</label>
                                <select id="stage_design_layout" onChange={(event) => setTheme('layout', event.target.value)} value={form.data.theme.layout}><option value="centered">Centered</option><option value="split">Portrait split</option><option value="banner">Lower banner</option></select>
                            </div>
                            {[
                                ['background_color', 'Background'], ['surface_color', 'Surface'], ['text_color', 'Text'], ['accent_color', 'Accent'],
                            ].map(([field, label]) => <div className="admin-field stage-theme-color" key={field}><label htmlFor={`design_${field}`}>{label} color</label><div><input id={`design_${field}`} onChange={(event) => setTheme(field, event.target.value)} type="color" value={form.data.theme[field]} /><code>{form.data.theme[field]}</code></div><FieldError id={`design-${field}-error`} message={form.errors[`theme.${field}`]} /></div>)}
                            <label className="stage-theme-checkbox"><input checked={Boolean(form.data.theme.show_photo)} onChange={(event) => setTheme('show_photo', event.target.checked)} type="checkbox" />Show graduate portrait when available</label>
                        </div>
                        <div className="admin-form-actions">
                            <button className="admin-button admin-button-primary" disabled={form.processing} type="submit">{form.processing ? 'Saving…' : editing ? 'Save design changes' : 'Save new design'}</button>
                            {editing && <button className="admin-button admin-button-danger" disabled={form.processing} onClick={() => setConfirmDelete(true)} type="button">Delete design</button>}
                        </div>
                    </form>
                </main>
            </div>
        </AdminLayout>
    );
}
