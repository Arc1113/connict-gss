import { router } from '@inertiajs/react';
import AppIcon from '../AppIcon';

export default function PageHeader({ actions, eyebrow, title, description }) {
    const navigateBack = () => {
        try {
            const referrer = document.referrer ? new URL(document.referrer) : null;

            if (window.history.length > 1 && referrer?.origin === window.location.origin) {
                window.history.back();

                return;
            }
        } catch {
            // Fall back to a safe in-app destination when the previous page is unknown.
        }

        router.visit('/dashboard');
    };

    return (
        <header className="admin-page-header" data-page-toolbar>
            <div>
                <div className="admin-page-eyebrow-row">
                    <button
                        aria-label="Go back"
                        className="admin-page-back"
                        onClick={navigateBack}
                        type="button"
                    >
                        <span className="admin-page-back-icon" aria-hidden="true">
                            <AppIcon name="chevron" size={18} />
                        </span>
                    </button>
                    {eyebrow && <p className="admin-eyebrow">{eyebrow}</p>}
                </div>
                <h1>{title}</h1>
                {description && <p>{description}</p>}
            </div>
            {actions && <div className="admin-page-actions">{actions}</div>}
        </header>
    );
}
