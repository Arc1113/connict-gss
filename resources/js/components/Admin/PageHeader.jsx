export default function PageHeader({ actions, eyebrow, title, description }) {
    return (
        <header className="admin-page-header" data-page-toolbar>
            <div>
                {eyebrow && <p className="admin-eyebrow">{eyebrow}</p>}
                <h1>{title}</h1>
                {description && <p>{description}</p>}
            </div>
            {actions && <div className="admin-page-actions">{actions}</div>}
        </header>
    );
}
