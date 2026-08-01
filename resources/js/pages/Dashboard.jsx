import { Head, Link, usePage } from '@inertiajs/react';
import AppIcon from '../components/AppIcon';
import AppShell from '../components/AppShell';

const ROLE_DEFAULTS = {
    administrator: '/admin',
    data_encoder: '/admin/students',
    scanner_operator: '/scanner',
    stage_operator: '/stage-control',
    technical_operator: '/admin/scan-logs',
};

export default function Dashboard() {
    const { auth, ceremonyContext } = usePage().props;
    const { abilities, user } = auth;
    const operations = [];

    if (abilities.view_ceremonies || abilities.view_students) {
        operations.push({
            href: '/admin',
            icon: 'overview',
            title: 'Ceremony readiness',
            copy: 'Review blockers, counts, and the active ceremony.',
        });
    } else if (abilities.view_operational_logs) {
        operations.push({
            href: '/admin/scan-logs',
            icon: 'scanLog',
            title: 'Technical operations',
            copy: 'Review scanner and stage evidence during live operations.',
        });
    }

    if (abilities.operate_scanner) {
        operations.push({
            href: '/scanner',
            icon: 'scanner',
            title: 'Arrival scanner',
            copy: 'Process graduate QR codes and controlled manual lookups.',
        });
    }

    if (abilities.operate_stage) {
        operations.push({
            href: '/stage-control',
            icon: 'stage',
            title: 'Stage control',
            copy: 'Advance the live queue and recover presentation state.',
        });
        operations.push({
            href: '/stage',
            icon: 'audience',
            title: 'Audience stage',
            copy: 'Open the committed presentation view on the projector.',
            external: true,
        });
    }

    if (abilities.view_backups) {
        operations.push({
            href: '/admin/backups',
            icon: 'backup',
            title: 'Backups',
            copy: 'Create and verify local recovery copies.',
        });
    }

    const defaultPath = ROLE_DEFAULTS[user.role.value] ?? operations[0]?.href;
    const primary =
        operations.find((operation) => operation.href === defaultPath) ??
        operations[0];
    const status = {
        ready: {
            label: 'Ready for ceremony work',
            detail: ceremonyContext.active?.name,
            tone: 'ready',
        },
        none: {
            label: 'Preparation required',
            detail: 'No active ceremony is available for live operations.',
            tone: 'warning',
        },
        conflict: {
            label: 'Activation conflict',
            detail: 'More than one ceremony is active.',
            tone: 'danger',
        },
        unavailable: {
            label: 'Status unavailable',
            detail: 'The local database could not confirm ceremony readiness.',
            tone: 'danger',
        },
    }[ceremonyContext.state];

    return (
        <AppShell>
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
                    <span>{user.role.label} workspace · choose the next authorized task.</span>
                </div>
            </header>

            <section
                className={`overview-readiness app-status-${status.tone}`}
                aria-label="Operational readiness"
            >
                <span className="app-status-symbol" aria-hidden="true" />
                <div>
                    <strong>{status.label}</strong>
                    <span>{status.detail}</span>
                </div>
                {abilities.view_ceremonies && (
                    <Link href="/admin/ceremonies">View ceremonies</Link>
                )}
            </section>

            {primary && (
                <section className="overview-next-action" aria-labelledby="next-action">
                    <AppIcon name={primary.icon} size={22} />
                    <div>
                        <span>Primary workspace</span>
                        <h2 id="next-action">{primary.title}</h2>
                        <p>{primary.copy}</p>
                    </div>
                    <Link
                        className="primary-action"
                        href={primary.href}
                        rel={primary.external ? 'noreferrer' : undefined}
                        target={primary.external ? '_blank' : undefined}
                    >
                        Open {primary.title.toLowerCase()}
                    </Link>
                </section>
            )}

            <section className="overview-tools" aria-labelledby="available-tools">
                <header>
                    <h2 id="available-tools">Available workspaces</h2>
                    <span>{operations.length} authorized</span>
                </header>
                <div role="list">
                    {operations.map((operation) => (
                        <Link
                            href={operation.href}
                            key={`${operation.href}-${operation.title}`}
                            rel={operation.external ? 'noreferrer' : undefined}
                            role="listitem"
                            target={operation.external ? '_blank' : undefined}
                        >
                            <AppIcon name={operation.icon} />
                            <span>
                                <strong>{operation.title}</strong>
                                <small>{operation.copy}</small>
                            </span>
                            {operation.external ? (
                                <AppIcon name="external" size={15} />
                            ) : (
                                <AppIcon name="chevron" size={15} />
                            )}
                        </Link>
                    ))}
                </div>
            </section>
        </AppShell>
    );
}
