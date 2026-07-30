import { execFileSync, spawn, spawnSync } from 'node:child_process';
import { mkdirSync, mkdtempSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import process from 'node:process';

const repositoryPath = process.cwd();
const applicationUrl = process.env.GSS_APP_URL ?? 'http://127.0.0.1:8001';
const password = process.env.GSS_SEED_PASSWORD;
const chromePath = '/usr/bin/google-chrome';
const evidencePath = join(repositoryPath, 'docs', 'evidence', 'phase-11');
const profilesPath = mkdtempSync(join(tmpdir(), 'gss-phase11-browser-'));
const processes = [];
const screenshots = [];

if (!password) {
    throw new Error('GSS_SEED_PASSWORD is required.');
}

mkdirSync(evidencePath, { recursive: true });

class DevToolsClient {
    constructor(socket) {
        this.socket = socket;
        this.nextId = 1;
        this.pending = new Map();

        socket.addEventListener('message', (event) => {
            const message = JSON.parse(event.data);

            if (!message.id || !this.pending.has(message.id)) return;

            const { resolve, reject } = this.pending.get(message.id);
            this.pending.delete(message.id);
            message.error
                ? reject(new Error(message.error.message))
                : resolve(message.result);
        });
    }

    static async connect(port) {
        const deadline = Date.now() + 15_000;

        while (Date.now() < deadline) {
            try {
                const response = await fetch(`http://127.0.0.1:${port}/json/list`);
                const targets = await response.json();
                const target = targets.find((candidate) => candidate.type === 'page');

                if (target) {
                    const socket = new WebSocket(target.webSocketDebuggerUrl);
                    await new Promise((resolve, reject) => {
                        socket.addEventListener('open', resolve, { once: true });
                        socket.addEventListener('error', reject, { once: true });
                    });
                    const client = new DevToolsClient(socket);
                    await client.send('Page.enable');
                    await client.send('Runtime.enable');
                    await client.send('Network.enable');

                    return client;
                }
            } catch {
                // Chrome may still be starting.
            }

            await pause(150);
        }

        throw new Error(`Chrome DevTools did not start on port ${port}.`);
    }

    send(method, params = {}) {
        const id = this.nextId++;

        return new Promise((resolve, reject) => {
            this.pending.set(id, { resolve, reject });
            this.socket.send(JSON.stringify({ id, method, params }));
        });
    }

    async evaluate(expression) {
        const result = await this.send('Runtime.evaluate', {
            awaitPromise: true,
            expression,
            returnByValue: true,
        });

        if (result.exceptionDetails) {
            throw new Error(
                result.exceptionDetails.exception?.description ??
                    'Browser evaluation failed.',
            );
        }

        return result.result.value;
    }

    async navigate(url) {
        await this.send('Page.navigate', { url });
        await this.waitFor('document.readyState === "complete"');
    }

    async screenshot(path) {
        const result = await this.send('Page.captureScreenshot', {
            captureBeyondViewport: false,
            format: 'png',
            fromSurface: true,
        });
        writeFileSync(path, Buffer.from(result.data, 'base64'));
    }

    async setReducedMotion() {
        await this.send('Emulation.setEmulatedMedia', {
            features: [{ name: 'prefers-reduced-motion', value: 'reduce' }],
            media: '',
        });
    }

    async setViewport(width, height) {
        await this.send('Emulation.setDeviceMetricsOverride', {
            deviceScaleFactor: 1,
            height,
            mobile: false,
            width,
        });
    }

    async waitFor(expression, timeout = 20_000) {
        const deadline = Date.now() + timeout;

        while (Date.now() < deadline) {
            if (await this.evaluate(expression)) return;
            await pause(100);
        }

        const diagnostic = await this.evaluate(`({
            body: document.body.innerText.slice(0, 2200),
            href: location.href,
            pusher: window.Pusher?.instances?.[0]?.connection?.state ?? null,
        })`);
        throw new Error(
            `Timed out waiting for ${expression}\n${JSON.stringify(diagnostic, null, 2)}`,
        );
    }

    close() {
        this.socket.close();
    }
}

try {
    prepareFixtures();

    const admin = await launchBrowser('admin', 9281, 1440, 900);
    const encoder = await launchBrowser('encoder', 9282, 1440, 900);
    const scanner = await launchBrowser('scanner', 9283, 1440, 900);
    const control = await launchBrowser('control', 9284, 1440, 900);
    const stage = await launchBrowser('stage', 9285, 1920, 1080);

    await Promise.all([
        login(admin.client, 'admin@example.test'),
        login(encoder.client, 'encoder@example.test'),
        login(scanner.client, 'scanner@example.test'),
        login(control.client, 'stage@example.test'),
        login(stage.client, 'stage@example.test'),
    ]);

    await Promise.all([
        scanner.client.navigate(`${applicationUrl}/scanner`),
        control.client.navigate(`${applicationUrl}/stage-control`),
        stage.client.navigate(`${applicationUrl}/stage`),
    ]);

    await scanner.client.waitFor('document.body.innerText.includes("Scanner")');
    await control.client.waitFor('document.body.innerText.includes("Stage Control") || document.body.innerText.includes("Stage control")');
    await stage.client.waitFor('document.body.innerText.includes("GSS-RC1 FULL-SYSTEM REHEARSAL")');

    await captureAt(scanner.client, 'scanner-ready-1440x900.png', 1440, 900);
    await captureAt(control.client, 'stage-control-empty-1440x900.png', 1440, 900);
    await captureAt(stage.client, 'stage-idle-1920x1080.png', 1920, 1080);
    await captureAt(stage.client, 'stage-idle-1366x768.png', 1366, 768);

    await postJson(scanner.client, '/scans', { token: 'bad-token' }, 422);
    await postJson(scanner.client, '/scans', { token: 'GSS-RC1-INELIGIBLE' }, 422);

    await scan(scanner.client, 'GSS-RC1-ALPHA');
    await scan(scanner.client, 'GSS-RC1-BETA');
    await scan(scanner.client, 'GSS-RC1-GAMMA');
    await scan(scanner.client, ' GSS-RC1-DELTA\n');
    await scan(scanner.client, 'GSS-RC1-EPSILON');
    await postJson(scanner.client, '/scans', { token: 'GSS-RC1-ALPHA' }, 409);

    let queue = await getJson(control.client, '/stage/queue');
    assertQueueOrder(queue, [
        'Ana María de la Cruz-Santos',
        'Christopher Emmanuel Villanueva-Santos Rehearsal',
        'Ma. Lourdes P. Reyes',
        'Zoë Alexandra Montgomery-Li III',
        'No Audio Rehearsal Graduate',
    ]);

    await postJson(control.client, '/stage/present-next', {}, 200);
    await stage.client.waitFor('document.body.innerText.includes("Ana María de la Cruz-Santos")');
    await captureAt(stage.client, 'stage-presented-approved-audio-1920x1080.png', 1920, 1080);

    await scan(scanner.client, 'GSS-RC1-ZETA');
    await stage.client.waitFor('document.body.innerText.includes("Ana María de la Cruz-Santos")');

    await postJson(control.client, '/stage/complete-current', {}, 200);
    queue = await getJson(control.client, '/stage/queue');
    const longName = queue.control.waiting.find((item) =>
        item.student.display_name.includes('Christopher Emmanuel'),
    );
    await postJson(control.client, `/stage/present/${longName.id}`, {}, 200);
    await stage.client.waitFor('document.body.innerText.includes("Christopher Emmanuel Villanueva-Santos Rehearsal")');
    await captureAt(stage.client, 'stage-long-name-program-1366x768.png', 1366, 768);
    await assertAudienceSafe(stage.client);

    await postJson(control.client, '/stage/replay', {}, 200);
    await postJson(control.client, '/stage/holding/activate', {}, 200);
    await stage.client.waitFor('document.querySelector(".stage-page")?.dataset.stageStatus === "holding"');
    await captureAt(stage.client, 'stage-holding-1920x1080.png', 1920, 1080);
    await postJson(control.client, '/stage/holding/deactivate', {}, 200);
    await stage.client.waitFor('document.body.innerText.includes("Christopher Emmanuel Villanueva-Santos Rehearsal")');
    await postJson(control.client, '/stage/complete-current', {}, 200);

    queue = await getJson(control.client, '/stage/queue');
    const skipCandidate = queue.control.waiting.find((item) =>
        item.student.display_name.includes('Ma. Lourdes'),
    );
    await postJson(control.client, `/stage/skip/${skipCandidate.id}`, {}, 200);
    queue = await getJson(control.client, '/stage/queue');
    const skipped = queue.control.skipped.find((item) =>
        item.student.display_name.includes('Ma. Lourdes'),
    );
    await postJson(control.client, `/stage/return/${skipped.id}`, {}, 200);

    queue = await getJson(control.client, '/stage/queue');
    const missingAudio = queue.control.waiting.find((item) =>
        item.student.display_name.includes('No Audio'),
    );
    await postJson(control.client, `/stage/present/${missingAudio.id}`, {}, 200);
    await control.client.navigate(`${applicationUrl}/stage-control`);
    await control.client.waitFor('document.body.innerText.includes("Live announcer required")');
    await captureAt(control.client, 'stage-control-missing-audio-1024x768.png', 1024, 768);
    await postJson(control.client, '/stage/complete-current', {}, 200);

    await setCeremonyMode('automatic');
    await control.client.navigate(`${applicationUrl}/stage-control`);
    await control.client.waitFor('document.body.innerText.includes("Automatic mode")');
    await postJson(control.client, '/stage/automatic/pause', {}, 200);
    await scan(scanner.client, 'GSS-RC1-ETA');
    queue = await getJson(control.client, '/stage/queue');
    if (!queue.control.waiting.some((item) => item.student.display_name.includes('Paused Automatic Graduate'))) {
        throw new Error('Paused automatic scan did not remain waiting.');
    }
    await postJson(control.client, '/stage/automatic/resume', {}, 200);

    restartService('reverb');
    await waitForPort(8080);
    await scan(scanner.client, 'GSS-RC1-THETA');
    await control.client.navigate(`${applicationUrl}/stage-control`);
    await stage.client.navigate(`${applicationUrl}/stage`);
    await control.client.waitFor('document.body.innerText.includes("Paused Automatic Graduate")');
    await assertNoHorizontalOverflow(control.client, 'stage-control after Reverb restart');

    restartService('app');
    await waitForHttp(`${applicationUrl}/up`);
    await Promise.all([
        scanner.client.navigate(`${applicationUrl}/scanner`),
        control.client.navigate(`${applicationUrl}/stage-control`),
        stage.client.navigate(`${applicationUrl}/stage`),
    ]);
    await scanner.client.waitFor('document.body.innerText.includes("Scanner")');
    await control.client.waitFor('document.body.innerText.includes("Waiting queue")');
    await assertAudienceSafe(stage.client);

    await inspectAdminReadiness(admin.client, encoder.client);
    await inspectViewports(scanner.client, control.client, stage.client);

    const summary = await finalDatabaseSummary();
    if (summary.queue_items < 8 || summary.scan_logs < 10 || summary.stage_events < 8) {
        throw new Error(`Unexpectedly low rehearsal counts: ${JSON.stringify(summary)}`);
    }

    console.log(JSON.stringify({
        adminAndEncoderReviewed: true,
        appRestartRecovered: true,
        audienceSafe: true,
        browserProfiles: ['admin', 'encoder', 'scanner', 'stage-control', 'stage'],
        finalCounts: summary,
        localAssetEvidence: await localAssetCheck(stage.client),
        reverbRestartRecovered: true,
        screenshots,
        stageViewports: ['1920x1080', '1366x768'],
        status: 'phase11-browser-rehearsal-passed',
    }, null, 2));
} finally {
    for (const item of processes) {
        item.process.kill('SIGTERM');
    }

    await pause(500);
    try {
        rmSync(profilesPath, { force: true, maxRetries: 3, recursive: true, retryDelay: 250 });
    } catch (error) {
        console.warn(`Temporary browser profile cleanup skipped: ${error.message}`);
    }
}

async function scan(client, token) {
    const result = await postJson(client, '/scans', { token }, 201);
    if (result.status !== 'queued') {
        throw new Error(`Expected queued scan for ${token}: ${JSON.stringify(result)}`);
    }
    return result;
}

async function postJson(client, url, body = {}, expectedStatus = 200) {
    const result = await client.evaluate(`(async () => {
        const response = await fetch(${JSON.stringify(url)}, {
            body: JSON.stringify(${JSON.stringify(body)}),
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            method: 'POST',
        });
        let data = null;
        try { data = await response.json(); } catch {}
        return { data, status: response.status };
    })()`);

    if (result.status !== expectedStatus) {
        throw new Error(`Unexpected ${url} status ${result.status}; expected ${expectedStatus}: ${JSON.stringify(result.data)}`);
    }

    return result.data;
}

async function getJson(client, url) {
    const result = await client.evaluate(`(async () => {
        const response = await fetch(${JSON.stringify(url)}, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        });
        return { data: await response.json(), status: response.status };
    })()`);

    if (result.status !== 200) {
        throw new Error(`Unexpected ${url} status ${result.status}: ${JSON.stringify(result.data)}`);
    }

    return result.data;
}

async function login(client, email) {
    await client.navigate(`${applicationUrl}/login`);
    const result = await client.evaluate(`(async () => {
        const token = document.querySelector('meta[name="csrf-token"]')?.content;
        if (!token) return { status: 0, url: location.href, body: document.body.innerText.slice(0, 500) };
        const response = await fetch('/login', {
            body: new URLSearchParams({
                email: ${JSON.stringify(email)},
                password: ${JSON.stringify(password)},
            }),
            credentials: 'same-origin',
            headers: {
                Accept: 'text/html',
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-CSRF-TOKEN': token,
            },
            method: 'POST',
        });
        return { status: response.status, url: response.url };
    })()`);

    if (result.status !== 200 || !result.url.endsWith('/dashboard')) {
        throw new Error(`Login failed for ${email}: ${JSON.stringify(result)}`);
    }
}

async function launchBrowser(name, port, width, height) {
    const userDataDir = join(profilesPath, name);
    const browserProcess = spawn(chromePath, [
        '--headless=new',
        '--autoplay-policy=no-user-gesture-required',
        '--disable-gpu',
        '--no-first-run',
        '--no-default-browser-check',
        `--remote-debugging-port=${port}`,
        `--user-data-dir=${userDataDir}`,
        `${applicationUrl}/login`,
    ], {
        stdio: 'ignore',
    });
    processes.push({ name, process: browserProcess });

    const client = await DevToolsClient.connect(port);
    await client.setViewport(width, height);

    return { client, process: browserProcess };
}

async function captureAt(client, filename, width, height) {
    await client.setViewport(width, height);
    await client.waitFor('document.readyState === "complete"');
    await assertNoHorizontalOverflow(client, filename);
    const path = join(evidencePath, filename);
    await client.screenshot(path);
    screenshots.push(filename);
}

async function inspectViewports(scanner, control, stage) {
    await scanner.navigate(`${applicationUrl}/scanner`);
    await captureAt(scanner, 'scanner-1366x768.png', 1366, 768);
    await captureAt(scanner, 'scanner-1024x768.png', 1024, 768);

    await control.navigate(`${applicationUrl}/stage-control`);
    await captureAt(control, 'stage-control-1440x900.png', 1440, 900);
    await captureAt(control, 'stage-control-1366x768.png', 1366, 768);
    await captureAt(control, 'stage-control-1024x768.png', 1024, 768);

    await stage.navigate(`${applicationUrl}/stage`);
    await captureAt(stage, 'stage-final-1920x1080.png', 1920, 1080);
    await captureAt(stage, 'stage-final-1366x768.png', 1366, 768);
    await stage.setReducedMotion();
    await captureAt(stage, 'stage-reduced-motion-1366x768.png', 1366, 768);
}

async function inspectAdminReadiness(admin, encoder) {
    const routes = [
        '/admin',
        '/admin/ceremonies',
        '/admin/students',
        '/admin/students/import',
        '/admin/students/qr-codes',
        '/admin/scan-logs',
        '/admin/stage-events',
        '/admin/audit-logs',
        '/admin/reports/ceremony-summary',
        '/admin/backups',
    ];

    for (const route of routes) {
        await admin.navigate(`${applicationUrl}${route}`);
        await admin.waitFor('document.body.innerText.length > 200');
        await assertNoHorizontalOverflow(admin, route);
    }

    await encoder.navigate(`${applicationUrl}/admin/backups`);
    await encoder.waitFor('document.body.innerText.includes("403") || document.body.innerText.includes("Forbidden")');

    const exportCheck = await admin.evaluate(`(async () => {
        const response = await fetch('/admin/scan-logs/export', { credentials: 'same-origin' });
        const text = await response.text();
        return { status: response.status, safe: !text.includes('GSS-RC1-') && !text.includes('qr_token') };
    })()`);

    if (exportCheck.status !== 200 || !exportCheck.safe) {
        throw new Error(`Scan export failed privacy check: ${JSON.stringify(exportCheck)}`);
    }
}

async function assertAudienceSafe(client) {
    const safe = await client.evaluate(`(() => {
        const text = document.body.innerText;
        const bad = [
            'Dashboard',
            'Scanner',
            'Stage Control',
            'queue position',
            'GSS-RC1-',
            'student number',
            'Reverb',
            'WebSocket',
            'SQLSTATE',
            'Stack Trace',
        ];
        return {
            bad: bad.filter((value) => text.includes(value)),
            overflow: document.documentElement.scrollWidth > document.documentElement.clientWidth,
            status: document.querySelector('.stage-page')?.dataset.stageStatus ?? null,
        };
    })()`);

    if (safe.bad.length > 0 || safe.overflow) {
        throw new Error(`Audience safety check failed: ${JSON.stringify(safe)}`);
    }
}

async function assertNoHorizontalOverflow(client, label) {
    const result = await client.evaluate(`(() => ({
        clientWidth: document.documentElement.clientWidth,
        scrollWidth: document.documentElement.scrollWidth,
        bodyScrollWidth: document.body.scrollWidth,
        offenders: Array.from(document.querySelectorAll('body *'))
            .map((element) => {
                const rect = element.getBoundingClientRect();
                return {
                    className: element.className?.toString() ?? '',
                    right: Math.round(rect.right),
                    tag: element.tagName,
                    width: Math.round(rect.width),
                };
            })
            .filter((item) => item.right > document.documentElement.clientWidth + 2)
            .sort((left, right) => right.right - left.right)
            .slice(0, 8),
    }))()`);

    if (
        result.scrollWidth > result.clientWidth + 2 ||
        result.bodyScrollWidth > result.clientWidth + 2
    ) {
        throw new Error(`Horizontal overflow in ${label}: ${JSON.stringify(result)}`);
    }
}

async function localAssetCheck(client) {
    return client.evaluate(`(() => {
        const pageHost = location.host;
        const entries = performance.getEntriesByType('resource').map((entry) => entry.name);
        const remote = entries.filter((url) => {
            try {
                const parsed = new URL(url);
                return parsed.host !== pageHost;
            } catch {
                return false;
            }
        });
        return {
            hasBuildAssets: entries.some((url) => url.includes('/build/assets/')),
            remote,
            viteHot: entries.some((url) => url.includes('/@vite') || url.includes('vite/client')),
        };
    })()`);
}

function assertQueueOrder(queue, expectedNames) {
    const names = queue.control.waiting.map((item) => item.student.display_name);
    for (const [index, expected] of expectedNames.entries()) {
        if (names[index] !== expected) {
            throw new Error(`Queue order mismatch at ${index}: expected ${expected}, got ${names[index]}; full=${JSON.stringify(names)}`);
        }
    }
}

function restartService(service) {
    execFileSync('docker', ['compose', 'restart', service], {
        cwd: repositoryPath,
        stdio: 'ignore',
    });
}

async function waitForHttp(url) {
    const deadline = Date.now() + 30_000;

    while (Date.now() < deadline) {
        try {
            const response = await fetch(url);
            if (response.ok) return;
        } catch {
            // Service may still be restarting.
        }
        await pause(500);
    }

    throw new Error(`Timed out waiting for ${url}`);
}

async function waitForPort(port) {
    const deadline = Date.now() + 30_000;

    while (Date.now() < deadline) {
        const result = spawnSync('php', [
            '-r',
            `exit(@fsockopen('127.0.0.1', ${Number(port)}) ? 0 : 1);`,
        ], { stdio: 'ignore' });

        if (result.status === 0) return;
        await pause(500);
    }

    throw new Error(`Timed out waiting for 127.0.0.1:${port}`);
}

async function setCeremonyMode(mode) {
    execFileSync('docker', [
        'compose',
        'exec',
        '-T',
        'app',
        'php',
        'artisan',
        'tinker',
        '--execute',
        `App\\Models\\Ceremony::query()->where("name", "GSS-RC1 Full-System Rehearsal")->update(["presentation_mode" => "${mode}"]);`,
    ], {
        cwd: repositoryPath,
        stdio: 'ignore',
    });
}

async function finalDatabaseSummary() {
    const output = execFileSync('docker', [
        'compose',
        'exec',
        '-T',
        'app',
        'php',
        'artisan',
        'tinker',
        '--execute',
        `echo json_encode([
            "graduates" => App\\Models\\Student::query()->whereHas("ceremony", fn ($q) => $q->where("name", "GSS-RC1 Full-System Rehearsal"))->count(),
            "queue_items" => App\\Models\\StageQueueItem::query()->whereHas("ceremony", fn ($q) => $q->where("name", "GSS-RC1 Full-System Rehearsal"))->count(),
            "scan_logs" => App\\Models\\ScanLog::query()->whereHas("ceremony", fn ($q) => $q->where("name", "GSS-RC1 Full-System Rehearsal"))->count(),
            "stage_events" => App\\Models\\StageEvent::query()->whereHas("ceremony", fn ($q) => $q->where("name", "GSS-RC1 Full-System Rehearsal"))->count(),
        ]);`,
    ], {
        cwd: repositoryPath,
        encoding: 'utf8',
    });

    return JSON.parse(output.trim());
}

function prepareFixtures() {
    const students = [
        ['RC1-001', 'GSS-RC1-ALPHA', 'Ana María de la Cruz-Santos', 'Ana María de la Cruz-Santos', 'Bachelor of Science in Computer Science', 'Magna Cum Laude', 'approved', 'media/phase3/audio/student-01.wav'],
        ['RC1-002', 'GSS-RC1-BETA', 'Christopher Emmanuel Villanueva-Santos Rehearsal', 'Christopher Emmanuel Villanueva-Santos', 'Bachelor of Science in Electronics Engineering with Extended Rehearsal Program Name', 'Summa Cum Laude · Leadership Award', 'approved', 'media/phase3/audio/student-02.wav'],
        ['RC1-003', 'GSS-RC1-GAMMA', 'Ma. Lourdes P. Reyes', 'Ma. Lourdes P. Reyes', 'Bachelor of Science in Business Administration', null, 'approved', 'media/phase3/audio/student-03.wav'],
        ['RC1-004', 'GSS-RC1-DELTA', 'Zoë Alexandra Montgomery-Li III', 'Zoë Alexandra Montgomery-Li III', 'Bachelor of Arts in Communication', 'With Academic Distinction', 'rejected', null],
        ['RC1-005', 'GSS-RC1-EPSILON', 'No Audio Rehearsal Graduate', 'No Audio Rehearsal Graduate', 'Bachelor of Science in Computer Science', null, 'missing', null],
        ['RC1-006', 'GSS-RC1-ZETA', 'Queued While Presenting Graduate', 'Queued While Presenting Graduate', 'Bachelor of Science in Electronics Engineering', null, 'approved', 'media/phase3/audio/student-04.wav'],
        ['RC1-007', 'GSS-RC1-ETA', 'Paused Automatic Graduate', 'Paused Automatic Graduate', 'Bachelor of Science in Business Administration', null, 'approved', 'media/phase3/audio/student-01.wav'],
        ['RC1-008', 'GSS-RC1-THETA', 'Reverb Recovery Graduate', 'Reverb Recovery Graduate', 'Bachelor of Science in Computer Science', null, 'approved', 'media/phase3/audio/student-02.wav'],
        ['RC1-009', 'GSS-RC1-INELIGIBLE', 'Ineligible Rehearsal Graduate', 'Ineligible Rehearsal Graduate', 'Bachelor of Science in Computer Science', null, 'approved', 'media/phase3/audio/student-03.wav', 'ineligible'],
    ];

    const encoded = Buffer.from(JSON.stringify(students)).toString('base64');
    const code = `
        $students = json_decode(base64_decode("${encoded}"), true);
        App\\Models\\Ceremony::query()->where("status", "active")->where("name", "!=", "GSS-RC1 Full-System Rehearsal")->update(["status" => "completed", "completed_at" => now()]);
        $ceremony = App\\Models\\Ceremony::query()->updateOrCreate(
            ["name" => "GSS-RC1 Full-System Rehearsal", "academic_year" => "2026-2027"],
            ["venue" => "Fictional University Auditorium", "event_date" => now()->addDay()->toDateString(), "status" => "active", "presentation_mode" => "controlled", "activated_at" => now(), "completed_at" => null]
        );
        App\\Models\\StageState::query()->updateOrCreate(
            ["ceremony_id" => $ceremony->id],
            ["current_queue_item_id" => null, "display_mode" => "idle", "audio_status" => "stopped", "is_muted" => false, "automatic_paused" => false, "version" => 0]
        );
        App\\Models\\StageEvent::query()->where("ceremony_id", $ceremony->id)->delete();
        App\\Models\\ScanLog::query()->where("ceremony_id", $ceremony->id)->delete();
        App\\Models\\StageQueueItem::query()->where("ceremony_id", $ceremony->id)->delete();
        $admin = App\\Models\\User::query()->where("email", "admin@example.test")->sole();
        foreach ($students as $index => $row) {
            [$number, $token, $name, $announcement, $program, $honors, $audioStatus, $audioPath] = array_pad($row, 9, null);
            $eligibility = $row[8] ?? "eligible";
            App\\Models\\Student::query()->updateOrCreate(
                ["ceremony_id" => $ceremony->id, "student_number" => $number],
                [
                    "qr_token" => $token,
                    "first_name" => $name,
                    "last_name" => " ",
                    "display_name" => $name,
                    "announcement_name" => $announcement,
                    "college" => "Fictional College",
                    "program" => $program,
                    "major" => null,
                    "honors" => $honors,
                    "sequence_number" => $index + 1,
                    "eligibility_status" => $eligibility,
                    "audio_path" => $audioPath,
                    "audio_readiness_status" => $audioStatus,
                    "audio_uploaded_at" => $audioPath ? now() : null,
                    "audio_approved_at" => $audioStatus === "approved" ? now() : null,
                    "audio_approved_by" => $audioStatus === "approved" ? $admin->id : null,
                    "audio_review_notes" => $audioStatus === "rejected" ? "Rejected rehearsal audio; use live announcer." : null,
                    "audio_mime_type" => $audioPath ? "audio/wav" : null,
                    "audio_file_size" => $audioPath ? 1024 : null,
                ]
            );
        }
    `;

    execFileSync('docker', [
        'compose',
        'exec',
        '-T',
        'app',
        'php',
        'artisan',
        'tinker',
        '--execute',
        code,
    ], {
        cwd: repositoryPath,
        stdio: 'inherit',
    });
}

function pause(ms) {
    return new Promise((resolve) => setTimeout(resolve, ms));
}
