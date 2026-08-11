import test from 'node:test';
import assert from 'node:assert/strict';
import { buildNavigation } from '../../resources/js/components/appNavigation.js';

function labels(navigation) {
    return navigation.map((group) => ({
        label: group.label,
        items: group.items.map((item) => item.label),
    }));
}

const administratorAbilities = {
    manage_users: true,
    operate_scanner: true,
    operate_stage: true,
    view_ceremonies: true,
    manage_ceremonies: true,
    view_students: true,
    manage_students: true,
    prepare_qr_codes: true,
    regenerate_qr_codes: true,
    approve_audio: true,
    import_students: true,
    delete_students: true,
    view_audit_logs: true,
    view_operational_logs: true,
    export_operational_reports: true,
    view_ceremony_reports: true,
    view_backups: true,
    create_backups: true,
    manage_device_connections: true,
    reset_ceremony_operations: true,
    manage_announcement_settings: true,
    manage_stage_designs: true,
    manage_announcement_batches: true,
    generate_announcement_candidates: true,
    review_announcement_candidates: true,
};

test('administrator navigation uses the workflow-oriented group model', () => {
    const navigation = buildNavigation(
        administratorAbilities,
        '/dashboard',
        { workingCeremony: { id: 42 } },
    );

    assert.deepEqual(labels(navigation), [
        {
            label: 'OVERVIEW',
            items: ['Overview'],
        },
        {
            label: 'CEREMONY WORKSPACE',
            items: [
                'Ceremony Overview',
                'Graduates',
                'Import',
                'QR & Media',
                'Announcements',
                'Stage Design',
            ],
        },
        {
            label: 'LIVE OPERATIONS',
            items: ['Scanner', 'Stage Control', 'Stage Display'],
        },
        {
            label: 'REVIEW',
            items: ['Ceremony Summary', 'Scan Logs', 'Stage Events'],
        },
        {
            label: 'SYSTEM',
            items: ['Connect Devices', 'Backups', 'Operator Accounts', 'Audit Logs'],
        },
    ]);
});

test('data encoder navigation only shows permitted preparation destinations', () => {
    const navigation = buildNavigation(
        {
            ...administratorAbilities,
            manage_users: false,
            operate_scanner: false,
            operate_stage: false,
            view_audit_logs: false,
            view_operational_logs: false,
            view_ceremony_reports: false,
            view_backups: false,
            create_backups: false,
            manage_device_connections: false,
            reset_ceremony_operations: false,
            manage_announcement_settings: false,
            manage_stage_designs: false,
            manage_announcement_batches: false,
            review_announcement_candidates: false,
        },
        '/admin/students',
        { workingCeremony: { id: 12 } },
    );

    assert.deepEqual(labels(navigation), [
        {
            label: 'OVERVIEW',
            items: ['Overview'],
        },
        {
            label: 'CEREMONY WORKSPACE',
            items: [
                'Ceremony Overview',
                'Graduates',
                'Import',
                'QR & Media',
                'Announcements',
            ],
        },
    ]);
});

test('restricted live operator does not receive workspace or system ceremony administration links', () => {
    const navigation = buildNavigation(
        {
            ...administratorAbilities,
            manage_users: false,
            operate_scanner: false,
            view_ceremonies: false,
            manage_ceremonies: false,
            view_students: false,
            manage_students: false,
            prepare_qr_codes: false,
            regenerate_qr_codes: false,
            approve_audio: false,
            import_students: false,
            delete_students: false,
            view_audit_logs: false,
            view_ceremony_reports: false,
            view_backups: false,
            create_backups: false,
            manage_device_connections: false,
            reset_ceremony_operations: false,
            manage_announcement_settings: false,
            manage_stage_designs: false,
            manage_announcement_batches: false,
            generate_announcement_candidates: false,
            review_announcement_candidates: false,
        },
        '/stage-control',
    );

    assert.deepEqual(labels(navigation), [
        {
            label: 'OVERVIEW',
            items: ['Overview'],
        },
        {
            label: 'LIVE OPERATIONS',
            items: ['Stage Control', 'Stage Display'],
        },
        {
            label: 'REVIEW',
            items: ['Scan Logs', 'Stage Events'],
        },
    ]);
});

test('ceremony overview links to the working ceremony when available and falls back safely', () => {
    const withWorkingCeremony = buildNavigation(
        administratorAbilities,
        '/dashboard',
        { workingCeremony: { id: 88 } },
    );
    const withoutWorkingCeremony = buildNavigation(
        administratorAbilities,
        '/dashboard',
    );

    assert.equal(
        withWorkingCeremony[1].items[0].href,
        '/admin/ceremonies/88',
    );
    assert.equal(
        withoutWorkingCeremony[1].items[0].href,
        '/admin/ceremonies',
    );
});

test('active route matching prefers the specific primary workspace', () => {
    const importNavigation = buildNavigation(
        administratorAbilities,
        '/admin/students/import',
        { workingCeremony: { id: 12 } },
    );
    const studentDetailNavigation = buildNavigation(
        administratorAbilities,
        '/admin/students/123',
        { workingCeremony: { id: 12 } },
    );
    const announcementsNavigation = buildNavigation(
        administratorAbilities,
        '/admin/ceremonies/12/announcements',
        { workingCeremony: { id: 12 } },
    );

    assert.equal(
        importNavigation[1].items.find((item) => item.active)?.label,
        'Import',
    );
    assert.equal(
        studentDetailNavigation[1].items.find((item) => item.active)?.label,
        'Graduates',
    );
    assert.equal(
        announcementsNavigation[1].items.find((item) => item.active)?.label,
        'Announcements',
    );
});
