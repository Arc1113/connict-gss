import { Head, Link } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { getEcho, observeEchoConnection } from '../lib/echo';
import { requestJson } from '../lib/http';

const EMPTY_CONTROL = {
    version: 0,
    automatic_paused: false,
    display_mode: 'idle',
    current: null,
    waiting: [],
    skipped: [],
    recent_actions: [],
};

const ACTION_LABELS = {
    student_queued: 'Graduate queued',
    present_next: 'Next graduate presented',
    present_selected: 'Selected graduate presented',
    student_presented_automatically: 'Graduate presented automatically',
    student_completed: 'Graduate completed',
    student_skipped: 'Graduate skipped',
    student_returned_to_queue: 'Graduate returned to queue',
    announcement_replayed: 'Announcement replayed',
    automatic_mode_paused: 'Automatic presentation paused',
    automatic_mode_resumed: 'Automatic presentation resumed',
    holding_screen_activated: 'Holding screen shown',
    holding_screen_deactivated: 'Live display restored',
};

function connectionCopy(state) {
    if (state === 'connected') {
        return 'Live updates connected';
    }

    if (state === 'reconnected') {
        return 'Reconnected — state recovered';
    }

    if (state === 'connecting') {
        return 'Connecting to live updates';
    }

    return 'Live updates disconnected — use refresh before the next command';
}

