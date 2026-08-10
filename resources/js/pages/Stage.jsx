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

const AUDIO_CHECK_URL = 'data:audio/wav;base64,UklGRv////9XQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgATElTVBoAAABJTkZPSVNGVA4AAABMYXZmNjAuMTYuMTAwAGRhdGH/////gIGCgoKBgH9+fn5/gIGCgoKBgH9+fn5/gIGCgoKCgH9+fn5+gIGCgoKCgX9+fn5+f4GCgoKCgX9+fn5+f4CCgoKCgYB+fn5+f4CBgoKCgYB/fn5+f4CBgoKCgYB/fn5+f4CBgoKCgYB/fn5+f4CBgoKCgYB/fn5+foCBgoKCgoB/fn5+fn+BgoKCgoF/fn5+fn+BgoKCgoGAfn5+fn+AgoKCgoGAf35+fn+AgYKCgoGAf35+fn+AgYKCgoGAf35+fn+AgYKCgoGAf35+fn+AgYKCgoGAf35+fn6AgYKCgoKAf35+fn5/gYKCgoKBf35+fn5/gYKCgoKBgH5+fn5/gIKCgoKBgH9+fn5/gIGCgoKBgH9+fn5/gIGCgoKBgH9+fn5/gIGCgoKBgH9+fn5/gIGCgoKCgH9+fn5+gIGCgoKCgX9+fn5+f4GCgoKCgX9+fn5+f4CCgoKCgYB+fn5+f4CBgoKCgYB/fn5+f4CBgoKCgYB/fn5+f4CBgoKCgYB/fn5+f4CBgoKCgYB/fn5+f4CBgoKCgoB/fn5+foCBgoKCgoF/fn5+fn+BgoKCgoF/fn5+fn+AgoKCgoGAfn5+fn+AgYKCgoGAf35+fn+AgYKCgoGAf35+fn+AgYKCgoGAf35+fn+AgYKCgoGAf35+fn+AgYKCgoGAf35+fn6AgYKCgoKAf35+fn5/gYKCgoKBf35+fn5/gYKCgoKBgH5+fn5/gIKCgoKBgH9+fn5/gIGCgoKBgH9+fn5/gIGCgoKBgH9+fn5/gIGCgoKBgH9+fn5+gIGCgoKCgH9+fn5+f4GCgoKCgX9+fn5+f4GCgoKCgYB+fn5+f4CCgoKCgYB/fn5+f4CBgoKCgYB/fn5+f4CBgoKCgYB/fn5+f4CBgoKCgYB/fn5+f4CBgoKCgYB/fn5+f4CBgoKCgoB/fn5+foCBgoKCgoF/fn5+fn+BgoKCgoF/fn5+fn+AgoKCgoGAfn5+fn+AgYKCgoGAf35+fn+AgYKCgoGAf35+fn+AgYKCgoGAf35+fn+AgYKCgoGAf35+fn+AgYKCgoKAf35+fn6AgYKCgoKBf35+fn5/gYKCgoKBf35+fn5/gIKCgoKBgH5+fn5/gIGCgoKBgH9+fn5/gIGCgoKBgH9+fn5/gIGCgoKBgH9+fn5/gIGCgoKBgH9+fn5/gIGCgoKBgH9+fn5+gIGCgoKCgH9+fn5+f4GCgoKCgX9+fn5+f4GC';

const VALID_AUDIO_CHECK_URL = AUDIO_CHECK_URL.includes('////')
    ? 'data:audio/wav;base64,UklGRnQAAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YVAAAACAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgA=='
    : AUDIO_CHECK_URL;

