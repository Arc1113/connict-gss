import { Head } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { getEcho, observeEchoConnection } from '../lib/echo';
import { requestJson } from '../lib/http';
import { DEFAULT_STAGE_THEME } from '../lib/stageTheme';

const IDLE_STATE = {
    version: 0,
    mode: 'idle',
    presentation: null,
};

export default function Stage({ ceremony }) {
    const [stageState, setStageState] = useState(IDLE_STATE);
    const [hasLoaded, setHasLoaded] = useState(false);
    const [audioEnabled, setAudioEnabled] = useState(false);
    const [connectionState, setConnectionState] = useState('syncing');
    const [theme, setTheme] = useState(() => ({
        ...DEFAULT_STAGE_THEME,
        ...(ceremony?.theme ?? {}),
    }));
    const versionRef = useRef(0);
    const audioRef = useRef(null);
    const preloadRef = useRef(null);
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

    const preloadAudio = useCallback((url) => {
        if (preloadRef.current?.src === url) {
            return;
        }

        preloadRef.current = null;

        if (!url) {
            return;
        }

        const audio = new Audio(url);
        audio.preload = 'auto';
        preloadRef.current = audio;
    }, []);

    const enableAudio = useCallback(() => {
        if (audioEnabledRef.current) {
            return;
        }

        audioEnabledRef.current = true;
        setAudioEnabled(true);

        if (stageStateRef.current.presentation?.audio_url) {
            void playAudio(stageStateRef.current.presentation);
        }
    }, [playAudio]);

    const reconcile = useCallback(async (markLoaded = false) => {
        let nextState = stageStateRef.current;

        try {
            const { data } = await requestJson('/stage/state');
            nextState = data.state ?? IDLE_STATE;

            if (data.ceremony?.theme) {
                setTheme({ ...DEFAULT_STAGE_THEME, ...data.ceremony.theme });
            }

            versionRef.current = nextState.version;
            setStageState(nextState);
            preloadAudio(nextState.presentation?.audio_url ?? nextState.preload_audio_url);
        } catch {
            setStageState((current) => current);
        } finally {
            if (markLoaded) {
                setHasLoaded(true);
            }
        }

        return nextState;
    }, [preloadAudio]);

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
                .listen('.audio.replay-requested', (event) => {
                    if (event.version <= versionRef.current) {
                        return;
                    }

                    if (event.version > versionRef.current + 1) {
                        void reconcile();

                        return;
                    }

                    versionRef.current = event.version;
                    setStageState((current) => ({
                        ...current,
                        version: event.version,
                    }));

                    if (
                        stageStateRef.current.mode === 'graduate' &&
                        stageStateRef.current.presentation?.student_id ===
                            event.student_id
                    ) {
                        void playAudio(stageStateRef.current.presentation);
                    }
                })
                .listen('.stage.holding-changed', (event) => {
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
            stopAudio();

            if (channel && ceremony) {
                getEcho()?.leave(`stage.${ceremony.id}`);
            }
        };
    }, [ceremony, playAudio, reconcile, stopAudio]);

    useEffect(() => {
        stageStateRef.current = stageState;
    }, [stageState]);

    useEffect(() => {
        const stopObserving = observeEchoConnection((current) => {
            if (current === 'connected') {
                setConnectionState('live');

                if (reconnectPendingRef.current) {
                    void reconcile();
                }

                hasConnectedRef.current = true;
                reconnectPendingRef.current = false;

                return;
            }

            setConnectionState('syncing');

            if (hasConnectedRef.current) {
                reconnectPendingRef.current = true;
            }
        });

        return stopObserving;
    }, [reconcile]);

    useEffect(() => {
        window.addEventListener('pointerdown', enableAudio, { once: true });
        window.addEventListener('keydown', enableAudio, { once: true });

        return () => {
            window.removeEventListener('pointerdown', enableAudio);
            window.removeEventListener('keydown', enableAudio);
        };
    }, [enableAudio]);

    useEffect(() => {
        if (stageState.mode === 'graduate' && stageState.presentation) {
            preloadAudio(stageState.presentation.audio_url);
            void playAudio(stageState.presentation);
        } else {
            stopAudio();
            preloadAudio(stageState.preload_audio_url);
        }
    }, [
        playAudio,
        preloadAudio,
        stageState.mode,
        stageState.preload_audio_url,
        stageState.presentation,
        stopAudio,
    ]);

    const presentation = stageState.presentation;
    const displayName = presentation?.display_name?.trim() || 'Graduate';
    const program = presentation?.program?.trim();
    const major = presentation?.major?.trim();
    const honors = presentation?.honors?.trim();
    const hasStagePhoto = Boolean(presentation?.photo_url && theme.show_photo);
    const stageStatus = hasLoaded
        ? stageState.mode === 'holding'
            ? 'holding'
            : presentation
              ? 'presented'
              : 'idle'
        : 'loading';
    const stageStyle = {
        '--stage-theme-accent': theme.accent_color,
        '--stage-theme-background': theme.background_color,
        '--stage-theme-surface': theme.surface_color,
        '--stage-theme-text': theme.text_color,
        backgroundImage: theme.background_url
            ? `linear-gradient(rgb(0 0 0 / 0.46), rgb(0 0 0 / 0.46)), url(${theme.background_url})`
            : undefined,
    };

    return (
        <>
            <Head title="Ceremony Stage" />

            <main
                className="stage-page"
                data-has-photo={hasStagePhoto}
                data-stage-layout={theme.layout}
                data-stage-preset={theme.preset}
                data-stage-status={stageStatus}
                style={stageStyle}
            >
                {!audioEnabled && (
                    <div className="stage-audio-setup" role="status">
                        <span>
                            <strong>Audio setup required</strong>
                            <small>One time on this display</small>
                        </span>
                        <button
                            className="stage-audio-enable"
                            onClick={enableAudio}
                            type="button"
                        >
                            Enable audio
                        </button>
                    </div>
                )}
                <div
                    aria-live="polite"
                    className={`stage-connection-status stage-connection-status-${connectionState}`}
                    role="status"
                >
                    <span aria-hidden="true" />
                    {connectionState === 'live' ? 'Live' : 'Syncing'}
                </div>
                {theme.logo_url && (
                    <img className="stage-theme-logo" src={theme.logo_url} alt="" />
                )}
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
                ) : stageState.mode === 'holding' ? (
                    <section
                        className="stage-idle stage-holding"
                        aria-labelledby="stage-holding-title"
                    >
                        <p>{ceremony?.name ?? 'Graduation Ceremony'}</p>
                        <h1 id="stage-holding-title">Ceremony program continues</h1>
                        <span>Please remain seated</span>
                    </section>
                ) : presentation ? (
                    <section
                        className="stage-presentation"
                        aria-labelledby="graduate-name"
                    >
                        {hasStagePhoto && (
                            <img
                                className="stage-photo"
                                src={presentation.photo_url}
                                alt={`Portrait of ${displayName}`}
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
