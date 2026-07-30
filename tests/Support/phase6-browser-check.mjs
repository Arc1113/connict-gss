import { execFileSync, spawn } from 'node:child_process';
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
const evidencePath = join(repositoryPath, 'docs', 'evidence', 'phase-6');
const profilePath = mkdtempSync(join(tmpdir(), 'gss-phase6-browser-'));
const hotPath = join(repositoryPath, 'public', 'hot');
const savedHotPath = join(
    repositoryPath,
    'public',
    'hot.phase6-browser-check',
);
const screenshots = [];
const pdfs = [];
let browserProcess;
let client;
let testData;
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
                const response = await fetch(
                    `http://127.0.0.1:${port}/json/list`,
                );
                const targets = await response.json();
                const target = targets.find(
                    (candidate) => candidate.type === 'page',
                );

                if (target) {
                    const socket = new WebSocket(target.webSocketDebuggerUrl);
                    await new Promise((resolve, reject) => {
                        socket.addEventListener('open', resolve, { once: true });
                        socket.addEventListener('error', reject, { once: true });
                    });

                    const connected = new DevToolsClient(socket);
                    await connected.send('Page.enable');
                    await connected.send('Runtime.enable');
                    await connected.send('Network.enable');

                    return connected;
                }
            } catch {
                // Chrome may still be starting.
            }

            await pause(150);
        }

        throw new Error(
            `Chrome DevTools did not become available on port ${port}.`,
        );
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

    async printToPdf(path) {
        const result = await this.send('Page.printToPDF', {
            displayHeaderFooter: false,
            marginBottom: 0,
            marginLeft: 0,
            marginRight: 0,
            marginTop: 0,
            paperHeight: 11.6929,
            paperWidth: 8.2677,
            preferCSSPageSize: true,
            printBackground: true,
            scale: 1,
        });

        writeFileSync(path, Buffer.from(result.data, 'base64'));
    }

    async setPrintMedia(enabled) {
        await this.send('Emulation.setEmulatedMedia', {
            media: enabled ? 'print' : 'screen',
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
            `Timed out waiting for: ${expression}\n${JSON.stringify(
                diagnostic,
                null,
                2,
            )}`,
        );
    }
}

