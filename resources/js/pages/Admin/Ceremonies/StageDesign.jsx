import { Head, Link, usePage } from '@inertiajs/react';
import AdminLayout from '../../../components/Admin/AdminLayout';
import CeremonyContextHeader from '../../../components/Admin/CeremonyContextHeader';
import CeremonyWorkspaceNav from '../../../components/Admin/CeremonyWorkspaceNav';
import StageThemePreview from '../../../components/Admin/StageThemePreview';

function settingLabel(value, fallback = 'Not set') {
    return value || fallback;
}

export default function CeremonyStageDesign({ ceremony, stageDesign }) {
    const { auth } = usePage().props;
    const theme = ceremony.stage_theme ?? {};

    return (
        <AdminLayout>
            <Head title={`Stage Design · ${ceremony.name}`} />

            <CeremonyContextHeader
                actions={
                    <>
                        <Link
                            className="admin-button admin-button-primary"
                            href={stageDesign.setup_url}
                        >
                            Edit stage setup
                        </Link>
                        {auth.abilities.manage_stage_designs && (
                            <Link
                                className="admin-button admin-button-secondary"
                                href={stageDesign.library_url}
                            >
                                Manage reusable design library
                            </Link>
                        )}
                    </>
                }
                ceremony={ceremony}
            />

            <CeremonyWorkspaceNav ceremony={ceremony} />

            <section className="ceremony-workspace-panel">
                <div className="admin-section-heading">
                    <div>
                        <h2>Current ceremony stage design</h2>
                        <p>
                            This preview reflects the appearance saved on this ceremony.
                            Reusable templates stay available in the separate library.
                        </p>
                    </div>
                </div>

                <div className="stage-design-workspace">
                    <div className="stage-design-editor">
                        <StageThemePreview
                            backgroundUrl={theme.background_url}
                            logoUrl={theme.logo_url}
                            theme={theme}
                        />
                    </div>

                    <aside className="admin-detail-section">
                        <h2>Applied settings</h2>
                        <dl className="admin-detail-list admin-detail-list-compact">
                            <div>
                                <dt>Typography treatment</dt>
                                <dd>{settingLabel(theme.preset)}</dd>
                            </div>
                            <div>
                                <dt>Graduate layout</dt>
                                <dd>{settingLabel(theme.layout)}</dd>
                            </div>
                            <div>
                                <dt>Show graduate portrait</dt>
                                <dd>{theme.show_photo ? 'Enabled' : 'Hidden'}</dd>
                            </div>
                            <div>
                                <dt>Logo</dt>
                                <dd>{theme.logo_url ? 'Configured' : 'Not configured'}</dd>
                            </div>
                            <div>
                                <dt>Background image</dt>
                                <dd>
                                    {theme.background_url
                                        ? 'Configured'
                                        : 'Using color only'}
                                </dd>
                            </div>
                            <div>
                                <dt>Background color</dt>
                                <dd>{settingLabel(theme.background_color)}</dd>
                            </div>
                            <div>
                                <dt>Surface color</dt>
                                <dd>{settingLabel(theme.surface_color)}</dd>
                            </div>
                            <div>
                                <dt>Text color</dt>
                                <dd>{settingLabel(theme.text_color)}</dd>
                            </div>
                            <div>
                                <dt>Accent color</dt>
                                <dd>{settingLabel(theme.accent_color)}</dd>
                            </div>
                        </dl>
                    </aside>
                </div>
            </section>
        </AdminLayout>
    );
}
