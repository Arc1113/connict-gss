function parseLocation(currentUrl) {
    const url = new URL(currentUrl, 'http://localhost');

    return { path: url.pathname, query: url.searchParams };
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

const isImportRoute = anyMatcher(
    pathPrefix('/admin/students/import'),
    regexPath(/^\/admin\/ceremonies\/\d+\/import(?:\/.*)?$/),
);
const isQrRoute = anyMatcher(
    pathPrefix('/admin/students/qr-codes'),
    regexPath(/^\/admin\/students\/\d+\/qr-code$/),
    regexPath(/^\/admin\/ceremonies\/\d+\/qr(?:\/.*)?$/),
);
const isAnnouncementRoute = anyMatcher(
    pathPrefix('/admin/voice-generation'),
    regexPath(/^\/admin\/ceremonies\/\d+\/(?:announcements|announcement-settings|announcement-batches)(?:\/.*)?$/),
);
const isPreparationRoute = anyMatcher(
    isImportRoute,
    isQrRoute,
    isAnnouncementRoute,
    pathPrefix('/admin/pronunciation-rules'),
    pathPrefix('/admin/stage-designs'),
    regexPath(/^\/admin\/ceremonies\/\d+\/(?:setup|guided-setup|preparation|readiness)$/),
    exactPath('/admin/preparation'),
);
const isStudentRoute = andMatcher(
    anyMatcher(
        pathPrefix('/admin/students'),
        regexPath(/^\/admin\/ceremonies\/\d+\/graduates(?:\/.*)?$/),
    ),
    notMatcher(isImportRoute),
    notMatcher(isQrRoute),
);
const isCeremonyRoute = anyMatcher(
    exactPath('/admin/ceremonies'),
    exactPath('/admin/ceremonies/create'),
    regexPath(/^\/admin\/ceremonies\/\d+(?:\/edit)?$/),
    regexPath(/^\/admin\/ceremonies\/\d+\/(?:setup|readiness|operational-reset)$/),
);
const isCeremonyAdministrationRoute = anyMatcher(
    exactPath('/admin/ceremonies'),
    exactPath('/admin/ceremonies/create'),
    regexPath(/^\/admin\/ceremonies\/\d+\/edit$/),
);
const isPreparationWorkspaceRoute = anyMatcher(
    isPreparationRoute,
    regexPath(/^\/admin\/ceremonies\/\d+$/),
);
const isRunCeremonyRoute = anyMatcher(
    pathPrefix('/scanner'),
    pathPrefix('/stage-control'),
    exactPath('/stage'),
);
const isReportsRoute = anyMatcher(
    pathPrefix('/admin/reports/ceremony-summary'),
    pathPrefix('/admin/scan-logs'),
    pathPrefix('/admin/stage-events'),
);
const isSettingsRoute = anyMatcher(
    pathPrefix('/admin/device-connections'),
    pathPrefix('/admin/backups'),
    pathPrefix('/admin/users'),
    pathPrefix('/admin/audit-logs'),
);

function hasAnnouncementAccess(abilities) {
    return (
        abilities.generate_announcement_candidates
        || abilities.review_announcement_candidates
        || abilities.manage_announcement_settings
        || abilities.manage_announcement_batches
    );
}

function preparationHubHref(abilities, options) {
    if (!abilities.view_ceremonies) {
        return null;
    }

    return options.workingCeremony?.id
        ? `/admin/ceremonies/${options.workingCeremony.id}/preparation`
        : '/admin/preparation';
}

function item(href, label, icon, visible, match, options = {}) {
    return {
        href,
        icon,
        label,
        visible,
        match,
        external: options.external ?? false,
        tour: options.tour,
    };
}

function navigationDefinition(abilities, options = {}) {
    const preparationHref = preparationHubHref(abilities, options);
    const reportHref = abilities.view_ceremony_reports
        ? '/admin/reports/ceremony-summary'
        : abilities.view_operational_logs && !abilities.operate_stage
          ? '/admin/scan-logs'
          : null;

    const primaryNavigation = [
        {
            label: 'Home',
            items: [
                item('/dashboard', 'Home', 'overview', true, anyMatcher(exactPath('/dashboard'), exactPath('/admin')), { tour: 'home' }),
            ],
        },
        {
            label: 'Graduates',
            items: [
                item('/admin/students', 'Graduates', 'graduates', abilities.view_students, isStudentRoute, { tour: 'graduates' }),
            ],
        },
        {
            label: 'Preparation',
            items: [
                item(preparationHref, 'Preparation', 'ceremony', Boolean(preparationHref), isPreparationWorkspaceRoute, { tour: 'preparation' }),
                item('/admin/students/import', 'Import Graduates', 'import', abilities.import_students, isImportRoute),
            ],
        },
        {
            label: 'Run Ceremony',
            items: [
                item('/scanner', 'Scanner', 'scanner', abilities.operate_scanner, pathPrefix('/scanner')),
                item('/stage-control', 'Stage Control', 'stage', abilities.operate_stage, pathPrefix('/stage-control')),
                item('/stage', 'Stage Display', 'audience', abilities.operate_stage, exactPath('/stage'), { external: true }),
            ],
        },
        {
            label: 'Reports',
            items: [
                item(reportHref, 'Reports', 'reports', Boolean(reportHref), isReportsRoute, { tour: 'reports' }),
            ],
        },
        {
            label: 'Settings',
            items: [
                item('/admin/ceremonies', 'Ceremonies', 'ceremony', abilities.manage_ceremonies, isCeremonyAdministrationRoute, { tour: 'ceremonies' }),
                item('/admin/device-connections', 'Device Connections', 'devices', abilities.manage_device_connections, pathPrefix('/admin/device-connections'), { tour: 'device-connections' }),
                item('/admin/backups', 'Backups', 'backup', abilities.view_backups, pathPrefix('/admin/backups'), { tour: 'backups' }),
            ],
        },
    ];

    const secondaryNavigation = [
        {
            label: 'Preparation',
            items: [
                item('/admin/students/import', 'Import Graduates', 'import', abilities.import_students, isImportRoute),
                item('/admin/students/qr-codes', 'QR & Media', 'qr', abilities.prepare_qr_codes, isQrRoute),
                item('/admin/voice-generation', 'Announcements', 'audio', hasAnnouncementAccess(abilities), isAnnouncementRoute, { tour: 'announcements' }),
                item('/admin/pronunciation-rules', 'Pronunciation Dictionary', 'audio', abilities.view_pronunciation_rules, pathPrefix('/admin/pronunciation-rules'), { tour: 'pronunciation-dictionary' }),
                item('/admin/stage-designs', 'Stage Design', 'audience', abilities.manage_stage_designs, pathPrefix('/admin/stage-designs')),
            ],
        },
        {
            label: 'Reports',
            items: [
                item('/admin/scan-logs', 'Scan Logs', 'scanLog', abilities.view_operational_logs, pathPrefix('/admin/scan-logs'), { tour: 'operational-logs' }),
                item('/admin/stage-events', 'Stage Events', 'events', abilities.view_operational_logs, pathPrefix('/admin/stage-events')),
            ],
        },
        {
            label: 'Settings',
            items: [
                item('/admin/users', 'Operator Accounts', 'users', abilities.manage_users, pathPrefix('/admin/users'), { tour: 'operator-accounts' }),
                item('/admin/audit-logs', 'Audit Logs', 'audit', abilities.view_audit_logs, pathPrefix('/admin/audit-logs')),
            ],
        },
    ];

    return { primaryNavigation, secondaryNavigation };
}

function mapNavigation(groups, location) {
    return groups
        .map((group) => ({
            label: group.label,
            items: group.items
                .filter((entry) => entry.visible && entry.href)
                .map((entry) => ({
                    href: entry.href,
                    icon: entry.icon,
                    label: entry.label,
                    external: entry.external,
                    tour: entry.tour,
                    active: entry.match(location),
                })),
        }))
        .filter((group) => group.items.length > 0);
}

export function buildNavigation(abilities, currentUrl, options = {}) {
    const { primaryNavigation } = navigationDefinition(abilities, options);

    return mapNavigation(primaryNavigation, parseLocation(currentUrl));
}

export function buildCommandNavigation(abilities, currentUrl, options = {}) {
    const { primaryNavigation, secondaryNavigation } = navigationDefinition(abilities, options);
    const location = parseLocation(currentUrl);
    const primary = mapNavigation(primaryNavigation, location);
    const secondary = mapNavigation(secondaryNavigation, location);
    const seen = new Set();

    return [...primary, ...secondary]
        .map((group) => ({
            ...group,
            items: group.items.filter((entry) => {
                if (seen.has(entry.href)) {
                    return false;
                }
                seen.add(entry.href);
                return true;
            }),
        }))
        .filter((group) => group.items.length > 0);
}

export const navigationMatchers = {
    isAnnouncementRoute,
    isCeremonyRoute,
    isImportRoute,
    isPreparationRoute,
    isQrRoute,
    isReportsRoute,
    isRunCeremonyRoute,
    isSettingsRoute,
    isStudentRoute,
    queryValue,
};
