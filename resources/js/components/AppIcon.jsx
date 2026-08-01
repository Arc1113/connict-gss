const ICON_PATHS = {
    overview: (
        <>
            <rect x="3" y="3" width="7" height="7" rx="1" />
            <rect x="14" y="3" width="7" height="4" rx="1" />
            <rect x="14" y="11" width="7" height="10" rx="1" />
            <rect x="3" y="14" width="7" height="7" rx="1" />
        </>
    ),
    ceremony: (
        <>
            <rect x="3" y="5" width="18" height="16" rx="2" />
            <path d="M8 3v4M16 3v4M3 10h18M7 14h3M14 14h3M7 18h3" />
        </>
    ),
    graduates: (
        <>
            <path d="m3 8 9-5 9 5-9 5-9-5Z" />
            <path d="M6 11v5c3.2 2.7 8.8 2.7 12 0v-5M21 9v6" />
        </>
    ),
    import: (
        <>
            <path d="M12 3v12M7 10l5 5 5-5" />
            <path d="M4 18v3h16v-3" />
        </>
    ),
    qr: (
        <>
            <rect x="3" y="3" width="7" height="7" rx="1" />
            <rect x="14" y="3" width="7" height="7" rx="1" />
            <rect x="3" y="14" width="7" height="7" rx="1" />
            <path d="M14 14h3v3h-3zM18 18h3v3h-3zM18 14h3M14 18v3" />
        </>
    ),
    audio: (
        <>
            <path d="M5 10v4h4l5 4V6l-5 4H5Z" />
            <path d="M17 9c1.6 1.6 1.6 4.4 0 6M19.5 6.5c3 3 3 8 0 11" />
        </>
    ),
    scanner: (
        <>
            <path d="M4 8V4h4M16 4h4v4M20 16v4h-4M8 20H4v-4" />
            <path d="M7 12h10M7 15h10" />
        </>
    ),
    stage: (
        <>
            <path d="M4 20V8h16v12M2 20h20" />
            <path d="M8 8V4h8v4M8 13h8M12 13v7" />
        </>
    ),
    audience: (
        <>
            <rect x="3" y="4" width="18" height="13" rx="2" />
            <path d="M8 21h8M12 17v4M8 9h8M8 12h5" />
        </>
    ),
    scanLog: (
        <>
            <path d="M8 4H5a2 2 0 0 0-2 2v14h14v-3" />
            <path d="M9 3h7l5 5v9H9V3Z" />
            <path d="M15 3v6h6M12 12h6M12 15h6" />
        </>
    ),
    events: (
        <>
            <path d="M4 4v16h16" />
            <path d="m7 15 4-4 3 2 5-6" />
        </>
    ),
    audit: (
        <>
            <path d="M12 3 5 6v5c0 5 3 8.5 7 10 4-1.5 7-5 7-10V6l-7-3Z" />
            <path d="m9 12 2 2 4-5" />
        </>
    ),
    reports: (
        <>
            <path d="M5 3h10l4 4v14H5V3Z" />
            <path d="M14 3v5h5M8 17v-4M12 17V9M16 17v-6" />
        </>
    ),
    backup: (
        <>
            <path d="M4 7h16v14H4V7Z" />
            <path d="M7 3h10v4H7V3ZM8 12h8M8 16h5" />
        </>
    ),
    users: (
        <>
            <circle cx="9" cy="8" r="4" />
            <path d="M3 21v-2a6 6 0 0 1 12 0v2M16 5a4 4 0 0 1 0 7M17 15a5 5 0 0 1 4 5v1" />
        </>
    ),
    devices: (
        <>
            <rect x="3" y="4" width="12" height="16" rx="2" />
            <path d="M7 17h4M18 8h3v9h-3M16 12h5" />
        </>
    ),
    menu: <path d="M4 7h16M4 12h16M4 17h16" />,
    command: (
        <>
            <path d="M9 6H7a3 3 0 1 0 3 3V7a3 3 0 1 0-3 3h10a3 3 0 1 0-3-3v10a3 3 0 1 0 3-3h-2a3 3 0 1 0-3 3V6" />
        </>
    ),
    chevron: <path d="m9 18 6-6-6-6" />,
    close: <path d="m6 6 12 12M18 6 6 18" />,
    search: (
        <>
            <circle cx="11" cy="11" r="7" />
            <path d="m20 20-4-4" />
        </>
    ),
    help: (
        <>
            <circle cx="12" cy="12" r="9" />
            <path d="M9.8 9a2.4 2.4 0 1 1 3.4 2.2c-.8.4-1.2.9-1.2 1.8M12 17h.01" />
        </>
    ),
    external: <path d="M14 4h6v6M20 4l-9 9M18 13v7H4V6h7" />,
};

export default function AppIcon({ name, size = 18 }) {
    return (
        <svg
            aria-hidden="true"
            className="app-icon"
            fill="none"
            height={size}
            viewBox="0 0 24 24"
            width={size}
        >
            <g
                stroke="currentColor"
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth="1.7"
            >
                {ICON_PATHS[name] ?? ICON_PATHS.overview}
            </g>
        </svg>
    );
}
