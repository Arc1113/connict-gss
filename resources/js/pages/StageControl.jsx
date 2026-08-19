import { Head, Link, usePage } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { getEcho, observeEchoConnection } from '../lib/echo';
import { requestJson } from '../lib/http';
import OperationCommandMenu from '../components/OperationCommandMenu';
import OnboardingGuide from '../components/OnboardingGuide';

const EMPTY_CONTROL = {
    version: 0,
    automatic_paused: false,
    display_mode: 'idle',
    audio_status: 'stopped',
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
    student_completed_and_next_presented: 'Completed and presented next',
    present_next_after_completion: 'Next graduate presented',
    current_presentation_returned: 'Wrong graduate returned to queue',
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

    return 'Live updates disconnected — reconnecting automatically';
}

export default function StageControl({ ceremony }) {
    const { onboarding } = usePage().props;
    const [activeCeremony, setActiveCeremony] = useState(() => ceremony ?? null);
    const [channelCeremonyId, setChannelCeremonyId] = useState(
        ceremony?.id ?? null,
    );
    const [control, setControl] = useState(EMPTY_CONTROL);
    const [stageMode, setStageMode] = useState('idle');
    const [loaded, setLoaded] = useState(false);
    const [selectedId, setSelectedId] = useState(null);
    const [pendingAction, setPendingAction] = useState(null);
    const [connectionState, setConnectionState] = useState('connecting');
    const [feedback, setFeedback] = useState(null);
    const [confirmation, setConfirmation] = useState(null);
    const versionRef = useRef(0);
    const connectedRef = useRef(false);
    const reconnectRef = useRef(false);
    const commandLockRef = useRef(false);
    const confirmationDialogRef = useRef(null);
    const confirmationCancelRef = useRef(null);
    const confirmationTriggerRef = useRef(null);

    const closeConfirmation = useCallback(() => {
        setConfirmation(null);

        window.requestAnimationFrame(() => {
            const trigger = confirmationTriggerRef.current;

            if (trigger?.isConnected && !trigger.disabled) {
                trigger.focus();
            }
        });
    }, []);

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
            const nextCeremony =
                queueResult.data.ceremony ?? stageResult.data.ceremony ?? null;

            versionRef.current = Math.max(
                nextControl.version ?? 0,
                stageResult.data.state?.version ?? 0,
            );
            setActiveCeremony(nextCeremony);
            setChannelCeremonyId(nextCeremony?.id ?? null);
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
        let subscribedCeremonyId = null;

        async function connect() {
            await reconcile(true);

            if (cancelled || channelCeremonyId === null) {
                return;
            }

            const echo = getEcho();

            if (!echo) {
                return;
            }

            channel = echo
                .private(`stage-control.${channelCeremonyId}`)
                .listen('.queue.updated', (event) => {
                    if (event.version <= versionRef.current) {
                        return;
                    }

                    void reconcile();
                })
                .listen('.stage.audio-status', () => {
                    void reconcile();
                })
                .subscribed(() => {
                    void reconcile();
                });

            subscribedCeremonyId = channelCeremonyId;
        }

        void connect();

        return () => {
            cancelled = true;

            if (channel && subscribedCeremonyId !== null) {
                getEcho()?.leave(`stage-control.${subscribedCeremonyId}`);
            }
        };
    }, [channelCeremonyId, reconcile]);

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

    useEffect(() => {
        if (!confirmation) {
            return undefined;
        }

        confirmationCancelRef.current?.focus();

        const handleConfirmationKeyDown = (event) => {
            if (event.key === 'Escape') {
                event.preventDefault();
                closeConfirmation();

                return;
            }

            if (event.key !== 'Tab') {
                return;
            }

            const focusable = Array.from(
                confirmationDialogRef.current?.querySelectorAll(
                    'button:not(:disabled), [href], input:not(:disabled), select:not(:disabled), textarea:not(:disabled), [tabindex]:not([tabindex="-1"])',
                ) ?? [],
            );

            if (focusable.length === 0) {
                event.preventDefault();

                return;
            }

            const first = focusable[0];
            const last = focusable[focusable.length - 1];

            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        };

        window.addEventListener('keydown', handleConfirmationKeyDown);

        return () =>
            window.removeEventListener('keydown', handleConfirmationKeyDown);
    }, [closeConfirmation, confirmation]);

    async function runCommand(action, url, body = null) {
        if (commandLockRef.current) {
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
                body: body ? JSON.stringify(body) : undefined,
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

    function requestConfirmation(
        action,
        url,
        message,
        body = null,
        options = {},
    ) {
        confirmationTriggerRef.current = document.activeElement;
        setConfirmation({
            action,
            url,
            message,
            body,
            title: options.title ?? 'Confirm exceptional action',
            confirmLabel: options.confirmLabel ?? 'Confirm action',
        });
    }

    const current = control.current;
    const waiting = control.waiting;
    const selected = waiting.find((item) => item.id === selectedId);
    const isHolding = stageMode === 'holding' || control.display_mode === 'holding';
    const stageBusy = Boolean(current);
    const automatic = activeCeremony?.presentation_mode === 'automatic';
    const unavailable = !activeCeremony;

    return (
        <>
            <Head title="Stage control" />
            <OnboardingGuide onboarding={onboarding} standalone />

            <a className="skip-link" href="#stage-control-content">
                Skip to stage controls
            </a>

            <main className="stage-control-page" id="stage-control-content">
                <header className="stage-control-header">
                    <div>
                        <h1>Stage control</h1>
                        <p>
                            {activeCeremony?.name ?? 'No active ceremony'}
                            <span aria-hidden="true"> · </span>
                            <strong>
                                {automatic
                                    ? 'Assisted automatic'
                                    : 'Controlled mode'}
                                {automatic && control.automatic_paused
                                    ? ' paused'
                                    : ''}
                            </strong>
                        </p>
                    </div>
                    <nav aria-label="Stage control links">
                        <OperationCommandMenu />
                        <Link className="secondary-action" href="/stage" target="_blank">
                            Open audience stage
                        </Link>
                        <Link className="secondary-action" href="/dashboard">
                            Return to operations
                        </Link>
                    </nav>
                </header>

                <section
                    className={`stage-control-status${automatic ? ' stage-control-status-automatic' : ''}`}
                    aria-label="Operational status"
                    data-tour="stage-connection"
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
                            {control.audio_status === 'failed'
                                ? 'Playback failed — replay or use the live announcer'
                                : control.audio_status === 'playing'
                                  ? 'Playing on audience display'
                                  : current?.student.audio_status?.stage_label ?? 'No current graduate'}
                        </span>
                    </p>
                    {automatic && (
                        <p className="stage-control-automatic-command">
                            <strong>Assisted automatic</strong>
                            <span>
                                {control.automatic_paused
                                    ? 'Paused — scans wait in queue'
                                    : 'Running — idle scans present'}
                            </span>
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
                                      ? 'Resume assisted automatic'
                                      : 'Pause assisted automatic'}
                            </button>
                        </p>
                    )}
                </section>

                {feedback && (
                    <p
                        className={`stage-control-feedback stage-control-feedback-${feedback.tone}`}
                        role={feedback.tone === 'error' ? 'alert' : 'status'}
                    >
                        {feedback.message}
                    </p>
                )}

                {confirmation && (
                    <section
                        ref={confirmationDialogRef}
                        aria-keyshortcuts="Escape"
                        aria-modal="true"
                        className="stage-control-confirmation"
                        aria-labelledby="stage-confirmation-heading"
                        role="alertdialog"
                    >
                        <div>
                            <strong id="stage-confirmation-heading">
                                {confirmation.title}
                            </strong>
                            <p>{confirmation.message}</p>
                        </div>
                        <div>
                            <button
                                ref={confirmationCancelRef}
                                className="secondary-action"
                                onClick={closeConfirmation}
                                type="button"
                            >
                                Cancel
                            </button>
                            <button
                                className="stage-control-danger-action"
                                onClick={() => {
                                    const command = confirmation;
                                    closeConfirmation();
                                    void runCommand(
                                        command.action,
                                        command.url,
                                        command.body,
                                    );
                                }}
                                type="button"
                            >
                                {confirmation.confirmLabel}
                            </button>
                        </div>
                    </section>
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

                            <div className="stage-control-next">
                                <span>Next in queue</span>
                                {waiting[0] ? (
                                    <strong>
                                        {waiting[0].student.display_name}
                                        <small>{waiting[0].student.program}</small>
                                    </strong>
                                ) : (
                                    <strong>No graduate waiting</strong>
                                )}
                            </div>

                            <div className="stage-control-primary-actions">
                                <p className="stage-control-action-label">
                                    Safe next action
                                </p>
                                {current ? (
                                    <>
                                        {waiting.length > 0 && (
                                            <button
                                                className="primary-action"
                                                disabled={Boolean(pendingAction)}
                                                onClick={() =>
                                                    runCommand(
                                                        'complete-next',
                                                        '/stage/complete-and-present-next',
                                                        {
                                                            expected_version:
                                                                control.version,
                                                        },
                                                    )
                                                }
                                                type="button"
                                            >
                                                {pendingAction === 'complete-next'
                                                    ? 'Completing and presenting…'
                                                    : 'Complete and present next'}
                                            </button>
                                        )}
                                        <button
                                            className={
                                                waiting.length === 0
                                                    ? 'primary-action'
                                                    : 'secondary-action'
                                            }
                                            disabled={Boolean(pendingAction)}
                                            onClick={() =>
                                                runCommand(
                                                    'complete',
                                                    '/stage/complete-current',
                                                )
                                            }
                                            type="button"
                                        >
                                            {pendingAction === 'complete'
                                                ? 'Completing…'
                                                : 'Complete and clear'}
                                        </button>
                                        <button
                                            className="secondary-action"
                                            data-tour="stage-replay"
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
                                        <button
                                            className="stage-control-danger-action"
                                            disabled={Boolean(pendingAction)}
                                            onClick={() =>
                                                requestConfirmation(
                                                    'return-current',
                                                    '/stage/return-current',
                                                    `Correct the current presentation for ${current.student.display_name}? The graduate will return to queue position ${current.queue_position}, audio will stop, and the audience will see the holding screen.`,
                                                    {
                                                        expected_version:
                                                            control.version,
                                                    },
                                                    {
                                                        title: 'Confirm wrong-graduate correction',
                                                        confirmLabel: 'Return graduate to queue',
                                                    },
                                                )
                                            }
                                            type="button"
                                        >
                                            {pendingAction === 'return-current'
                                                ? 'Returning graduate…'
                                                : 'Correct wrong graduate'}
                                        </button>
                                    </>
                                ) : (
                                    <button
                                        className="primary-action"
                                        disabled={
                                            Boolean(pendingAction) ||
                                            waiting.length === 0
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
                                    data-tour="stage-recovery"
                                    disabled={Boolean(pendingAction)}
                                    onClick={() =>
                                        runCommand(
                                            'holding',
                                            isHolding
                                                ? '/stage/holding/deactivate'
                                                : '/stage/holding/activate',
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
                        </section>

                        <section
                            className="stage-control-queue"
                            data-tour="stage-queue"
                            id="queue"
                            aria-labelledby="waiting-queue-heading"
                        >
                            <div className="stage-control-section-heading">
                                <div>
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
                                                        <td className="stage-control-select-cell">
                                                            <label className="stage-control-radio-target">
                                                                <input
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
                                                                <span className="sr-only">
                                                                    Select{' '}
                                                                    {
                                                                        item.student
                                                                            .display_name
                                                                    }
                                                                </span>
                                                            </label>
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
                                                            {item.student.audio_status?.label ?? 'Missing'}
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
                                                Boolean(pendingAction)
                                            }
                                            onClick={() =>
                                                selected.id !== waiting[0]?.id
                                                    ? requestConfirmation(
                                                          'present-selected',
                                                          `/stage/present/${selected.id}`,
                                                          `Present ${selected.student.display_name} ahead of earlier waiting graduates? Queue order will otherwise remain unchanged.`,
                                                          null,
                                                          {
                                                              title: 'Confirm out-of-order presentation',
                                                              confirmLabel: 'Present selected graduate',
                                                          },
                                                      )
                                                    : runCommand(
                                                          'present-selected',
                                                          `/stage/present/${selected.id}`,
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
