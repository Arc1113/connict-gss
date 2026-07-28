import { Head, Link } from '@inertiajs/react';

export default function Welcome({ appName, auth, phase, stack }) {
    return (
        <>
            <Head title="Foundation ready" />

            <main className="foundation-page">
                <section className="foundation-card">
                    <p className="foundation-eyebrow">{phase} foundation</p>
                    <h1 className="foundation-title">{appName}</h1>
                    <p className="foundation-copy">
                        Laravel routing returned this page through Inertia, and React
                        rendered it from locally compiled Vite assets.
                    </p>

                    <div className="foundation-actions">
                        <Link
                            className="primary-action"
                            href={auth.user ? '/dashboard' : '/login'}
                        >
                            {auth.user ? 'Open dashboard' : 'Operator sign in'}
                        </Link>
                    </div>

                    <ul className="foundation-stack" aria-label="Verified application stack">
                        {stack.map((technology) => (
                            <li key={technology}>{technology}</li>
                        ))}
                    </ul>

                    <p className="foundation-note">
                        Phase 2 adds session authentication, role authorization, and
                        the PostgreSQL domain foundation. Operational ceremony features
                        remain reserved for approved later phases.
                    </p>
                </section>
            </main>
        </>
    );
}
