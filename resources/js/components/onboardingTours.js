export const ONBOARDING_TOURS = {
    administrator: [
        { target: 'nav-ceremonies', href: '/admin/ceremonies', title: 'Create ceremony', body: 'Create the event and choose the ceremony you are preparing.' },
        { target: 'nav-graduates', href: '/admin/students', title: 'Add graduates', body: 'Add or import verified graduate records, then resolve any preparation warnings.' },
        { target: 'nav-preparation', href: '/dashboard', title: 'Review preparation', body: 'Use the preparation checklist to see the next action and open the exact workflow that needs attention.' },
        { target: 'nav-operator-accounts', href: '/admin/users', title: 'Check operators and devices', body: 'Give each station a named account and verify devices before rehearsal.' },
        { target: 'nav-preparation', href: '/dashboard', title: 'Ready', body: 'When the checklist is clear, the ceremony workspace is ready for rehearsal or live operation.' },
    ],
    data_encoder: [
        { target: 'nav-graduates', href: '/admin/students', title: 'Add or import graduates', body: 'Add verified records or import a graduate list, then review each preparation status.' },
        { target: 'nav-preparation', href: '/admin/pronunciation-rules', title: 'Fix preparation issues', body: 'Correct spoken names and wording before announcements are generated.' },
        { target: 'nav-preparation', href: '/admin/voice-generation', title: 'Review announcements', body: 'Generate missing audio and listen for approval before the ceremony.' },
    ],
    scanner_operator: [
        { target: 'scanner-connection', href: '/scanner', title: 'Verify scanner ready', body: 'Confirm the server and live updates are ready before scanning.' },
        { target: 'scanner-input', href: '/scanner', title: 'Test scan', body: 'Keep the USB input focused and scan a designated rehearsal code.' },
        { target: 'scanner-fallback', href: '/scanner', title: 'Learn fallback lookup', body: 'If a QR code cannot be read, find and verify the graduate before processing.' },
    ],
    stage_operator: [
        { target: 'stage-connection', href: '/stage-control', title: 'Verify stage connection', body: 'Confirm live updates and the audience display state before issuing commands.' },
        { target: 'stage-queue', href: '/stage-control', title: 'Test queue controls', body: 'Check the current graduate, next graduate, and queue order before advancing.' },
        { target: 'stage-connection', href: '/stage-control', title: 'Review recovery actions', body: 'Use pause, holding, return, skip, or replay when correcting an exceptional presentation state.' },
    ],
    technical_operator: [
        { target: 'nav-device-connections', href: '/admin/device-connections', title: 'Verify devices', body: 'Check the local certificate, server address, and enrolled devices.' },
        { target: 'nav-reports', href: '/admin/scan-logs', title: 'Verify logs', body: 'Use scan and stage records to investigate operational issues.' },
        { target: 'nav-backups', href: '/admin/backups', title: 'Verify backup', body: 'Create and verify a local backup before rehearsal or a system update.' },
    ],
};