try {
    if (existsSync(hotPath)) {
        if (existsSync(savedHotPath)) {
            throw new Error(
                `Temporary Vite marker already exists: ${savedHotPath}`,
            );
        }

        renameSync(hotPath, savedHotPath);
        hotMarkerMoved = true;
    }

    const health = await fetch(`${applicationUrl}/up`);

    if (!health.ok) {
        throw new Error(`Application health check failed with ${health.status}.`);
    }

    testData = prepareTestData();
    browserProcess = spawn(
        chromePath,
        [
            '--disable-dev-shm-usage',
            '--disable-gpu',
            '--headless=new',
            '--no-first-run',
            '--no-sandbox',
            '--remote-debugging-port=9360',
            `--user-data-dir=${profilePath}`,
            'about:blank',
        ],
        { stdio: 'ignore' },
    );
    client = await DevToolsClient.connect(9360);
    await login('admin@example.test');

    const indexUrl = `${applicationUrl}/admin/students/qr-codes?ceremony_id=${testData.ceremonyId}&sequence_from=${testData.sequenceStart}&sequence_to=${testData.sequenceStart + 2}`;
    await client.navigate(indexUrl);
    await client.waitFor(
        'document.querySelector("h1")?.textContent === "QR preparation"',
    );
    await assertQrWorkspace();
    await capture('qr-workspace-1440x900.png', 1440, 900);
    await capture('qr-workspace-1366x768.png', 1366, 768);
    await capture('qr-workspace-1024x768.png', 1024, 768);

    const selection = await client.evaluate(`(() => {
        const available = Array.from(
            document.querySelectorAll(
                '.admin-qr-table input[type="checkbox"]:not(:disabled)'
            )
        );
        available.forEach((input) => input.click());

        return {
            available: available.length,
            selected: document.querySelectorAll(
                '.admin-qr-table input[type="checkbox"]:checked'
            ).length,
        };
    })()`);

    if (selection.available !== 3 || selection.selected !== 3) {
        throw new Error(
            `Expected three printable selections, received ${JSON.stringify(
                selection,
            )}.`,
        );
    }

    await capture('qr-workspace-selected-1366x768.png', 1366, 768);

    await client.navigate(
        `${applicationUrl}/admin/students/${testData.students.christopher.id}/qr-code`,
    );
    await client.waitFor(
        'document.querySelector("h1")?.textContent.includes("Christopher Emmanuel")',
    );
    const individual = await client.evaluate(`(() => {
        const body = document.body.innerText;
        const image = document.querySelector('.admin-qr-card-preview img');

        return {
            imageComplete: image?.complete,
            imageWidth: image?.naturalWidth,
            hasLongName: body.includes(
                'Christopher Emmanuel Villanueva-Santos'
            ),
            hasLongProgram: body.includes(
                'Bachelor of Science in Electronics Engineering'
            ),
            leaksToken: body.includes('GSS-'),
        };
    })()`);

    if (
        !individual.imageComplete ||
        individual.imageWidth !== 512 ||
        !individual.hasLongName ||
        !individual.hasLongProgram ||
        individual.leaksToken
    ) {
        throw new Error(
            `Individual QR review failed: ${JSON.stringify(individual)}.`,
        );
    }

    await capture('qr-individual-long-name-1440x900.png', 1440, 900);
    await capture('qr-individual-long-name-1024x768.png', 1024, 768);

    await client.navigate(
        `${applicationUrl}/admin/students/${testData.students.christopher.id}/qr-code/print`,
    );
    await client.waitFor(
        'document.querySelectorAll(".qr-card").length === 1',
    );
    await assertPrintPage(1);
    await capture('qr-print-individual-screen-1366x768.png', 1366, 768);
    await client.setPrintMedia(true);
    await capture('qr-print-individual-a4.png', 794, 1123);
    const individualPdf = join(
        evidencePath,
        'qr-print-individual-a4.pdf',
    );
    await client.printToPdf(individualPdf);
    pdfs.push(individualPdf);
    await client.setPrintMedia(false);

    await client.navigate(indexUrl);
    await client.waitFor(
        'document.querySelector("h1")?.textContent === "QR preparation"',
    );
    await client.evaluate(`(async () => {
        const csrf = document.querySelector(
            'meta[name="csrf-token"]'
        )?.content;
        const body = new URLSearchParams();
        body.set('_token', csrf);
        body.set('ceremony_id', '${testData.ceremonyId}');
        ${Object.values(testData.students)
            .map(
                (student) =>
                    `body.append('student_ids[]', '${student.id}');`,
            )
            .join('\n')}
        body.set('sequence_from', '${testData.sequenceStart}');
        body.set('sequence_to', '${testData.sequenceStart + 2}');
        const response = await fetch('/admin/students/qr-codes/batch', {
            body,
            method: 'POST',
        });
        const html = await response.text();
        document.open();
        document.write(html);
        document.close();
        return response.status;
    })()`);
    await client.waitFor(
        'document.querySelectorAll(".qr-card").length === 3',
    );
    await assertPrintPage(3);
    await capture('qr-print-batch-screen-1366x768.png', 1366, 768);
    await client.setPrintMedia(true);
    await capture('qr-print-batch-a4.png', 794, 1123);
    const batchPdf = join(evidencePath, 'qr-print-batch-a4.pdf');
    await client.printToPdf(batchPdf);
    pdfs.push(batchPdf);
    await client.setPrintMedia(false);

    await client.navigate(
        `${applicationUrl}/admin/students/${testData.students.lourdes.id}/qr-code`,
    );
    await client.waitFor(
        'document.querySelector("h1")?.textContent.includes("Lourdes")',
    );
    await client.waitFor(
        'document.querySelector(".admin-qr-card-preview img")?.naturalWidth === 512',
    );
    await client.evaluate(`(() => {
        window.confirm = () => true;
        const confirmation = document.querySelector(
            '.admin-qr-confirmation input'
        );
        confirmation.click();
        document.querySelector('.admin-qr-regeneration button').click();
        return confirmation.checked;
    })()`);
    await client.waitFor(
        'document.body.innerText.includes("Previously printed cards no longer work")',
    );
    const replacement = await client.evaluate(`(() => ({
        hasHistory: document.body.innerText.includes('Replacement history') &&
            !document.body.innerText.includes(
                'No token replacement has been recorded'
            ),
        needsReprint: document.body.innerText.includes(
            'Regenerated — reprint'
        ),
        rawTokenVisible: document.body.innerText.includes('GSS-'),
    }))()`);

    if (
        !replacement.hasHistory ||
        !replacement.needsReprint ||
        replacement.rawTokenVisible
    ) {
        throw new Error(
            `Replacement browser state failed: ${JSON.stringify(replacement)}.`,
        );
    }

    await capture('qr-regenerated-warning-1366x768.png', 1366, 768);
    const previousLourdesToken = testData.students.lourdes.token;
    testData.students.lourdes.token = runPhp(`
echo App\\Models\\Student::query()
    ->findOrFail(${testData.students.lourdes.id})
    ->qr_token;`);

    await logout();
    await login('scanner@example.test');
    await client.navigate(`${applicationUrl}/scanner`);
    await client.waitFor(
        'document.querySelector(".scan-feedback")?.dataset.feedbackStatus === "ready"',
    );
    await client.waitFor('document.activeElement?.id === "scan-token"');
    await capture('scanner-phase6-ready-1440x900.png', 1440, 900);
    await setInput('#scan-token', previousLourdesToken);
    await client.evaluate(`(() => {
        performance.clearResourceTimings();
        const form = document.querySelector('.scanner-form');
        form.requestSubmit();
        form.requestSubmit();
    })()`);
    await client.waitFor(
        'document.querySelector(".scan-feedback")?.dataset.feedbackStatus === "invalid_token"',
    );
    await client.waitFor('document.activeElement?.id === "scan-token"');
    const invalidatedCard = await client.evaluate(`(() => ({
        requests: performance.getEntriesByType('resource').filter((entry) => {
            const url = new URL(entry.name);
            return url.pathname === '/scans';
        }).length,
        tokenRetained: document.querySelector('#scan-token')?.value,
    }))()`);

    if (
        invalidatedCard.requests !== 1 ||
        invalidatedCard.tokenRetained !== ''
    ) {
        throw new Error(
            `Invalidated-card rapid submission failed: ${JSON.stringify(
                invalidatedCard,
            )}.`,
        );
    }

    await submitScannerToken(testData.students.lourdes.token);
    await client.waitFor(
        'document.querySelector(".scan-feedback")?.dataset.feedbackStatus === "queued"',
    );
    await client.waitFor('document.activeElement?.id === "scan-token"');
    const scanResult = await client.evaluate(`(() => ({
        feedback: document.querySelector('.scan-feedback')?.innerText,
        last: document.querySelector('.scanner-last-success dd')?.innerText,
        tokenRetained: document.querySelector('#scan-token')?.value,
    }))()`);

    if (
        !scanResult.feedback.includes('Queued and presented automatically') ||
        !scanResult.last.includes('Ma. Lourdes P. Reyes') ||
        scanResult.tokenRetained !== ''
    ) {
        throw new Error(
            `Generated-token scanner result failed: ${JSON.stringify({
                last: scanResult.last,
                tokenRetained: scanResult.tokenRetained,
            })}.`,
        );
    }

    await capture('scanner-generated-qr-success-1440x900.png', 1440, 900);
    await logout();
    await login('admin@example.test');
    const completed = await client.evaluate(`(async () => {
        const token = document.querySelector(
            'meta[name="csrf-token"]'
        )?.content;
        const response = await fetch('/stage/complete-current', {
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': token,
            },
            method: 'POST',
        });
        return {
            body: await response.json(),
            status: response.status,
        };
    })()`);

    if (
        completed.status !== 200 ||
        completed.body.status !== 'completed'
    ) {
        throw new Error(
            `Stage completion before controlled lookup failed: ${JSON.stringify(
                completed,
            )}.`,
        );
    }

    await logout();
    await login('scanner@example.test');
    await client.navigate(`${applicationUrl}/scanner`);
    await client.waitFor('document.activeElement?.id === "scan-token"');
    await client.evaluate(`(() => {
        document.querySelector('.scanner-fallback summary').click();
        return document.querySelector('.scanner-fallback').open;
    })()`);
    await setInput(
        '#graduate-lookup',
        testData.students.ana.studentNumber,
    );
    await client.evaluate(
        'document.querySelector(".scanner-fallback form").requestSubmit()',
    );
    await client.waitFor(
        'document.querySelectorAll(".scanner-lookup-results label").length >= 1',
    );
    const fallback = await client.evaluate(`(() => ({
        count: document.querySelectorAll(
            '.scanner-lookup-results label'
        ).length,
        hasIdentity: document.body.innerText.includes(
            'Ana Marie dela Cruz'
        ),
        rawTokenVisible: document.body.innerText.includes('GSS-'),
    }))()`);

    if (
        fallback.count < 1 ||
        !fallback.hasIdentity ||
        fallback.rawTokenVisible
    ) {
        throw new Error(
            `Controlled scanner lookup failed: ${JSON.stringify(fallback)}.`,
        );
    }

    await client.setViewport(1024, 768);
    await client.evaluate(`(() => {
        document.querySelector('.scanner-fallback').scrollIntoView({
            block: 'start',
        });
        return window.scrollY;
    })()`);
    await pause(120);
    await assertNoDocumentOverflow('scanner controlled lookup');
    const fallbackScreenshot = join(
        evidencePath,
        'scanner-controlled-lookup-1024x768.png',
    );
    await client.screenshot(fallbackScreenshot);
    screenshots.push(fallbackScreenshot);
    await client.evaluate(`(() => {
        document.querySelector(
            '.scanner-lookup-results input[type="radio"][value="${testData.students.ana.id}"]'
        ).click();
        document.querySelector('.scanner-manual-submit').click();
    })()`);
    await client.waitFor(
        'document.querySelector(".scan-feedback")?.dataset.feedbackStatus === "queued"',
    );
    await client.waitFor('document.activeElement?.id === "scan-token"');
    const manualResult = await client.evaluate(`(() => ({
        feedback: document.querySelector('.scan-feedback')?.innerText,
        last: document.querySelector('.scanner-last-success dd')?.innerText,
    }))()`);

    if (
        !manualResult.feedback.includes('Queued and presented automatically') ||
        !manualResult.last.includes('Ana Marie dela Cruz')
    ) {
        throw new Error(
            `Controlled scanner processing failed: ${JSON.stringify(
                manualResult,
            )}.`,
        );
    }

    await logout();
    await login('encoder@example.test');
    await client.navigate(
        `${applicationUrl}/admin/students/${testData.students.christopher.id}/qr-code`,
    );
    await client.waitFor(
        'document.querySelector("h1")?.textContent.includes("Christopher Emmanuel")',
    );
    await client.waitFor(
        'document.querySelector(".admin-qr-card-preview img")?.naturalWidth === 512',
    );
    const encoderAccess = await client.evaluate(`(() => ({
        canPrint: document.body.innerText.includes('Open print sheet'),
        hasReplacementControl: Boolean(
            document.querySelector('.admin-qr-regeneration')
        ),
        hasReplacementHistory: document.body.innerText.includes(
            'Replacement history'
        ),
        imageLoaded: document.querySelector(
            '.admin-qr-card-preview img'
        )?.naturalWidth === 512,
    }))()`);

    if (
        !encoderAccess.canPrint ||
        encoderAccess.hasReplacementControl ||
        encoderAccess.hasReplacementHistory ||
        !encoderAccess.imageLoaded
    ) {
        throw new Error(
            `Data encoder QR restrictions failed: ${JSON.stringify(
                encoderAccess,
            )}.`,
        );
    }

    await client.navigate(
        `${applicationUrl}/admin/students/${testData.importedStudent.id}/qr-code`,
    );
    await client.waitFor(
        'document.querySelector("h1")?.textContent.includes("Phase 6 Imported Graduate")',
    );
    await client.waitFor(
        'document.querySelector(".admin-qr-card-preview img")?.naturalWidth === 512',
    );
    const importedOutput = await client.evaluate(`(() => ({
        imageLoaded: document.querySelector(
            '.admin-qr-card-preview img'
        )?.naturalWidth === 512,
        rawTokenVisible: document.body.innerText.includes('GSS-'),
    }))()`);

    if (!importedOutput.imageLoaded || importedOutput.rawTokenVisible) {
        throw new Error(
            `Imported graduate QR output failed: ${JSON.stringify(
                importedOutput,
            )}.`,
        );
    }

    await client.setReducedMotion(true);
    const reducedMotion = await client.evaluate(
        'matchMedia("(prefers-reduced-motion: reduce)").matches',
    );

    if (!reducedMotion) {
        throw new Error('Reduced-motion emulation was not recognized.');
    }

    await assertLocalResources();

    process.stdout.write(
        `${JSON.stringify(
            {
                browserRoutes: [
                    '/admin/students/qr-codes',
                    '/admin/students/{student}/qr-code',
                    '/admin/students/{student}/qr-code/print',
                    '/admin/students/qr-codes/batch',
                    '/scanner',
                ],
                pdfs: pdfs.map((path) => path.replace(`${repositoryPath}/`, '')),
                reducedMotion,
                screenshots: screenshots.map((path) =>
                    path.replace(`${repositoryPath}/`, ''),
                ),
                viewports: [
                    '1440x900',
                    '1366x768',
                    '1024x768',
                    '794x1123 print media',
                ],
            },
            null,
            2,
        )}\n`,
    );
} finally {
    client?.socket.close();
    browserProcess?.kill('SIGTERM');

    if (testData) {
        cleanupTestData(testData);
    }

    if (hotMarkerMoved && existsSync(savedHotPath)) {
        renameSync(savedHotPath, hotPath);
    }

    rmSync(profilePath, { force: true, recursive: true });
}

