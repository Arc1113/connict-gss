import { Head, Link, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { requestJson } from '../lib/http';

export default function Dashboard() {
    const { auth } = usePage().props;
    const user = auth.user;
    const abilities = auth.abilities;
    const [completion, setCompletion] = useState(null);
    const [completing, setCompleting] = useState(false);

    async function completeCurrent() {
        setCompleting(true);

        try {
            const { data } = await requestJson('/stage/complete-current', {
                method: 'POST',
            });
            setCompletion(data.message);
        } catch {
            setCompletion('The server could not confirm the action.');
        } finally {
            setCompleting(false);
        }
    }

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
                                        href="/stage"
                                    >
                                        Open audience stage
                                    </Link>
                                    <button
                                        className="secondary-action"
                                        type="button"
                                        onClick={completeCurrent}
                                        disabled={completing}
                                    >
                                        {completing
                                            ? 'Completing…'
                                            : 'Complete current presentation'}
                                    </button>
                                </>
                            )}
                            {completion && (
                                <p className="operation-feedback" role="status">
                                    {completion}
                                </p>
                            )}
                        </div>
                    )}

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
