import { Head } from '@inertiajs/react';

export default function Welcome({ appName, phase, stack }) {
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

                    <ul className="foundation-stack" aria-label="Verified application stack">
                        {stack.map((technology) => (
                            <li key={technology}>{technology}</li>
                        ))}
                    </ul>

                    <p className="foundation-note">
                        This page is intentionally limited to the Phase 1 integration
                        proof. Ceremony features begin only in approved later phases.
                    </p>
                </section>
            </main>
        </>
    );
}
