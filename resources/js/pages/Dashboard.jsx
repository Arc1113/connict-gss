import { Head, Link, usePage } from '@inertiajs/react';

export default function Dashboard() {
    const { auth } = usePage().props;
    const user = auth.user;

    return (
        <>
            <Head title="Dashboard" />

            <main className="dashboard-page">
                <section className="dashboard-card" aria-labelledby="dashboard-heading">
                    <div>
                        <p className="foundation-eyebrow">Phase 2 protected area</p>
                        <h1 id="dashboard-heading" className="auth-title">
                            Authentication confirmed
                        </h1>
                        <p className="auth-copy">
                            This minimal page verifies the authenticated Inertia
                            boundary. Operational interfaces are not part of this phase.
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

                    <div className="dashboard-actions">
                        <Link className="secondary-action" href="/">
                            Foundation status
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
