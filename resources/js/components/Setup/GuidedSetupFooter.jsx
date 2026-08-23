import { Link, router } from '@inertiajs/react';

export default function GuidedSetupFooter({ currentStep, setup, urls }) {
    const started = setup.started;

    return (
        <footer className="guided-setup-footer">
            <Link className="admin-button admin-button-secondary" href={setup.back_url}>Back</Link>
            <div>
                {!started && (
                    <button className="admin-button admin-button-secondary" onClick={() => router.post(urls.skip_setup)} type="button">
                        Skip setup
                    </button>
                )}
                {started && currentStep?.key !== 'complete' && (
                    <button className="admin-button admin-button-primary" onClick={() => router.post(urls.continue)} type="button">
                        Continue
                    </button>
                )}
                {started && currentStep?.key === 'complete' && (
                    <Link className="admin-button admin-button-primary" href={urls.workspace}>Open workspace</Link>
                )}
                {!started && currentStep?.key !== 'complete' && (
                    <button className="admin-button admin-button-primary" onClick={() => router.post(urls.start)} type="button">
                        Start guided setup
                    </button>
                )}
            </div>
        </footer>
    );
}
