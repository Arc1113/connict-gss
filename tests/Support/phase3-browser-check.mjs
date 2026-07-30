import { execFileSync, spawn } from 'node:child_process';
import { mkdirSync, mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import process from 'node:process';

const repositoryPath = process.cwd();
const applicationUrl =
    process.env.GSS_APP_URL ?? 'http://127.0.0.1:8000';
const chromePath = '/usr/bin/google-chrome';
const password = readEnvironmentValue('GSS_SEED_PASSWORD');
const evidencePath = join(repositoryPath, 'docs', 'evidence', 'phase-3a');
const screenshots = [];

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

            if (message.error) {
                reject(new Error(message.error.message));
            } else {
                resolve(message.result);
            }
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

        throw new Error(`Chrome DevTools did not become available on port ${port}.`);
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
            expression,
            awaitPromise: true,
            returnByValue: true,
        });

        if (result.exceptionDetails) {
            throw new Error(result.exceptionDetails.exception?.description ?? 'Browser evaluation failed.');
        }

        return result.result.value;
    }

    async navigate(url) {
        await this.send('Page.navigate', { url });
        await this.waitFor('document.readyState === "complete"');
    }

    async pressKey(key, code, keyCode) {
        await this.send('Input.dispatchKeyEvent', {
            code,
            key,
            nativeVirtualKeyCode: keyCode,
            type: 'rawKeyDown',
            windowsVirtualKeyCode: keyCode,
        });

        if (key === 'Enter') {
            await this.send('Input.dispatchKeyEvent', {
                code,
                key,
                text: '\r',
                type: 'char',
                unmodifiedText: '\r',
            });
        }

        await this.send('Input.dispatchKeyEvent', {
            code,
            key,
            nativeVirtualKeyCode: keyCode,
            type: 'keyUp',
            windowsVirtualKeyCode: keyCode,
        });
    }

    async waitFor(expression, timeout = 15_000) {
        const deadline = Date.now() + timeout;

        while (Date.now() < deadline) {
            if (await this.evaluate(expression)) {
                return;
            }

            await pause(100);
        }

        const diagnostic = await this.evaluate(`({
            body: document.body.innerText.slice(0, 2_000),
            href: window.location.href,
            pusherState: window.Pusher?.instances?.[0]?.connection?.state ?? null,
        })`);

        throw new Error(
            `Timed out waiting for: ${expression}\n${JSON.stringify(diagnostic, null, 2)}`,
        );
    }

    async screenshot(path) {
        const result = await this.send('Page.captureScreenshot', {
            captureBeyondViewport: false,
            format: 'png',
            fromSurface: true,
        });

        writeFileSync(path, Buffer.from(result.data, 'base64'));
    }

    async setBlockedUrls(urls) {
        await this.send('Network.setBlockedURLs', { urls });
    }

    async setNetworkLatency(latency) {
        await this.send('Network.emulateNetworkConditions', {
            connectionType: 'wifi',
            downloadThroughput: -1,
            latency,
            offline: false,
            uploadThroughput: -1,
        });
    }

    async setReducedMotion(enabled) {
        await this.send('Emulation.setEmulatedMedia', {
            features: enabled
                ? [{ name: 'prefers-reduced-motion', value: 'reduce' }]
                : [],
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

    close() {
        this.socket.close();
    }
}

const profilesPath = mkdtempSync(join(tmpdir(), 'gss-phase3a-browser-'));
const processes = [];
let reverbStopped = false;
let ceremonyPaused = false;
let studentDisplayNameChanged = false;
let studentEligibilityChanged = false;

try {
    const stage = await launchBrowser('stage', 9222, 1920, 1080);
    const scanner = await launchBrowser('scanner', 9223, 1440, 900);

    await login(stage.client, 'stage@example.test');
    await login(scanner.client, 'scanner@example.test');

    await stage.client.setNetworkLatency(2_000);
    await stage.client.navigate(`${applicationUrl}/stage`);
    await stage.client.waitFor(
        'document.querySelector(".stage-page")?.dataset.stageStatus === "loading"',
    );
    await captureAt(stage.client, 'stage-loading-1920x1080.png', 1920, 1080);
    await stage.client.setNetworkLatency(0);

    await scanner.client.navigate(`${applicationUrl}/scanner`);

    await stage.client.waitFor(
        'document.querySelector(".stage-page")?.dataset.stageStatus === "idle"',
    );
    await waitForPrivateStageSubscription(stage.client);
    await scanner.client.waitFor(
        'document.querySelector(".scan-feedback")?.dataset.feedbackStatus === "ready"',
    );
    await scanner.client.waitFor(
        'document.body.innerText.toLowerCase().includes("live updates connected")',
    );
    await scanner.client.waitFor('document.activeElement?.id === "scan-token"');

    if (await stage.client.evaluate('Boolean(document.querySelector("#graduate-name"))')) {
        await completeCurrentPresentation(stage.client);
    }

    await stage.client.waitFor(
        'document.querySelector(".stage-page")?.dataset.stageStatus === "idle"',
    );
    await assertNoHorizontalOverflow(stage.client, 'initial stage');
    await assertNoAudienceTechnicalContent(stage.client);
    await captureAt(stage.client, 'stage-empty-1920x1080.png', 1920, 1080);
    await captureAt(stage.client, 'stage-empty-1366x768.png', 1366, 768);

    await captureAt(scanner.client, 'scanner-ready-1440x900.png', 1440, 900);
    await captureAt(scanner.client, 'scanner-ready-1366x768.png', 1366, 768);
    await captureAt(scanner.client, 'scanner-ready-1024x768.png', 1024, 768);
    await assertNoHorizontalOverflow(scanner.client, 'ready scanner');

    await stage.client.evaluate(`(() => {
        window.__phase3aAudioEvents = [];
        const NativeAudio = window.Audio;
        window.Audio = function (...arguments_) {
            const audio = new NativeAudio(...arguments_);
            for (const name of ['play', 'playing', 'ended', 'error']) {
                audio.addEventListener(name, () => {
                    window.__phase3aAudioEvents.push({
                        name,
                        source: audio.currentSrc || audio.src,
                    });
                });
            }
            window.__phase3aLastAudio = audio;
            return audio;
        };
        window.Audio.prototype = NativeAudio.prototype;
        document.body.dispatchEvent(new PointerEvent('pointerdown', { bubbles: true }));
        return true;
    })()`);

    await scanner.client.evaluate(`(() => {
        window.__phase3aScanRequests = 0;
        const nativeFetch = window.fetch.bind(window);
        window.fetch = (...arguments_) => {
            const requestUrl = String(arguments_[0]);
            if (requestUrl === '/scans' || requestUrl.endsWith('/scans')) {
                window.__phase3aScanRequests += 1;
            }
            return nativeFetch(...arguments_);
        };
        return true;
    })()`);

    await scanner.client.setNetworkLatency(900);
    await startScannerToken(scanner.client, 'NOT-A-REAL-QR', true);
    await scanner.client.waitFor(
        'document.querySelector(".scan-feedback")?.dataset.feedbackStatus === "processing"',
    );
    await repeatScannerSubmission(scanner.client);
    await captureAt(scanner.client, 'scanner-processing-1024x768.png', 1024, 768);
    await scanner.client.setNetworkLatency(0);
    await scanner.client.waitFor(
        'document.querySelector(".scan-feedback")?.dataset.feedbackStatus === "invalid_token"',
    );
    await scanner.client.waitFor('!document.querySelector("#scan-token").readOnly');

    const rapidRequestCount = await scanner.client.evaluate(
        'window.__phase3aScanRequests',
    );

    if (rapidRequestCount !== 1) {
        throw new Error(`Rapid double submission created ${rapidRequestCount} scan requests.`);
    }

    await captureAt(scanner.client, 'scanner-invalid-1366x768.png', 1366, 768);
    await scanner.client.waitFor('document.activeElement?.id === "scan-token"');

    await scanner.client.setBlockedUrls([`${applicationUrl}/scans`]);
    await submitScannerToken(scanner.client, 'NOT-A-REAL-QR');
    await scanner.client.waitFor(
        'document.querySelector(".scan-feedback")?.dataset.feedbackStatus === "request_failed"',
    );
    await scanner.client.setBlockedUrls([]);
    await captureAt(scanner.client, 'scanner-server-failure-1024x768.png', 1024, 768);

    await captureAt(stage.client, 'stage-before-long-name-1920x1080.png', 1920, 1080);
    await submitScannerToken(scanner.client, 'GSS-PHASE3-B8L3N5R7');
    await scanner.client.waitFor(
        'document.querySelector(".scan-feedback")?.dataset.feedbackStatus === "accepted"',
    );
    await stage.client.waitFor(
        'document.body.innerText.toLowerCase().includes("christopher emmanuel villanueva-santos")',
    );
    await stage.client.waitFor(
        'window.__phase3aAudioEvents.some((event) => event.name === "playing" || event.name === "ended")',
    );
    await assertStagePresentationFits(stage.client);
    await assertNoAudienceTechnicalContent(stage.client);

    const optionalContentState = await stage.client.evaluate(`({
        hasHonors: Boolean(document.querySelector('.stage-honors')),
        hasPhoto: Boolean(document.querySelector('.stage-photo')),
    })`);

    if (optionalContentState.hasHonors || optionalContentState.hasPhoto) {
        throw new Error(`Absent optional stage content was rendered: ${JSON.stringify(optionalContentState)}`);
    }

    await captureAt(scanner.client, 'scanner-success-long-name-1440x900.png', 1440, 900);
    await captureAt(stage.client, 'stage-long-name-1920x1080.png', 1920, 1080);
    await captureAt(stage.client, 'stage-long-name-1366x768.png', 1366, 768);

    const firstAudio = await stage.client.evaluate(`({
        events: window.__phase3aAudioEvents,
        source: window.__phase3aLastAudio?.currentSrc ?? null,
        error: window.__phase3aLastAudio?.error?.code ?? null,
    })`);

    await submitScannerToken(scanner.client, 'GSS-PHASE3-B8L3N5R7');
    await scanner.client.waitFor(
        'document.querySelector(".scan-feedback")?.dataset.feedbackStatus === "duplicate_active"',
    );
    await captureAt(scanner.client, 'scanner-duplicate-1366x768.png', 1366, 768);

    await submitScannerToken(scanner.client, 'GSS-PHASE3-A7K9M2Q4');
    await scanner.client.waitFor(
        'document.querySelector(".scan-feedback")?.dataset.feedbackStatus === "stage_busy"',
    );
    await captureAt(scanner.client, 'scanner-stage-busy-1366x768.png', 1366, 768);

    await stage.client.navigate(`${applicationUrl}/stage`);
    await stage.client.waitFor(
        'document.body.innerText.toLowerCase().includes("christopher emmanuel villanueva-santos")',
    );
    await waitForPrivateStageSubscription(stage.client);
    await assertStagePresentationFits(stage.client);
    await assertNoAudienceTechnicalContent(stage.client);
    await captureAt(stage.client, 'stage-http-recovery-1920x1080.png', 1920, 1080);

    await completeCurrentPresentation(stage.client);
    await stage.client.waitFor(
        'document.querySelector(".stage-page")?.dataset.stageStatus === "idle"',
    );

    await submitScannerToken(scanner.client, 'GSS-PHASE3-A7K9M2Q4');
    await scanner.client.waitFor(
        'document.querySelector(".scan-feedback")?.dataset.feedbackStatus === "accepted"',
    );
    await stage.client.waitFor(
        'document.body.innerText.toLowerCase().includes("ana marie dela cruz")',
    );

    await completeCurrentPresentation(stage.client);
    await stage.client.waitFor(
        'document.querySelector(".stage-page")?.dataset.stageStatus === "idle"',
    );

    runTinker(String.raw`App\Models\Student::where('student_number', 'DEMO-0004')->update(['eligibility_status' => 'ineligible']);`);
    studentEligibilityChanged = true;
    await submitScannerToken(scanner.client, 'GSS-PHASE3-D9T5W7Y3');
    await scanner.client.waitFor(
        'document.querySelector(".scan-feedback")?.dataset.feedbackStatus === "student_ineligible"',
    );
    await captureAt(scanner.client, 'scanner-ineligible-1366x768.png', 1366, 768);
    runTinker(String.raw`App\Models\Student::where('student_number', 'DEMO-0004')->update(['eligibility_status' => 'eligible']);`);
    studentEligibilityChanged = false;

    runTinker(String.raw`App\Models\Student::where('student_number', 'DEMO-0004')->update(['display_name' => '   ']);`);
    studentDisplayNameChanged = true;
    await submitScannerToken(scanner.client, 'GSS-PHASE3-D9T5W7Y3');
    await scanner.client.waitFor(
        'document.querySelector(".scan-feedback")?.dataset.feedbackStatus === "missing_student_information"',
    );
    await stage.client.waitFor(
        'document.querySelector("#graduate-name")?.textContent.trim() === "Graduate"',
    );
    await captureAt(scanner.client, 'scanner-missing-student-information-1366x768.png', 1366, 768);
    await captureAt(stage.client, 'stage-missing-display-name-1366x768.png', 1366, 768);
    await completeCurrentPresentation(stage.client);
    await stage.client.waitFor(
        'document.querySelector(".stage-page")?.dataset.stageStatus === "idle"',
    );
    runTinker(String.raw`App\Models\Student::where('student_number', 'DEMO-0004')->update(['display_name' => 'Dario Navarro']);`);
    studentDisplayNameChanged = false;

    runTinker(String.raw`App\Models\Ceremony::query()->update(['status' => 'paused']);`);
    ceremonyPaused = true;
    await startScannerToken(scanner.client, 'GSS-PHASE3-C4P6S8V2');
    await scanner.client.waitFor(
        'document.querySelector(".scan-feedback")?.dataset.feedbackStatus === "ceremony_inactive"',
    );
    await scanner.client.waitFor('document.querySelector("#scan-token").readOnly');
    await captureAt(scanner.client, 'scanner-ceremony-paused-1366x768.png', 1366, 768);
    runTinker(String.raw`App\Models\Ceremony::query()->update(['status' => 'active']);`);
    ceremonyPaused = false;
    await scanner.client.navigate(`${applicationUrl}/scanner`);
    await scanner.client.waitFor(
        'document.querySelector(".scan-feedback")?.dataset.feedbackStatus === "ready"',
    );
    await scanner.client.waitFor(
        'document.body.innerText.toLowerCase().includes("live updates connected")',
    );
    await scanner.client.waitFor('document.activeElement?.id === "scan-token"');

    execFileSync('docker', ['compose', 'stop', 'reverb'], {
        cwd: repositoryPath,
        stdio: 'ignore',
    });
    reverbStopped = true;

    await scanner.client.waitFor(
        'document.body.innerText.toLowerCase().includes("live updates disconnected")',
        20_000,
    );
    await captureAt(scanner.client, 'scanner-reverb-disconnected-1440x900.png', 1440, 900);
    await assertNoAudienceTechnicalContent(stage.client);

    await submitScannerToken(scanner.client, 'GSS-PHASE3-C4P6S8V2');
    await scanner.client.waitFor(
        'document.querySelector(".scan-feedback")?.dataset.feedbackStatus === "accepted"',
        20_000,
    );
    await scanner.client.waitFor(
        'document.body.innerText.toLowerCase().includes("was saved")',
    );
    await captureAt(scanner.client, 'scanner-saved-during-outage-1440x900.png', 1440, 900);
    await stage.client.waitFor(
        'document.querySelector(".stage-page")?.dataset.stageStatus === "idle"',
    );

    execFileSync('docker', ['compose', 'up', '-d', '--wait', 'reverb'], {
        cwd: repositoryPath,
        stdio: 'ignore',
    });
    reverbStopped = false;

    await stage.client.waitFor(
        'document.body.innerText.toLowerCase().includes("ma. lourdes p. reyes")',
        30_000,
    );
    await scanner.client.waitFor(
        'document.body.innerText.toLowerCase().includes("live updates reconnected")',
        30_000,
    );
    await waitForPrivateStageSubscription(stage.client);
    await assertStagePresentationFits(stage.client);
    await assertNoAudienceTechnicalContent(stage.client);
    await captureAt(scanner.client, 'scanner-reverb-reconnected-1366x768.png', 1366, 768);
    await captureAt(stage.client, 'stage-recovered-1920x1080.png', 1920, 1080);
    await captureAt(stage.client, 'stage-recovered-1366x768.png', 1366, 768);

    await completeCurrentPresentation(stage.client);
    await stage.client.waitFor(
        'document.querySelector(".stage-page")?.dataset.stageStatus === "idle"',
    );
    await captureAt(stage.client, 'stage-cleared-1920x1080.png', 1920, 1080);

    await stage.client.evaluate(`(() => {
        window.__phase3aMissingAudioInstances = 0;
        const NativeAudio = window.Audio;
        window.Audio = function (...arguments_) {
            window.__phase3aMissingAudioInstances += 1;
            return new NativeAudio(...arguments_);
        };
        window.Audio.prototype = NativeAudio.prototype;
        document.body.dispatchEvent(new PointerEvent('pointerdown', { bubbles: true }));
        return true;
    })()`);

    await submitScannerToken(scanner.client, 'GSS-PHASE3-E2H6J8X4');
    await stage.client.waitFor(
        'document.body.innerText.toLowerCase().includes("elena alexandria-cassandra villanueva")',
    );
    await pause(500);

    const missingAudioWasVisualOnly = await stage.client.evaluate(
        'window.__phase3aMissingAudioInstances === 0',
    );

    if (!missingAudioWasVisualOnly) {
        throw new Error('The missing-audio presentation created an Audio object.');
    }

    await assertStagePresentationFits(stage.client);
    await captureAt(stage.client, 'stage-missing-audio-1366x768.png', 1366, 768);
    await completeCurrentPresentation(stage.client);
    await stage.client.waitFor(
        'document.querySelector(".stage-page")?.dataset.stageStatus === "idle"',
    );

    await stage.client.setReducedMotion(true);
    await scanner.client.setReducedMotion(true);
    const reducedMotion = await Promise.all([
        stage.client.evaluate('matchMedia("(prefers-reduced-motion: reduce)").matches'),
        scanner.client.evaluate('matchMedia("(prefers-reduced-motion: reduce)").matches'),
    ]);

    if (!reducedMotion.every(Boolean)) {
        throw new Error(`Reduced-motion emulation failed: ${JSON.stringify(reducedMotion)}`);
    }

    const stageText = await stage.client.evaluate('document.body.innerText');
    const scannerText = await scanner.client.evaluate('document.body.innerText');

    console.log(JSON.stringify({
        audio: firstAudio,
        checks: {
            authenticatedPrivateChannel: true,
            audienceTechnicalContentHidden: true,
            busyDidNotPreempt: true,
            duplicateActiveWasIdempotent: true,
            focusRestoredAfterProcessing: await scanner.client.evaluate(
                'document.activeElement?.id === "scan-token"',
            ),
            ineligibleStateWasActionable: true,
            keyboardEnterSubmittedScan: true,
            livePresentationDelivery: true,
            longContentFitViewport: true,
            missingStudentInformationWasSafe: true,
            missingAudioWasVisualOnly,
            pausedCeremonyStateWasActionable: true,
            rapidDoubleSubmissionRequestCount: rapidRequestCount,
            reducedMotionRecognized: reducedMotion.every(Boolean),
            refreshRecoveredAuthoritativeState: true,
            reverbOutagePersistedScan: true,
            reconnectReconciledAuthoritativeState: true,
            stageReturnedToIdle: stageText.toLowerCase().includes('welcome, graduates'),
            scannerRemainedOperational: scannerText.toLowerCase().includes('scanner readiness'),
        },
        screenshots,
    }, null, 2));
} finally {
    if (ceremonyPaused) {
        runTinker(String.raw`App\Models\Ceremony::query()->update(['status' => 'active']);`);
    }

    if (studentDisplayNameChanged) {
        runTinker(String.raw`App\Models\Student::where('student_number', 'DEMO-0004')->update(['display_name' => 'Dario Navarro']);`);
    }

    if (studentEligibilityChanged) {
        runTinker(String.raw`App\Models\Student::where('student_number', 'DEMO-0004')->update(['eligibility_status' => 'eligible']);`);
    }

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
    const profilePath = join(profilesPath, name);
    const browserProcess = spawn(chromePath, [
        '--autoplay-policy=no-user-gesture-required',
        '--disable-dev-shm-usage',
        '--disable-gpu',
        '--headless=new',
        '--mute-audio',
        '--no-first-run',
        '--no-sandbox',
        `--remote-debugging-port=${port}`,
        `--user-data-dir=${profilePath}`,
        'about:blank',
    ], {
        stdio: 'ignore',
    });

    processes.push(browserProcess);

    const client = await DevToolsClient.connect(port);
    await client.setViewport(width, height);

    return { client, process: browserProcess };
}

async function login(client, email) {
    await client.navigate(`${applicationUrl}/login`);

    const response = await client.evaluate(`(async () => {
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
        const response = await fetch('/login', {
            body: new URLSearchParams({
                email: ${JSON.stringify(email)},
                password: ${JSON.stringify(password)},
            }),
            credentials: 'same-origin',
            headers: {
                'Accept': 'text/html',
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-CSRF-TOKEN': csrfToken,
            },
            method: 'POST',
        });

        return {
            status: response.status,
            url: response.url,
        };
    })()`);

    if (response.status !== 200 || !response.url.endsWith('/dashboard')) {
        throw new Error(`Login failed for ${email}: ${JSON.stringify(response)}`);
    }
}

async function submitScannerToken(client, token) {
    await startScannerToken(client, token);
    await pause(200);
    await client.waitFor('!document.querySelector("#scan-token").readOnly');
}

async function startScannerToken(client, token, submitWithEnter = false) {
    await client.evaluate(`(() => {
        const input = document.querySelector('#scan-token');
        const setter = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value').set;
        setter.call(input, ${JSON.stringify(token)});
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
        input.focus();
        return true;
    })()`);

    if (submitWithEnter) {
        await client.pressKey('Enter', 'Enter', 13);

        return;
    }

    await client.evaluate(`(() => {
        document.querySelector('.scanner-form').requestSubmit();
        return true;
    })()`);
}

async function repeatScannerSubmission(client) {
    await client.evaluate(`(() => {
        document.querySelector('.scanner-form').requestSubmit();
        return true;
    })()`);
}

async function completeCurrentPresentation(client) {
    const response = await client.evaluate(`(async () => {
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
        const response = await fetch('/stage/complete-current', {
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            method: 'POST',
        });

        return {
            body: await response.json(),
            status: response.status,
        };
    })()`);

    if (response.status !== 200 || response.body.status !== 'completed') {
        throw new Error(`Completion failed: ${JSON.stringify(response)}`);
    }
}

function readEnvironmentValue(key) {
    const content = readFileSync(join(repositoryPath, '.env'), 'utf8');

    for (const line of content.split(/\r?\n/)) {
        if (!line.startsWith(`${key}=`)) {
            continue;
        }

        const value = line.slice(key.length + 1).trim();

        return value.replace(/^(['"])(.*)\1$/, '$2');
    }

    return null;
}

function pause(milliseconds) {
    return new Promise((resolve) => setTimeout(resolve, milliseconds));
}

async function captureAt(client, filename, width, height) {
    await client.setViewport(width, height);
    await pause(150);

    const viewport = await client.evaluate(`({
        height: window.innerHeight,
        width: window.innerWidth,
    })`);

    if (viewport.width !== width || viewport.height !== height) {
        throw new Error(
            `Expected ${width}x${height} for ${filename}, received ${viewport.width}x${viewport.height}.`,
        );
    }

    const path = join(evidencePath, filename);
    await client.screenshot(path);
    screenshots.push(`docs/evidence/phase-3a/${filename}`);
}

async function assertNoAudienceTechnicalContent(client) {
    const result = await client.evaluate(`(() => {
        const text = document.body.innerText.toLowerCase();
        const forbiddenText = [
            'enable ceremony audio',
            'reverb',
            'syncing',
            'technical connection',
            'websocket',
        ];

        return {
            controls: document.querySelectorAll('button, nav, .stage-status, .stage-audio-enable').length,
            matches: forbiddenText.filter((value) => text.includes(value)),
        };
    })()`);

    if (result.controls !== 0 || result.matches.length > 0) {
        throw new Error(`Audience-facing technical content found: ${JSON.stringify(result)}`);
    }
}

async function assertNoHorizontalOverflow(client, context) {
    const overflow = await client.evaluate(`({
        clientWidth: document.documentElement.clientWidth,
        scrollWidth: document.documentElement.scrollWidth,
    })`);

    if (overflow.scrollWidth > overflow.clientWidth + 1) {
        throw new Error(`${context} has horizontal overflow: ${JSON.stringify(overflow)}`);
    }
}

async function assertStagePresentationFits(client) {
    const layout = await client.evaluate(`(() => {
        const content = document.querySelector('.stage-presentation');
        const name = document.querySelector('#graduate-name');
        const program = document.querySelector('.stage-program');
        const contentRect = content?.getBoundingClientRect();
        const nameRect = name?.getBoundingClientRect();
        const programRect = program?.getBoundingClientRect();

        return {
            contentBottom: contentRect?.bottom ?? null,
            contentLeft: contentRect?.left ?? null,
            contentRight: contentRect?.right ?? null,
            contentTop: contentRect?.top ?? null,
            nameHeight: nameRect?.height ?? null,
            nameScrollWidth: name?.scrollWidth ?? null,
            nameWidth: name?.clientWidth ?? null,
            programScrollWidth: program?.scrollWidth ?? null,
            programWidth: program?.clientWidth ?? null,
            viewportHeight: window.innerHeight,
            viewportWidth: window.innerWidth,
        };
    })()`);

    const fits =
        layout.contentTop >= 0 &&
        layout.contentBottom <= layout.viewportHeight &&
        layout.contentLeft >= 0 &&
        layout.contentRight <= layout.viewportWidth &&
        layout.nameHeight > 0 &&
        layout.nameScrollWidth <= layout.nameWidth + 1 &&
        layout.programScrollWidth <= layout.programWidth + 1;

    if (!fits) {
        throw new Error(`Stage presentation does not fit the viewport: ${JSON.stringify(layout)}`);
    }

    await assertNoHorizontalOverflow(client, 'stage presentation');
}

function runTinker(code) {
    execFileSync(
        'docker',
        ['compose', 'exec', '-T', 'app', 'php', 'artisan', 'tinker', `--execute=${code}`],
        {
            cwd: repositoryPath,
            stdio: 'ignore',
        },
    );
}

async function stopBrowser(browserProcess) {
    if (browserProcess.exitCode !== null) {
        return;
    }

    const exited = new Promise((resolve) => {
        browserProcess.once('exit', resolve);
    });

    browserProcess.kill('SIGTERM');
    await Promise.race([exited, pause(5_000)]);
}

async function waitForPrivateStageSubscription(client) {
    await client.waitFor(`Object.values(
        window.Pusher?.instances?.[0]?.channels?.channels ?? {},
    ).some((channel) => channel.subscribed)`);
}
