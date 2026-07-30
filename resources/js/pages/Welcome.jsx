import { Head, Link } from '@inertiajs/react';

export default function Welcome({ appName, auth }) {
    return (
        <>
            <Head title={appName} />

            <main className="foundation-page">
                <section className="foundation-card">
                    <p className="foundation-eyebrow">Institutional Ceremony System</p>
                    <h1 className="foundation-title">{appName}</h1>
                    <p className="foundation-copy">
                        Local ceremony operations for graduate preparation,
                        scanning, stage control, and audience presentation.
                    </p>

                    <div className="foundation-actions">
                        <Link
                            className="primary-action"
                            href={auth.user ? '/dashboard' : '/login'}
                        >
                            {auth.user ? 'Open dashboard' : 'Operator sign in'}
                        </Link>
                    </div>

                    <p className="foundation-note">
                        Use the account assigned for your ceremony duty. Contact
                        the local administrator if access is unavailable.
                    </p>
                </section>
            </main>
        </>
    );
}
