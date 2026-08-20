import { execFileSync } from 'node:child_process';

const repositoryPath = process.cwd();
const browserFixtureEnvironment = [
    '-e',
    `DB_DATABASE=${process.env.GSS_E2E_DB_DATABASE ?? 'graduation_stage_system_testing'}`,
    '-e',
    `LARAVEL_STORAGE_PATH=${process.env.GSS_E2E_STORAGE_PATH ?? '/app/.browser-e2e-storage'}`,
    '-e',
    `GSS_BACKUP_PATH=${process.env.GSS_E2E_BACKUP_PATH ?? '/app/.browser-e2e-storage/app/gss-backups'}`,
];

function runBrowserFixture(script) {
    return execFileSync(
        'docker',
        [
            'compose',
            'run',
            '--rm',
            '-T',
            ...browserFixtureEnvironment,
            'app',
            'php',
            script,
        ],
        {
            cwd: repositoryPath,
            encoding: 'utf8',
            stdio: 'pipe',
        },
    );
}

export function preparePhaseThreeStageFixture() {
    const output = runBrowserFixture('tests/Support/browser-phase3-fixture.php');

    const fixture = JSON.parse(output);

    if (
        fixture.active_ceremony !== 'Sample Graduation Ceremony' ||
        fixture.stage_state?.display_mode !== 'idle' ||
        fixture.stage_state?.current_queue_item_id !== null ||
        fixture.queue_count !== 0
    ) {
        throw new Error(`Unexpected browser fixture state: ${JSON.stringify(fixture)}`);
    }
}

export function prepareBrowserBackupFixture() {
    const output = runBrowserFixture('tests/Support/browser-backup-fixture.php');

    const fixture = JSON.parse(output);

    if (
        !Number.isInteger(fixture.removed_generations) ||
        fixture.candidate_storage_consistent !== true
    ) {
        throw new Error(`Unexpected backup fixture state: ${JSON.stringify(fixture)}`);
    }
}