async function login(email) {
    await client.navigate(`${applicationUrl}/login`);
    await setInput('#email', email);
    await setInput('#password', password);
    await client.evaluate('document.querySelector("form").requestSubmit()');
    await client.waitFor('window.location.pathname === "/dashboard"');
}

async function logout() {
    await client.evaluate(`(async () => {
        const token = document.querySelector(
            'meta[name="csrf-token"]'
        )?.content;
        const response = await fetch('/logout', {
            headers: {
                Accept: 'text/html',
                'X-CSRF-TOKEN': token,
            },
            method: 'POST',
        });
        return response.status;
    })()`);
}

async function setInput(selector, value) {
    await client.evaluate(`(() => {
        const input = document.querySelector(${JSON.stringify(selector)});
        const setter = Object.getOwnPropertyDescriptor(
            HTMLInputElement.prototype,
            'value'
        ).set;
        setter.call(input, ${JSON.stringify(value)});
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
        return input.value;
    })()`);
}

async function submitScannerToken(token) {
    await setInput('#scan-token', token);
    await client.evaluate(
        'document.querySelector(".scanner-form").requestSubmit()',
    );
}

async function capture(filename, width, height) {
    await client.setViewport(width, height);
    await pause(120);
    await assertNoDocumentOverflow(filename);
    const path = join(evidencePath, filename);
    await client.screenshot(path);
    screenshots.push(path);
}

