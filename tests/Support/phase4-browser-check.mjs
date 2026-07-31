import { spawn } from 'node:child_process';
import {
    copyFileSync,
    existsSync,
    mkdirSync,
    mkdtempSync,
    readFileSync,
    renameSync,
    rmSync,
    writeFileSync,
} from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import process from 'node:process';

const repositoryPath = process.cwd();
const applicationUrl =
    process.env.GSS_APP_URL ?? 'http://127.0.0.1:8000';
const chromePath = '/usr/bin/google-chrome';
const password = readEnvironmentValue('GSS_SEED_PASSWORD');
const evidencePath = join(repositoryPath, 'docs', 'evidence', 'phase-4');
const profilePath = mkdtempSync(join(tmpdir(), 'gss-phase4-browser-'));
const uploadPath = mkdtempSync(join(tmpdir(), 'gss-phase4-media-'));
const hotPath = join(repositoryPath, 'public', 'hot');
const savedHotPath = join(repositoryPath, 'public', 'hot.phase4-browser-check');
const screenshots = [];
let browserProcess;
let hotMarkerMoved = false;

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
                    await client.send('DOM.enable');

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

    async setFile(selector, path) {
        const document = await this.send('DOM.getDocument', { depth: 0 });
        const match = await this.send('DOM.querySelector', {
            nodeId: document.root.nodeId,
            selector,
        });

        if (!match.nodeId) {
            throw new Error(`File input not found: ${selector}`);
        }

        await this.send('DOM.setFileInputFiles', {
            files: [path],
            nodeId: match.nodeId,
        });
    }

    async setViewport(width, height) {
        await this.send('Emulation.setDeviceMetricsOverride', {
            deviceScaleFactor: 1,
            height,
            mobile: false,
            screenHeight: height,
            screenWidth: width,
            width,
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
        })`);

        throw new Error(
            `Timed out waiting for: ${expression}\n${JSON.stringify(diagnostic, null, 2)}`,
        );
    }
}

try {
    if (existsSync(hotPath)) {
        if (existsSync(savedHotPath)) {
            throw new Error(`Temporary Vite marker already exists: ${savedHotPath}`);
        }

        renameSync(hotPath, savedHotPath);
        hotMarkerMoved = true;
    }

    const health = await fetch(`${applicationUrl}/up`);

    if (!health.ok) {
        throw new Error(`Application health check failed with ${health.status}.`);
    }

    const photoPath = join(uploadPath, 'approved-phase4-photo.png');
    const audioPath = join(uploadPath, 'approved-phase4-audio.wav');
    writeFileSync(
        photoPath,
        Buffer.from(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            'base64',
        ),
    );
    copyFileSync(
        join(repositoryPath, 'public', 'media', 'phase3', 'audio', 'student-01.wav'),
        audioPath,
    );

    browserProcess = spawn(
        chromePath,
        [
            '--autoplay-policy=no-user-gesture-required',
            '--disable-dev-shm-usage',
            '--disable-gpu',
            '--headless=new',
            '--mute-audio',
            '--no-first-run',
            '--no-sandbox',
            '--remote-debugging-port=9334',
            `--user-data-dir=${profilePath}`,
            'about:blank',
        ],
        { stdio: 'ignore' },
    );

    const client = await DevToolsClient.connect(9334);
    await login(client, 'admin@example.test');

    await client.navigate(`${applicationUrl}/admin`);
    await client.waitFor(
        'document.querySelector("h1")?.textContent.includes("Ceremony preparation")',
    );
    await captureAt(client, 'admin-readiness-1440x900.png', 1440, 900);
    await assertAdministrationPage(client, 'administration readiness');

    await client.navigate(`${applicationUrl}/admin/ceremonies`);
    await client.waitFor('document.querySelector("h1")?.textContent === "Ceremonies"');
    await captureAt(client, 'ceremonies-list-1366x768.png', 1366, 768);
    await assertAdministrationPage(client, 'ceremony list');
    await captureAt(client, 'ceremonies-list-1024x768.png', 1024, 768);
    await assertAdministrationPage(client, 'ceremony list at 1024');

    const ceremonyEditUrl = await client.evaluate(`(() => {
        return document.querySelector('tbody a[href$="/edit"]')?.href ?? null;
    })()`);

    if (!ceremonyEditUrl) {
        throw new Error('Could not locate a ceremony edit link.');
    }

    await client.navigate(`${applicationUrl}/admin/ceremonies/create`);
    await client.waitFor(
        'document.querySelector("h1")?.textContent === "Add ceremony"',
    );
    await assertAdministrationPage(client, 'ceremony create form');

    await client.navigate(ceremonyEditUrl);
    await client.waitFor(
        'document.querySelector("h1")?.textContent.startsWith("Edit ")',
    );
    await assertAdministrationPage(client, 'ceremony edit form');

    await client.navigate(ceremonyEditUrl.replace(/\/edit$/, ''));
    await client.waitFor(
        'Boolean(document.querySelector(".admin-detail-list"))',
    );
    await assertAdministrationPage(client, 'ceremony detail');

    await client.navigate(`${applicationUrl}/admin/students`);
    await client.waitFor(
        'document.querySelector("h1")?.textContent === "Graduate records"',
    );
    await captureAt(client, 'graduates-list-1440x900.png', 1440, 900);
    await assertAdministrationPage(client, 'graduate list at 1440');
    await assertGraduateListPrivacyAndContent(client);
    await captureAt(client, 'graduates-list-1366x768.png', 1366, 768);
    await assertAdministrationPage(client, 'graduate list at 1366');
    await captureAt(client, 'graduates-list-1024x768.png', 1024, 768);
    await assertAdministrationPage(client, 'graduate list at 1024');

    await client.send('Emulation.setFocusEmulationEnabled', { enabled: true });
    const focusResult = await client.evaluate(`(() => {
        const input = document.querySelector('#search');
        input.focus();
        const style = getComputedStyle(input);
        return {
            active: document.activeElement === input,
            borderColor: style.borderColor,
            boxShadow: style.boxShadow,
            inAdmin: Boolean(input.closest('.admin-app')),
            matchesFocus: input.matches(':focus'),
            outlineStyle: style.outlineStyle,
            outlineWidth: style.outlineWidth,
            stylesheets: Array.from(document.styleSheets).map((sheet) => sheet.href),
        };
    })()`);

    if (
        !focusResult.active ||
        (focusResult.outlineStyle === 'none' &&
            focusResult.boxShadow === 'none') ||
        (focusResult.outlineWidth === '0px' && focusResult.boxShadow === 'none')
    ) {
        throw new Error(`Search focus is not visible: ${JSON.stringify(focusResult)}`);
    }

    await client.navigate(
        `${applicationUrl}/admin/students?search=NoSuchGraduateRecordForVisualQA`,
    );
    await client.waitFor(
        'document.body.innerText.includes("No matching graduates")',
    );
    await captureAt(client, 'graduates-no-results-1024x768.png', 1024, 768);
    await assertAdministrationPage(client, 'graduate no-results state');

    await client.navigate(`${applicationUrl}/admin/students/create`);
    await client.waitFor('document.querySelector("h1")?.textContent === "Add graduate"');
    await client.setViewport(1024, 768);
    await client.evaluate(`(() => {
        const form = document.querySelector('.admin-form');
        form.noValidate = true;
        form.requestSubmit();
        return true;
    })()`);
    await client.waitFor('Boolean(document.querySelector(".admin-validation-summary"))');
    await captureAt(client, 'graduate-validation-errors-1024x768.png', 1024, 768);
    await assertAdministrationPage(client, 'graduate validation state');

    await client.navigate(`${applicationUrl}/admin/students`);
    const editUrl = await client.evaluate(`(() => {
        const row = Array.from(document.querySelectorAll('tbody tr')).find((candidate) =>
            candidate.innerText.includes('Elena Alexandria-Cassandra Villanueva')
        );
        return row?.querySelector('a[href$="/edit"]')?.href ?? null;
    })()`);

    if (!editUrl) {
        throw new Error('Could not locate the realistic long-name graduate edit link.');
    }

    await client.navigate(editUrl);
    await client.waitFor('Boolean(document.querySelector("#photo"))');
    await client.setViewport(1366, 768);
    await client.setFile('#photo', photoPath);
    await client.setFile('#audio', audioPath);

    const selectedFiles = await client.evaluate(`({
        photo: document.querySelector('#photo').files.length,
        audio: document.querySelector('#audio').files.length,
    })`);

    if (selectedFiles.photo !== 1 || selectedFiles.audio !== 1) {
        throw new Error(`Media files were not selected: ${JSON.stringify(selectedFiles)}`);
    }

    await client.evaluate(`(() => {
        document.querySelector('.admin-form').requestSubmit();
        return true;
    })()`);
    await client.waitFor(
        'location.pathname.match(/^\\/admin\\/students\\/\\d+$/) && Boolean(document.querySelector(".admin-flash-success"))',
    );
    await captureAt(client, 'graduate-media-detail-1366x768.png', 1366, 768);
    await assertAdministrationPage(client, 'graduate media detail');

    const mediaResult = await client.evaluate(`(async () => {
        const image = document.querySelector('.admin-photo-preview');
        const audio = document.querySelector('audio');
        const imageResponse = image ? await fetch(image.src) : null;
        const audioResponse = audio ? await fetch(audio.src) : null;
        let playback = false;

        if (audio) {
            await audio.play();
            playback = !audio.paused || audio.ended;
            audio.pause();
            audio.currentTime = 0;
        }

        return {
            audioLocal: audio?.src.startsWith(location.origin + '/storage/graduates/audio/') ?? false,
            audioStatus: audioResponse?.status ?? null,
            imageLocal: image?.src.startsWith(location.origin + '/storage/graduates/photos/') ?? false,
            imageStatus: imageResponse?.status ?? null,
            playback,
        };
    })()`);

    if (
        !mediaResult.audioLocal ||
        !mediaResult.imageLocal ||
        mediaResult.audioStatus !== 200 ||
        mediaResult.imageStatus !== 200 ||
        !mediaResult.playback
    ) {
        throw new Error(`Local media verification failed: ${JSON.stringify(mediaResult)}`);
    }

    await client.navigate(`${applicationUrl}/admin/audit-logs`);
    await client.waitFor(
        'document.querySelector("h1")?.textContent.includes("audit history")',
    );
    await captureAt(client, 'audit-history-1440x900.png', 1440, 900);
    await assertAdministrationPage(client, 'administrative audit history');

    const auditPrivacy = await client.evaluate(`(() => {
        const text = document.body.innerText;
        return {
            hasMediaAction: text.includes('student.photo_') || text.includes('student.audio_'),
            leaksToken: text.includes('GSS-PHASE3-') || text.includes('PHASE4-MEDIA-TOKEN'),
            leaksPath: text.includes('graduates/photos/') || text.includes('graduates/audio/'),
        };
    })()`);

    if (!auditPrivacy.hasMediaAction || auditPrivacy.leaksToken || auditPrivacy.leaksPath) {
        throw new Error(`Audit privacy check failed: ${JSON.stringify(auditPrivacy)}`);
    }

    await logout(client);
    await login(client, 'encoder@example.test');
    await client.navigate(`${applicationUrl}/admin`);
    await client.waitFor('Boolean(document.querySelector(".admin-app"))');

    const encoderNavigation = await client.evaluate(`({
        auditLink: Boolean(document.querySelector('a[href="/admin/audit-logs"]')),
        ceremonyCreateLink: Boolean(document.querySelector('a[href="/admin/ceremonies/create"]')),
        graduateListLink: Boolean(document.querySelector('a[href="/admin/students"]')),
    })`);

    if (
        encoderNavigation.auditLink ||
        encoderNavigation.ceremonyCreateLink ||
        !encoderNavigation.graduateListLink
    ) {
        throw new Error(
            `Data encoder navigation boundary failed: ${JSON.stringify(encoderNavigation)}`,
        );
    }

    await client.navigate(`${applicationUrl}/admin/ceremonies/create`);
    await client.waitFor('document.body.innerText.includes("403")');
    await captureAt(client, 'encoder-ceremony-denied-1024x768.png', 1024, 768);

    const denial = await client.evaluate(`({
        hasForm: Boolean(document.querySelector('.admin-form')),
        statusText: document.body.innerText.includes('403'),
    })`);

    if (denial.hasForm || !denial.statusText) {
        throw new Error(`Data encoder denial failed: ${JSON.stringify(denial)}`);
    }

    process.stdout.write(
        `${JSON.stringify(
            {
                auditPrivacy,
                encoderNavigation,
                mediaResult,
                screenshots,
                status: 'passed',
                viewports: ['1440x900', '1366x768', '1024x768'],
            },
            null,
            2,
        )}\n`,
    );
} finally {
    if (browserProcess) {
        browserProcess.kill('SIGTERM');
        await pause(250);
    }

    rmSync(profilePath, {
        force: true,
        maxRetries: 5,
        recursive: true,
        retryDelay: 200,
    });
    rmSync(uploadPath, {
        force: true,
        maxRetries: 5,
        recursive: true,
        retryDelay: 200,
    });

    if (hotMarkerMoved && existsSync(savedHotPath)) {
        renameSync(savedHotPath, hotPath);
    }
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

        return { status: response.status, url: response.url };
    })()`);

    if (response.status !== 200 || response.url.endsWith('/login')) {
        throw new Error(`Login failed for ${email}: ${JSON.stringify(response)}`);
    }
}

