import { execFileSync, spawn } from 'node:child_process';
import { mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import process from 'node:process';

const repositoryPath = process.cwd();
const applicationUrl = 'http://127.0.0.1:8000';
const chromePath = '/usr/bin/google-chrome';
const password = readEnvironmentValue('GSS_SEED_PASSWORD');

if (!password) {
    throw new Error('GSS_SEED_PASSWORD is missing from .env.');
}

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
            captureBeyondViewport: true,
            format: 'png',
        });

        writeFileSync(path, Buffer.from(result.data, 'base64'));
    }

    close() {
        this.socket.close();
    }
}

const profilesPath = mkdtempSync(join(tmpdir(), 'gss-phase3-browser-'));
const processes = [];
let reverbStopped = false;

try {
    const stage = await launchBrowser('stage', 9222, 1440, 900);
    const scanner = await launchBrowser('scanner', 9223, 1280, 900);

    await login(stage.client, 'stage@example.test');
    await login(scanner.client, 'scanner@example.test');

    await stage.client.navigate(`${applicationUrl}/stage`);
    await scanner.client.navigate(`${applicationUrl}/scanner`);

    await stage.client.waitFor('document.body.innerText.toLowerCase().includes("live")');
    await waitForPrivateStageSubscription(stage.client);
    await scanner.client.waitFor('document.body.innerText.toLowerCase().includes("ready")');
    await scanner.client.waitFor('document.body.innerText.toLowerCase().includes("live")');

    if (await stage.client.evaluate('Boolean(document.querySelector("#graduate-name"))')) {
        await completeCurrentPresentation(stage.client);
    }

    await stage.client.waitFor('document.body.innerText.toLowerCase().includes("welcome, graduates")');

    await stage.client.evaluate(`(() => {
        window.__phase3AudioEvents = [];
        const NativeAudio = window.Audio;
        window.Audio = function (...arguments_) {
            const audio = new NativeAudio(...arguments_);
            for (const name of ['play', 'playing', 'ended', 'error']) {
                audio.addEventListener(name, () => {
                    window.__phase3AudioEvents.push({
                        name,
                        source: audio.currentSrc || audio.src,
                    });
                });
            }
            window.__phase3LastAudio = audio;
            return audio;
        };
        window.Audio.prototype = NativeAudio.prototype;
        document.querySelector('.stage-audio-enable')?.click();
        return true;
    })()`);

    await submitScannerToken(scanner.client, 'GSS-PHASE3-A7K9M2Q4');
    await scanner.client.waitFor('document.body.innerText.toLowerCase().includes("accepted")');
    await stage.client.waitFor('document.body.innerText.toLowerCase().includes("amara santos")');
    await stage.client.waitFor(
        'window.__phase3AudioEvents.some((event) => event.name === "playing" || event.name === "ended")',
    );

    const firstAudio = await stage.client.evaluate(`({
        events: window.__phase3AudioEvents,
        source: window.__phase3LastAudio?.currentSrc ?? null,
        error: window.__phase3LastAudio?.error?.code ?? null,
    })`);

    await submitScannerToken(scanner.client, 'GSS-PHASE3-A7K9M2Q4');
    await scanner.client.waitFor('document.body.innerText.toLowerCase().includes("duplicate active")');

    await submitScannerToken(scanner.client, 'GSS-PHASE3-B8L3N5R7');
    await scanner.client.waitFor('document.body.innerText.toLowerCase().includes("stage busy")');

    await stage.client.navigate(`${applicationUrl}/stage`);
    await stage.client.waitFor('document.body.innerText.toLowerCase().includes("amara santos")');
    await stage.client.waitFor('document.body.innerText.toLowerCase().includes("live")');
    await waitForPrivateStageSubscription(stage.client);

    await completeCurrentPresentation(stage.client);
    await stage.client.waitFor('document.body.innerText.toLowerCase().includes("welcome, graduates")');

    await submitScannerToken(scanner.client, 'GSS-PHASE3-B8L3N5R7');
    await scanner.client.waitFor('document.body.innerText.toLowerCase().includes("accepted")');
    await stage.client.waitFor('document.body.innerText.toLowerCase().includes("benicio reyes")');

    await completeCurrentPresentation(stage.client);
    await stage.client.waitFor('document.body.innerText.toLowerCase().includes("welcome, graduates")');

    execFileSync('docker', ['compose', 'stop', 'reverb'], {
        cwd: repositoryPath,
        stdio: 'ignore',
    });
    reverbStopped = true;

    await stage.client.waitFor('document.body.innerText.toLowerCase().includes("syncing")', 20_000);
    await submitScannerToken(scanner.client, 'GSS-PHASE3-C4P6S8V2');
    await scanner.client.waitFor(
        'document.body.innerText.toLowerCase().includes("was saved. the stage will recover")',
        20_000,
    );
    await stage.client.waitFor('document.body.innerText.toLowerCase().includes("welcome, graduates")');

    execFileSync('docker', ['compose', 'up', '-d', '--wait', 'reverb'], {
        cwd: repositoryPath,
        stdio: 'ignore',
    });
    reverbStopped = false;

    await stage.client.waitFor('document.body.innerText.toLowerCase().includes("clarisse mendoza")', 30_000);
    await stage.client.waitFor('document.body.innerText.toLowerCase().includes("live")', 30_000);
    await waitForPrivateStageSubscription(stage.client);

    await stage.client.navigate(`${applicationUrl}/stage`);
    await stage.client.waitFor('document.body.innerText.toLowerCase().includes("clarisse mendoza")');
    await stage.client.waitFor('document.body.innerText.toLowerCase().includes("live")');
    await waitForPrivateStageSubscription(stage.client);

    await stage.client.screenshot('/tmp/gss-phase3-stage.png');
    await scanner.client.screenshot('/tmp/gss-phase3-scanner.png');

    await completeCurrentPresentation(stage.client);
    await stage.client.waitFor('document.body.innerText.toLowerCase().includes("welcome, graduates")');

    await stage.client.evaluate(`(() => {
        window.__phase3MissingAudioInstances = 0;
        const NativeAudio = window.Audio;
        window.Audio = function (...arguments_) {
            window.__phase3MissingAudioInstances += 1;
            return new NativeAudio(...arguments_);
        };
        window.Audio.prototype = NativeAudio.prototype;
        document.querySelector('.stage-audio-enable')?.click();
        return true;
    })()`);

    await submitScannerToken(scanner.client, 'GSS-PHASE3-E2H6J8X4');
    await stage.client.waitFor(
        'document.body.innerText.toLowerCase().includes("elena alexandria-cassandra villanueva")',
    );
    await pause(500);

    const missingAudioWasVisualOnly = await stage.client.evaluate(
        'window.__phase3MissingAudioInstances === 0',
    );

    if (!missingAudioWasVisualOnly) {
        throw new Error('The missing-audio presentation created an Audio object.');
    }

    await completeCurrentPresentation(stage.client);
    await stage.client.waitFor('document.body.innerText.toLowerCase().includes("welcome, graduates")');

    const stageText = await stage.client.evaluate('document.body.innerText');
    const scannerText = await scanner.client.evaluate('document.body.innerText');

    console.log(JSON.stringify({
        audio: firstAudio,
        checks: {
            authenticatedPrivateChannel: true,
            busyDidNotPreempt: true,
            duplicateActiveWasIdempotent: true,
            livePresentationDelivery: true,
            missingAudioWasVisualOnly,
            refreshRecoveredAuthoritativeState: true,
            reverbOutagePersistedScan: true,
            reconnectReconciledAuthoritativeState: true,
            stageReturnedToIdle: stageText.toLowerCase().includes('welcome, graduates'),
            scannerRemainedOperational: scannerText.toLowerCase().includes('scanner station'),
        },
        screenshots: [
            '/tmp/gss-phase3-stage.png',
            '/tmp/gss-phase3-scanner.png',
        ],
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
    await client.send('Emulation.setDeviceMetricsOverride', {
        deviceScaleFactor: 1,
        height,
        mobile: false,
        width,
    });

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
    await client.evaluate(`(() => {
        const input = document.querySelector('#scan-token');
        const setter = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value').set;
        setter.call(input, ${JSON.stringify(token)});
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
        document.querySelector('.scanner-form').requestSubmit();
        return true;
    })()`);

    await pause(200);
    await client.waitFor('!document.querySelector("#scan-token").readOnly');
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
