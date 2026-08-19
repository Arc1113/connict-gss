export const ONBOARDING_TOURS = {
    administrator: [
        { target: 'nav-ceremonies', href: '/admin/ceremonies', title: 'Ceremonies', body: 'Create the event, confirm its date and mode, then activate only the ceremony you are preparing.' },
        { target: 'nav-operator-accounts', href: '/admin/users', title: 'Operator accounts', body: 'Give each operator the role for their station instead of sharing an administrator account.' },
        { target: 'nav-ceremonies', href: '/admin/ceremonies', title: 'Announcement settings', body: 'Open the ceremony to choose its provider, voice, wording, pacing, and active announcement template.' },
        { target: 'nav-pronunciation-dictionary', href: '/admin/pronunciation-rules', title: 'Pronunciation dictionary', body: 'Review common terms and add ceremony-specific corrections before generating audio.' },
        { target: 'nav-readiness', href: '/admin', title: 'Readiness', body: 'Use readiness status before rehearsal and again before opening the doors.' },
        { target: 'nav-device-connections', href: '/admin/device-connections', title: 'Connections', body: 'Verify every operator device on the local ceremony network before the rehearsal.' },
    ],
    data_encoder: [
        { target: 'nav-graduates', href: '/admin/students', title: 'Graduate data', body: 'Add or import verified names, programs, honors, and awards.' },
        { target: 'nav-pronunciation-dictionary', href: '/admin/pronunciation-rules', title: 'Spoken wording', body: 'Create ceremony corrections and leave common terms for an administrator to manage.' },
        { target: 'nav-graduates', href: '/admin/students', title: 'Name and award preview', body: 'Open a graduate to compare display spelling with actual TTS text and correct exact name or award wording.' },
        { target: 'nav-voice-generation', href: '/admin/voice-generation', title: 'Batch generation', body: 'Generate private candidates for prepared graduates after dictionary and exact overrides are reviewed.' },
        { target: 'nav-voice-generation', href: '/admin/voice-generation', title: 'Review status', body: 'Listen to every generated candidate and track missing, stale, review, and approved audio before the ceremony.' },
    ],
    scanner_operator: [
        { target: 'scanner-connection', href: '/scanner', title: 'Connection state', body: 'Confirm both server requests and live updates are ready before scanning.' },
        { target: 'scanner-input', href: '/scanner', title: 'Scanner focus', body: 'Keep this input focused for a USB scanner. Press slash to restore focus.' },
        { target: 'scanner-fallback', href: '/scanner', title: 'Controlled lookup', body: 'Use verified lookup only when a QR code cannot be read.' },
        { target: 'scanner-input', href: '/scanner', title: 'Test scan', body: 'During rehearsal, scan a designated test code and confirm the expected graduate before live operation.' },
        { target: 'scanner-feedback', href: '/scanner', title: 'Scan feedback', body: 'Wait for accepted or rejected feedback before processing another graduate.' },
    ],
    stage_operator: [
        { target: 'stage-connection', href: '/stage-control', title: 'Live state', body: 'Do not issue stage commands while the system is disconnected or recovering.' },
        { target: 'stage-replay', href: '/stage-control', title: 'Audio activation', body: 'Activate venue audio and confirm an approved local announcement is playable before opening doors.' },
        { target: 'stage-queue', href: '/stage-control', title: 'Queue controls', body: 'Confirm the current graduate and the waiting order before advancing.' },
        { target: 'stage-replay', href: '/stage-control', title: 'Replay audio', body: 'Replay only the current approved announcement when the venue needs it.' },
        { target: 'stage-recovery', href: '/stage-control', title: 'Stop and recovery', body: 'Pause automatic operation or use the holding screen while correcting an exceptional presentation state.' },
    ],
    technical_operator: [
        { target: 'nav-readiness', href: '/dashboard', title: 'System health', body: 'Confirm the active ceremony and local service state before checking individual devices.' },
        { target: 'nav-device-connections', href: '/admin/device-connections', title: 'Device verification', body: 'Verify the local certificate, server address, and enrolled devices.' },
        { target: 'nav-operational-logs', href: '/admin/scan-logs', title: 'Operational logs', body: 'Use server records to investigate scanner and stage events.' },
        { target: 'nav-backups', href: '/admin/backups', title: 'Backup checks', body: 'Create and verify a backup before the event and before a system update.' },
        { target: 'nav-backups', href: '/admin/backups', title: 'Rehearsal and recovery', body: 'Complete venue rehearsal, export diagnostics, and confirm the recovery checklist before live operation.' },
    ],
};
