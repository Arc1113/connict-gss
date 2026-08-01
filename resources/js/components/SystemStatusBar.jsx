import { useEffect, useState } from 'react';

function localTime(date) {
    return new Intl.DateTimeFormat('en-PH', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: false,
    }).format(date);
}

export default function SystemStatusBar({ ceremony, connection, online }) {
    const [now, setNow] = useState(() => new Date());

    useEffect(() => {
        const timer = window.setInterval(() => setNow(new Date()), 1000);
        return () => window.clearInterval(timer);
    }, []);

    return (
        <footer className="app-statusbar" aria-label="Application status">
            <div>
                <span className={online ? 'is-ready' : 'is-danger'}>
                    <i aria-hidden="true" />
                    Network {online ? 'online' : 'offline'}
                </span>
                <span className={`is-${connection.tone}`}>
                    <i aria-hidden="true" />
                    Live updates {connection.shortLabel}
                </span>
                <span className={`is-${ceremony.tone}`}>
                    <i aria-hidden="true" />
                    Ceremony {ceremony.shortLabel}
                </span>
            </div>
            <time dateTime={now.toISOString()}>{localTime(now)}</time>
        </footer>
    );
}
