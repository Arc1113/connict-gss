import { Head, Link, usePage } from '@inertiajs/react';

export default function Dashboard() {
    const { auth } = usePage().props;
    const user = auth.user;
    const abilities = auth.abilities;

    return (
        <>
            <Head title="Dashboard" />

            <main className="dashboard-page">
                <section className="dashboard-card" aria-labelledby="dashboard-heading">
                    <div>
                        <p className="foundation-eyebrow">Local operations</p>
                        <h1 id="dashboard-heading" className="auth-title">
                            Operational dashboard
                        </h1>
                        <p className="auth-copy">
                            Open the operational or data-preparation interface
                            authorized for this account.
                        </p>
                    </div>

                    <dl className="account-summary">
                        <div>
                            <dt>Name</dt>
                            <dd>{user.name}</dd>
                        </div>
                        <div>
                            <dt>Email</dt>
                            <dd>{user.email}</dd>
                        </div>
                        <div>
                            <dt>Role</dt>
                            <dd>{user.role.label}</dd>
                        </div>
                        <div>
                            <dt>Account</dt>
                            <dd>{user.is_active ? 'Active' : 'Inactive'}</dd>
                        </div>
                    </dl>

                    {(abilities.view_ceremonies || abilities.view_students) && (
                        <div className="dashboard-operations">
                            <Link className="primary-action" href="/admin">
                                Open data administration
                            </Link>
                            <p className="operation-feedback">
                                Prepare ceremony and graduate records before
                                operational scanning begins.
                            </p>
                        </div>
                    )}

                    {!abilities.view_ceremonies &&
                        !abilities.view_students &&
                        (abilities.view_operational_logs ||
                            abilities.view_backups) && (
                            <div className="dashboard-operations">
                                {abilities.view_operational_logs && (
                                    <Link
                                        className="primary-action"
                                        href="/admin/scan-logs"
                                    >
                                        Open technical operations
                                    </Link>
                                )}
                                {abilities.view_backups && (
                                    <Link
                                        className="secondary-action"
                                        href="/admin/backups"
                                    >
                                        Open backups
                                    </Link>
                                )}
                                <p className="operation-feedback">
                                    Review local service evidence, operational logs,
                                    backups, and recovery status.
                                </p>
                            </div>
                        )}

                    {(abilities.operate_scanner || abilities.operate_stage) && (
                        <div className="dashboard-operations">
                            {abilities.operate_scanner && (
                                <Link className="primary-action" href="/scanner">
                                    Open scanner
                                </Link>
                            )}
                            {abilities.operate_stage && (
                                <>
                                    <Link
                                        className="primary-action"
                                        href="/stage-control"
                                    >
                                        Open stage control
                                    </Link>
                                    <Link
                                        className="secondary-action"
                                        href="/stage"
                                    >
                                        Open audience stage
                                    </Link>
                                </>
                            )}
                        </div>
                    )}

                    <div className="dashboard-actions">
                        <Link className="secondary-action" href="/">
                            System home
                        </Link>
                        <Link
                            className="primary-action"
                            href="/logout"
                            method="post"
                            as="button"
                        >
                            Sign out
                        </Link>
                    </div>
                </section>
            </main>
        </>
    );
}
