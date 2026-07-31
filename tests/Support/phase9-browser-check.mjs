import { execFileSync, spawn } from 'node:child_process';
import { mkdirSync, mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import process from 'node:process';

const repositoryPath = process.cwd();
const applicationUrl = process.env.GSS_APP_URL ?? 'http://127.0.0.1:8001';
const chromePath = '/usr/bin/google-chrome';
const password = readEnvironmentValue('GSS_SEED_PASSWORD');
const evidencePath = join(repositoryPath, 'docs', 'evidence', 'phase-9');
const profilesPath = mkdtempSync(join(tmpdir(), 'gss-phase9-browser-'));
const processes = [];
const screenshots = [];
let reverbStopped = false;

if (!password) {
    throw new Error('GSS_SEED_PASSWORD is missing from .env.');
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
            body: document.body.innerText.slice(0, 2500),
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

    const admin = await launchBrowser('admin', 9261, 1440, 900);
    const technical = await launchBrowser('technical', 9262, 1440, 900);
    const encoder = await launchBrowser('encoder', 9263, 1440, 900);
    const scanner = await launchBrowser('scanner', 9264, 1440, 900);
    const control = await launchBrowser('control', 9265, 1440, 900);
    const stage = await launchBrowser('stage', 9266, 1920, 1080);

    await Promise.all([
        login(admin.client, 'admin@example.test'),
        login(technical.client, 'technical@example.test'),
        login(encoder.client, 'encoder@example.test'),
        login(scanner.client, 'scanner@example.test'),
        login(control.client, 'stage@example.test'),
        login(stage.client, 'stage@example.test'),
    ]);

    await inspectOperationalPage(
        admin.client,
        '/admin/scan-logs?result=accepted',
        'Scan logs',
        'browser formulas',
        'scan-logs',
    );
    await inspectOperationalPage(
        admin.client,
        '/admin/stage-events?event_type=student_queued',
        'Stage events',
        'student_queued',
        'stage-events',
    );
    await inspectOperationalPage(
        admin.client,
        '/admin/audit-logs?action=phase9.browser',
        'Administrative audit history',
        'phase9.browser',
        'audit-logs',
    );
    const auditSafe = await admin.client.evaluate(`(() => {
        document.querySelector('.admin-audit-details summary')?.click();
        const text = document.body.innerText;
        return text.includes('[redacted]') &&
            !text.includes('GSS-PHASE9-SECRET') &&
            !text.includes('storage/graduates/audio/private.wav');
    })()`);

    if (!auditSafe) {
        throw new Error('Audit detail redaction was not visible after expanding details.');
    }
    await inspectOperationalPage(
        admin.client,
        '/admin/reports/ceremony-summary',
        'Ceremony completion summary',
        'Operational counts',
        'ceremony-summary',
    );
    await inspectOperationalPage(
        technical.client,
        '/admin/backups',
        'Backups',
        'gss-backup',
        'backups',
    );

    const csvSafe = await admin.client.evaluate(`(async () => {
        const response = await fetch('/admin/scan-logs/export?result=accepted', {
            credentials: 'same-origin',
        });
        const text = await response.text();
        return text.includes("'=Browser Formula Graduate") &&
            !text.includes('GSS-PHASE9-SECRET') &&
            !text.includes('raw_token_hash');
    })()`);

    if (!csvSafe) {
        throw new Error('CSV export did not preserve formula mitigation/privacy.');
    }

    const encoderDenied = await encoder.client.evaluate(`(async () => {
        const response = await fetch('/admin/backups', {
            credentials: 'same-origin',
            headers: { Accept: 'text/html' },
        });
        return response.status;
    })()`);

    if (encoderDenied !== 403) {
        throw new Error(`Data encoder backup access returned ${encoderDenied}.`);
    }

    await admin.client.navigate(`${applicationUrl}/admin/reports/ceremony-summary`);
    await admin.client.evaluate(`(() => {
        const input = document.querySelector('#confirmation');
        const setter = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value').set;
        setter.call(input, 'RESET CURRENT OPERATIONS');
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
        return true;
    })()`);
    await clickButton(admin.client, 'Reset current operations');
    await admin.client.waitFor('document.body.innerText.includes("Current operational state reset")');

    await Promise.all([
        scanner.client.navigate(`${applicationUrl}/scanner`),
        control.client.navigate(`${applicationUrl}/stage-control`),
        stage.client.navigate(`${applicationUrl}/stage`),
    ]);
    await scanner.client.waitFor('document.activeElement?.id === "scan-token"');
    await control.client.waitFor('document.body.innerText.includes("Live updates connected")');
    await stage.client.waitFor(
        'document.querySelector(".stage-page")?.dataset.stageStatus === "idle"',
    );

    execFileSync('docker', ['compose', 'stop', 'reverb'], {
        cwd: repositoryPath,
        stdio: 'ignore',
    });
    reverbStopped = true;
    await control.client.waitFor(
        'document.body.innerText.includes("Live updates disconnected")',
        30_000,
    );
    execFileSync('docker', ['compose', 'up', '-d', '--wait', 'reverb'], {
        cwd: repositoryPath,
        stdio: 'ignore',
    });
    reverbStopped = false;
    await control.client.waitFor(
        'document.body.innerText.includes("Reconnected — state recovered")',
        40_000,
    );

    await Promise.all([
        admin.client.setReducedMotion(),
        technical.client.setReducedMotion(),
        stage.client.setReducedMotion(),
    ]);
    const reducedMotion = await Promise.all([
        admin.client.evaluate('matchMedia("(prefers-reduced-motion: reduce)").matches'),
        technical.client.evaluate('matchMedia("(prefers-reduced-motion: reduce)").matches'),
        stage.client.evaluate('matchMedia("(prefers-reduced-motion: reduce)").matches'),
    ]);

    await assertNoHorizontalOverflow(admin.client, 'phase9 admin');
    await assertNoHorizontalOverflow(technical.client, 'phase9 technical backup');
    await assertStageSafe(stage.client);

    console.log(JSON.stringify({
        checks: {
            auditRedactionVisible: true,
            backupMetadataVisible: true,
            csvFormulaMitigated: csvSafe,
            encoderDeniedBackup: encoderDenied === 403,
            logsAndReportsOpened: true,
            noHorizontalOverflow: true,
            reducedMotionRecognized: reducedMotion.every(Boolean),
            resetRecoveredScannerControlStage: true,
            reverbRecoveredAfterReset: true,
        },
        screenshots,
    }, null, 2));
} finally {
    if (reverbStopped) {
        execFileSync('docker', ['compose', 'up', '-d', '--wait', 'reverb'], {
            cwd: repositoryPath,
            stdio: 'ignore',
        });
    }

    for (const browserProcess of processes) {
        await stopBrowser(browserProcess);
    }

    rmSync(profilesPath, {
        force: true,
        maxRetries: 5,
        recursive: true,
        retryDelay: 200,
    });
}

async function assertNoHorizontalOverflow(client, context) {
    const dimensions = await client.evaluate(`({
        client: document.documentElement.clientWidth,
        scroll: document.documentElement.scrollWidth,
    })`);

    if (dimensions.scroll > dimensions.client + 1) {
        throw new Error(`${context} has horizontal overflow: ${JSON.stringify(dimensions)}`);
    }
}

async function assertStageSafe(client) {
    const result = await client.evaluate(`(() => {
        const text = document.body.innerText.toLowerCase();
        return {
            controls: document.querySelectorAll('button, nav, input, select, textarea').length,
            forbidden: [
                'reverb',
                'websocket',
                'student number',
                'qr token',
                'stage control',
                'connection',
                'backup',
                'restore',
            ].filter((value) => text.includes(value)),
            overflowX: document.documentElement.scrollWidth > innerWidth + 1,
            overflowY: document.documentElement.scrollHeight > innerHeight + 1,
        };
    })()`);

    if (
        result.controls !== 0 ||
        result.forbidden.length > 0 ||
        result.overflowX ||
        result.overflowY
    ) {
        throw new Error(`Audience stage safety failure: ${JSON.stringify(result)}`);
    }
}

async function captureAt(client, filename, width, height) {
    await client.setViewport(width, height);
    await pause(180);
    const path = join(evidencePath, filename);
    await client.screenshot(path);
    screenshots.push(`docs/evidence/phase-9/${filename}`);
}

async function clickButton(client, label) {
    await client.waitFor(`(() => {
        const button = [...document.querySelectorAll('button')]
            .find((candidate) => candidate.textContent.trim() === ${JSON.stringify(label)});
        return Boolean(button && !button.disabled);
    })()`);
    await client.evaluate(`(() => {
        const button = [...document.querySelectorAll('button')]
            .find((candidate) => candidate.textContent.trim() === ${JSON.stringify(label)});
        button.click();
        return true;
    })()`);
    await pause(100);
}

async function inspectOperationalPage(client, path, heading, expected, slug) {
    await client.navigate(`${applicationUrl}${path}`);
    await client.waitFor(
        `document.body.innerText.includes(${JSON.stringify(heading)}) &&
            document.body.innerText.includes(${JSON.stringify(expected)})`,
    );
    await captureAt(client, `${slug}-1440x900.png`, 1440, 900);
    await captureAt(client, `${slug}-1366x768.png`, 1366, 768);
    await captureAt(client, `${slug}-1024x768.png`, 1024, 768);
    await assertNoHorizontalOverflow(client, slug);
}

async function launchBrowser(name, port, width, height) {
    const browserProcess = spawn(chromePath, [
        '--autoplay-policy=no-user-gesture-required',
        '--disable-dev-shm-usage',
        '--disable-gpu',
        '--headless=new',
        '--mute-audio',
        '--no-first-run',
        '--no-sandbox',
        `--remote-debugging-port=${port}`,
        `--user-data-dir=${join(profilesPath, name)}`,
        'about:blank',
    ], { stdio: 'ignore' });
    processes.push(browserProcess);
    const client = await DevToolsClient.connect(port);
    await client.setViewport(width, height);

    return { client, process: browserProcess };
}

async function login(client, email) {
    await client.navigate(`${applicationUrl}/login`);
    const result = await client.evaluate(`(async () => {
        const response = await fetch('/login', {
            body: new URLSearchParams({
                email: ${JSON.stringify(email)},
                password: ${JSON.stringify(password)},
            }),
            credentials: 'same-origin',
            headers: {
                Accept: 'text/html',
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            method: 'POST',
        });
        return { status: response.status, url: response.url };
    })()`);

    if (result.status !== 200 || result.url.endsWith('/login')) {
        throw new Error(`Login failed for ${email}: ${JSON.stringify(result)}`);
    }
}

function prepareFixtures() {
    const code = `
        $admin = App\\Models\\User::query()->where("email", "admin@example.test")->sole();
        $ceremony = App\\Models\\Ceremony::query()->where("status", "active")->firstOrFail();
        $state = App\\Models\\StageState::query()->firstOrCreate(
            ["ceremony_id" => $ceremony->id],
            ["display_mode" => "idle", "audio_status" => "stopped", "automatic_paused" => false, "version" => 1]
        );
        $state->update(["current_queue_item_id" => null, "display_mode" => "idle", "automatic_paused" => false, "version" => $state->version + 1]);
        $student = App\\Models\\Student::query()->updateOrCreate(
            ["ceremony_id" => $ceremony->id, "student_number" => "PHASE9-BROWSER"],
            [
                "qr_token" => "GSS-PHASE9-SECRET",
                "first_name" => "=Browser",
                "last_name" => "Formula Graduate",
                "display_name" => "=Browser Formula Graduate",
                "announcement_name" => "=Browser Formula Graduate",
                "college" => "College of Operational Recovery",
                "program" => "Bachelor of Science in Computer Science",
                "sequence_number" => 901,
                "eligibility_status" => "eligible",
                "audio_readiness_status" => "missing",
            ]
        );
        $queue = App\\Models\\StageQueueItem::query()->updateOrCreate(
            ["ceremony_id" => $ceremony->id, "student_id" => $student->id],
            ["status" => "waiting", "queue_position" => 99, "scanned_at" => now(), "scanned_by" => $admin->id]
        );
        App\\Models\\ScanLog::query()->updateOrCreate(
            ["ceremony_id" => $ceremony->id, "student_id" => $student->id, "message" => "+browser formulas are escaped"],
            ["raw_token_hash" => hash("sha256", "GSS-PHASE9-SECRET"), "result" => "accepted", "scanned_by" => $admin->id, "scanned_at" => now()]
        );
        App\\Models\\StageEvent::query()->updateOrCreate(
            ["ceremony_id" => $ceremony->id, "queue_item_id" => $queue->id, "event_type" => "student_queued"],
            ["payload" => ["version" => $state->version, "qr_token" => "GSS-PHASE9-SECRET"], "performed_by" => $admin->id, "created_at" => now()]
        );
        App\\Models\\AuditLog::query()->updateOrCreate(
            ["action" => "phase9.browser", "entity_type" => "student", "entity_id" => $student->id],
            ["user_id" => $admin->id, "old_values" => ["qr_token" => "GSS-PHASE9-SECRET"], "new_values" => ["audio_path" => "storage/graduates/audio/private.wav"], "ip_address" => "127.0.0.1", "created_at" => now()]
        );
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
        stdio: 'ignore',
    });
}

function readEnvironmentValue(key) {
    const content = readFileSync(join(repositoryPath, '.env'), 'utf8');

    for (const line of content.split(/\r?\n/)) {
        if (line.startsWith(`${key}=`)) {
            return line
                .slice(key.length + 1)
                .trim()
                .replace(/^(['"])(.*)\1$/, '$2');
        }
    }

    return null;
}

function pause(milliseconds) {
    return new Promise((resolve) => setTimeout(resolve, milliseconds));
}

async function stopBrowser(browserProcess) {
    if (!browserProcess || browserProcess.exitCode !== null) return;

    browserProcess.kill('SIGTERM');
    await Promise.race([
        new Promise((resolve) => browserProcess.once('exit', resolve)),
        pause(2_000),
    ]);

    if (browserProcess.exitCode === null) {
        browserProcess.kill('SIGKILL');
    }
}
