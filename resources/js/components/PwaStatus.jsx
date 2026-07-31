import { useEffect, useState } from 'react';
import AppIcon from './AppIcon';

export default function PwaStatus() {
    const [online, setOnline] = useState(() => navigator.onLine);
    const [installPrompt, setInstallPrompt] = useState(null);
    const [registration, setRegistration] = useState(null);
    const [updateReady, setUpdateReady] = useState(false);
    const [installDismissed, setInstallDismissed] = useState(() => {
        try {
            return window.sessionStorage.getItem('gss-install-dismissed') === 'true';
        } catch {
            return false;
        }
    });

    useEffect(() => {
        const handleOnline = () => setOnline(true);
        const handleOffline = () => setOnline(false);
        const handleInstallPrompt = (event) => {
            event.preventDefault();
            setInstallPrompt(event);
        };
        const handleInstalled = () => setInstallPrompt(null);

        window.addEventListener('online', handleOnline);
        window.addEventListener('offline', handleOffline);
        window.addEventListener('beforeinstallprompt', handleInstallPrompt);
        window.addEventListener('appinstalled', handleInstalled);

        if ('serviceWorker' in navigator && import.meta.env.PROD) {
            navigator.serviceWorker.register('/sw.js').then((nextRegistration) => {
                setRegistration(nextRegistration);

                if (nextRegistration.waiting) {
                    setUpdateReady(true);
                }

                nextRegistration.addEventListener('updatefound', () => {
                    const worker = nextRegistration.installing;

                    worker?.addEventListener('statechange', () => {
                        if (
                            worker.state === 'installed' &&
                            navigator.serviceWorker.controller
                        ) {
                            setUpdateReady(true);
                        }
                    });
                });
            });
        }

        return () => {
            window.removeEventListener('online', handleOnline);
            window.removeEventListener('offline', handleOffline);
            window.removeEventListener('beforeinstallprompt', handleInstallPrompt);
            window.removeEventListener('appinstalled', handleInstalled);
        };
    }, []);

    const install = async () => {
        if (!installPrompt) {
            return;
        }

        await installPrompt.prompt();
        await installPrompt.userChoice;
        setInstallPrompt(null);
    };

    const applyUpdate = () => {
        if (!registration?.waiting) {
            window.location.reload();

            return;
        }

        navigator.serviceWorker.addEventListener(
            'controllerchange',
            () => window.location.reload(),
            { once: true },
        );
        registration.waiting.postMessage({ type: 'SKIP_WAITING' });
    };

    const dismissInstall = () => {
        setInstallDismissed(true);
        try {
            window.sessionStorage.setItem('gss-install-dismissed', 'true');
        } catch {
            // The reminder can still be dismissed when storage is unavailable.
        }
    };

    if (online && (!installPrompt || installDismissed) && !updateReady) {
        return null;
    }

    return (
        <aside className="pwa-status" aria-live="polite">
            {!online && (
                <div className="pwa-status-offline" role="alert">
                    <strong>Server connection unavailable</strong>
                    <span>
                        Do not scan or advance the stage until the connection is
                        restored.
                    </span>
                </div>
            )}
            {online && updateReady && (
                <div>
                    <span>
                        <strong>Application update ready</strong>
                        Apply it only when live ceremony work is paused.
                    </span>
                    <button onClick={applyUpdate} type="button">
                        Reload and update
                    </button>
                </div>
            )}
            {online && !updateReady && installPrompt && !installDismissed && (
                <div className="pwa-status-install">
                    <span>
                        <strong>Install this workstation app</strong>
                        Launch it later without opening a browser tab.
                    </span>
                    <button onClick={install} type="button">
                        Install
                    </button>
                    <button
                        aria-label="Dismiss install reminder"
                        className="pwa-status-dismiss"
                        onClick={dismissInstall}
                        type="button"
                    >
                        <AppIcon name="close" size={16} />
                    </button>
                </div>
            )}
        </aside>
    );
}
