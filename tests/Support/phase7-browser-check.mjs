import { execFileSync, spawn } from 'node:child_process';
import { mkdirSync, mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import process from 'node:process';

const repositoryPath = process.cwd();
const applicationUrl = process.env.GSS_APP_URL ?? 'http://127.0.0.1:8001';
const chromePath = '/usr/bin/google-chrome';
const password = readEnvironmentValue('GSS_SEED_PASSWORD');
const evidencePath = join(repositoryPath, 'docs', 'evidence', 'phase-7');
const profilesPath = mkdtempSync(join(tmpdir(), 'gss-phase7-browser-'));
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

    async setViewport(width, height) {
        await this.send('Emulation.setDeviceMetricsOverride', {
            deviceScaleFactor: 1,
            height,
            mobile: false,
            width,
        });
    }

    async setReducedMotion() {
        await this.send('Emulation.setEmulatedMedia', {
            features: [{ name: 'prefers-reduced-motion', value: 'reduce' }],
            media: '',
        });
    }

    async screenshot(path) {
        const result = await this.send('Page.captureScreenshot', {
            captureBeyondViewport: false,
            format: 'png',
            fromSurface: true,
        });
        writeFileSync(path, Buffer.from(result.data, 'base64'));
    }

    close() {
        this.socket.close();
    }
}

