import { Link, usePage } from '@inertiajs/react';
import StatusText from './StatusText';

function formatDate(value) {
    if (!value) {
        return 'Event date not set';
    }

    return new Intl.DateTimeFormat('en-PH', {
        dateStyle: 'long',
    }).format(new Date(value));
}

export default function CeremonyContextHeader({ actions, ceremony }) {
    const { liveCeremonyContext, workingCeremonyContext } = usePage().props;
    const liveCeremony = liveCeremonyContext?.ceremony;
    const workingCeremony = workingCeremonyContext?.ceremony;
    const isWorkingCeremony = workingCeremony?.id === ceremony.id;
    const isLiveCeremony = liveCeremony?.id === ceremony.id;
    const details = [
        ceremony.academic_year || 'Academic year not set',
        formatDate(ceremony.event_date),
        ceremony.venue || 'Venue not set',
    ];

    return (
        <header className="ceremony-context-header">
            <div className="ceremony-context-primary">
                <p className="admin-eyebrow">Ceremony Workspace</p>
                <h1>{ceremony.name}</h1>
                <p className="ceremony-context-meta">
                    {details.map((detail) => (
                        <span key={detail}>{detail}</span>
                    ))}
                </p>
            </div>

            <div className="ceremony-context-status">
                <StatusText
                    label={ceremony.status.label}
                    value={ceremony.status.value}
                />
                <dl className="ceremony-context-flags">
                    {isWorkingCeremony && (
                        <div>
                            <dt>Working ceremony</dt>
                            <dd>This workspace is the current preparation context.</dd>
                        </div>
                    )}
                    {isLiveCeremony ? (
                        <div>
                            <dt>Live ceremony</dt>
                            <dd>This ceremony is active for scanner and stage operations.</dd>
                        </div>
                    ) : liveCeremony ? (
                        <div>
                            <dt>Live ceremony</dt>
                            <dd>
                                <Link href={`/admin/ceremonies/${liveCeremony.id}`}>
                                    {liveCeremony.name}
                                </Link>
                            </dd>
                        </div>
                    ) : (
                        <div>
                            <dt>Live ceremony</dt>
                            <dd>No ceremony is currently active.</dd>
                        </div>
                    )}
                </dl>
            </div>

            {actions ? (
                <div className="ceremony-context-actions">{actions}</div>
            ) : null}
        </header>
    );
}