export default function StageControl({ ceremony, ceremonyStatus }) {
    const [control, setControl] = useState(EMPTY_CONTROL);
    const [stageMode, setStageMode] = useState('idle');
    const [loaded, setLoaded] = useState(false);
    const [selectedId, setSelectedId] = useState(null);
    const [pendingAction, setPendingAction] = useState(null);
    const [connectionState, setConnectionState] = useState('connecting');
    const [feedback, setFeedback] = useState(null);
    const versionRef = useRef(0);
    const connectedRef = useRef(false);
    const reconnectRef = useRef(false);
    const commandLockRef = useRef(false);

    const reconcile = useCallback(async (markLoaded = false) => {
        try {
            const [queueResult, stageResult] = await Promise.all([
                requestJson('/stage/queue'),
                requestJson('/stage/state'),
            ]);

            if (!queueResult.response.ok || !stageResult.response.ok) {
                throw new Error('Authoritative state unavailable');
            }

            const nextControl = queueResult.data.control ?? EMPTY_CONTROL;
            versionRef.current = Math.max(
                nextControl.version ?? 0,
                stageResult.data.state?.version ?? 0,
            );
            setControl(nextControl);
            setStageMode(stageResult.data.state?.mode ?? 'idle');
            setSelectedId((current) =>
                nextControl.waiting.some((item) => item.id === current)
                    ? current
                    : null,
            );
        } catch {
            setFeedback({
                tone: 'error',
                message: 'State could not be recovered. Check the local server, then refresh before issuing a command.',
            });
        } finally {
            if (markLoaded) {
                setLoaded(true);
            }
        }
    }, []);

    useEffect(() => {
        let channel;
        let cancelled = false;

        async function connect() {
            await reconcile(true);

            if (cancelled || !ceremony) {
                return;
            }

            const echo = getEcho();

            if (!echo) {
                return;
            }

            channel = echo
                .private(`stage-control.${ceremony.id}`)
                .listen('.queue.updated', (event) => {
                    if (event.version <= versionRef.current) {
                        return;
                    }

                    void reconcile();
                })
                .subscribed(() => {
                    void reconcile();
                });
        }

        void connect();

        return () => {
            cancelled = true;

            if (channel && ceremony) {
                getEcho()?.leave(`stage-control.${ceremony.id}`);
            }
        };
    }, [ceremony, reconcile]);

    useEffect(() => {
        return observeEchoConnection((current) => {
            if (current === 'connected') {
                const next = reconnectRef.current ? 'reconnected' : 'connected';
                setConnectionState(next);

                if (reconnectRef.current) {
                    void reconcile();
                }

                connectedRef.current = true;
                reconnectRef.current = false;

                return;
            }

            if (connectedRef.current) {
                reconnectRef.current = true;
            }

            setConnectionState(current);
        });
    }, [reconcile]);

    async function runCommand(action, url, confirmation = null) {
        if (commandLockRef.current || (confirmation && !window.confirm(confirmation))) {
            return;
        }

        commandLockRef.current = true;
        setPendingAction(action);
        setFeedback({
            tone: 'neutral',
            message: 'Command in progress. Wait for server confirmation.',
        });

        try {
            const { response, data } = await requestJson(url, {
                method: 'POST',
            });

            setFeedback({
                tone: response.ok ? 'success' : 'error',
                message:
                    data.message ??
                    'The command was not confirmed. Refresh before trying again.',
            });
            await reconcile();
        } catch {
            setFeedback({
                tone: 'error',
                message: 'The command was not confirmed. Check the local server, then refresh stage control.',
            });
        } finally {
            commandLockRef.current = false;
            setPendingAction(null);
        }
    }

    const current = control.current;
    const waiting = control.waiting;
    const selected = waiting.find((item) => item.id === selectedId);
    const isHolding = stageMode === 'holding' || control.display_mode === 'holding';
    const stageBusy = Boolean(current);
    const automatic = ceremony?.presentation_mode === 'automatic';
    const unavailable = !ceremony || ceremonyStatus !== 'ready';

    return (
        <>
            <Head title="Stage control" />

            <main className="stage-control-page">
                <header className="stage-control-header">
                    <div>
                        <p className="stage-control-kicker">Live ceremony operations</p>
                        <h1>Stage control</h1>
                        <p>
                            {ceremony?.name ?? 'No active ceremony'}
                            <span aria-hidden="true"> · </span>
                            <strong>
                                {automatic ? 'Automatic mode' : 'Controlled mode'}
                                {automatic && control.automatic_paused
                                    ? ' paused'
                                    : ''}
                            </strong>
                        </p>
                    </div>
                    <nav aria-label="Stage control links">
                        <Link className="secondary-action" href="/stage" target="_blank">
                            Open audience stage
                        </Link>
                        <Link className="secondary-action" href="/dashboard">
                            Return to operations
                        </Link>
                    </nav>
                </header>

                <section
                    className="stage-control-status"
                    aria-label="Operational status"
                >
                    <p>
                        <strong>Connection</strong>
                        <span>{connectionCopy(connectionState)}</span>
                    </p>
                    <p>
                        <strong>Audience display</strong>
                        <span>
                            {isHolding
                                ? 'Holding screen'
                                : current
                                  ? 'Graduate shown'
                                  : 'Awaiting presentation'}
                        </span>
                    </p>
                    <p>
                        <strong>Announcement</strong>
                        <span>
                            {current?.student.has_audio
                                ? 'Local audio ready'
                                : current
                                  ? 'No approved audio'
                                  : 'No current graduate'}
                        </span>
                    </p>
                </section>

                {feedback && (
                    <p
                        className={`stage-control-feedback stage-control-feedback-${feedback.tone}`}
                        role={feedback.tone === 'error' ? 'alert' : 'status'}
                    >
                        {feedback.message}
                    </p>
                )}

                {!loaded ? (
                    <section className="stage-control-loading" role="status">
                        <h2>Recovering ceremony state</h2>
                        <p>Wait for the queue and audience state to load.</p>
                    </section>
                ) : unavailable ? (
                    <section className="stage-control-empty" role="alert">
                        <h2>Stage control unavailable</h2>
                        <p>
                            Exactly one active ceremony is required. Ask an
                            administrator to correct ceremony status, then reload.
                        </p>
                    </section>
                ) : (
                    <div className="stage-control-layout">
                        <section
                            className="stage-control-current"
                            aria-labelledby="current-graduate-heading"
                        >
                            <div className="stage-control-section-heading">
                                <div>
                                    <p>Audience state</p>
                                    <h2 id="current-graduate-heading">
                                        Current graduate
                                    </h2>
                                </div>
                                <span>
                                    {isHolding
                                        ? 'Holding'
                                        : current
                                          ? 'Presenting'
                                          : 'Idle'}
                                </span>
                            </div>

                            {current ? (
                                <div className="stage-control-current-identity">
                                    <h3>{current.student.display_name}</h3>
                                    <p>{current.student.program}</p>
                                    {current.student.major && (
                                        <p>Major in {current.student.major}</p>
                                    )}
                                    {current.student.honors && (
                                        <p>{current.student.honors}</p>
                                    )}
                                </div>
                            ) : (
                                <div className="stage-control-current-empty">
                                    <h3>No graduate is currently presented</h3>
                                    <p>
                                        Present the next graduate when the
                                        audience display is ready.
                                    </p>
                                </div>
                            )}

                            <div className="stage-control-primary-actions">
                                {current ? (
                                    <>
                                        <button
                                            className="primary-action"
                                            disabled={Boolean(pendingAction)}
                                            onClick={() =>
                                                runCommand(
                                                    'complete',
                                                    '/stage/complete-current',
                                                    `Complete ${current.student.display_name}? This cannot be undone in Phase 7.`,
                                                )
                                            }
                                            type="button"
                                        >
                                            {pendingAction === 'complete'
                                                ? 'Completing…'
                                                : 'Complete presentation'}
                                        </button>
                                        <button
                                            className="secondary-action"
                                            disabled={
                                                Boolean(pendingAction) ||
                                                !current.student.has_audio ||
                                                isHolding
                                            }
                                            onClick={() =>
                                                runCommand(
                                                    'replay',
                                                    '/stage/replay',
                                                )
                                            }
                                            type="button"
                                        >
                                            {pendingAction === 'replay'
                                                ? 'Replaying…'
                                                : 'Replay announcement'}
                                        </button>
                                    </>
                                ) : (
                                    <button
                                        className="primary-action"
                                        disabled={
                                            Boolean(pendingAction) ||
                                            waiting.length === 0 ||
                                            isHolding
                                        }
                                        onClick={() =>
                                            runCommand(
                                                'present-next',
                                                '/stage/present-next',
                                            )
                                        }
                                        type="button"
                                    >
                                        {pendingAction === 'present-next'
                                            ? 'Presenting…'
                                            : 'Present next graduate'}
                                    </button>
                                )}

                                <button
                                    className="secondary-action"
                                    disabled={Boolean(pendingAction)}
                                    onClick={() =>
                                        runCommand(
                                            'holding',
                                            isHolding
                                                ? '/stage/holding/deactivate'
                                                : '/stage/holding/activate',
                                            isHolding
                                                ? null
                                                : 'Show the holding screen? The current graduate remains active and is not completed.',
                                        )
                                    }
                                    type="button"
                                >
                                    {pendingAction === 'holding'
                                        ? 'Updating display…'
                                        : isHolding
                                          ? 'Restore live display'
                                          : 'Show holding screen'}
                                </button>
                            </div>

                            {automatic && (
                                <div className="stage-control-automatic">
                                    <strong>Automatic initial presentation</strong>
                                    <p>
                                        {control.automatic_paused
                                            ? 'Paused. New scans will remain waiting.'
                                            : 'Active only when the stage is idle and no graduate is ahead. Completion remains manual.'}
                                    </p>
                                    <button
                                        className="secondary-action"
                                        disabled={Boolean(pendingAction)}
                                        onClick={() =>
                                            runCommand(
                                                'automatic',
                                                control.automatic_paused
                                                    ? '/stage/automatic/resume'
                                                    : '/stage/automatic/pause',
                                            )
                                        }
                                        type="button"
                                    >
                                        {pendingAction === 'automatic'
                                            ? 'Updating…'
                                            : control.automatic_paused
                                              ? 'Resume automatic mode'
                                              : 'Pause automatic mode'}
                                    </button>
                                </div>
                            )}
                        </section>

                        <section
                            className="stage-control-queue"
                            aria-labelledby="waiting-queue-heading"
                        >
                            <div className="stage-control-section-heading">
                                <div>
                                    <p>Deterministic order</p>
                                    <h2 id="waiting-queue-heading">
                                        Waiting queue
                                    </h2>
                                </div>
                                <span>{waiting.length} waiting</span>
                            </div>

                            {waiting.length === 0 ? (
                                <div className="stage-control-queue-empty">
                                    <h3>Queue is empty</h3>
                                    <p>Accepted scans will appear here.</p>
                                </div>
                            ) : (
                                <>
                                    <div className="stage-control-table-wrap">
                                        <table>
                                            <thead>
                                                <tr>
                                                    <th scope="col">Select</th>
                                                    <th scope="col">Position</th>
                                                    <th scope="col">Graduate</th>
                                                    <th scope="col">Audio</th>
                                                    <th scope="col">Queue action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {waiting.map((item) => (
                                                    <tr key={item.id}>
                                                        <td>
                                                            <input
                                                                aria-label={`Select ${item.student.display_name}`}
                                                                checked={
                                                                    selectedId ===
                                                                    item.id
                                                                }
                                                                name="selected_queue_item"
                                                                onChange={() =>
                                                                    setSelectedId(
                                                                        item.id,
                                                                    )
                                                                }
                                                                type="radio"
                                                            />
                                                        </td>
                                                        <td>
                                                            {item.queue_position}
                                                        </td>
                                                        <th scope="row">
                                                            <strong>
                                                                {
                                                                    item.student
                                                                        .display_name
                                                                }
                                                            </strong>
                                                            <span>
                                                                {
                                                                    item.student
                                                                        .program
                                                                }
                                                            </span>
                                                        </th>
                                                        <td>
                                                            {item.student.has_audio
                                                                ? 'Ready'
                                                                : 'Missing'}
                                                        </td>
                                                        <td>
                                                            <button
                                                                className="stage-control-text-action"
                                                                disabled={Boolean(
                                                                    pendingAction,
                                                                )}
                                                                onClick={() =>
                                                                    runCommand(
                                                                        `skip-${item.id}`,
                                                                        `/stage/skip/${item.id}`,
                                                                        `Skip ${item.student.display_name}? They can be returned to the end of the queue.`,
                                                                    )
                                                                }
                                                                type="button"
                                                            >
                                                                {pendingAction ===
                                                                `skip-${item.id}`
                                                                    ? 'Skipping…'
                                                                    : 'Skip'}
                                                            </button>
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                    <div className="stage-control-selected-action">
                                        <p>
                                            {selected
                                                ? `${selected.student.display_name} selected.`
                                                : 'Select one waiting graduate to present out of order.'}
                                        </p>
                                        <button
                                            className="secondary-action"
                                            disabled={
                                                !selected ||
                                                stageBusy ||
                                                isHolding ||
                                                Boolean(pendingAction)
                                            }
                                            onClick={() =>
                                                runCommand(
                                                    'present-selected',
                                                    `/stage/present/${selected.id}`,
                                                    selected.id !== waiting[0]?.id
                                                        ? `Present ${selected.student.display_name} ahead of earlier waiting graduates? Queue order will otherwise remain unchanged.`
                                                        : null,
                                                )
                                            }
                                            type="button"
                                        >
                                            {pendingAction === 'present-selected'
                                                ? 'Presenting…'
                                                : 'Present selected'}
                                        </button>
                                    </div>
                                </>
                            )}
                        </section>

                        <section
                            className="stage-control-support"
                            aria-labelledby="skipped-heading"
                        >
                            <div>
                                <h2 id="skipped-heading">Skipped graduates</h2>
                                {control.skipped.length === 0 ? (
                                    <p>No graduates are currently skipped.</p>
                                ) : (
                                    <ul>
                                        {control.skipped.map((item) => (
                                            <li key={item.id}>
                                                <span>
                                                    <strong>
                                                        {item.student.display_name}
                                                    </strong>
                                                    <small>
                                                        {item.student.program}
                                                    </small>
                                                </span>
                                                <button
                                                    className="stage-control-text-action"
                                                    disabled={Boolean(pendingAction)}
                                                    onClick={() =>
                                                        runCommand(
                                                            `return-${item.id}`,
                                                            `/stage/return/${item.id}`,
                                                        )
                                                    }
                                                    type="button"
                                                >
                                                    {pendingAction ===
                                                    `return-${item.id}`
                                                        ? 'Returning…'
                                                        : 'Return to queue'}
                                                </button>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </div>
                            <div>
                                <h2>Recent actions</h2>
                                {control.recent_actions.length === 0 ? (
                                    <p>No stage actions recorded yet.</p>
                                ) : (
                                    <ol>
                                        {control.recent_actions.map((action) => (
                                            <li key={action.id}>
                                                <strong>
                                                    {ACTION_LABELS[action.type] ??
                                                        'Stage state updated'}
                                                </strong>
                                                {action.graduate && (
                                                    <span>{action.graduate}</span>
                                                )}
                                            </li>
                                        ))}
                                    </ol>
                                )}
                            </div>
                        </section>
                    </div>
                )}
            </main>
        </>
    );
}