async function assertNoDocumentOverflow(context) {
    const overflow = await client.evaluate(`(() => ({
        body: document.body.scrollWidth,
        document: document.documentElement.scrollWidth,
        viewport: document.documentElement.clientWidth,
    }))()`);

    if (
        overflow.body > overflow.viewport + 1 ||
        overflow.document > overflow.viewport + 1
    ) {
        throw new Error(
            `${context} has document overflow: ${JSON.stringify(overflow)}.`,
        );
    }
}

async function assertQrWorkspace() {
    const state = await client.evaluate(`(() => {
        const text = document.body.innerText;
        const table = document.querySelector('.admin-qr-table');

        return {
            allRealisticNames:
                text.includes('Ana Marie dela Cruz') &&
                text.includes(
                    'Christopher Emmanuel Villanueva-Santos'
                ) &&
                text.includes('Ma. Lourdes P. Reyes'),
            allPrograms:
                text.includes(
                    'Bachelor of Science in Computer Science'
                ) &&
                text.includes(
                    'Bachelor of Science in Business Administration'
                ) &&
                text.includes(
                    'Bachelor of Science in Electronics Engineering'
                ),
            fakeAnalytics:
                text.includes('Conversion rate') ||
                Boolean(document.querySelector('canvas')),
            rawTokenVisible: text.includes('GSS-'),
            tableContained: table?.closest('.admin-table-wrap')
                ?.scrollWidth >= table?.closest('.admin-table-wrap')
                ?.clientWidth,
        };
    })()`);

    if (
        !state.allRealisticNames ||
        !state.allPrograms ||
        state.fakeAnalytics ||
        state.rawTokenVisible ||
        !state.tableContained
    ) {
        throw new Error(`QR workspace failed: ${JSON.stringify(state)}.`);
    }

    await assertLocalResources();
}