export default function Stage({ ceremony }) {
    const [stageState, setStageState] = useState(IDLE_STATE);
    const [hasLoaded, setHasLoaded] = useState(false);
    const [audienceReady, setAudienceReady] = useState(false);
    const [audioCheckState, setAudioCheckState] = useState('waiting');
    const [connectionState, setConnectionState] = useState('syncing');
    const [channelCeremonyId, setChannelCeremonyId] = useState(
        ceremony?.id ?? null,
    );
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
    const automaticCheckAttemptedRef = useRef(false);

    const stopAudio = useCallback(() => {
        if (audioRef.current) {
            audioRef.current.pause();
            audioRef.current.currentTime = 0;
            audioRef.current.onended = null;
            audioRef.current.onerror = null;
        }
    }, []);

    const reportAudioStatus = useCallback((presentation, status) => {
        if (!presentation?.student_id) {
            return;
        }

        void requestJson('/stage/audio-status', {
            method: 'POST',
            body: JSON.stringify({
                version: presentation.version,
                student_id: presentation.student_id,
                status,
            }),
        });
    }, []);

    const playAudio = useCallback(
        async (presentation) => {
            stopAudio();

            if (!audioEnabledRef.current || !presentation?.audio_url) {
                return;
            }

            const audio = audioRef.current;

            if (!audio) {
                return;
            }

            audio.src = presentation.audio_url;
            audio.preload = 'auto';
            audio.onended = () => reportAudioStatus(presentation, 'stopped');
            audio.onerror = () => reportAudioStatus(presentation, 'failed');

            try {
                await audio.play();
                reportAudioStatus(presentation, 'playing');
            } catch {
                reportAudioStatus(presentation, 'failed');
            }
        },
        [reportAudioStatus, stopAudio],
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

    const runPreflight = useCallback(async () => {
        if (!hasLoaded || connectionState !== 'live' || !audioRef.current) {
            return false;
        }

        const audio = audioRef.current;
        setAudioCheckState('checking');
        audio.src = VALID_AUDIO_CHECK_URL;
        audio.preload = 'auto';
        audio.onended = null;
        audio.onerror = null;

        try {
            await audio.play();
            await new Promise((resolve) => window.setTimeout(resolve, 180));
            audio.pause();
            audio.currentTime = 0;
            audioEnabledRef.current = true;
            setAudioCheckState('ready');
            setAudienceReady(true);

            return true;
        } catch {
            audioEnabledRef.current = false;
            setAudioCheckState('blocked');

            return false;
        }
    }, [connectionState, hasLoaded]);

    const reconcile = useCallback(async (markLoaded = false) => {
        let nextState = stageStateRef.current;

        try {
            const { data } = await requestJson('/stage/state');
            nextState = data.state ?? IDLE_STATE;
            setChannelCeremonyId(data.ceremony?.id ?? null);

            if (data.ceremony?.theme) {
                setTheme({ ...DEFAULT_STAGE_THEME, ...data.ceremony.theme });
            } else {
                setTheme(DEFAULT_STAGE_THEME);
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
                .private(`stage.${channelCeremonyId}`)
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
                        presentation: current.presentation
                            ? { ...current.presentation, version: event.version }
                            : null,
                    }));

                    if (
                        stageStateRef.current.mode === 'graduate' &&
                        stageStateRef.current.presentation?.student_id ===
                            event.student_id
                    ) {
                        void playAudio({
                            ...stageStateRef.current.presentation,
                            version: event.version,
                        });
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

            subscribedCeremonyId = channelCeremonyId;
        }

        void connect();

        return () => {
            cancelled = true;
            stopAudio();

            if (channel && subscribedCeremonyId !== null) {
                getEcho()?.leave(`stage.${subscribedCeremonyId}`);
            }
        };
    }, [channelCeremonyId, playAudio, reconcile, stopAudio]);

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
        if (
            hasLoaded
            && connectionState === 'live'
            && !audienceReady
            && !automaticCheckAttemptedRef.current
        ) {
            automaticCheckAttemptedRef.current = true;
            void runPreflight();
        }
    }, [audienceReady, connectionState, hasLoaded, runPreflight]);

    useEffect(() => {
        if (
            audienceReady
            && stageState.mode === 'graduate'
            && stageState.presentation
        ) {
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
        audienceReady,
    ]);

    const presentation = stageState.presentation;
    const displayName = presentation?.display_name?.trim() || 'Graduate';
    const program = presentation?.program?.trim();
    const major = presentation?.major?.trim();
    const honors = presentation?.honors?.trim();
    const hasStagePhoto = Boolean(presentation?.photo_url && theme.show_photo);
    const stageStatus = !audienceReady
        ? 'preflight'
        : hasLoaded
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
                <audio className="stage-audio-element" ref={audioRef} />
                {!audienceReady ? (
                    <section className="stage-preflight" aria-labelledby="stage-preflight-title">
                        <p className="stage-preflight-kicker">Audience display setup</p>
                        <h1 id="stage-preflight-title">Check the screen before opening the curtain</h1>
                        <p className="stage-preflight-copy">
                            Confirm live updates and speaker output once. Setup controls will disappear before graduates are shown.
                        </p>
                        <dl className="stage-preflight-checks">
                            <div data-ready={hasLoaded ? 'true' : 'false'}>
                                <dt>Stage state</dt>
                                <dd>{hasLoaded ? 'Loaded' : 'Loading'}</dd>
                            </div>
                            <div data-ready={connectionState === 'live' ? 'true' : 'false'}>
                                <dt>Live updates</dt>
                                <dd>{connectionState === 'live' ? 'Connected' : 'Connecting'}</dd>
                            </div>
                            <div data-ready={audioCheckState === 'ready' ? 'true' : 'false'}>
                                <dt>Speaker output</dt>
                                <dd>
                                    {audioCheckState === 'checking'
                                        ? 'Playing check tone'
                                        : audioCheckState === 'blocked'
                                          ? 'Choose the button to allow audio'
                                          : 'Ready to test'}
                                </dd>
                            </div>
                        </dl>
                        <button
                            className="stage-preflight-action"
                            disabled={!hasLoaded || connectionState !== 'live' || audioCheckState === 'checking'}
                            onClick={() => void runPreflight()}
                            type="button"
                        >
                            {audioCheckState === 'checking' ? 'Checking speaker…' : 'Test audio and start display'}
                        </button>
                        <small>Use the stage-control laptop for commands and technical status.</small>
                    </section>
                ) : (
                    <>
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
                    </>
                )}
            </main>
        </>
    );
}
