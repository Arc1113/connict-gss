import { useEffect, useRef, useState } from 'react';

function AudioPlayer({ label, onPlayableChange, src }) {
    const audioRef = useRef(null);
    const playableChangeRef = useRef(onPlayableChange);
    const [duration, setDuration] = useState(null);
    const [state, setState] = useState('loading');

    useEffect(() => {
        playableChangeRef.current = onPlayableChange;
    }, [onPlayableChange]);

    function preventOverlap() {
        document.querySelectorAll('audio').forEach((audio) => {
            if (audio !== audioRef.current) {
                audio.pause();
                audio.currentTime = 0;
            }
        });
    }

    function markPlayable(event) {
        setDuration(Number.isFinite(event.currentTarget.duration) ? event.currentTarget.duration : null);
        setState('ready');
        playableChangeRef.current?.(true);
    }

    function markUnavailable() {
        setState('error');
        playableChangeRef.current?.(false);
    }

    const status = state === 'error'
        ? 'Audio unavailable. Regenerate or replace this clip before approval.'
        : state === 'playing'
          ? 'Playing preview…'
          : state === 'ready'
            ? `Ready to review${duration ? ` · ${Math.ceil(duration)} seconds` : ''}`
            : 'Checking audio…';

    return (
        <div className={`admin-audio-player admin-audio-player-${state}`}>
            <audio
                aria-label={label}
                className="admin-audio-preview"
                controls
                onCanPlay={markPlayable}
                onEnded={() => setState('ready')}
                onError={markUnavailable}
                onPause={() => state !== 'error' && setState('ready')}
                onPlay={() => {
                    preventOverlap();
                    setState('playing');
                }}
                preload="metadata"
                ref={audioRef}
                src={src}
            />
            <p aria-live="polite" className="admin-audio-player-status" role={state === 'error' ? 'alert' : 'status'}>
                {status}
            </p>
        </div>
    );
}

export default function AudioPreview({ label, onPlayableChange, src }) {
    if (!src) {
        return <p className="admin-media-missing">No audio file uploaded.</p>;
    }

    return (
        <AudioPlayer
            key={src}
            label={label}
            onPlayableChange={onPlayableChange}
            src={src}
        />
    );
}