try {
    const scanner = await launchBrowser('scanner', 9231, 1440, 900);
    const control = await launchBrowser('control', 9232, 1440, 900);
    const stage = await launchBrowser('stage', 9233, 1920, 1080);
    const admin = await launchBrowser('admin', 9234, 1440, 900);
    const technical = await launchBrowser('technical', 9235, 1366, 768);

    await Promise.all([
        login(scanner.client, 'scanner@example.test'),
        login(control.client, 'stage@example.test'),
        login(stage.client, 'stage@example.test'),
        login(admin.client, 'admin@example.test'),
        login(technical.client, 'technical@example.test'),
    ]);
    approveSeedAudioFixtures();
    await Promise.all([
        scanner.client.navigate(`${applicationUrl}/scanner`),
        control.client.navigate(`${applicationUrl}/stage-control`),
        stage.client.navigate(`${applicationUrl}/stage`),
    ]);

    await scanner.client.waitFor(
        'document.querySelector(".scan-feedback")?.dataset.feedbackStatus === "ready"',
    );
    await scanner.client.waitFor('document.activeElement?.id === "scan-token"');
    await control.client.waitFor(
        'document.body.innerText.includes("Queue is empty")',
    );
    await control.client.waitFor(
        'document.body.innerText.includes("Live updates connected")',
    );
    await stage.client.waitFor(
        'document.querySelector(".stage-page")?.dataset.stageStatus === "idle"',
    );

    await control.client.evaluate('window.confirm = () => true');
    await stage.client.evaluate(`(() => {
        window.__phase7AudioEvents = [];
        const NativeAudio = window.Audio;
        window.Audio = function (...arguments_) {
            const audio = new NativeAudio(...arguments_);
            for (const name of ['play', 'playing', 'ended', 'pause']) {
                audio.addEventListener(name, () => {
                    window.__phase7AudioEvents.push({
                        name,
                        source: audio.currentSrc || audio.src,
                    });
                });
            }
            window.__phase7LastAudio = audio;
            return audio;
        };
        window.Audio.prototype = NativeAudio.prototype;
        document.body.dispatchEvent(new PointerEvent('pointerdown', { bubbles: true }));
        return true;
    })()`);

    await captureAt(control.client, 'stage-control-empty-1440x900.png', 1440, 900);
    await captureAt(control.client, 'stage-control-empty-1366x768.png', 1366, 768);
    await captureAt(control.client, 'stage-control-empty-1024x768.png', 1024, 768);
    await captureAt(stage.client, 'stage-idle-1920x1080.png', 1920, 1080);
    await captureAt(stage.client, 'stage-idle-1366x768.png', 1366, 768);

    for (const token of [
        'GSS-PHASE3-B8L3N5R7',
        'GSS-PHASE3-A7K9M2Q4',
        'GSS-PHASE3-C4P6S8V2',
    ]) {
        await submitScannerToken(scanner.client, token);
        await scanner.client.waitFor(
            'document.querySelector(".scan-feedback")?.dataset.feedbackStatus === "queued"',
        );
    }

    await control.client.waitFor(
        'document.querySelectorAll(".stage-control-table-wrap tbody tr").length === 3',
    );
    const initialQueue = await queueSnapshot(control.client);

    if (
        initialQueue.length !== 3 ||
        initialQueue[0].position !== '1' ||
        !initialQueue[0].text.includes('Christopher Emmanuel Villanueva-Santos')
    ) {
        throw new Error(`Unexpected initial queue order: ${JSON.stringify(initialQueue)}`);
    }

    await captureAt(control.client, 'stage-control-queued-long-name-1440x900.png', 1440, 900);
    await captureAt(control.client, 'stage-control-queued-1024x768.png', 1024, 768);
    await assertNoHorizontalOverflow(control.client, 'queued stage control');
    await clickButton(control.client, 'Present next graduate');
    await control.client.waitFor(
        'document.querySelector(".stage-control-current-identity")?.innerText.includes("Christopher Emmanuel Villanueva-Santos")',
    );
    await stage.client.waitFor(
        'document.body.innerText.includes("Christopher Emmanuel Villanueva-Santos")',
    );
    await stage.client.waitFor(
        'window.__phase7AudioEvents.some((event) => event.name === "playing" || event.name === "ended")',
    );
    await assertStageSafe(stage.client);
    await captureAt(stage.client, 'stage-presented-long-name-1920x1080.png', 1920, 1080);
    await captureAt(stage.client, 'stage-presented-long-name-1366x768.png', 1366, 768);

    await submitScannerToken(scanner.client, 'GSS-PHASE3-D9T5W7Y3');
    await scanner.client.waitFor(
        'document.body.innerText.includes("queue position 4")',
    );
    await control.client.waitFor(
        'document.querySelectorAll(".stage-control-table-wrap tbody tr").length === 3',
    );
    const stillCurrent = await stage.client.evaluate(
        'document.body.innerText.includes("Christopher Emmanuel Villanueva-Santos")',
    );

    if (!stillCurrent) {
        throw new Error('A later scan preempted the current graduate.');
    }

    const audioCountBeforeReplay = await stage.client.evaluate(
        'window.__phase7AudioEvents.filter((event) => event.name === "play").length',
    );
    await clickButton(control.client, 'Replay announcement');
    await stage.client.waitFor(
        `window.__phase7AudioEvents.filter((event) => event.name === "play").length > ${audioCountBeforeReplay}`,
    );
    await clickButton(control.client, 'Correct wrong graduate');
    await stage.client.waitFor(
        'document.querySelector(".stage-page")?.dataset.stageStatus === "holding"',
    );
    await control.client.waitFor(
        'document.body.innerText.includes("Graduate returned to the queue without completion")',
    );
    await control.client.waitFor(
        'document.querySelector(".stage-control-table-wrap")?.innerText.includes("Christopher Emmanuel Villanueva-Santos")',
    );

    await selectQueueRow(control.client, 'Ma. Lourdes P. Reyes');
    await clickButton(control.client, 'Present selected');
    await stage.client.waitFor(
        'document.body.innerText.includes("Ma. Lourdes P. Reyes")',
    );

    await clickRowAction(control.client, 'Ana Marie dela Cruz', 'Skip');
    await control.client.waitFor(
        'document.querySelector(".stage-control-support")?.innerText.includes("Ana Marie dela Cruz")',
    );
    await clickSupportAction(control.client, 'Ana Marie dela Cruz', 'Return to queue');
    await control.client.waitFor(
        'document.querySelector(".stage-control-table-wrap")?.innerText.includes("Ana Marie dela Cruz")',
    );

    await clickButton(control.client, 'Show holding screen');
    await stage.client.waitFor(
        'document.querySelector(".stage-page")?.dataset.stageStatus === "holding"',
    );
    await assertStageSafe(stage.client);
    await captureAt(stage.client, 'stage-holding-1920x1080.png', 1920, 1080);
    await clickButton(control.client, 'Restore live display');
    await stage.client.waitFor(
        'document.body.innerText.includes("Ma. Lourdes P. Reyes")',
    );

    await control.client.navigate(`${applicationUrl}/stage-control`);
    await control.client.waitFor(
        'document.querySelector(".stage-control-current-identity")?.innerText.includes("Ma. Lourdes P. Reyes")',
    );
    await control.client.evaluate('window.confirm = () => true');
    await stage.client.navigate(`${applicationUrl}/stage`);
    await stage.client.waitFor(
        'document.body.innerText.includes("Ma. Lourdes P. Reyes")',
    );
    await assertStageSafe(stage.client);

    execFileSync('docker', ['compose', 'stop', 'reverb'], {
        cwd: repositoryPath,
        stdio: 'ignore',
    });
    reverbStopped = true;
    await control.client.waitFor(
        'document.body.innerText.includes("Live updates disconnected")',
        30_000,
    );
    await captureAt(control.client, 'stage-control-disconnected-1366x768.png', 1366, 768);
    await submitScannerToken(scanner.client, 'GSS-PHASE3-E2H6J8X4');
    await scanner.client.waitFor(
        'document.body.innerText.includes("saved at queue position")',
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
    await control.client.waitFor(
        'document.querySelector(".stage-control-table-wrap")?.innerText.includes("Elena Alexandria-Cassandra Villanueva")',
    );

    await clickButton(control.client, 'Complete and present next');
    await control.client.waitFor(
        'document.querySelector(".stage-control-current-identity")?.innerText.includes("Christopher Emmanuel Villanueva-Santos")',
    );
    await stage.client.waitFor(
        'document.body.innerText.includes("Christopher Emmanuel Villanueva-Santos")',
    );
    await clickButton(control.client, 'Complete and clear');
    await control.client.waitFor(
        'document.body.innerText.includes("No graduate is currently presented")',
    );
    await selectQueueRow(control.client, 'Elena Alexandria-Cassandra Villanueva');
    await clickButton(control.client, 'Present selected');
    await control.client.waitFor(
        'document.querySelector(".stage-control-current-identity")?.innerText.includes("Elena Alexandria-Cassandra Villanueva")',
    );
    const replayDisabled = await control.client.evaluate(`(() => {
        const button = [...document.querySelectorAll('button')]
            .find((candidate) => candidate.textContent.trim() === 'Replay announcement');
        return button?.disabled === true &&
            document.body.innerText.includes('Live announcer required');
    })()`);

    if (!replayDisabled) {
        throw new Error('Missing audio did not disable replay with operator guidance.');
    }

    await captureAt(control.client, 'stage-control-missing-audio-1440x900.png', 1440, 900);
    await captureAt(stage.client, 'stage-missing-audio-1366x768.png', 1366, 768);

    await clickButton(control.client, 'Complete and clear');
    await control.client.waitFor(
        'document.body.innerText.includes("No graduate is currently presented")',
    );
    prepareAutomaticModeFixtures();
    await Promise.all([
        scanner.client.navigate(`${applicationUrl}/scanner`),
        control.client.navigate(`${applicationUrl}/stage-control`),
        stage.client.navigate(`${applicationUrl}/stage`),
    ]);
    await control.client.waitFor(
        'document.body.innerText.includes("Assisted automatic")',
    );
    await control.client.evaluate('window.confirm = () => true');
    await submitScannerToken(scanner.client, 'GSS-PHASE7-AUTOMATIC');
    await scanner.client.waitFor(
        'document.body.innerText.includes("Queued and presented automatically")',
    );
    await stage.client.waitFor(
        'document.body.innerText.includes("Automatic Mode Graduate")',
    );
    await control.client.waitFor(
        'document.querySelector(".stage-control-current-identity")?.innerText.includes("Automatic Mode Graduate")',
    );
    await clickButton(control.client, 'Complete and clear');
    await clickButton(control.client, 'Pause assisted automatic');
    await control.client.waitFor(
        'document.body.innerText.includes("Automatic presentation paused")',
    );
    await submitScannerToken(scanner.client, 'GSS-PHASE7-AUTO-PAUSED');
    await scanner.client.waitFor(
        'document.body.innerText.includes("waiting at queue position")',
    );
    await stage.client.waitFor(
        'document.querySelector(".stage-page")?.dataset.stageStatus === "idle"',
    );
    await control.client.waitFor(
        'document.querySelector(".stage-control-table-wrap")?.innerText.includes("Paused Automatic Graduate")',
    );
    await clickButton(control.client, 'Resume assisted automatic');
    await stage.client.waitFor(
        'document.querySelector(".stage-page")?.dataset.stageStatus === "idle"',
    );
    const automaticPausePreservedQueue = await control.client.evaluate(
        'document.querySelector(".stage-control-table-wrap")?.innerText.includes("Paused Automatic Graduate")',
    );

    if (!automaticPausePreservedQueue) {
        throw new Error('Resuming automatic mode unexpectedly bypassed the waiting queue.');
    }

    const scannerDenied = await scanner.client.evaluate(`(async () => {
        const response = await fetch('/stage-control', {
            credentials: 'same-origin',
            headers: { Accept: 'text/html' },
        });
        return response.status;
    })()`);

    if (scannerDenied !== 403) {
        throw new Error(`Scanner operator received stage control status ${scannerDenied}.`);
    }

    await admin.client.navigate(`${applicationUrl}/admin/users`);
    await admin.client.waitFor(
        'document.body.innerText.includes("Operator accounts")',
    );
    for (const [width, height] of [
        [1440, 900],
        [1366, 768],
        [1024, 768],
    ]) {
        await captureAt(
            admin.client,
            `operator-accounts-${width}x${height}.png`,
            width,
            height,
        );
        await assertNoHorizontalOverflow(
            admin.client,
            `operator accounts ${width}x${height}`,
        );
    }
    await admin.client.navigate(`${applicationUrl}/admin/users/create`);
    await admin.client.waitFor(
        'document.querySelector("#password")?.getAttribute("minlength") === "12"',
    );
    await assertNoHorizontalOverflow(admin.client, 'operator account form');

    await admin.client.navigate(`${applicationUrl}/admin/ceremonies`);
    const ceremonyEditUrl = await admin.client.evaluate(
        '[...document.querySelectorAll("a")].find((link) => link.textContent.trim() === "Edit")?.href',
    );
    await admin.client.navigate(ceremonyEditUrl);
    await admin.client.waitFor(
        '[...document.querySelectorAll("#presentation_mode option")].some((option) => option.textContent.trim() === "Assisted automatic")',
    );
    const modeHelpIsAccurate = await admin.client.evaluate(
        'document.querySelector("#presentation_mode-hint")?.innerText.includes("operator")',
    );

    await technical.client.navigate(`${applicationUrl}/dashboard`);
    await technical.client.waitFor(
        'document.body.innerText.includes("Open technical operations")',
    );
    await technical.client.navigate(`${applicationUrl}/admin/scan-logs`);
    await technical.client.waitFor(
        'document.body.innerText.includes("Scan logs")',
    );
    const technicalNavigationSafe = await technical.client.evaluate(`(() => ({
        brand: document.querySelector('.admin-brand')?.getAttribute('href'),
        readiness: [...document.querySelectorAll('.admin-nav-link')]
            .some((link) => link.textContent.trim() === 'Readiness'),
        backups: document.body.innerText.includes('Backups'),
    }))()`);

    if (
        technicalNavigationSafe.brand !== '/admin/scan-logs' ||
        technicalNavigationSafe.readiness ||
        !technicalNavigationSafe.backups
    ) {
        throw new Error(
            `Technical navigation is unsafe: ${JSON.stringify(technicalNavigationSafe)}`,
        );
    }

    await assertNoHorizontalOverflow(technical.client, 'technical operations');

    await Promise.all([
        scanner.client.setReducedMotion(),
        control.client.setReducedMotion(),
        stage.client.setReducedMotion(),
        admin.client.setReducedMotion(),
        technical.client.setReducedMotion(),
    ]);
    const reducedMotion = await Promise.all([
        scanner.client.evaluate('matchMedia("(prefers-reduced-motion: reduce)").matches'),
        control.client.evaluate('matchMedia("(prefers-reduced-motion: reduce)").matches'),
        stage.client.evaluate('matchMedia("(prefers-reduced-motion: reduce)").matches'),
        admin.client.evaluate('matchMedia("(prefers-reduced-motion: reduce)").matches'),
        technical.client.evaluate('matchMedia("(prefers-reduced-motion: reduce)").matches'),
    ]);

    await scanner.client.navigate(`${applicationUrl}/scanner`);
    await scanner.client.waitFor('document.activeElement?.id === "scan-token"');
    await scanner.client.evaluate('document.querySelector("#scan-token").focus()');
    await scanner.client.send('Input.dispatchKeyEvent', {
        code: 'Tab',
        key: 'Tab',
        type: 'keyDown',
        windowsVirtualKeyCode: 9,
    });
    await scanner.client.send('Input.dispatchKeyEvent', {
        code: 'Tab',
        key: 'Tab',
        type: 'keyUp',
        windowsVirtualKeyCode: 9,
    });
    const scannerTabEscaped = await scanner.client.evaluate(
        'document.activeElement?.id !== "scan-token"',
    );

    await assertNoHorizontalOverflow(scanner.client, 'scanner');
    await assertNoHorizontalOverflow(control.client, 'stage control recovered');
    await assertStageSafe(stage.client);

    console.log(JSON.stringify({
        checks: {
            automaticInitialPresentationWorked: true,
            automaticPausePreservedQueue,
            combinedCompletionAdvancedOnce: true,
            controlledQueueOrder: initialQueue,
            holdingPreservedCurrent: true,
            keyboardFocusNotTrapped: scannerTabEscaped,
            localAudioPlayedAndReplayed: true,
            missingAudioSafe: replayDisabled,
            noAudienceTechnicalContent: true,
            noHorizontalOverflow: true,
            reducedMotionRecognized: reducedMotion.every(Boolean),
            refreshRecoveredQueueAndStage: true,
            reverbReconnectRecoveredQueue: true,
            scannerRoleDeniedControl: scannerDenied === 403,
            scanDuringPresentationDidNotPreempt: stillCurrent,
            skipAndReturnWorked: true,
            technicalNavigationSafe: true,
            userAdministrationResponsive: true,
            wrongCurrentReturnedWithoutCompletion: true,
            modeHelpIsAccurate,
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

function approveSeedAudioFixtures() {
    const code = [
        '$admin = App\\Models\\User::query()->where("email", "admin@example.test")->sole();',
        'App\\Models\\Student::query()->whereNotNull("audio_path")->where("audio_path", "like", "media/phase3/audio/%")->update([',
        '"audio_readiness_status" => "approved",',
        '"audio_approved_at" => now(),',
        '"audio_approved_by" => $admin->id,',
        '"audio_review_notes" => "Approved only for automated browser playback regression.",',
        '"audio_mime_type" => "audio/wav",',
        ']);',
    ].join(' ');

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

function prepareAutomaticModeFixtures() {
    const code = [
        '$ceremony = App\\Models\\Ceremony::query()->where("status", "active")->sole();',
        'App\\Models\\StageQueueItem::query()->where("ceremony_id", $ceremony->id)->whereIn("status", ["waiting", "ready"])->update(["status" => "skipped", "queue_position" => null]);',
        '$state = App\\Models\\StageState::query()->where("ceremony_id", $ceremony->id)->sole();',
        '$state->update(["current_queue_item_id" => null, "display_mode" => "idle", "automatic_paused" => false, "version" => $state->version + 1]);',
        '$ceremony->update(["presentation_mode" => "automatic"]);',
        '$students = [',
        '["student_number" => "PHASE7-AUTO-001", "qr_token" => "GSS-PHASE7-AUTOMATIC", "first_name" => "Automatic Mode", "last_name" => "Graduate", "sequence_number" => 101],',
        '["student_number" => "PHASE7-AUTO-002", "qr_token" => "GSS-PHASE7-AUTO-PAUSED", "first_name" => "Paused Automatic", "last_name" => "Graduate", "sequence_number" => 102],',
        '];',
        'foreach ($students as $student) {',
        'App\\Models\\Student::query()->updateOrCreate(',
        '["ceremony_id" => $ceremony->id, "student_number" => $student["student_number"]],',
        '[...$student, "display_name" => $student["first_name"]." ".$student["last_name"], "announcement_name" => $student["first_name"]." ".$student["last_name"], "college" => "Sample College", "program" => "Bachelor of Science in Computer Science", "eligibility_status" => "eligible"]',
        ');',
        '}',
    ].join(' ');

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

    if (result.status !== 200 || !result.url.endsWith('/dashboard')) {
        throw new Error(`Login failed for ${email}: ${JSON.stringify(result)}`);
    }
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

    await pause(50);
    await client.waitFor('!document.body.innerText.includes("Command in progress")');
}

async function selectQueueRow(client, name) {
    const selected = await client.evaluate(`(() => {
        const row = [...document.querySelectorAll('.stage-control-table-wrap tbody tr')]
            .find((candidate) => candidate.innerText.includes(${JSON.stringify(name)}));
        const input = row?.querySelector('input[type="radio"]');
        if (!input) return false;
        input.click();
        return true;
    })()`);

    if (!selected) {
        throw new Error(`Queue row unavailable: ${name}`);
    }
}

async function clickRowAction(client, name, action) {
    await client.waitFor(`(() => {
        const row = [...document.querySelectorAll('.stage-control-table-wrap tbody tr')]
            .find((candidate) => candidate.innerText.includes(${JSON.stringify(name)}));
        const button = [...(row?.querySelectorAll('button') ?? [])]
            .find((candidate) => candidate.textContent.trim() === ${JSON.stringify(action)});
        return Boolean(button && !button.disabled);
    })()`);
    const clicked = await client.evaluate(`(() => {
        const row = [...document.querySelectorAll('.stage-control-table-wrap tbody tr')]
            .find((candidate) => candidate.innerText.includes(${JSON.stringify(name)}));
        const button = [...(row?.querySelectorAll('button') ?? [])]
            .find((candidate) => candidate.textContent.trim() === ${JSON.stringify(action)});
        if (!button || button.disabled) return false;
        button.click();
        return true;
    })()`);

    if (!clicked) {
        throw new Error(`${action} unavailable for ${name}`);
    }

    await pause(50);
    await client.waitFor('!document.body.innerText.includes("Command in progress")');
}

async function clickSupportAction(client, name, action) {
    await client.waitFor(`(() => {
        const row = [...document.querySelectorAll('.stage-control-support li')]
            .find((candidate) => candidate.innerText.includes(${JSON.stringify(name)}));
        const button = [...(row?.querySelectorAll('button') ?? [])]
            .find((candidate) => candidate.textContent.trim() === ${JSON.stringify(action)});
        return Boolean(button && !button.disabled);
    })()`);
    const clicked = await client.evaluate(`(() => {
        const row = [...document.querySelectorAll('.stage-control-support li')]
            .find((candidate) => candidate.innerText.includes(${JSON.stringify(name)}));
        const button = [...(row?.querySelectorAll('button') ?? [])]
            .find((candidate) => candidate.textContent.trim() === ${JSON.stringify(action)});
        if (!button || button.disabled) return false;
        button.click();
        return true;
    })()`);

    if (!clicked) {
        throw new Error(`${action} unavailable for ${name}`);
    }

    await pause(50);
    await client.waitFor('!document.body.innerText.includes("Command in progress")');
}

async function queueSnapshot(client) {
    return client.evaluate(`[...document.querySelectorAll(
        '.stage-control-table-wrap tbody tr',
    )].map((row) => ({
        position: row.children[1]?.textContent.trim(),
        text: row.innerText,
    }))`);
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
    screenshots.push(`docs/evidence/phase-7/${filename}`);
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
                'queue position',
                'student number',
                'qr token',
                'stage control',
                'connection',
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
