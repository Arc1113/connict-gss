function parseLocation(currentUrl) {
    const url = new URL(currentUrl, 'http://localhost');

    return {
        path: url.pathname,
        query: url.searchParams,
    };
}

function exactPath(pathname) {
    return ({ path }) => path === pathname;
}

function pathPrefix(prefix) {
    return ({ path }) => path === prefix || path.startsWith(`${prefix}/`);
}

function anyMatcher(...matchers) {
    return (location) => matchers.some((matcher) => matcher(location));
}

function notMatcher(matcher) {
    return (location) => !matcher(location);
}

function andMatcher(...matchers) {
    return (location) => matchers.every((matcher) => matcher(location));
}

function regexPath(pattern) {
    return ({ path }) => pattern.test(path);
}

function queryValue(name, expectedValue) {
    return ({ query }) => query.get(name) === expectedValue;
}

const isImportRoute = pathPrefix('/admin/students/import');
const isQrRoute = anyMatcher(
    pathPrefix('/admin/students/qr-codes'),
    regexPath(/^\/admin\/students\/\d+\/qr-code$/),
);
const isAnnouncementRoute = anyMatcher(
    pathPrefix('/admin/voice-generation'),
    regexPath(/^\/admin\/ceremonies\/\d+\/announcements(?:\/.*)?$/),
    regexPath(/^\/admin\/ceremonies\/\d+\/announcement-settings(?:\/.*)?$/),
    regexPath(/^\/admin\/ceremonies\/\d+\/announcement-batches(?:\/.*)?$/),
);
const isStudentRoute = andMatcher(
    pathPrefix('/admin/students'),
    notMatcher(isImportRoute),
    notMatcher(isQrRoute),
);
const isCeremonyOverviewRoute = anyMatcher(
    exactPath('/admin/ceremonies'),
    exactPath('/admin/ceremonies/create'),
    regexPath(/^\/admin\/ceremonies\/\d+(?:\/edit)?$/),
    regexPath(/^\/admin\/ceremonies\/\d+\/setup$/),
    regexPath(/^\/admin\/ceremonies\/\d+\/operational-reset$/),
);

function hasAnnouncementAccess(abilities) {
    return (
        abilities.generate_announcement_candidates
        || abilities.review_announcement_candidates
        || abilities.manage_announcement_settings
        || abilities.manage_announcement_batches
    );
}

function ceremonyOverviewHref(abilities, options) {
    if (!abilities.view_ceremonies) {
        return null;
    }

    return options.workingCeremony?.id
        ? `/admin/ceremonies/${options.workingCeremony.id}`
        : '/admin/ceremonies';
}

function navigationDefinition(abilities, options = {}) {
    return [
        {
            label: 'OVERVIEW',
            items: [
                {
                    href: '/dashboard',
                    icon: 'overview',
                    label: 'Overview',
                    visible: true,
                    match: exactPath('/dashboard'),
                    tour: 'readiness',
                },
            ],
        },
        {
            label: 'CEREMONY WORKSPACE',
            items: [
                {
                    href: ceremonyOverviewHref(abilities, options),
                    icon: 'ceremony',
                    label: 'Ceremony Overview',
                    visible: abilities.view_ceremonies,
                    match: isCeremonyOverviewRoute,
                },
                {
                    href: '/admin/students',
                    icon: 'graduates',
                    label: 'Graduates',
                    visible: abilities.view_students,
                    match: isStudentRoute,
                    tour: 'graduates',
                },
                {
                    href: '/admin/students/import',
                    icon: 'import',
                    label: 'Import',
                    visible: abilities.import_students,
                    match: isImportRoute,
                },
                {
                    href: '/admin/students/qr-codes',
                    icon: 'qr',
                    label: 'QR & Media',
                    visible: abilities.prepare_qr_codes,
                    match: isQrRoute,
                },
                {
                    href: '/admin/voice-generation',
                    icon: 'audio',
                    label: 'Announcements',
                    visible: hasAnnouncementAccess(abilities),
                    match: isAnnouncementRoute,
                },
                abilities.view_pronunciation_rules && {
                    href: '/admin/pronunciation-rules',
                    icon: 'audio',
                    label: 'Pronunciation dictionary',
                    visible: true,
                    match: pathPrefix('/admin/pronunciation-rules'),
                    tour: 'pronunciation-dictionary',
                },
                {
                    href: '/admin/stage-designs',
                    icon: 'audience',
                    label: 'Stage Design',
                    visible: abilities.manage_stage_designs,
                    match: pathPrefix('/admin/stage-designs'),
                },
            ],
        },
        {
            label: 'LIVE OPERATIONS',
            items: [
                {
                    href: '/scanner',
                    icon: 'scanner',
                    label: 'Scanner',
                    visible: abilities.operate_scanner,
                    match: pathPrefix('/scanner'),
                },
                {
                    href: '/stage-control',
                    icon: 'stage',
                    label: 'Stage Control',
                    visible: abilities.operate_stage,
                    match: pathPrefix('/stage-control'),
                },
                {
                    href: '/stage',
                    icon: 'audience',
                    label: 'Stage Display',
                    visible: abilities.operate_stage,
                    match: exactPath('/stage'),
                    external: true,
                },
            ],
        },
        {
            label: 'REVIEW',
            items: [
                {
                    href: '/admin/reports/ceremony-summary',
                    icon: 'reports',
                    label: 'Ceremony Summary',
                    visible: abilities.view_ceremony_reports,
                    match: pathPrefix('/admin/reports/ceremony-summary'),
                },
                {
                    href: '/admin/scan-logs',
                    icon: 'scanLog',
                    label: 'Scan Logs',
                    visible: abilities.view_operational_logs,
                    match: pathPrefix('/admin/scan-logs'),
                    tour: 'operational-logs',
                },
                {
                    href: '/admin/stage-events',
                    icon: 'events',
                    label: 'Stage Events',
                    visible: abilities.view_operational_logs,
                    match: pathPrefix('/admin/stage-events'),
                },
            ],
        },
        {
            label: 'SYSTEM',
            items: [
                {
                    href: '/admin/device-connections',
                    icon: 'devices',
                    label: 'Connect Devices',
                    visible: abilities.manage_device_connections,
                    match: pathPrefix('/admin/device-connections'),
                },
                {
                    href: '/admin/backups',
                    icon: 'backup',
                    label: 'Backups',
                    visible: abilities.view_backups,
                    match: pathPrefix('/admin/backups'),
                    tour: 'backups',
                },
                {
                    href: '/admin/users',
                    icon: 'users',
                    label: 'Operator Accounts',
                    visible: abilities.manage_users,
                    match: pathPrefix('/admin/users'),
                    tour: 'operator-accounts',
                },
                {
                    href: '/admin/audit-logs',
                    icon: 'audit',
                    label: 'Audit Logs',
                    visible: abilities.view_audit_logs,
                    match: pathPrefix('/admin/audit-logs'),
                },
            ],
        },
    ];
}

export function buildNavigation(abilities, currentUrl, options = {}) {
    const location = parseLocation(currentUrl);

    return navigationDefinition(abilities, options)
        .map((group) => ({
            label: group.label,
            items: group.items
                .filter((item) => item.visible && item.href)
                .map((item) => ({
                    href: item.href,
                    icon: item.icon,
                    label: item.label,
                    external: item.external ?? false,
                    tour: item.tour,
                    active: item.match(location),
                })),
        }))
        .filter((group) => group.items.length > 0);
}

export const navigationMatchers = {
    isAnnouncementRoute,
    isCeremonyOverviewRoute,
    isImportRoute,
    isQrRoute,
    isStudentRoute,
    queryValue,
};
