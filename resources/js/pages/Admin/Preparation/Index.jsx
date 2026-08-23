import { Head, Link } from '@inertiajs/react';
import AdminLayout from '../../../components/Admin/AdminLayout';
import CeremonyWorkspaceNav from '../../../components/Admin/CeremonyWorkspaceNav';

export default function PreparationIndex({ ceremony, preparation }) {
    const readyCount = preparation.steps.filter((step) => step.status === 'ready').length;
    return (
        <AdminLayout>
            <Head title={`Preparation · ${ceremony.name}`} />
            <header className="workspace-page-header preparation-page-header">
                <div>
                    <h1>Preparation</h1>
                    <span>{ceremony.name}{ceremony.academic_year ? ` · ${ceremony.academic_year}` : ''}</span>
                </div>
            </header>
            <CeremonyWorkspaceNav ceremony={ceremony} />
            <section aria-labelledby="preparation-hub-title" className="preparation-hub">
                <div className="preparation-hub-heading">
                    <div>
                        <h2 id="preparation-hub-title">Checklist</h2>
                    </div>
                    <div aria-label={`${readyCount} of ${preparation.steps.length} preparation areas ready`} className="preparation-hub-progress">
                        <strong>{readyCount}<span>/{preparation.steps.length}</span></strong>
                        <span>ready</span>
                    </div>
                </div>
                <div className="preparation-hub-rows">
                    {preparation.steps.map((step) => (
                        <article className={`preparation-hub-row is-${step.status}`} key={step.key}>
                            <span aria-hidden="true" className="preparation-hub-marker">{step.status === 'ready' ? '✓' : '!'}</span>
                            <div>
                                <div className="preparation-hub-row-title">
                                    <h3>{step.label}</h3>
                                </div>
                                <p>{step.detail}</p>
                            </div>
                            <strong className="preparation-hub-issues">
                                {step.issue_count > 0 && `${step.issue_count} issue${step.issue_count === 1 ? '' : 's'}`}
                            </strong>
                            <Link className="admin-row-action" href={step.href}>{step.status === 'ready' ? 'Review' : 'Open'}</Link>
                        </article>
                    ))}
                </div>
            </section>
        </AdminLayout>
    );
}