async function assertPrintPage(expectedCards) {
    const state = await client.evaluate(`(() => ({
        cards: document.querySelectorAll('.qr-card').length,
        externalResources: performance.getEntriesByType('resource')
            .map((entry) => entry.name)
            .filter((url) => {
                try {
                    const parsed = new URL(url);
                    return ['http:', 'https:'].includes(parsed.protocol) &&
                        parsed.origin !== window.location.origin;
                } catch {
                    return true;
                }
            }),
        hasGuidance: document.body.innerText.includes('100% scale') &&
            document.body.innerText.includes('disable Fit to page'),
        rawTokenVisible: document.body.innerText.includes('GSS-'),
    }))()`);

    if (
        state.cards !== expectedCards ||
        state.externalResources.length > 0 ||
        !state.hasGuidance ||
        state.rawTokenVisible
    ) {
        throw new Error(`Print page failed: ${JSON.stringify(state)}.`);
    }
}

async function assertLocalResources() {
    const external = await client.evaluate(`performance
        .getEntriesByType('resource')
        .map((entry) => entry.name)
        .filter((url) => {
            try {
                const parsed = new URL(url);
                return ['http:', 'https:'].includes(parsed.protocol) &&
                    parsed.origin !== window.location.origin &&
                    parsed.port !== '8080';
            } catch {
                return false;
            }
        })`);

    if (external.length > 0) {
        throw new Error(
            `External runtime resources were requested: ${external.join(', ')}.`,
        );
    }
}