async function logout(client) {
    const response = await client.evaluate(`(async () => {
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
        const response = await fetch('/logout', {
            credentials: 'same-origin',
            headers: {
                'Accept': 'text/html',
                'X-CSRF-TOKEN': csrfToken,
            },
            method: 'POST',
        });
        return { status: response.status, url: response.url };
    })()`);

    if (response.status !== 200 || !response.url.endsWith('/login')) {
        throw new Error(`Logout failed: ${JSON.stringify(response)}`);
    }
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
    screenshots.push(`docs/evidence/phase-4/${filename}`);
}

async function assertAdministrationPage(client, context) {
    const result = await client.evaluate(`(() => {
        const controls = Array.from(document.querySelectorAll('input, select, textarea'));
        const unlabeled = controls.filter((control) => {
            if (control.type === 'hidden') return false;
            return !control.labels || control.labels.length === 0;
        }).map((control) => control.id || control.name || control.type);
        const unnamedButtons = Array.from(document.querySelectorAll('button')).filter(
            (button) => !button.innerText.trim() && !button.getAttribute('aria-label')
        ).length;
        const externalResources = performance.getEntriesByType('resource')
            .map((entry) => entry.name)
            .filter((url) => {
                try {
                    return new URL(url).origin !== location.origin;
                } catch {
                    return false;
                }
            });

        return {
            bodyOverflow: document.documentElement.scrollWidth - document.documentElement.clientWidth,
            externalResources,
            h1Count: document.querySelectorAll('h1').length,
            unnamedButtons,
            unlabeled,
        };
    })()`);

    if (
        result.bodyOverflow > 1 ||
        result.externalResources.length > 0 ||
        result.h1Count !== 1 ||
        result.unnamedButtons > 0 ||
        result.unlabeled.length > 0
    ) {
        throw new Error(`${context} accessibility/layout check failed: ${JSON.stringify(result)}`);
    }
}

async function assertGraduateListPrivacyAndContent(client) {
    const result = await client.evaluate(`(() => {
        const text = document.body.innerText;
        const tableWrap = document.querySelector('.admin-table-wrap');
        return {
            containsLongName: text.includes('Christopher Emmanuel Villanueva-Santos'),
            containsLongProgram: text.includes('Bachelor of Science in Electronics Engineering'),
            leaksKnownToken: text.includes('GSS-PHASE3-'),
            tableContained: tableWrap ? tableWrap.scrollWidth >= tableWrap.clientWidth : false,
        };
    })()`);

    if (
        !result.containsLongName ||
        !result.containsLongProgram ||
        result.leaksKnownToken ||
        !result.tableContained
    ) {
        throw new Error(`Graduate content/privacy check failed: ${JSON.stringify(result)}`);
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
