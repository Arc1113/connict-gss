import { Head, Link, usePage } from '@inertiajs/react';
import PreparationChecklist from '../components/Admin/PreparationChecklist';
import AppShell from '../components/AppShell';

function ceremonyMeta(ceremony) {
    return [ceremony.academic_year, ceremony.event_date, ceremony.status]
        .filter(Boolean)
        .join(' · ');
}

export default function Dashboard() {
    const { auth, overview } = usePage().props;
    const workingCeremony = overview.working_ceremony;
    const live = overview.live_ceremony;
    const liveCeremony = live.ceremony;
    const primary = overview.primary_action;
    const attention = overview.attention ?? [];
    const actions = overview.relevant_actions ?? [];
    const checklist = overview.preparation_checklist ?? [];
    const shellMode = {
        scanner_operator: 'operational',
        stage_operator: 'operational',
        technical_operator: 'technical',
    }[auth.user.role.value] ?? 'preparation';
    const liveCopy = {
        ready: ['Active', ceremonyMeta(liveCeremony)],
        none: ['No live ceremony', 'Scanner and stage operations are unavailable until a ceremony is active.'],
        conflict: ['Live ceremony conflict', 'More than one ceremony is active. Scanner and stage operations remain safely blocked.'],
        unavailable: ['Live ceremony unavailable', 'The local database could not confirm live ceremony state.'],
    }[live.state];

    return (
        <AppShell mode={shellMode}>
            <Head title="Overview">
                <meta
                    name="description"
                    content="Role-based access to local graduation ceremony operations."
                />
            </Head>

            <header className="workspace-page-header">
                <div>
                    <p>Overview</p>
                    <h1>Operations overview</h1>
                    <span>{auth.user.role.label} workspace · state and priority for your authorized work.</span>
                </div>
            </header>

            {workingCeremony ? (
                <section className="overview-ceremony-panel" aria-labelledby="working-ceremony">
                    <div>
                        <p>Preparation context</p>
                        <h2 id="working-ceremony">Working ceremony</h2>
                    </div>
                    <div className="overview-ceremony-panel-detail">
                        <strong>{workingCeremony.name}</strong>
                        <span>{ceremonyMeta(workingCeremony)}</span>
                    </div>
                    <Link href={`/admin/ceremonies/${workingCeremony.id}`}>
                        Continue preparation
                    </Link>
                </section>
            ) : overview.working_ceremony === null ? (
                <section className="overview-ceremony-panel overview-empty-state" aria-labelledby="working-ceremony">
                    <div>
                        <p>Preparation context</p>
                        <h2 id="working-ceremony">No working ceremony selected</h2>
                        <span>Choose a ceremony to begin preparation.</span>
                    </div>
                    <Link href="/admin/ceremonies">Choose ceremony</Link>
                </section>
            ) : null}

            {attention.length > 0 && (
                <section className="overview-attention" aria-labelledby="needs-attention">
                    <header>
                        <div>
                            <h2 id="needs-attention">Needs attention</h2>
                            <p>These blockers apply to the Working Ceremony only.</p>
                        </div>
                    </header>
                    <ol>
                        {attention.map((item) => (
                            <li key={item.label}>
                                <span>
                                    <strong>{item.label}</strong>
                                    <small>{item.detail}</small>
                                </span>
                                <Link href={item.href}>Review</Link>
                            </li>
                        ))}
                    </ol>
                </section>
            )}

            {primary && (
                <section className="overview-next-action" aria-labelledby="next-action">
                    <div>
                        <span>{overview.next_action ? 'Next action' : 'Primary workspace'}</span>
                        <h2 id="next-action">{primary.title || primary.label}</h2>
                        <p>{primary.detail}</p>
                    </div>
                    <Link
                        className="primary-action"
                        href={primary.href}
                    >
                        {primary.label}
                    </Link>
                </section>
            )}

            <PreparationChecklist checklist={checklist} compact />

            <section className="overview-ceremony-panel overview-live-ceremony" aria-labelledby="live-ceremony">
                <div>
                    <p>Live operations context</p>
                    <h2 id="live-ceremony">Live ceremony</h2>
                </div>
                <div className="overview-ceremony-panel-detail">
                    <strong>{liveCeremony?.name || liveCopy[0]}</strong>
                    <span>{liveCeremony ? ceremonyMeta(liveCeremony) : liveCopy[1]}</span>
                </div>
            </section>

            {actions.length > 0 && (
                <nav className="overview-relevant-actions" aria-label="Relevant actions">
                    {actions.map((action) => (
                        <Link
                            href={action.href}
                            key={action.href}
                            rel={action.external ? 'noreferrer' : undefined}
                            target={action.external ? '_blank' : undefined}
                        >
                            {action.label}
                        </Link>
                    ))}
                </nav>
            )}
        </AppShell>
    );
}