function prepareTestData() {
    const suffix = Date.now().toString(36).toUpperCase();
    const php = `
$ceremony = App\\Models\\Ceremony::query()
    ->where('status', 'active')
    ->orderBy('id')
    ->firstOrFail();
$ceremony->update(['presentation_mode' => 'automatic']);
$state = App\\Models\\StageState::query()
    ->where('ceremony_id', $ceremony->id)
    ->first();
if ($state !== null && $state->current_queue_item_id !== null) {
    throw new RuntimeException('The active ceremony stage must be idle for Phase 6 browser verification.');
}
$sequenceStart = ((int) App\\Models\\Student::query()
    ->where('ceremony_id', $ceremony->id)
    ->max('sequence_number')) + 100;
$tokenService = app(App\\Services\\StudentQrTokenGenerator::class);
$rows = [
    [
        'key' => 'ana',
        'student_number' => 'PHASE6-ANA-${suffix}',
        'first_name' => 'Ana Marie',
        'middle_name' => null,
        'last_name' => 'dela Cruz',
        'display_name' => 'Ana Marie dela Cruz',
        'announcement_name' => 'Ana Marie dela Cruz',
        'college' => 'College of Computing',
        'program' => 'Bachelor of Science in Computer Science',
        'honors' => 'Magna Cum Laude',
    ],
    [
        'key' => 'christopher',
        'student_number' => 'PHASE6-CHRIS-${suffix}',
        'first_name' => 'Christopher Emmanuel',
        'middle_name' => null,
        'last_name' => 'Villanueva-Santos',
        'display_name' => 'Christopher Emmanuel Villanueva-Santos',
        'announcement_name' => 'Christopher Emmanuel Villanueva-Santos',
        'college' => 'College of Engineering',
        'program' => 'Bachelor of Science in Electronics Engineering',
        'honors' => null,
    ],
    [
        'key' => 'lourdes',
        'student_number' => 'PHASE6-LOURDES-${suffix}',
        'first_name' => 'Ma. Lourdes',
        'middle_name' => 'P.',
        'last_name' => 'Reyes',
        'display_name' => 'Ma. Lourdes P. Reyes',
        'announcement_name' => 'Ma. Lourdes P. Reyes',
        'college' => 'College of Business',
        'program' => 'Bachelor of Science in Business Administration',
        'honors' => 'Cum Laude, Leadership Award',
    ],
];
$students = [];
foreach ($rows as $index => $row) {
    $key = $row['key'];
    unset($row['key']);
    $student = App\\Models\\Student::query()->create([
        ...$row,
        'ceremony_id' => $ceremony->id,
        'qr_token' => $tokenService->generate(),
        'suffix' => null,
        'pronunciation_guide' => null,
        'major' => null,
        'sequence_number' => $sequenceStart + $index,
        'photo_path' => null,
        'audio_path' => null,
        'eligibility_status' => 'eligible',
    ]);
    $students[$key] = [
        'id' => $student->id,
        'studentNumber' => $student->student_number,
        'token' => $student->qr_token,
    ];
}
$admin = App\\Models\\User::query()
    ->where('email', 'admin@example.test')
    ->firstOrFail();
$importStudentNumber = 'PHASE6-IMPORTED-${suffix}';
$importSequence = $sequenceStart + 10;
$source = implode("\\n", [
    'student_number,first_name,middle_name,last_name,program,sequence_number,eligibility_status',
    $importStudentNumber.',Phase 6,,Imported Graduate,Bachelor of Science in Computer Science,'.$importSequence.',eligible',
]);
$parser = app(App\\Services\\StudentCsvParser::class);
$parsed = $parser->parse($source);
$preparation = app(App\\Services\\StudentImportPreparation::class);
$studentImport = App\\Models\\StudentImport::query()->create([
    'token' => (string) Illuminate\\Support\\Str::uuid(),
    'user_id' => $admin->id,
    'ceremony_id' => $ceremony->id,
    'original_filename' => 'phase-6-browser-import.csv',
    'mode' => 'create_only',
    'delimiter' => $parsed['delimiter'],
    'headers' => $parsed['headers'],
    'mapping' => $preparation->suggestedMapping($parsed['headers']),
    'source_content' => $source,
    'row_count' => count($parsed['rows']),
    'summary' => null,
    'expires_at' => now()->utc()->addMinutes(30),
    'consumed_at' => null,
]);
app(App\\Actions\\Admin\\ConfirmStudentImport::class)
    ->execute($studentImport, $admin, '127.0.0.1');
$importedStudent = App\\Models\\Student::query()
    ->where('student_number', $importStudentNumber)
    ->firstOrFail();
echo json_encode([
    'ceremonyId' => $ceremony->id,
    'importedStudent' => [
        'id' => $importedStudent->id,
        'importId' => $studentImport->id,
        'token' => $importedStudent->qr_token,
    ],
    'sequenceStart' => $sequenceStart,
    'startedAt' => now()->utc()->toIso8601String(),
    'state' => $state === null ? null : [
        'audio_status' => $state->audio_status->value,
        'current_queue_item_id' => $state->current_queue_item_id,
        'display_mode' => $state->display_mode->value,
        'is_muted' => $state->is_muted,
        'version' => $state->version,
    ],
    'students' => $students,
]);`;

    return JSON.parse(runPhp(php));
}

