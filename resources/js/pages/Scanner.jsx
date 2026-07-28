import { Head, Link } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { observeEchoConnection } from '../lib/echo';
import { requestJson } from '../lib/http';

const INITIAL_FEEDBACK = {
    tone: 'neutral',
    title: 'Ready to scan',
    message: 'Scan a graduate QR code or enter a token manually.',
};

function connectionLabel(state) {
    if (state === 'connected') {
        return 'Live connection ready';
    }

    if (state === 'connecting') {
        return 'Connecting to live updates';
    }

    return 'Live connection recovering';
}

export default function Scanner({ ceremony, ceremonyStatus }) {
    const inputRef = useRef(null);
    const [token, setToken] = useState('');
    const [submitting, setSubmitting] = useState(false);
    const [connectionState, setConnectionState] = useState('connecting');
    const [feedback, setFeedback] = useState(
        ceremony
            ? INITIAL_FEEDBACK
            : {
                  tone: 'rejected',
                  title: 'Scanner unavailable',
                  message:
                      ceremonyStatus === 'multiple_active_ceremonies'
                          ? 'Multiple active ceremonies require administrator correction.'
                          : 'No active ceremony is configured.',
              },
    );
    const [lastAccepted, setLastAccepted] = useState(null);

    useEffect(() => {
        inputRef.current?.focus();

        const refocus = () => inputRef.current?.focus();
        window.addEventListener('focus', refocus);
        const stopObserving = observeEchoConnection(setConnectionState);

        return () => {
            window.removeEventListener('focus', refocus);
            stopObserving();
        };
    }, []);

    async function submitScan(event) {
        event.preventDefault();

        if (submitting || !ceremony) {
            return;
        }

        const submittedToken = token.trim();

        if (!submittedToken) {
            setFeedback({
                tone: 'rejected',
                title: 'Nothing scanned',
                message: 'Scan a QR code or enter a token before submitting.',
            });
            inputRef.current?.focus();

            return;
        }

        setSubmitting(true);
        setFeedback({
            tone: 'neutral',
            title: 'Checking scan',
            message: 'Validating against the active ceremony…',
        });

        try {
            const { data } = await requestJson('/scans', {
                method: 'POST',
                body: JSON.stringify({ token: submittedToken }),
            });

            if (data.status === 'accepted') {
                setFeedback({
                    tone: 'accepted',
                    title: 'Accepted',
                    message: data.broadcast_delivered
                        ? `${data.student.display_name} is now on stage.`
                        : `${data.student.display_name} was saved. The stage will recover from server state.`,
                });
                setLastAccepted(data.student.display_name);
            } else {
                setFeedback({
                    tone: 'rejected',
                    title: data.status.replaceAll('_', ' '),
                    message: data.message,
                });
            }
        } catch {
            setFeedback({
                tone: 'rejected',
                title: 'Server unavailable',
                message: 'The scan was not confirmed. Reconnect and scan again.',
            });
        } finally {
            setToken('');
            setSubmitting(false);
            window.requestAnimationFrame(() => inputRef.current?.focus());
        }
    }

    return (
        <>
            <Head title="Scanner" />

            <main
                className="scanner-page"
                onClick={() => inputRef.current?.focus()}
            >
                <section className="scanner-shell" aria-labelledby="scanner-heading">
                    <header className="scanner-header">
                        <div>
                            <p className="foundation-eyebrow">Scanner station</p>
                            <h1 id="scanner-heading">Graduate check-in</h1>
                            <p>{ceremony?.name ?? 'Ceremony unavailable'}</p>
                        </div>
                        <Link className="secondary-action" href="/dashboard">
                            Dashboard
                        </Link>
                    </header>

                    <div
                        className={`connection-pill connection-${connectionState}`}
                        role="status"
                    >
                        <span aria-hidden="true" />
                        {connectionLabel(connectionState)}
                    </div>

                    <form className="scanner-form" onSubmit={submitScan}>
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
                            maxLength={128}
                            readOnly={submitting || !ceremony}
                            aria-describedby="scanner-help"
                        />
                        <p id="scanner-help">
                            The USB scanner should send Enter automatically.
                        </p>
                        <button
                            className="primary-action scanner-submit"
                            type="submit"
                            disabled={submitting || !ceremony}
                        >
                            {submitting ? 'Checking…' : 'Submit scan'}
                        </button>
                    </form>

                    <section
                        className={`scan-feedback scan-feedback-${feedback.tone}`}
                        aria-live="assertive"
                        aria-atomic="true"
                    >
                        <strong>{feedback.title}</strong>
                        <p>{feedback.message}</p>
                    </section>

                    <dl className="scanner-summary">
                        <div>
                            <dt>Last accepted</dt>
                            <dd>{lastAccepted ?? 'None this session'}</dd>
                        </div>
                        <div>
                            <dt>Input</dt>
                            <dd>{submitting ? 'Locked during request' : 'Ready'}</dd>
                        </div>
                    </dl>
                </section>
            </main>
        </>
    );
}
