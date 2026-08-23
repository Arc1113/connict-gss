import test from 'node:test';
import assert from 'node:assert/strict';
import {
    buildCommandNavigation,
    buildNavigation,
} from '../../resources/js/components/appNavigation.js';

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
    view_pronunciation_rules: true,
};

test('administrator receives the complete high-level workflow navigation', () => {
    const navigation = buildNavigation(
        administratorAbilities,
        '/dashboard',
        { workingCeremony: { id: 42 } },
    );

    assert.deepEqual(labels(navigation), [
        { label: 'Home', items: ['Home'] },
        { label: 'Graduates', items: ['Graduates'] },
        { label: 'Preparation', items: ['Preparation', 'Import Graduates'] },
        {
            label: 'Run Ceremony',
            items: ['Scanner', 'Stage Control', 'Stage Display'],
        },
        { label: 'Reports', items: ['Reports'] },
        {
            label: 'Settings',
            items: ['Ceremonies', 'Device Connections', 'Backups'],
        },
    ]);
});

test('data encoder sees only Home, Graduates, and Preparation in primary navigation', () => {
    const navigation = buildNavigation(
        {
            ...administratorAbilities,
            manage_users: false,
            manage_ceremonies: false,
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
        { label: 'Home', items: ['Home'] },
        { label: 'Graduates', items: ['Graduates'] },
        { label: 'Preparation', items: ['Preparation', 'Import Graduates'] },
    ]);

    const commands = buildCommandNavigation(
        administratorAbilities,
        '/admin/students',
        { workingCeremony: { id: 12 } },
    );
    const commandLabels = commands.flatMap((group) => group.items.map((entry) => entry.label));
    assert.ok(commandLabels.includes('Import Graduates'));
    assert.ok(commandLabels.includes('Pronunciation Dictionary'));
});

test('scanner and stage operators receive only the relevant ceremony controls', () => {
    const scanner = buildNavigation(
        {
            ...administratorAbilities,
            manage_users: false,
            operate_stage: false,
            view_ceremonies: false,
            manage_ceremonies: false,
            view_students: false,
            manage_students: false,
            prepare_qr_codes: false,
            import_students: false,
            view_audit_logs: false,
            view_ceremony_reports: false,
            view_backups: false,
            manage_device_connections: false,
            view_operational_logs: false,
            manage_announcement_settings: false,
            manage_stage_designs: false,
            manage_announcement_batches: false,
            generate_announcement_candidates: false,
            review_announcement_candidates: false,
            view_pronunciation_rules: false,
        },
        '/scanner',
    );
    assert.deepEqual(labels(scanner), [
        { label: 'Home', items: ['Home'] },
        { label: 'Run Ceremony', items: ['Scanner'] },
    ]);

    const stage = buildNavigation(
        {
            ...administratorAbilities,
            manage_users: false,
            operate_scanner: false,
            view_ceremonies: false,
            manage_ceremonies: false,
            view_students: false,
            manage_students: false,
            prepare_qr_codes: false,
            import_students: false,
            view_audit_logs: false,
            view_ceremony_reports: false,
            view_backups: false,
            manage_device_connections: false,
            view_operational_logs: false,
            manage_announcement_settings: false,
            manage_stage_designs: false,
            manage_announcement_batches: false,
            generate_announcement_candidates: false,
            review_announcement_candidates: false,
            view_pronunciation_rules: false,
        },
        '/stage-control',
    );
    assert.deepEqual(labels(stage), [
        { label: 'Home', items: ['Home'] },
        { label: 'Run Ceremony', items: ['Stage Control', 'Stage Display'] },
    ]);
});

test('technical operator receives system-focused reports and settings', () => {
    const navigation = buildNavigation(
        {
            ...administratorAbilities,
            manage_users: false,
            operate_scanner: false,
            operate_stage: false,
            view_ceremonies: false,
            manage_ceremonies: false,
            view_students: false,
            manage_students: false,
            prepare_qr_codes: false,
            import_students: false,
            view_ceremony_reports: false,
            manage_announcement_settings: false,
            manage_stage_designs: false,
            manage_announcement_batches: false,
            generate_announcement_candidates: false,
            review_announcement_candidates: false,
            view_pronunciation_rules: false,
        },
        '/admin/device-connections',
    );
    assert.deepEqual(labels(navigation), [
        { label: 'Home', items: ['Home'] },
        { label: 'Reports', items: ['Reports'] },
        { label: 'Settings', items: ['Device Connections', 'Backups'] },
    ]);
});

test('primary and command navigation preserve role-aware active route matching', () => {
    const primary = buildNavigation(
        administratorAbilities,
        '/admin/students/import',
        { workingCeremony: { id: 12 } },
    );
    const commands = buildCommandNavigation(
        administratorAbilities,
        '/admin/students/import',
        { workingCeremony: { id: 12 } },
    );

    assert.equal(
        primary.find((group) => group.label === 'Preparation')?.items[0].active,
        true,
    );
    assert.equal(
        commands.flatMap((group) => group.items).find((item) => item.label === 'Import Graduates')?.active,
        true,
    );
});