function cleanupTestData(data) {
    const ids = [
        ...Object.values(data.students).map((student) => student.id),
        data.importedStudent.id,
    ];
    const state = JSON.stringify(data.state);
    const php = `
$ids = ${JSON.stringify(ids)};
$ceremonyId = ${Number(data.ceremonyId)};
$queueIds = App\\Models\\StageQueueItem::query()
    ->whereIn('student_id', $ids)
    ->pluck('id');
$originalState = json_decode('${escapePhpSingleQuoted(state)}', true);
if ($originalState === null) {
    App\\Models\\StageState::query()
        ->where('ceremony_id', $ceremonyId)
        ->where(function ($query) use ($queueIds) {
            $query->whereNull('current_queue_item_id')
                ->orWhereIn('current_queue_item_id', $queueIds);
        })
        ->delete();
} else {
    App\\Models\\StageState::query()
        ->where('ceremony_id', $ceremonyId)
        ->update($originalState);
}
App\\Models\\StageEvent::query()->whereIn('queue_item_id', $queueIds)->delete();
App\\Models\\ScanLog::query()->whereIn('student_id', $ids)->delete();
App\\Models\\StageQueueItem::query()->whereIn('id', $queueIds)->delete();
App\\Models\\AuditLog::query()
    ->where('entity_type', 'student')
    ->whereIn('entity_id', $ids)
    ->delete();
App\\Models\\AuditLog::query()
    ->where('entity_type', 'student_import')
    ->where('entity_id', ${Number(data.importedStudent.importId)})
    ->delete();
App\\Models\\AuditLog::query()
    ->where('action', 'student.qr_batch_generated')
    ->where('entity_type', 'ceremony')
    ->where('entity_id', $ceremonyId)
    ->where('created_at', '>=', '${escapePhpSingleQuoted(data.startedAt)}')
    ->delete();
App\\Models\\Student::query()->whereIn('id', $ids)->delete();
App\\Models\\StudentImport::query()
    ->whereKey(${Number(data.importedStudent.importId)})
    ->delete();`;

    runPhp(php);
}

function runPhp(code) {
    const bootstrap = `
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
${code}`;

    return execFileSync(
        'docker',
        ['compose', 'exec', '-T', 'app', 'php', '-r', bootstrap],
        {
            cwd: repositoryPath,
            encoding: 'utf8',
            stdio: ['ignore', 'pipe', 'pipe'],
        },
    ).trim();
}

function escapePhpSingleQuoted(value) {
    return String(value).replaceAll('\\', '\\\\').replaceAll("'", "\\'");
}

function readEnvironmentValue(key) {
    const environmentPath = join(repositoryPath, '.env');

    if (!existsSync(environmentPath)) {
        return null;
    }

    const line = readFileSync(environmentPath, 'utf8')
        .split(/\r?\n/)
        .find((candidate) => candidate.startsWith(`${key}=`));

    if (!line) {
        return null;
    }

    return line
        .slice(key.length + 1)
        .trim()
        .replace(/^(['"])(.*)\1$/, '$2');
}

function pause(milliseconds) {
    return new Promise((resolve) => {
        setTimeout(resolve, milliseconds);
    });
}
