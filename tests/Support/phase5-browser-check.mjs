import { spawn } from 'node:child_process';
import {
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
const evidencePath = join(repositoryPath, 'docs', 'evidence', 'phase-5');
const profilePath = mkdtempSync(join(tmpdir(), 'gss-phase5-browser-'));
const uploadPath = mkdtempSync(join(tmpdir(), 'gss-phase5-csv-'));
const hotPath = join(repositoryPath, 'public', 'hot');
const savedHotPath = join(repositoryPath, 'public', 'hot.phase5-browser-check');
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
            body: document.body.innerText.slice(0, 2_500),
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

    const validCsvPath = join(uploadPath, 'phase5-realistic-graduates.csv');
    const invalidCsvPath = join(uploadPath, 'phase5-blocked-graduates.csv');
    const runSuffix = Date.now().toString(36).toUpperCase();
    const sequenceStart = 1_000_000 + Number(String(Date.now()).slice(-6));
    const validStudentNumber = `PHASE5-CHRIS-${runSuffix}`;
    writeFileSync(
        validCsvPath,
        [
            'student_number,first_name,middle_name,last_name,program,honors,sequence_number,eligibility_status',
            `PHASE5-ANA-${runSuffix},Ana Marie,,dela Cruz,Bachelor of Science in Computer Science,Magna Cum Laude,${sequenceStart},eligible`,
            `${validStudentNumber},Christopher Emmanuel,,Villanueva-Santos,Bachelor of Science in Electronics Engineering,,${sequenceStart + 1},eligible`,
            `PHASE5-LOURDES-${runSuffix},Ma. Lourdes,P.,Reyes,Bachelor of Science in Business Administration,"Cum Laude, Leadership Award",${sequenceStart + 2},review`,
        ].join('\n'),
    );
    writeFileSync(
        invalidCsvPath,
        [
            'student_number,first_name,middle_name,last_name,program,sequence_number,eligibility_status',
            'PHASE5-DUPLICATE,Ana Marie,,dela Cruz,Bachelor of Science in Computer Science,901,eligible',
            'PHASE5-DUPLICATE,Christopher Emmanuel,,Villanueva-Santos,Bachelor of Science in Electronics Engineering,901,unknown',
        ].join('\n'),
    );

    browserProcess = spawn(
        chromePath,
        [
            '--disable-dev-shm-usage',
            '--disable-gpu',
            '--headless=new',
            '--no-first-run',
            '--no-sandbox',
            '--remote-debugging-port=9335',
            `--user-data-dir=${profilePath}`,
            'about:blank',
        ],
        { stdio: 'ignore' },
    );

    const client = await DevToolsClient.connect(9335);
    await login(client, 'encoder@example.test');
    await client.navigate(`${applicationUrl}/admin/students/import`);
    await client.waitFor(
        'document.querySelector("h1")?.textContent === "Import graduate records"',
    );
    await captureAt(client, 'import-upload-1440x900.png', 1440, 900);
    await assertAdministrationPage(client, 'upload at 1440');
    await assertVisibleFocus(client, '#ceremony_id');
    await captureAt(client, 'import-upload-1366x768.png', 1366, 768);
    await assertAdministrationPage(client, 'upload at 1366');
    await captureAt(client, 'import-upload-1024x768.png', 1024, 768);
    await assertAdministrationPage(client, 'upload at 1024');

    const templateResult = await client.evaluate(`(async () => {
        const response = await fetch('/admin/students/import/template');
        const bytes = new Uint8Array(await response.arrayBuffer());
        const text = new TextDecoder().decode(bytes);

        return {
            bom: Array.from(bytes.slice(0, 3)),
            hasEligibility: text.includes('eligibility_status'),
            hasQrToken: text.includes('qr_token'),
            status: response.status,
        };
    })()`);

    if (
        templateResult.status !== 200 ||
        templateResult.bom.join(',') !== '239,187,191' ||
        !templateResult.hasEligibility ||
        templateResult.hasQrToken
    ) {
        throw new Error(`Template verification failed: ${JSON.stringify(templateResult)}`);
    }

    await uploadCsv(client, validCsvPath);
    await client.waitFor(
        'document.querySelector("h1")?.textContent === "Map CSV columns"',
    );
    const mappingResult = await client.evaluate(`(() => {
        const selects = Array.from(document.querySelectorAll('tbody select'));
        return {
            mapped: selects.map((select) => select.value),
            sampleHasLongName: document.body.innerText.includes(
                'Christopher Emmanuel'
            ),
            selectCount: selects.length,
        };
    })()`);

    if (
        mappingResult.selectCount !== 8 ||
        !mappingResult.sampleHasLongName ||
        mappingResult.mapped.some((target) => !target)
    ) {
        throw new Error(`Automatic mapping failed: ${JSON.stringify(mappingResult)}`);
    }

    await captureAt(client, 'import-mapping-1366x768.png', 1366, 768);
    await assertAdministrationPage(client, 'mapping at 1366');
    await captureAt(client, 'import-mapping-1024x768.png', 1024, 768);
    await assertAdministrationPage(client, 'mapping at 1024');

    await submitForm(client, '.admin-import-mapping-form');
    await client.waitFor(
        'document.querySelector("h1")?.textContent === "Review imported rows"',
    );
    await captureAt(client, 'import-preview-warning-1440x900.png', 1440, 900);
    await assertAdministrationPage(client, 'preview at 1440');
    await captureAt(client, 'import-preview-warning-1366x768.png', 1366, 768);
    await assertAdministrationPage(client, 'preview at 1366');
    await captureAt(client, 'import-preview-warning-1024x768.png', 1024, 768);
    await assertAdministrationPage(client, 'preview at 1024');

    const previewResult = await client.evaluate(`(() => {
        const text = document.body.innerText;
        const lowerText = text.toLowerCase();
        const tableWrap = document.querySelector('.admin-import-preview-table');
        return {
            hasConfirmation: Boolean(document.querySelector('.admin-import-confirm')),
            hasLongName: text.includes('Christopher Emmanuel Villanueva-Santos'),
            hasLongProgram: text.includes('Bachelor of Science in Electronics Engineering'),
            hasWarnings: lowerText.includes('with warnings') &&
                lowerText.includes('warning:'),
            leaksQrToken: text.includes('GSS-'),
            tableContained: tableWrap?.scrollWidth >= tableWrap?.clientWidth,
        };
    })()`);

    if (
        !previewResult.hasConfirmation ||
        !previewResult.hasLongName ||
        !previewResult.hasLongProgram ||
        !previewResult.hasWarnings ||
        previewResult.leaksQrToken ||
        !previewResult.tableContained
    ) {
        throw new Error(`Preview verification failed: ${JSON.stringify(previewResult)}`);
    }

    await client.send('Emulation.setEmulatedMedia', {
        features: [{ name: 'prefers-reduced-motion', value: 'reduce' }],
    });
    const reducedMotion = await client.evaluate(
        'matchMedia("(prefers-reduced-motion: reduce)").matches',
    );

    if (!reducedMotion) {
        throw new Error('Reduced-motion emulation was not recognized.');
    }

    await client.evaluate(`(() => {
        document.querySelector('#confirmed').click();
        return document.querySelector('#confirmed').checked;
    })()`);
    await submitForm(client, '.admin-import-confirm');
    await client.waitFor(
        'document.querySelector("h1")?.textContent === "Import complete"',
    );
    await captureAt(client, 'import-summary-1366x768.png', 1366, 768);
    await assertAdministrationPage(client, 'summary at 1366');
    await captureAt(client, 'import-summary-1024x768.png', 1024, 768);
    await assertAdministrationPage(client, 'summary at 1024');

    const summaryResult = await client.evaluate(`(() => {
        const text = document.body.innerText;
        return {
            createdThree: text.includes('3 graduate records created'),
            futureBoundary: text.includes('Media preparation') &&
                text.includes('future-phase'),
            hasCeremony: text.includes('Sample Graduation Ceremony'),
            hasCompleteCounts: text.includes('UPDATED') &&
                text.includes('SKIPPED') &&
                text.includes('STARTED'),
            hasMode: text.includes('Create new graduates only'),
            leaksQrToken: text.includes('GSS-'),
        };
    })()`);

    if (
        !summaryResult.createdThree ||
        !summaryResult.futureBoundary ||
        !summaryResult.hasCeremony ||
        !summaryResult.hasCompleteCounts ||
        !summaryResult.hasMode ||
        summaryResult.leaksQrToken
    ) {
        throw new Error(`Summary verification failed: ${JSON.stringify(summaryResult)}`);
    }

    await client.navigate(
        `${applicationUrl}/admin/students?search=${encodeURIComponent(validStudentNumber)}`,
    );
    await client.waitFor(
        'document.body.innerText.includes("Christopher Emmanuel Villanueva-Santos")',
    );

    await client.navigate(`${applicationUrl}/admin/students/import`);
    await uploadCsv(client, invalidCsvPath);
    await client.waitFor(
        'document.querySelector("h1")?.textContent === "Map CSV columns"',
    );
    await submitForm(client, '.admin-import-mapping-form');
    await client.waitFor(
        'document.querySelector(".admin-blocking-message")?.textContent.includes("Import is blocked")',
    );
    await captureAt(client, 'import-preview-blocked-1366x768.png', 1366, 768);
    await assertAdministrationPage(client, 'blocked preview at 1366');
    await captureAt(client, 'import-preview-blocked-1024x768.png', 1024, 768);
    await assertAdministrationPage(client, 'blocked preview at 1024');

    const blockedResult = await client.evaluate(`(() => ({
        hasConfirm: Boolean(document.querySelector('.admin-import-confirm')),
        hasDuplicate: document.body.innerText.includes('appears more than once'),
        hasEligibilityError: document.body.innerText.includes(
            'Eligibility must be pending, eligible, or ineligible.'
        ),
    }))()`);

    if (
        blockedResult.hasConfirm ||
        !blockedResult.hasDuplicate ||
        !blockedResult.hasEligibilityError
    ) {
        throw new Error(`Blocked preview failed: ${JSON.stringify(blockedResult)}`);
    }

    await logout(client);
    await login(client, 'admin@example.test');
    await client.navigate(
        `${applicationUrl}/admin/audit-logs?action=student_import.completed`,
    );
    await client.waitFor(
        'document.body.innerText.includes("student_import.completed")',
    );
    await captureAt(client, 'import-audit-1440x900.png', 1440, 900);
    await assertAdministrationPage(client, 'import audit');
    const auditResult = await client.evaluate(`(() => {
        const text = document.body.innerText;
        return {
            hasCompletedAudit: text.includes('student_import.completed'),
            leaksKnownToken: text.includes('GSS-'),
            leaksRawName: text.includes('Christopher Emmanuel Villanueva-Santos'),
        };
    })()`);

    if (
        !auditResult.hasCompletedAudit ||
        auditResult.leaksKnownToken ||
        auditResult.leaksRawName
    ) {
        throw new Error(`Import audit privacy failed: ${JSON.stringify(auditResult)}`);
    }

    await logout(client);
    await login(client, 'scanner@example.test');
    await client.navigate(`${applicationUrl}/admin/students/import`);
    await client.waitFor('document.body.innerText.includes("403")');
    await captureAt(client, 'import-scanner-denied-1024x768.png', 1024, 768);
    const denialResult = await client.evaluate(`({
        hasImportForm: Boolean(document.querySelector('.admin-import-form')),
        statusText: document.body.innerText.includes('403'),
    })`);

    if (denialResult.hasImportForm || !denialResult.statusText) {
        throw new Error(`Import authorization denial failed: ${JSON.stringify(denialResult)}`);
    }

    process.stdout.write(
        `${JSON.stringify(
            {
                auditResult,
                blockedResult,
                previewResult,
                reducedMotion,
                screenshots,
                status: 'passed',
                summaryResult,
                templateResult,
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

async function uploadCsv(client, path) {
    await client.setFile('#csv_file', path);
    const fileSelected = await client.evaluate(
        'document.querySelector("#csv_file").files.length === 1',
    );

    if (!fileSelected) {
        throw new Error(`CSV file was not selected: ${path}`);
    }

    await submitForm(client, '.admin-import-form');
}

async function submitForm(client, selector) {
    await client.evaluate(`(() => {
        const form = document.querySelector(${JSON.stringify(selector)});
        form.requestSubmit();
        return true;
    })()`);
}

async function captureAt(client, filename, width, height) {
    await client.setViewport(width, height);
    await client.evaluate(`(() => {
        document.activeElement?.blur();
        document.documentElement.scrollTop = 0;
        document.body.scrollTop = 0;
        scrollTo(0, 0);
        return true;
    })()`);
    await client.waitFor('scrollY === 0');
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
    screenshots.push(`docs/evidence/phase-5/${filename}`);
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
            bodyOverflow: document.documentElement.scrollWidth -
                document.documentElement.clientWidth,
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
        throw new Error(
            `${context} accessibility/layout check failed: ${JSON.stringify(result)}`,
        );
    }
}

async function assertVisibleFocus(client, selector) {
    await client.send('Emulation.setFocusEmulationEnabled', { enabled: true });
    const result = await client.evaluate(`(() => {
        const control = document.querySelector(${JSON.stringify(selector)});
        control.focus();
        const style = getComputedStyle(control);

        return {
            active: document.activeElement === control,
            boxShadow: style.boxShadow,
            outlineStyle: style.outlineStyle,
            outlineWidth: style.outlineWidth,
        };
    })()`);

    if (
        !result.active ||
        (result.outlineStyle === 'none' && result.boxShadow === 'none') ||
        (result.outlineWidth === '0px' && result.boxShadow === 'none')
    ) {
        throw new Error(`Import focus is not visible: ${JSON.stringify(result)}`);
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
