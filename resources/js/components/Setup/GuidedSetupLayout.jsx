import { Head, Link, router } from '@inertiajs/react';
import GuidedSetupFooter from './GuidedSetupFooter';
import GuidedSetupProgress from './GuidedSetupProgress';

export default function GuidedSetupLayout({ ceremony, preparation, setup, currentStep, urls }) {
    const step = currentStep ?? {
        key: 'complete',
        label: 'Preparation is complete',
        detail: 'The current ceremony data has no unresolved preparation steps.',
        status: 'ready',
        href: urls.workspace,
    };

    return (
        <div className="guided-setup-page">
            <Head title={`Guided setup · ${ceremony.name}`} />
            <header className="guided-setup-header">
                <div>
                    <p className="guided-setup-kicker">Guided ceremony setup</p>
                    <h1>{ceremony.name}</h1>
                    <p>Work through the useful preparation steps for this ceremony. You can leave at any time.</p>
                </div>
                <Link className="admin-button admin-button-secondary" href={urls.workspace}>Exit to workspace</Link>
            </header>

            <main className="guided-setup-main">
                <GuidedSetupProgress steps={preparation.steps} currentKey={step.key} skipped={setup.skipped_steps} />
                <section aria-labelledby="guided-setup-step-title" className="guided-setup-step">
                    <p className="guided-setup-step-number">Current step</p>
                    <h2 id="guided-setup-step-title">{step.label}</h2>
                    <p>{step.detail}</p>
                    {step.status === 'ready' ? (
                        <p className="guided-setup-ready" role="status">This area is ready based on the current ceremony data.</p>
                    ) : (
                        <div className="guided-setup-step-actions">
                            <Link className="admin-button admin-button-primary" href={step.href}>Open task</Link>
                            <button
                                className="admin-button admin-button-secondary"
                                onClick={() => router.post(urls.skip, { step: step.key })}
                                type="button"
                            >
                                Skip for now
                            </button>
                        </div>
                    )}
                </section>
            </main>

            <GuidedSetupFooter
                currentStep={step}
                setup={setup}
                urls={urls}
            />
        </div>
    );
}
