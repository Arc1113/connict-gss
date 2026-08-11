import { Link } from '@inertiajs/react';
import AppIcon from '../AppIcon';

export default function PageHeader({
    actions,
    backHref,
    backLabel,
    eyebrow,
    title,
    description,
}) {
    return (
        <header className="admin-page-header" data-page-toolbar>
            <div>
                {(backHref || eyebrow) && (
                    <div className="admin-page-eyebrow-row">
                        {backHref && (
                            <Link
                                aria-label={`Go to ${backLabel ?? 'parent page'}`}
                                className="admin-page-back"
                                href={backHref}
                            >
                                <span className="admin-page-back-icon" aria-hidden="true">
                                    <AppIcon name="chevron" size={18} />
                                </span>
                                <span>{backLabel ?? 'Back'}</span>
                            </Link>
                        )}
                        {eyebrow && <p className="admin-eyebrow">{eyebrow}</p>}
                    </div>
                )}
                <h1>{title}</h1>
                {description && <p>{description}</p>}
            </div>
            {actions && <div className="admin-page-actions">{actions}</div>}
        </header>
    );
}
