import { existsSync, mkdirSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { spawnSync } from 'node:child_process';
import { dirname, resolve } from 'node:path';

const root = resolve(import.meta.dirname, '../..');
const spec = 'tests/Browser/full-system-critical.spec.js';
const checkpointPath = resolve(root, '.browser-e2e-results/phase7-release.json');
const command = process.argv[2] ?? '--run-pending';

function execute(commandName, args) {
    return spawnSync(commandName, args, { cwd: root, encoding: 'utf8' });
}

function discover() {
    const result = execute('npx', ['playwright', 'test', spec, '--list', '--reporter=json']);
    if (result.status !== 0) throw new Error(result.stderr || result.stdout);
    const report = JSON.parse(`${result.stdout}${result.stderr}`);
    return report.suites.flatMap((suite) => suite.specs.map((item) => item.title));
}

function checkpointFor(titles) {
    const saved = existsSync(checkpointPath)
        ? JSON.parse(readFileSync(checkpointPath, 'utf8')) : { tests: {} };
    return { tests: Object.fromEntries(titles.map((title) => {
        const savedTest = saved.tests?.[title];

        return [title, savedTest?.status === 'fail' && /No tests found/.test(savedTest.last_output ?? '')
            ? { ...savedTest, status: 'pending' }
            : savedTest ?? { title, status: 'pending', attempts: 0 }];
    })) };
}

function save(checkpoint) {
    mkdirSync(dirname(checkpointPath), { recursive: true });
    writeFileSync(checkpointPath, `${JSON.stringify(checkpoint, null, 2)}\n`);
}

function summary(checkpoint) {
    const tests = Object.values(checkpoint.tests);
    const count = (status) => tests.filter((test) => test.status === status).length;
    console.log(`Discovered: ${tests.length}\nPassed: ${count('pass')}\nFailed: ${count('fail')}\nPending: ${count('pending')}`);
}

const titles = discover();
if (command === '--reset') {
    rmSync(checkpointPath, { force: true });
    console.log('Checkpoint reset.');
    process.exit(0);
}
const checkpoint = checkpointFor(titles);
save(checkpoint);
if (command === '--status') {
    summary(checkpoint);
    process.exit(0);
}

const titlesToRun = command === '--rerun-failed'
    ? titles.filter((title) => checkpoint.tests[title].status === 'fail')
    : titles;

for (const title of titlesToRun) {
    const test = checkpoint.tests[title];
    if (test.status === 'pass') continue;
    if (test.status === 'fail' && command !== '--rerun-failed') break;
    test.attempts += 1;
    save(checkpoint);
    const escaped = title.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    const result = execute('tests/Support/run-browser-e2e.sh', ['--grep', escaped]);
    test.last_output = `${result.stdout}${result.stderr}`.slice(-2000);
    test.status = result.status === 0 ? 'pass' : /No tests found/.test(test.last_output) ? 'pending' : 'fail';
    save(checkpoint);
    console.log(`${test.status.toUpperCase()}: ${title}`);
    if (test.status !== 'pass') break;
}
summary(checkpoint);
