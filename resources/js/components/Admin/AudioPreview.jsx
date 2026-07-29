import { useRef } from 'react';

export default function AudioPreview({ label, src }) {
    const audioRef = useRef(null);

    function preventOverlap() {
        document.querySelectorAll('audio').forEach((audio) => {
            if (audio !== audioRef.current) {
                audio.pause();
                audio.currentTime = 0;
            }
        });
    }

    if (!src) {
        return <p className="admin-media-missing">No audio file uploaded.</p>;
    }

    return (
        <audio
            aria-label={label}
            className="admin-audio-preview"
            controls
            onPlay={preventOverlap}
            preload="metadata"
            ref={audioRef}
            src={src}
        />
    );
}
