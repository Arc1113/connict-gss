import { execFileSync, spawn } from 'node:child_process';
import { mkdirSync, mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import process from 'node:process';

const repositoryPath = process.cwd();
const applicationUrl = process.env.GSS_APP_URL ?? 'http://127.0.0.1:8001';
const chromePath = '/usr/bin/google-chrome';
const password = readEnvironmentValue('GSS_SEED_PASSWORD');
const evidencePath = join(repositoryPath, 'docs', 'evidence', 'phase-8');
const profilesPath = mkdtempSync(join(tmpdir(), 'gss-phase8-browser-'));
const fixtureAudioPath = join(
    repositoryPath,
    'public',
    'media',
    'phase3',
    'audio',
    'student-01.wav',
);
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

            if (!message.id || !this.pending.has(message.id)) {
                return;
            }

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
                    await client.send('DOM.enable');

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

    async setFileInput(selector, path) {
        const root = await this.send('DOM.getDocument', { depth: 1 });
        const input = await this.send('DOM.querySelector', {
            nodeId: root.root.nodeId,
            selector,
        });

        if (!input.nodeId) {
            throw new Error(`File input unavailable: ${selector}`);
        }

        await this.send('DOM.setFileInputFiles', {
            files: [path],
            nodeId: input.nodeId,
        });
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
            if (await this.evaluate(expression)) {
                return;
            }

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
    logStep('Preparing Phase 8 browser fixtures');
    const testData = prepareFixtures();
    logStep('Launching browser sessions');
    const admin = await launchBrowser('admin', 9251, 1440, 900);
    const encoder = await launchBrowser('encoder', 9252, 1440, 900);
    const scanner = await launchBrowser('scanner', 9253, 1440, 900);
    const control = await launchBrowser('control', 9254, 1440, 900);
    const stage = await launchBrowser('stage', 9255, 1920, 1080);

    logStep('Logging in seeded users');
    await Promise.all([
        login(admin.client, 'admin@example.test'),
        login(encoder.client, 'encoder@example.test'),
        login(scanner.client, 'scanner@example.test'),
        login(control.client, 'stage@example.test'),
        login(stage.client, 'stage@example.test'),
    ]);

    logStep('Opening Phase 8 routes');
    await Promise.all([
        admin.client.navigate(`${applicationUrl}/admin/students/${testData.review}`),
        encoder.client.navigate(`${applicationUrl}/admin/students/${testData.upload}/edit`),
        scanner.client.navigate(`${applicationUrl}/scanner`),
        control.client.navigate(`${applicationUrl}/stage-control`),
        stage.client.navigate(`${applicationUrl}/stage`),
    ]);
    await scanner.client.waitFor('document.activeElement?.id === "scan-token"');
    await control.client.waitFor('document.body.innerText.includes("Live updates connected")');
    await stage.client.waitFor(
        'document.querySelector(".stage-page")?.dataset.stageStatus === "idle"',
    );

    logStep('Checking existing needs-review state');
    await assertAdminAudioState(admin.client, 'Needs review');
    await captureAt(admin.client, 'admin-audio-needs-review-1440x900.png', 1440, 900);
    await captureAt(admin.client, 'admin-audio-needs-review-1366x768.png', 1366, 768);
    await captureAt(admin.client, 'admin-audio-needs-review-1024x768.png', 1024, 768);

    logStep('Uploading audio through encoder edit form');
    await encoder.client.setFileInput('#audio', fixtureAudioPath);
    await clickButton(encoder.client, 'Save graduate');
    await encoder.client.waitFor(
        'location.pathname.match(/\\/admin\\/students\\/\\d+$/) && document.body.innerText.includes("Uploaded")',
        30_000,
    );
    await assertAdminAudioState(encoder.client, 'Uploaded');
    await captureAt(encoder.client, 'encoder-audio-uploaded-1440x900.png', 1440, 900);

    logStep('Reviewing, approving, replacing, and rejecting audio');
    await admin.client.navigate(`${applicationUrl}/admin/students/${testData.upload}`);
    await postAudioReview(
        admin.client,
        testData.upload,
        'needs-review',
        'Browser review: pronunciation and timing checked.',
    );
    await admin.client.navigate(`${applicationUrl}/admin/students/${testData.upload}`);
    await admin.client.waitFor('document.body.innerText.includes("Needs review")');
    await postAudioReview(
        admin.client,
        testData.upload,
        'approve',
        'Approved in Phase 8 browser review.',
    );
    await admin.client.navigate(`${applicationUrl}/admin/students/${testData.upload}`);
    await admin.client.waitFor('document.body.innerText.includes("Approved")');
    await captureAt(admin.client, 'admin-audio-approved-1440x900.png', 1440, 900);

    await encoder.client.navigate(`${applicationUrl}/admin/students/${testData.upload}/edit`);
    await encoder.client.setFileInput('#audio', fixtureAudioPath);
    await clickButton(encoder.client, 'Save graduate');
    await encoder.client.waitFor('document.body.innerText.includes("Uploaded")', 30_000);
    await admin.client.navigate(`${applicationUrl}/admin/students/${testData.upload}`);
    await postAudioReview(
        admin.client,
        testData.upload,
        'reject',
        'Rejected after replacement to confirm approval reset.',
    );
    await admin.client.navigate(`${applicationUrl}/admin/students/${testData.upload}`);
    await admin.client.waitFor('document.body.innerText.includes("Rejected")');
    await captureAt(admin.client, 'admin-audio-rejected-1366x768.png', 1366, 768);

    logStep('Scanning missing-audio and approved-audio graduates');
    await scanner.client.waitFor('document.activeElement?.id === "scan-token"');
    await submitScannerToken(scanner.client, 'GSS-PHASE8-MISSING');
    await scanner.client.waitFor(
        'document.querySelector(".scan-feedback")?.dataset.feedbackStatus === "queued"',
    );
    await submitScannerToken(scanner.client, 'GSS-PHASE8-APPROVED');
    await scanner.client.waitFor(
        'document.querySelector(".scan-feedback")?.dataset.feedbackStatus === "queued"',
    );
    await control.client.waitFor(
        'document.querySelector(".stage-control-table-wrap")?.innerText.includes("Phase 8 Missing Audio Graduate")',
    );
    await control.client.evaluate('window.confirm = () => true');
    await clickButton(control.client, 'Present next graduate');
    await stage.client.waitFor(
        'document.body.innerText.includes("Phase 8 Missing Audio Graduate")',
    );
    await assertStageSafe(stage.client);
    const preloadReady = await stage.client.evaluate(`(async () => {
        const response = await fetch('/stage/state', {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        });
        const data = await response.json();
        return Boolean(data.state?.preload_audio_url);
    })()`);

    if (!preloadReady) {
        throw new Error('Approved next announcement audio was not exposed for safe preload.');
    }

    const replayDisabled = await control.client.evaluate(`(() => {
        const button = [...document.querySelectorAll('button')]
            .find((candidate) => candidate.textContent.trim() === 'Replay announcement');
        return button?.disabled === true &&
            document.body.innerText.includes('Live announcer required');
    })()`);

    if (!replayDisabled) {
        throw new Error('Missing audio did not disable replay with live-announcer guidance.');
    }

    logStep('Checking missing-audio fallback and approved preload');
    await captureAt(control.client, 'stage-control-missing-audio-1440x900.png', 1440, 900);
    await captureAt(control.client, 'stage-control-missing-audio-1366x768.png', 1366, 768);
    await captureAt(control.client, 'stage-control-missing-audio-1024x768.png', 1024, 768);
    await captureAt(stage.client, 'stage-missing-audio-1920x1080.png', 1920, 1080);
    await captureAt(stage.client, 'stage-missing-audio-1366x768.png', 1366, 768);

    logStep('Checking Reverb disconnect and reconnect recovery');
    execFileSync('docker', ['compose', 'stop', 'reverb'], {
        cwd: repositoryPath,
        stdio: 'ignore',
    });
    reverbStopped = true;
    await control.client.waitFor(
        'document.body.innerText.includes("Live updates disconnected")',
        30_000,
    );
    await assertStageSafe(stage.client);
    execFileSync('docker', ['compose', 'up', '-d', '--wait', 'reverb'], {
        cwd: repositoryPath,
        stdio: 'ignore',
    });
    reverbStopped = false;
    await control.client.waitFor(
        'document.body.innerText.includes("Reconnected — state recovered")',
        40_000,
    );

    logStep('Checking approved-audio stage replay');
    await clickButton(control.client, 'Complete and clear');
    await clickButton(control.client, 'Present next graduate');
    await stage.client.waitFor(
        'document.body.innerText.includes("Phase 8 Approved Audio Graduate")',
    );
    await stage.client.evaluate(`(() => {
        window.__phase8AudioEvents = [];
        const NativeAudio = window.Audio;
        window.Audio = function (...arguments_) {
            const audio = new NativeAudio(...arguments_);
            for (const name of ['play', 'playing', 'ended', 'pause']) {
                audio.addEventListener(name, () => {
                    window.__phase8AudioEvents.push({
                        name,
                        source: audio.currentSrc || audio.src,
                    });
                });
            }
            window.__phase8LastAudio = audio;
            return audio;
        };
        window.Audio.prototype = NativeAudio.prototype;
        document.body.dispatchEvent(new PointerEvent('pointerdown', { bubbles: true }));
        return true;
    })()`);
    await control.client.navigate(`${applicationUrl}/stage-control`);
    await clickButton(control.client, 'Replay announcement');
    await stage.client.waitFor(
        'window.__phase8AudioEvents.some((event) => event.name === "play")',
    );
    await assertStageSafe(stage.client);
    await captureAt(control.client, 'stage-control-approved-audio-1440x900.png', 1440, 900);
    await captureAt(stage.client, 'stage-approved-audio-1920x1080.png', 1920, 1080);
    await captureAt(stage.client, 'stage-approved-audio-1366x768.png', 1366, 768);

    logStep('Checking reduced motion and overflow');
    await Promise.all([
        admin.client.setReducedMotion(),
        control.client.setReducedMotion(),
        stage.client.setReducedMotion(),
    ]);
    const reducedMotion = await Promise.all([
        admin.client.evaluate('matchMedia("(prefers-reduced-motion: reduce)").matches'),
        control.client.evaluate('matchMedia("(prefers-reduced-motion: reduce)").matches'),
        stage.client.evaluate('matchMedia("(prefers-reduced-motion: reduce)").matches'),
    ]);
    await assertNoHorizontalOverflow(admin.client, 'admin audio review');
    await assertNoHorizontalOverflow(control.client, 'stage control audio status');
    await assertNoHorizontalOverflow(stage.client, 'audience stage audio states');

    console.log(JSON.stringify({
        checks: {
            adminUploadFlowReachedUploaded: true,
            adminApproveFlowReachedApproved: true,
            replacementResetAndRejectReachedRejected: true,
            approvedOnlyPreloadExposed: preloadReady,
            missingAudioReplayDisabled: replayDisabled,
            reverbReconnectRecoveredControl: true,
            localAudioReplayRequested: true,
            noAudienceTechnicalContent: true,
            noHorizontalOverflow: true,
            reducedMotionRecognized: reducedMotion.every(Boolean),
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

function logStep(message) {
    console.log(`[phase8-browser] ${message}`);
}

async function assertAdminAudioState(client, label) {
    await client.waitFor(
        `(() => {
            const text = document.body.innerText.toLowerCase();
            return text.includes(${JSON.stringify(label.toLowerCase())}) &&
                text.includes('announcement audio') &&
                text.includes('stage policy');
        })()`,
    );
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
                'audio missing',
                'audio approved',
                'audio rejected',
                'live announcer',
                'reverb',
                'websocket',
                'student number',
                'qr token',
                'stage control',
                'connection',
                'stack trace',
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
    const viewport = await client.evaluate(`({
        height: innerHeight,
        width: innerWidth,
    })`);

    if (viewport.width !== width || viewport.height !== height) {
        throw new Error(
            `Expected ${width}x${height}, received ${viewport.width}x${viewport.height}.`,
        );
    }

    const path = join(evidencePath, filename);
    await client.screenshot(path);
    screenshots.push(`docs/evidence/phase-8/${filename}`);
}

async function clickButton(client, label) {
    await client.waitFor(`(() => {
        const button = [...document.querySelectorAll('button')]
            .find((candidate) => candidate.textContent.trim() === ${JSON.stringify(label)});
        return Boolean(button && !button.disabled);
    })()`);
    const clicked = await client.evaluate(`(() => {
        const button = [...document.querySelectorAll('button')]
            .find((candidate) => candidate.textContent.trim() === ${JSON.stringify(label)});
        if (!button || button.disabled) return false;
        button.click();
        return true;
    })()`);

    if (!clicked) {
        throw new Error(`Button unavailable: ${label}`);
    }

    await pause(100);
    await client.waitFor(
        '!document.body.innerText.includes("Command in progress") && !document.body.innerText.includes("Saving…")',
        30_000,
    );
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

async function postAudioReview(client, studentId, action, notes) {
    const result = await client.evaluate(`(async () => {
        const response = await fetch(${JSON.stringify(`/admin/students/${studentId}/audio/${action}`)}, {
            body: new URLSearchParams({
                audio_review_notes: ${JSON.stringify(notes)},
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

    if (result.status !== 200) {
        throw new Error(
            `Audio review action failed: ${action} ${JSON.stringify(result)}`,
        );
    }
}

function prepareFixtures() {
    const code = `
        $admin = App\\Models\\User::query()->where("email", "admin@example.test")->sole();
        $ceremony = App\\Models\\Ceremony::query()->where("status", "active")->firstOrFail();
        $ceremony->update(["presentation_mode" => "controlled"]);
        $state = App\\Models\\StageState::query()->firstOrCreate(
            ["ceremony_id" => $ceremony->id],
            ["display_mode" => "idle", "audio_status" => "stopped", "version" => 1]
        );
        $state->update([
            "current_queue_item_id" => null,
            "display_mode" => "idle",
            "audio_status" => "stopped",
            "automatic_paused" => false,
            "version" => $state->version + 1,
        ]);
        App\\Models\\StageQueueItem::query()
            ->where("ceremony_id", $ceremony->id)
            ->whereIn("status", ["waiting", "ready", "presenting", "announced"])
            ->update(["status" => "skipped", "queue_position" => null]);

        $records = [
            "upload" => [
                "student_number" => "PHASE8-UPLOAD",
                "qr_token" => "GSS-PHASE8-UPLOAD",
                "display_name" => "Phase 8 Upload Review Graduate",
                "announcement_name" => "Phase 8 Upload Review Graduate",
                "sequence_number" => 801,
                "audio_path" => null,
                "audio_readiness_status" => "missing",
                "audio_review_notes" => null,
            ],
            "review" => [
                "student_number" => "PHASE8-REVIEW",
                "qr_token" => "GSS-PHASE8-REVIEW",
                "display_name" => "Phase 8 Pronunciation Review Graduate",
                "announcement_name" => "Phase 8 Pronunciation Review Graduate",
                "sequence_number" => 802,
                "audio_path" => "media/phase3/audio/student-01.wav",
                "audio_readiness_status" => "needs_review",
                "audio_review_notes" => "Browser fixture waiting for pronunciation review.",
            ],
            "missing" => [
                "student_number" => "PHASE8-MISSING",
                "qr_token" => "GSS-PHASE8-MISSING",
                "display_name" => "Phase 8 Missing Audio Graduate",
                "announcement_name" => "Phase 8 Missing Audio Graduate",
                "sequence_number" => 803,
                "audio_path" => null,
                "audio_readiness_status" => "missing",
                "audio_review_notes" => null,
            ],
            "approved" => [
                "student_number" => "PHASE8-APPROVED",
                "qr_token" => "GSS-PHASE8-APPROVED",
                "display_name" => "Phase 8 Approved Audio Graduate",
                "announcement_name" => "Phase 8 Approved Audio Graduate",
                "sequence_number" => 804,
                "audio_path" => "media/phase3/audio/student-01.wav",
                "audio_readiness_status" => "approved",
                "audio_review_notes" => "Browser fixture approved for local playback verification.",
            ],
        ];

        $ids = [];
        foreach ($records as $key => $record) {
            $audioStatus = $record["audio_readiness_status"];
            $student = App\\Models\\Student::query()->updateOrCreate(
                ["ceremony_id" => $ceremony->id, "student_number" => $record["student_number"]],
                [
                    "qr_token" => $record["qr_token"],
                    "first_name" => "Phase 8",
                    "last_name" => match ($key) {
                        "upload" => "Upload Review Graduate",
                        "review" => "Pronunciation Review Graduate",
                        "missing" => "Missing Audio Graduate",
                        default => "Approved Audio Graduate",
                    },
                    "display_name" => $record["display_name"],
                    "announcement_name" => $record["announcement_name"],
                    "college" => "College of Browser Verification",
                    "program" => "Bachelor of Science in Electronics Engineering",
                    "honors" => $key === "approved" ? "Magna Cum Laude; Leadership Award" : null,
                    "sequence_number" => $record["sequence_number"],
                    "eligibility_status" => "eligible",
                    "photo_path" => null,
                    "audio_path" => $record["audio_path"],
                    "audio_readiness_status" => $audioStatus,
                    "audio_uploaded_at" => $record["audio_path"] ? now() : null,
                    "audio_approved_at" => $audioStatus === "approved" ? now() : null,
                    "audio_approved_by" => $audioStatus === "approved" ? $admin->id : null,
                    "audio_review_notes" => $record["audio_review_notes"],
                    "audio_duration_seconds" => null,
                    "audio_mime_type" => $record["audio_path"] ? "audio/wav" : null,
                    "audio_file_size" => $record["audio_path"] ? 57408 : null,
                ]
            );
            $ids[$key] = $student->id;
        }

        echo json_encode($ids);
    `;

    const output = execFileSync('docker', [
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
        encoding: 'utf8',
    });

    return JSON.parse(output.trim());
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

async function submitScannerToken(client, token) {
    await client.evaluate(`(() => {
        const input = document.querySelector('#scan-token');
        const setter = Object.getOwnPropertyDescriptor(
            HTMLInputElement.prototype,
            'value',
        ).set;
        setter.call(input, ${JSON.stringify(token)});
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
        return true;
    })()`);
    await pause(50);
    await client.evaluate(
        'document.querySelector(".scanner-form").requestSubmit()',
    );
    await client.waitFor(
        'document.querySelector(".scan-feedback")?.dataset.feedbackStatus === "processing"',
    );
    await client.waitFor('!document.querySelector("#scan-token").readOnly');
}

function pause(milliseconds) {
    return new Promise((resolve) => setTimeout(resolve, milliseconds));
}

async function stopBrowser(browserProcess) {
    if (!browserProcess || browserProcess.exitCode !== null) {
        return;
    }

    browserProcess.kill('SIGTERM');
    await Promise.race([
        new Promise((resolve) => browserProcess.once('exit', resolve)),
        pause(2_000),
    ]);

    if (browserProcess.exitCode === null) {
        browserProcess.kill('SIGKILL');
    }
}
