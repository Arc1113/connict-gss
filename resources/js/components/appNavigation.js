function isGraduateRoute(url) {
    return (
        url.startsWith('/admin/students')
        && !url.startsWith('/admin/students/import')
        && !url.startsWith('/admin/students/qr-codes')
        && !url.includes('media=audio_')
        && !/\/admin\/students\/\d+\/qr-code/.test(url)
    );
}

export function buildNavigation(abilities, currentUrl) {
    return [
        {
            label: 'Ceremony',
            items: [
                {
                    href: '/dashboard',
                    icon: 'overview',
                    label: 'Overview',
                    active: currentUrl === '/dashboard',
                },
                abilities.operate_scanner && {
                    href: '/scanner',
                    icon: 'scanner',
                    label: 'Scanner',
                    active: currentUrl.startsWith('/scanner'),
                },
                abilities.operate_stage && {
                    href: '/stage-control',
                    icon: 'stage',
                    label: 'Stage control',
                    active: currentUrl.startsWith('/stage-control'),
                },
                abilities.operate_stage && {
                    href: '/stage',
                    icon: 'audience',
                    label: 'Stage display',
                    active: currentUrl === '/stage',
                    external: true,
                },
            ].filter(Boolean),
        },
        {
            label: 'Data',
            items: [
                abilities.view_students && {
                    href: '/admin/students',
                    icon: 'graduates',
                    label: 'Graduates',
                    active: isGraduateRoute(currentUrl),
                },
                abilities.view_ceremonies && {
                    href: '/admin/ceremonies',
                    icon: 'ceremony',
                    label: 'Ceremonies',
                    active: currentUrl.startsWith('/admin/ceremonies'),
                },
                abilities.manage_stage_designs && {
                    href: '/admin/stage-designs',
                    icon: 'audience',
                    label: 'Stage designs',
                    active: currentUrl.startsWith('/admin/stage-designs'),
                },
                abilities.import_students && {
                    href: '/admin/students/import',
                    icon: 'import',
                    label: 'CSV import',
                    active: currentUrl.startsWith('/admin/students/import'),
                },
                abilities.prepare_qr_codes && {
                    href: '/admin/students/qr-codes',
                    icon: 'qr',
                    label: 'QR codes',
                    active:
                        currentUrl.startsWith('/admin/students/qr-codes')
                        || /\/admin\/students\/\d+\/qr-code/.test(currentUrl),
                },
            ].filter(Boolean),
        },
        {
            label: 'Operations',
            items: [
                abilities.generate_announcement_candidates && {
                    href: '/admin/voice-generation',
                    icon: 'audio',
                    label: 'Voice generation',
                    active: currentUrl.startsWith('/admin/voice-generation'),
                },
                abilities.view_students && {
                    href: '/admin/students?media=audio_needs_review',
                    icon: 'audio',
                    label: 'Audio readiness',
                    active: currentUrl.includes('media=audio_'),
                },
                abilities.view_operational_logs && {
                    href: '/admin/scan-logs',
                    icon: 'scanLog',
                    label: 'Scan logs',
                    active: currentUrl.startsWith('/admin/scan-logs'),
                },
                abilities.view_operational_logs && {
                    href: '/admin/stage-events',
                    icon: 'events',
                    label: 'Stage events',
                    active: currentUrl.startsWith('/admin/stage-events'),
                },
                abilities.view_ceremony_reports && {
                    href: '/admin/reports/ceremony-summary',
                    icon: 'reports',
                    label: 'Ceremony summary',
                    active: currentUrl.startsWith('/admin/reports'),
                },
            ].filter(Boolean),
        },
        {
            label: 'System',
            items: [
                abilities.view_audit_logs && {
                    href: '/admin/audit-logs',
                    icon: 'audit',
                    label: 'Audit logs',
                    active: currentUrl.startsWith('/admin/audit-logs'),
                },
                abilities.view_backups && {
                    href: '/admin/backups',
                    icon: 'backup',
                    label: 'Backups',
                    active: currentUrl.startsWith('/admin/backups'),
                },
                abilities.manage_users && {
                    href: '/admin/users',
                    icon: 'users',
                    label: 'Operator accounts',
                    active: currentUrl.startsWith('/admin/users'),
                },
                abilities.manage_device_connections && {
                    href: '/admin/device-connections',
                    icon: 'devices',
                    label: 'Connect devices',
                    active: currentUrl.startsWith('/admin/device-connections'),
                },
            ].filter(Boolean),
        },
    ].filter((group) => group.items.length > 0);
}
