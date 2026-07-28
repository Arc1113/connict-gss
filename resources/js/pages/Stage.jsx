import { Head } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { getEcho, observeEchoConnection } from '../lib/echo';
import { requestJson } from '../lib/http';

const IDLE_STATE = {
    version: 0,
    mode: 'idle',
    presentation: null,
};

export default function Stage({ ceremony }) {
    const [stageState, setStageState] = useState(IDLE_STATE);
    const [hasLoaded, setHasLoaded] = useState(false);
    const versionRef = useRef(0);
    const audioRef = useRef(null);
    const audioEnabledRef = useRef(false);
    const stageStateRef = useRef(IDLE_STATE);
    const hasConnectedRef = useRef(false);
    const reconnectPendingRef = useRef(false);

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

            if (!audioEnabledRef.current || !presentation?.audio_url) {
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
        [stopAudio],
    );

    const reconcile = useCallback(async (markLoaded = false) => {
        try {
            const { data } = await requestJson('/stage/state');
            const nextState = data.state ?? IDLE_STATE;

            versionRef.current = nextState.version;
            setStageState(nextState);
        } catch {
            setStageState((current) => current);
        } finally {
            if (markLoaded) {
                setHasLoaded(true);
            }
        }
    }, []);

    useEffect(() => {
        let cancelled = false;
        let channel;

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
        stageStateRef.current = stageState;
    }, [stageState]);

    useEffect(() => {
        const stopObserving = observeEchoConnection((current) => {
            if (current === 'connected') {
                if (reconnectPendingRef.current) {
                    void reconcile();
                }

                hasConnectedRef.current = true;
                reconnectPendingRef.current = false;

                return;
            }

            if (hasConnectedRef.current) {
                reconnectPendingRef.current = true;
            }
        });

        return stopObserving;
    }, [reconcile]);

    useEffect(() => {
        const enableAudio = () => {
            if (audioEnabledRef.current) {
                return;
            }

            audioEnabledRef.current = true;

            if (stageStateRef.current.presentation?.audio_url) {
                void playAudio(stageStateRef.current.presentation);
            }
        };

        window.addEventListener('pointerdown', enableAudio, { once: true });
        window.addEventListener('keydown', enableAudio, { once: true });

        return () => {
            window.removeEventListener('pointerdown', enableAudio);
            window.removeEventListener('keydown', enableAudio);
        };
    }, [playAudio]);

    useEffect(() => {
        if (stageState.presentation) {
            void playAudio(stageState.presentation);
        } else {
            stopAudio();
        }
    }, [playAudio, stageState.presentation, stopAudio]);

    const presentation = stageState.presentation;
    const displayName = presentation?.display_name?.trim() || 'Graduate';
    const program = presentation?.program?.trim();
    const major = presentation?.major?.trim();
    const honors = presentation?.honors?.trim();
    const stageStatus = hasLoaded ? (presentation ? 'presented' : 'idle') : 'loading';

    return (
        <>
            <Head title="Ceremony Stage" />

            <main className="stage-page" data-stage-status={stageStatus}>
                {!hasLoaded ? (
                    <section
                        className="stage-loading"
                        role="status"
                        aria-live="polite"
                        aria-labelledby="stage-loading-title"
                    >
                        <p>{ceremony?.name ?? 'Graduation Ceremony'}</p>
                        <h1 id="stage-loading-title">Preparing ceremony display</h1>
                    </section>
                ) : presentation ? (
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
                        <h1 id="graduate-name">{displayName}</h1>
                        {program && (
                            <p className="stage-program">{program}</p>
                        )}
                        {major && (
                            <p className="stage-major">Major in {major}</p>
                        )}
                        {honors && (
                            <p className="stage-honors">{honors}</p>
                        )}
                    </section>
                ) : (
                    <section className="stage-idle" aria-labelledby="stage-title">
                        <p>{ceremony?.name ?? 'Graduation Ceremony'}</p>
                        <h1 id="stage-title">Welcome, graduates</h1>
                        <span>Awaiting the next graduate</span>
                    </section>
                )}
            </main>
        </>
    );
}
