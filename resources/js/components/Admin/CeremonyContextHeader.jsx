function formatDate(value) {
    if (!value) {
        return 'Event date not set';
    }

    return new Intl.DateTimeFormat('en-PH', {
        dateStyle: 'long',
    }).format(new Date(value));
}

export default function CeremonyContextHeader({ actions, ceremony }) {
    const details = [
        ceremony.academic_year || 'Academic year not set',
        formatDate(ceremony.event_date),
        ceremony.venue || 'Venue not set',
    ];

    return (
        <header className="ceremony-context-header">
            <div className="ceremony-context-primary">
                <p className="admin-eyebrow">Ceremony</p>
                <h1>{ceremony.name}</h1>
                <p className="ceremony-context-meta">
                    {details.map((detail) => (
                        <span key={detail}>{detail}</span>
                    ))}
                </p>
            </div>

            {actions ? (
                <div className="ceremony-context-actions">{actions}</div>
            ) : null}
        </header>
    );
}
