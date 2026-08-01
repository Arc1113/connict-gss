import { Head, Link } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { observeEchoConnection } from '../lib/echo';
import { requestJson } from '../lib/http';
import OperationCommandMenu from '../components/OperationCommandMenu';

const INITIAL_FEEDBACK = {
    status: 'ready',
    tone: 'neutral',
    title: 'Ready for the next graduate',
    message: 'Scan the QR code. If needed, enter the token and choose Process scan.',
};

const REJECTION_FEEDBACK = {
    invalid_token: {
        title: 'QR code not recognized',
        message: 'Check that the correct QR code is visible, then scan it again.',
    },
    already_queued: {
        title: 'Graduate is already in the queue',
        message: 'Do not scan again. Keep the graduate in line for the stage operator.',
    },
    already_presenting: {
        title: 'Graduate is already being presented',
        message: 'Do not scan again. Wait for the stage operator to complete the presentation.',
    },
    student_ineligible: {
        title: 'Graduate is not eligible',
        message: 'Do not continue with this QR code. Ask the ceremony administrator to verify the graduate record.',
    },
    ceremony_inactive: {
        title: 'Ceremony is not accepting scans',
        message: 'Stop scanning. Ask an administrator to resume the ceremony, then reload this screen.',
    },
    already_completed: {
        title: 'Presentation already completed',
        message: 'Do not scan again. Ask the stage operator if the graduate needs to be presented another time.',
    },
    skipped_requires_stage_action: {
        title: 'Graduate is on the skipped list',
        message: 'Do not scan again. Ask the stage operator to return the graduate to the queue.',
    },
    unauthorized: {
        title: 'Scanner access expired',
        message: 'Return to operations and sign in again before processing another scan.',
    },
    request_failed: {
        title: 'Scan not confirmed',
        message: 'Check the local server connection. When it is ready, scan the same QR code again.',
    },
};

function ceremonyUnavailableFeedback(ceremonyStatus) {
    if (ceremonyStatus === 'multiple_active_ceremonies') {
        return {
            status: 'ceremony_inactive',
            tone: 'rejected',
            title: 'Scanner stopped',
            message: 'More than one ceremony is active. Ask an administrator to correct the ceremony status before scanning.',
        };
    }

    return {
        status: 'ceremony_inactive',
        tone: 'rejected',
        title: 'Scanner stopped',
        message: 'No active ceremony is available. Ask an administrator to activate the ceremony before scanning.',
    };
}

function rejectionFeedback(status, fallbackMessage) {
    const content = REJECTION_FEEDBACK[status];
    const feedbackStatus =
        status === 'already_queued' || status === 'already_presenting'
            ? 'duplicate_active'
            : status;

    return {
        status: feedbackStatus,
        tone: 'rejected',
        title: content?.title ?? 'Scan not accepted',
        message:
            content?.message ??
            fallbackMessage ??
            'Check the graduate record, then follow the ceremony recovery procedure.',
    };
}

function realtimeStatus(state) {
    if (state === 'connected') {
        return {
            label: 'Live updates connected',
            detail: 'Queue updates are available.',
            tone: 'ready',
        };
    }

    if (state === 'reconnected') {
        return {
            label: 'Live updates reconnected',
            detail: 'The queue will recover the latest saved state.',
            tone: 'ready',
        };
    }

    if (state === 'connecting') {
        return {
            label: 'Connecting to live updates',
            detail: 'HTTP scanning remains available.',
            tone: 'checking',
        };
    }

    return {
        label: 'Live updates disconnected',
        detail: 'Keep using HTTP scans. Stage control will recover after reconnection.',
        tone: 'warning',
    };
}

function serverStatus(state) {
    if (state === 'checking') {
        return {
            label: 'Server request in progress',
            detail: 'Do not scan another QR code yet.',
            tone: 'checking',
        };
    }

    if (state === 'unavailable') {
        return {
            label: 'Server request failed',
            detail: 'No scan was confirmed. Check the local server connection.',
            tone: 'danger',
        };
    }

    return {
        label: 'Server available',
        detail: 'Scan validation is ready.',
        tone: 'ready',
    };
}

