import { execFileSync, spawn, spawnSync } from 'node:child_process';
import { mkdtempSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import process from 'node:process';

const repositoryPath = process.cwd();
const applicationUrl = process.env.GSS_APP_URL;
const password = process.env.GSS_SEED_PASSWORD;
const composeProject = process.env.COMPOSE_PROJECT_NAME ?? 'graduation-stage-system';
const chromePath = '/usr/bin/google-chrome';
const profilesPath = mkdtempSync(join(tmpdir(), 'gss-phase10-browser-'));
const processes = [];

if (!applicationUrl) {
    throw new Error('GSS_APP_URL is required.');
}

if (!password) {
    throw new Error('GSS_SEED_PASSWORD is required.');
}

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

    async waitFor(expression, timeout = 20_000) {
        const deadline = Date.now() + timeout;

        while (Date.now() < deadline) {
            if (await this.evaluate(expression)) return;
            await pause(100);
        }

        const diagnostic = await this.evaluate(`({
            body: document.body.innerText.slice(0, 1800),
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
    const scanner = await launchBrowser('scanner', 9271);
    const control = await launchBrowser('control', 9272);
    const stage = await launchBrowser('stage', 9273);

    await Promise.all([
        login(scanner.client, 'scanner@example.test'),
        login(control.client, 'stage@example.test'),
        login(stage.client, 'stage@example.test'),
    ]);

    await scanner.client.navigate(`${applicationUrl}/scanner`);
    await scanner.client.waitFor('document.body.innerText.includes("Scanner")');
    await control.client.navigate(`${applicationUrl}/stage-control`);
    await control.client.waitFor('document.body.innerText.includes("Stage Control") || document.body.innerText.includes("Stage control")');
    await stage.client.navigate(`${applicationUrl}/stage`);
    await stage.client.waitFor('document.body.innerText.length > 0');

    const assetCheck = await stage.client.evaluate(`(() => {
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
            hasManifestAssets: entries.some((url) => url.includes('/build/assets/')),
            hasViteHot: entries.some((url) => url.includes('/@vite') || url.includes('vite/client')),
            remote,
        };
    })()`);

    if (!assetCheck.hasManifestAssets || assetCheck.hasViteHot || assetCheck.remote.length > 0) {
        throw new Error(`Deployment asset check failed: ${JSON.stringify(assetCheck)}`);
    }

    await scanner.client.send('Page.reload');
    await scanner.client.waitFor('document.body.innerText.includes("Scanner")');
    await stage.client.send('Page.reload');
    await stage.client.waitFor('document.body.innerText.length > 0');

    restartService('reverb');
    await waitForPort(applicationUrl, process.env.GSS_REVERB_PORT ?? '8091');
    await stage.client.send('Page.reload');
    await stage.client.waitFor('document.body.innerText.length > 0');

    restartService('app');
    await waitForHttp(`${applicationUrl}/up`);
    await scanner.client.send('Page.reload');
    await scanner.client.waitFor('document.body.innerText.includes("Scanner")');

    console.log(JSON.stringify({
        appUrl: applicationUrl,
        appRestartRecovered: true,
        browserRefreshRecovered: true,
        localAssetsOnly: true,
        reverbRestartRecovered: true,
        roleSeparatedProfiles: true,
    }, null, 2));
} finally {
    for (const item of processes) {
        item.process.kill('SIGTERM');
    }

    rmSync(profilesPath, { force: true, recursive: true });
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

async function launchBrowser(name, port) {
    const userDataDir = join(profilesPath, name);
    const browserProcess = spawn(chromePath, [
        '--headless=new',
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

    return {
        client: await DevToolsClient.connect(port),
        process: browserProcess,
    };
}

function restartService(service) {
    execFileSync('docker', [
        'compose',
        '--project-name',
        composeProject,
        '--env-file',
        '.env.phase10.verify',
        '-f',
        'compose.yaml',
        '-f',
        'compose.deploy.yaml',
        'restart',
        service,
    ], {
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

async function waitForPort(url, port) {
    const host = new URL(url).hostname;
    const deadline = Date.now() + 30_000;

    while (Date.now() < deadline) {
        const result = spawnSync('php', [
            '-r',
            `exit(@fsockopen('${host}', ${Number(port)}) ? 0 : 1);`,
        ], { stdio: 'ignore' });

        if (result.status === 0) return;

        await pause(500);
    }

    throw new Error(`Timed out waiting for ${host}:${port}`);
}

function pause(ms) {
    return new Promise((resolve) => setTimeout(resolve, ms));
}
