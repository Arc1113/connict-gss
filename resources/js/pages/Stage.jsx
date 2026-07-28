import { Head } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { getEcho, observeEchoConnection } from '../lib/echo';
import { requestJson } from '../lib/http';

const IDLE_STATE = {
    version: 0,
    mode: 'idle',
    presentation: null,
};

function liveLabel(state) {
    return state === 'connected' ? 'Live' : 'Syncing';
}

export default function Stage({ ceremony }) {
    const [stageState, setStageState] = useState(IDLE_STATE);
    const [connectionState, setConnectionState] = useState('connecting');
    const [audioEnabled, setAudioEnabled] = useState(false);
    const versionRef = useRef(0);
    const audioRef = useRef(null);
    const previousConnectionRef = useRef('connecting');

    const stopAudio = useCallback(() => {
        if (audioRef.current) {
            audioRef.current.pause();
            audioRef.current.currentTime = 0;
            audioRef.current = null;
        }
    }, []);

    const playAudio = useCallback(
        async (presentation) => {
            stopAudio();

            if (!audioEnabled || !presentation?.audio_url) {
                return;
            }

            const audio = new Audio(presentation.audio_url);
            audio.preload = 'auto';
            audioRef.current = audio;

            try {
                await audio.play();
            } catch {
                audioRef.current = null;
            }
        },
        [audioEnabled, stopAudio],
    );

    const reconcile = useCallback(async () => {
        try {
            const { data } = await requestJson('/stage/state');
            const nextState = data.state ?? IDLE_STATE;

            versionRef.current = nextState.version;
            setStageState(nextState);
        } catch {
            setStageState((current) => current);
        }
    }, []);

    useEffect(() => {
        let cancelled = false;
        let channel;

        async function connect() {
            await reconcile();

            if (cancelled || !ceremony) {
                return;
            }

            const echo = getEcho();

            if (!echo) {
                setConnectionState('unavailable');

                return;
            }

            channel = echo
                .private(`stage.${ceremony.id}`)
                .listen('.student.presented', (event) => {
                    if (event.version <= versionRef.current) {
                        return;
                    }

                    if (event.version > versionRef.current + 1) {
                        void reconcile();

                        return;
                    }

                    versionRef.current = event.version;
                    setStageState({
                        version: event.version,
                        mode: 'graduate',
                        presentation: event,
                    });
                })
                .listen('.stage.cleared', (event) => {
                    if (event.version <= versionRef.current) {
                        return;
                    }

                    if (event.version > versionRef.current + 1) {
                        void reconcile();

                        return;
                    }

                    versionRef.current = event.version;
                    setStageState({
                        version: event.version,
                        mode: 'idle',
                        presentation: null,
                    });
                })
                .subscribed(() => {
                    void reconcile();
                });
        }

        void connect();

        return () => {
            cancelled = true;
            stopAudio();

            if (channel && ceremony) {
                getEcho()?.leave(`stage.${ceremony.id}`);
            }
        };
    }, [ceremony, reconcile, stopAudio]);

    useEffect(() => {
        const stopObserving = observeEchoConnection((current) => {
            const previous = previousConnectionRef.current;
            previousConnectionRef.current = current;
            setConnectionState(current);

            if (current === 'connected' && previous !== 'connecting') {
                void reconcile();
            }
        });

        return stopObserving;
    }, [reconcile]);

    useEffect(() => {
        if (stageState.presentation) {
            void playAudio(stageState.presentation);
        } else {
            stopAudio();
        }
    }, [playAudio, stageState.presentation, stopAudio]);

    async function enableAudio() {
        setAudioEnabled(true);

        if (stageState.presentation?.audio_url) {
            stopAudio();
            const audio = new Audio(stageState.presentation.audio_url);
            audio.preload = 'auto';
            audioRef.current = audio;

            try {
                await audio.play();
            } catch {
                audioRef.current = null;
            }
        }
    }

    const presentation = stageState.presentation;

    return (
        <>
            <Head title="Ceremony Stage" />

            <main className="stage-page">
                <div className="stage-status" aria-live="polite">
                    <span
                        className={
                            connectionState === 'connected'
                                ? 'stage-live-dot'
                                : 'stage-sync-dot'
                        }
                        aria-hidden="true"
                    />
                    {liveLabel(connectionState)}
                </div>

                {!audioEnabled && (
                    <button
                        className="stage-audio-enable"
                        type="button"
                        onClick={enableAudio}
                    >
                        Enable ceremony audio
                    </button>
                )}

                {presentation ? (
                    <section
                        className="stage-presentation"
                        aria-labelledby="graduate-name"
                    >
                        {presentation.photo_url && (
                            <img
                                className="stage-photo"
                                src={presentation.photo_url}
                                alt=""
                            />
                        )}
                        <p className="stage-ceremony-name">
                            {ceremony?.name ?? 'Graduation Ceremony'}
                        </p>
                        <h1 id="graduate-name">{presentation.display_name}</h1>
                        {presentation.program && (
                            <p className="stage-program">
                                {presentation.program}
                            </p>
                        )}
                        {presentation.major && (
                            <p className="stage-major">
                                Major in {presentation.major}
                            </p>
                        )}
                        {presentation.honors && (
                            <p className="stage-honors">
                                {presentation.honors}
                            </p>
                        )}
                    </section>
                ) : (
                    <section className="stage-idle" aria-labelledby="stage-title">
                        <p>{ceremony?.name ?? 'Graduation Ceremony'}</p>
                        <h1 id="stage-title">Welcome, graduates</h1>
                        <span>Awaiting the next presentation</span>
                    </section>
                )}
            </main>
        </>
    );
}
