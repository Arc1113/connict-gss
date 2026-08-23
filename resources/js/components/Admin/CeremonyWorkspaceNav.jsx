import { Link, usePage } from '@inertiajs/react';

function sectionsFor(ceremonyId, abilities) {
    return [
        {
            href: `/admin/ceremonies/${ceremonyId}`,
            key: 'overview',
            label: 'Overview',
            visible: abilities.view_ceremonies,
        },
        {
            href: `/admin/ceremonies/${ceremonyId}/setup`,
            key: 'setup',
            label: 'Setup',
            visible: abilities.manage_ceremonies,
        },
        {
            href: `/admin/ceremonies/${ceremonyId}/preparation`,
            key: 'preparation',
            label: 'Preparation',
            visible: abilities.view_ceremonies,
        },
        {
            href: `/admin/ceremonies/${ceremonyId}/graduates`,
            key: 'graduates',
            label: 'Graduates',
            visible: abilities.view_students,
        },
        {
            href: `/admin/ceremonies/${ceremonyId}/import`,
            key: 'import',
            label: 'Import',
            visible: abilities.import_students,
        },
        {
            href: `/admin/ceremonies/${ceremonyId}/qr`,
            key: 'qr',
            label: 'QR & Media',
            visible: abilities.prepare_qr_codes,
        },
        {
            href: `/admin/ceremonies/${ceremonyId}/announcements`,
            key: 'announcements',
            label: 'Announcements',
            visible:
                abilities.manage_announcement_settings
                || abilities.manage_announcement_batches
                || abilities.generate_announcement_candidates
                || abilities.review_announcement_candidates,
        },
        {
            href: `/admin/ceremonies/${ceremonyId}/stage-design`,
            key: 'stage-design',
            label: 'Stage Design',
            visible: abilities.manage_ceremonies || abilities.manage_stage_designs,
        },
        {
            href: `/admin/ceremonies/${ceremonyId}/readiness`,
            key: 'readiness',
            label: 'Readiness',
            visible: abilities.view_ceremonies,
        },
    ].filter((section) => section.visible);
}

function activeSection(url, ceremonyId) {
    const path = new URL(url, 'http://localhost').pathname;
    const base = `/admin/ceremonies/${ceremonyId}`;

    if (path === base) {
        return 'overview';
    }

    if (path === `${base}/setup`) {
        return 'setup';
    }

    if (path === `${base}/preparation`) {
        return 'preparation';
    }

    if (
        path === `${base}/graduates`
        || path.startsWith(`${base}/graduates/`)
        || path === '/admin/students'
        || path.startsWith('/admin/students/')
    ) {
        return 'graduates';
    }

    if (
        path === `${base}/import`
        || path.startsWith(`${base}/import/`)
        || path === '/admin/students/import'
        || path.startsWith('/admin/students/import/')
    ) {
        return 'import';
    }

    if (
        path === `${base}/qr`
        || path.startsWith(`${base}/qr/`)
        || path === '/admin/students/qr-codes'
        || path.startsWith('/admin/students/qr-codes')
    ) {
        return 'qr';
    }

    if (
        path === `${base}/announcements`
        || path.startsWith(`${base}/announcements/`)
        || path === '/admin/voice-generation'
    ) {
        return 'announcements';
    }

    if (path === `${base}/stage-design`) {
        return 'stage-design';
    }

    if (path === `${base}/readiness`) {
        return 'readiness';
    }

    return null;
}

export default function CeremonyWorkspaceNav({ ceremony }) {
    const { auth } = usePage().props;
    const currentUrl = usePage().url;
    const sections = sectionsFor(ceremony.id, auth.abilities);
    const current = activeSection(currentUrl, ceremony.id);

    return (
        <nav
            aria-label="Ceremony workspace sections"
            className="ceremony-workspace-nav"
        >
            {sections.map((section) => (
                <Link
                    aria-current={current === section.key ? 'page' : undefined}
                    className={
                        current === section.key
                            ? 'ceremony-workspace-nav-link is-current'
                            : 'ceremony-workspace-nav-link'
                    }
                    href={section.href}
                    key={section.key}
                >
                    {section.label}
                </Link>
            ))}
        </nav>
    );
}