export default function Scanner({ ceremony, ceremonyStatus }) {
    const inputRef = useRef(null);
    const submissionLockRef = useRef(false);
    const sessionEventRef = useRef(0);
    const hasConnectedRef = useRef(false);
    const reconnectPendingRef = useRef(false);
    const [token, setToken] = useState('');
    const [submitting, setSubmitting] = useState(false);
    const [ceremonyAccepting, setCeremonyAccepting] = useState(Boolean(ceremony));
    const [connectionState, setConnectionState] = useState('connecting');
    const [serverState, setServerState] = useState('available');
    const [feedback, setFeedback] = useState(ceremony ? INITIAL_FEEDBACK : ceremonyUnavailableFeedback(ceremonyStatus));
    const [lastAccepted, setLastAccepted] = useState(null);
    const [recentScans, setRecentScans] = useState([]);
    const [lookupQuery, setLookupQuery] = useState('');
    const [lookupStudents, setLookupStudents] = useState([]);
    const [lookupMessage, setLookupMessage] = useState(
        'Search the active ceremony by graduate name or student number.',
    );
    const [lookupLoading, setLookupLoading] = useState(false);
    const [selectedStudentId, setSelectedStudentId] = useState(null);
    const canScan = Boolean(ceremony) && ceremonyAccepting;

    function recordSessionScan({ detail, label, tone }) {
        sessionEventRef.current += 1;

        setRecentScans((current) => [
            {
                id: sessionEventRef.current,
                detail,
                label,
                time: new Date().toLocaleTimeString([], {
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit',
                }),
                tone,
            },
            ...current,
        ].slice(0, 6));
    }

    useEffect(() => {
        inputRef.current?.focus();

        const refocus = () => inputRef.current?.focus();
        const focusScannerShortcut = (event) => {
            const target = event.target;
            const isTyping =
                target instanceof HTMLElement &&
                (target.isContentEditable ||
                    ['INPUT', 'SELECT', 'TEXTAREA'].includes(target.tagName));

            if (event.key === '/' && !isTyping) {
                event.preventDefault();
                inputRef.current?.focus();
            }
        };

        window.addEventListener('focus', refocus);
        window.addEventListener('keydown', focusScannerShortcut);
        const stopObserving = observeEchoConnection((current) => {
            if (current === 'connected') {
                setConnectionState(reconnectPendingRef.current ? 'reconnected' : 'connected');
                hasConnectedRef.current = true;
                reconnectPendingRef.current = false;

                return;
            }

            if (hasConnectedRef.current) {
                reconnectPendingRef.current = true;
            }

            setConnectionState(current);
        });

        return () => {
            window.removeEventListener('focus', refocus);
            window.removeEventListener('keydown', focusScannerShortcut);
            stopObserving();
        };
    }, []);

    function applyScanResult(data, manual = false) {
        setServerState(data.status === 'request_failed' ? 'unavailable' : 'available');

        if (data.status === 'queued') {
            const displayName = data.student?.display_name?.trim();

            if (!displayName) {
                setFeedback({
                    status: 'missing_student_information',
                    tone: 'warning',
                    title: 'Queued — information incomplete',
                    message: 'Ask the stage operator to verify the graduate before presenting.',
                });
                setLastAccepted('Graduate information unavailable');
                recordSessionScan({
                    detail: 'Graduate information unavailable',
                    label: 'Queued — verify record',
                    tone: 'warning',
                });
            } else {
                setFeedback({
                    status: data.automatically_presented ? 'accepted' : 'stage_busy',
                    tone: 'accepted',
                    title: data.automatically_presented
                        ? 'Queued and presented automatically'
                        : manual
                          ? 'Manual selection queued'
                          : 'Graduate added to queue',
                    message: data.automatically_presented
                        ? `${displayName} is now on the audience display. Explicit completion is still required.`
                        : data.broadcast_delivered
                          ? `${displayName} is waiting at queue position ${data.queue_position}.`
                          : `${displayName} is saved at queue position ${data.queue_position}. Stage control will recover from server state.`,
                });
                setLastAccepted(displayName);
                recordSessionScan({
                    detail: displayName,
                    label: data.automatically_presented
                        ? 'Presented automatically'
                        : manual
                          ? 'Manual lookup queued'
                          : `Queued · position ${data.queue_position}`,
                    tone: 'accepted',
                });
            }

            return;
        }

        if (data.status === 'ceremony_inactive') {
            setCeremonyAccepting(false);
        }

        if (manual && data.status === 'invalid_token') {
            setFeedback({
                status: 'invalid_manual_selection',
                tone: 'rejected',
                title: 'Graduate selection is no longer available',
                message: 'Search the active ceremony again, verify the graduate, then retry.',
            });
            recordSessionScan({
                detail: 'Manual lookup result expired',
                label: 'Not accepted',
                tone: 'rejected',
            });

            return;
        }

        const rejected = rejectionFeedback(data.status, data.message);

        setFeedback({ ...rejected });
        recordSessionScan({
            detail: rejected.title,
            label: 'Not accepted',
            tone: 'rejected',
        });
    }

    async function submitScan(event) {
        event.preventDefault();

        if (submissionLockRef.current || !canScan) {
            return;
        }

        const submittedToken = token.trim();

        if (!submittedToken) {
            setFeedback({
                status: 'empty_input',
                tone: 'rejected',
                title: 'Nothing scanned',
                message: 'Place the cursor in the scan field, scan the QR code, then try again.',
            });
            inputRef.current?.focus();

            return;
        }

        submissionLockRef.current = true;
        setSubmitting(true);
        setServerState('checking');
        setFeedback({
            status: 'processing',
            tone: 'neutral',
            title: 'Processing scan',
            message: 'Wait for confirmation before scanning another graduate.',
        });

        try {
            const { data } = await requestJson('/scans', {
                method: 'POST',
                body: JSON.stringify({ token: submittedToken }),
            });

            applyScanResult(data);
        } catch {
            setServerState('unavailable');
            setFeedback({
                status: 'request_failed',
                tone: 'rejected',
                title: 'Scan not confirmed',
                message: 'Check the local server connection. When it is restored, scan the same QR code again.',
            });
            recordSessionScan({
                detail: 'Server confirmation unavailable',
                label: 'Not confirmed',
                tone: 'rejected',
            });
        } finally {
            setToken('');
            setSubmitting(false);
            submissionLockRef.current = false;
            window.requestAnimationFrame(() => inputRef.current?.focus());
        }
    }

    async function searchStudents(event) {
        event.preventDefault();

        const query = lookupQuery.trim();

        if (query.length < 2 || lookupLoading || submitting || !canScan) {
            setLookupMessage('Enter at least two characters before searching.');

            return;
        }

        setLookupLoading(true);
        setSelectedStudentId(null);
        setLookupMessage('Searching the active ceremony…');

        try {
            const { response, data } = await requestJson('/scanner/lookup', {
                method: 'POST',
                body: JSON.stringify({ query }),
            });

            if (!response.ok) {
                setLookupStudents([]);
                setLookupMessage(
                    data.message ??
                        'Manual lookup failed. Check the local server and try again.',
                );

                if (data.status === 'ceremony_inactive') {
                    setCeremonyAccepting(false);
                }

                return;
            }

            setLookupStudents(data.students ?? []);
            setLookupMessage(data.message);
        } catch {
            setLookupStudents([]);
            setLookupMessage(
                'Manual lookup failed. Check the local server connection and try again.',
            );
        } finally {
            setLookupLoading(false);
        }
    }

    async function processSelectedStudent() {
        if (
            submissionLockRef.current ||
            submitting ||
            !canScan ||
            selectedStudentId === null
        ) {
            return;
        }

        submissionLockRef.current = true;
        setSubmitting(true);
        setServerState('checking');
        setFeedback({
            status: 'processing',
            tone: 'neutral',
            title: 'Processing verified graduate',
            message: 'Wait for server confirmation before processing anyone else.',
        });

        try {
            const { data } = await requestJson('/scans/manual', {
                method: 'POST',
                body: JSON.stringify({ student_id: selectedStudentId }),
            });

            applyScanResult(data, true);

            if (data.status === 'queued') {
                setLookupQuery('');
                setLookupStudents([]);
                setSelectedStudentId(null);
                setLookupMessage(
                    'Search the active ceremony by graduate name or student number.',
                );
            }
        } catch {
            setServerState('unavailable');
            setFeedback({
                status: 'request_failed',
                tone: 'rejected',
                title: 'Selection not confirmed',
                message: 'Check the local server connection. Search and verify the graduate again after it is restored.',
            });
            recordSessionScan({
                detail: 'Manual selection confirmation unavailable',
                label: 'Not confirmed',
                tone: 'rejected',
            });
        } finally {
            setSubmitting(false);
            submissionLockRef.current = false;
            window.requestAnimationFrame(() => inputRef.current?.focus());
        }
    }

    return (
        <>
            <Head title="Scanner" />

            <a className="skip-link" href="#scanner-content">
                Skip to scanner
            </a>

            <main className="scanner-page" id="scanner-content">
                <section className="scanner-shell" aria-labelledby="scanner-heading">
                    <header className="scanner-header">
                        <div>
                            <h1 id="scanner-heading">
                                {submitting ? 'Processing scan' : canScan ? 'Ready for the next graduate' : 'Scanner stopped'}
                            </h1>
                            <p>
                                {ceremony?.name ?? 'No active ceremony'}
                                <span aria-hidden="true"> · </span>
                                <strong
                                    className={
                                        canScan
                                            ? 'ceremony-status-active'
                                            : 'ceremony-status-inactive'
                                    }
                                >
                                    {canScan ? 'Active' : 'Paused or inactive'}
                                </strong>
                            </p>
                        </div>
                        <nav aria-label="Scanner navigation">
                            <OperationCommandMenu />
                            <Link className="secondary-action" href="/dashboard">
                                Return to operations
                            </Link>
                        </nav>
                    </header>

                    <ul className="scanner-system-status" aria-label="Scanner service status">
                        {[
                            {
                                detail: submitting
                                    ? 'Input is locked until confirmation.'
                                    : canScan
                                      ? 'USB and keyboard input are ready.'
                                      : 'Reload after the ceremony is resumed.',
                                label: submitting ? 'Scanner processing' : canScan ? 'Scanner ready' : 'Scanner unavailable',
                                tone: submitting ? 'checking' : canScan ? 'ready' : 'danger',
                            },
                            serverStatus(serverState),
                            realtimeStatus(connectionState),
                        ].map((status) => (
                            <li key={status.label}>
                                <span
                                    className={`status-marker status-marker-${status.tone}`}
                                    aria-hidden="true"
                                />
                                <span>
                                    <strong>{status.label}</strong>
                                    <small>{status.detail}</small>
                                </span>
                            </li>
                        ))}
                    </ul>

                    <div className="scanner-workspace">
                        <div className="scanner-primary">
                    <form
                        aria-busy={submitting}
                        className="scanner-form"
                        onSubmit={submitScan}
                    >
                        <label htmlFor="scan-token">
                            QR scan or manual token
                        </label>
                        <input
                            ref={inputRef}
                            id="scan-token"
                            name="token"
                            value={token}
                            onChange={(event) => setToken(event.target.value)}
                            autoComplete="off"
                            autoCapitalize="characters"
                            autoFocus
                            aria-keyshortcuts="/"
                            inputMode="text"
                            maxLength={128}
                            readOnly={submitting || !canScan}
                            spellCheck="false"
                            aria-describedby="scanner-help"
                        />
                        <button
                            className="primary-action scanner-submit"
                            type="submit"
                            disabled={submitting || !canScan}
                        >
                            {submitting ? 'Processing…' : 'Process scan'}
                        </button>
                        <p id="scanner-help">
                            Scanner input stays focused. Press Enter to process.
                        </p>
                    </form>

                    <section
                        className={`scan-feedback scan-feedback-${feedback.tone}`}
                        data-feedback-status={feedback.status}
                        role={feedback.tone === 'neutral' ? 'status' : 'alert'}
                        aria-live={feedback.tone === 'neutral' ? 'polite' : 'assertive'}
                        aria-atomic="true"
                    >
                        <p className="scan-feedback-label">Current scan result</p>
                        <strong>{feedback.title}</strong>
                        <p>{feedback.message}</p>
                        {(feedback.tone === 'rejected' || feedback.tone === 'warning') && canScan && (
                            <div className="scan-feedback-actions">
                                <button
                                    className="secondary-action"
                                    onClick={() => inputRef.current?.focus()}
                                    type="button"
                                >
                                    Focus scanner and try again
                                </button>
                            </div>
                        )}
                    </section>

                    <dl className="scanner-last-success">
                        <dt>Last successful graduate</dt>
                        <dd>{lastAccepted ?? 'No successful scan in this browser session'}</dd>
                    </dl>

                    <details className="scanner-fallback">
                        <summary>QR will not scan? Use controlled lookup</summary>
                        <div className="scanner-fallback-body">
                            <p>
                                Verify the graduate aloud before processing.
                            </p>
                            <form onSubmit={searchStudents}>
                                <label htmlFor="graduate-lookup">
                                    Graduate name or student number
                                </label>
                                <div>
                                    <input
                                        autoComplete="off"
                                        disabled={!canScan || submitting}
                                        id="graduate-lookup"
                                        maxLength="80"
                                        onChange={(event) =>
                                            setLookupQuery(event.target.value)
                                        }
                                        placeholder="Ana Marie dela Cruz"
                                        type="search"
                                        value={lookupQuery}
                                    />
                                    <button
                                        className="secondary-action"
                                        disabled={
                                            lookupLoading ||
                                            submitting ||
                                            !canScan ||
                                            lookupQuery.trim().length < 2
                                        }
                                        type="submit"
                                    >
                                        {lookupLoading ? 'Searching…' : 'Search'}
                                    </button>
                                </div>
                            </form>
                            <p
                                aria-live="polite"
                                className="scanner-lookup-message"
                                role="status"
                            >
                                {lookupMessage}
                            </p>
                            {lookupStudents.length > 0 && (
                                <fieldset className="scanner-lookup-results">
                                    <legend>Verify one graduate</legend>
                                    {lookupStudents.map((student) => (
                                        <label key={student.id}>
                                            <input
                                                checked={
                                                    selectedStudentId === student.id
                                                }
                                                name="manual_student_id"
                                                onChange={() =>
                                                    setSelectedStudentId(student.id)
                                                }
                                                type="radio"
                                                value={student.id}
                                            />
                                            <span>
                                                <strong>{student.display_name}</strong>
                                                <small>
                                                    Sequence {student.sequence_number}{' '}
                                                    · {student.program} ·{' '}
                                                    {student.eligibility}
                                                </small>
                                            </span>
                                        </label>
                                    ))}
                                </fieldset>
                            )}
                            <button
                                className="primary-action scanner-manual-submit"
                                disabled={
                                    selectedStudentId === null ||
                                    submitting ||
                                    !canScan
                                }
                                onClick={processSelectedStudent}
                                type="button"
                            >
                                {submitting
                                    ? 'Processing…'
                                    : 'Process verified graduate'}
                            </button>
                        </div>
                    </details>
                        </div>

                        <aside className="scanner-recent" aria-labelledby="scanner-recent-heading">
                            <header>
                                <div>
                                    <p>Browser session</p>
                                    <h2 id="scanner-recent-heading">Recent scans</h2>
                                </div>
                                <span>{recentScans.length}</span>
                            </header>
                            {recentScans.length === 0 ? (
                                <div className="scanner-recent-empty">
                                    <strong>No scans yet</strong>
                                    <p>Confirmed and rejected attempts will appear here.</p>
                                </div>
                            ) : (
                                <ol className="scanner-recent-list">
                                    {recentScans.map((scan) => (
                                        <li key={scan.id}>
                                            <span
                                                aria-hidden="true"
                                                className={`status-marker status-marker-${scan.tone}`}
                                            />
                                            <div>
                                                <strong>{scan.detail}</strong>
                                                <small>{scan.label}</small>
                                            </div>
                                            <time>{scan.time}</time>
                                        </li>
                                    ))}
                                </ol>
                            )}
                            <p className="scanner-recent-note">
                                Session history is temporary. Use Scan logs for the authoritative record.
                            </p>
                        </aside>
                    </div>
                </section>
            </main>
        </>
    );
}
