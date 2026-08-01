import { useEffect, useRef, useState } from 'react';
import AppIcon from '../AppIcon';

const MAX_RECORDING_SECONDS = 180;

function recorderFormat() {
    const candidates = [
        ['audio/webm;codecs=opus', 'webm'],
        ['audio/ogg;codecs=opus', 'ogg'],
        ['audio/webm', 'webm'],
    ];

    return candidates.find(([type]) => window.MediaRecorder?.isTypeSupported(type));
}

function timeLabel(seconds) {
    return `${String(Math.floor(seconds / 60)).padStart(2, '0')}:${String(seconds % 60).padStart(2, '0')}`;
}

export default function AnnouncementRecorder({
    disabled,
    graduateName,
    onRecorded,
    onRecordingChange,
}) {
    const [status, setStatus] = useState('idle');
    const [seconds, setSeconds] = useState(0);
    const [error, setError] = useState('');
    const [previewUrl, setPreviewUrl] = useState('');
    const recorderRef = useRef(null);
    const streamRef = useRef(null);
    const chunksRef = useRef([]);
    const format = typeof window === 'undefined' ? null : recorderFormat();
    const supported = Boolean(
        typeof navigator !== 'undefined'
        && navigator.mediaDevices?.getUserMedia
        && format,
    );

    function releaseStream() {
        streamRef.current?.getTracks().forEach((track) => track.stop());
        streamRef.current = null;
    }

    function discard() {
        if (previewUrl) {
            URL.revokeObjectURL(previewUrl);
        }
        setPreviewUrl('');
        setSeconds(0);
        setStatus('idle');
        setError('');
        onRecorded(null);
    }

    async function start() {
        setError('');

        if (!window.isSecureContext) {
            setError('Microphone recording requires the installed HTTPS application or localhost.');
            return;
        }

        if (!supported) {
            setError('This browser does not support local microphone recording.');
            return;
        }

        try {
            onRecordingChange?.(true);
            const stream = await navigator.mediaDevices.getUserMedia({
                audio: {
                    autoGainControl: true,
                    channelCount: 1,
                    echoCancellation: true,
                    noiseSuppression: true,
                },
                video: false,
            });
            onRecorded(null);
            const [mimeType, extension] = format;
            const recorder = new MediaRecorder(stream, { mimeType });
            streamRef.current = stream;
            recorderRef.current = recorder;
            chunksRef.current = [];
            setSeconds(0);

            recorder.addEventListener('dataavailable', (event) => {
                if (event.data.size > 0) {
                    chunksRef.current.push(event.data);
                }
            });
            recorder.addEventListener('stop', () => {
                const blob = new Blob(chunksRef.current, { type: mimeType });
                releaseStream();

                if (blob.size === 0) {
                    setError('No audio was captured. Check the microphone and try again.');
                    setStatus('idle');
                    onRecordingChange?.(false);
                    return;
                }

                if (previewUrl) {
                    URL.revokeObjectURL(previewUrl);
                }

                const nextUrl = URL.createObjectURL(blob);
                const safeName = (graduateName || 'graduate')
                    .replace(/[^a-z0-9]+/gi, '-')
                    .replace(/^-|-$/g, '')
                    .toLowerCase() || 'graduate';
                const file = new File(
                    [blob],
                    `${safeName}-announcement.${extension}`,
                    { type: mimeType, lastModified: Date.now() },
                );
                setPreviewUrl(nextUrl);
                setStatus('recorded');
                onRecordingChange?.(false);
                onRecorded(file);
            }, { once: true });
            recorder.start(500);
            setStatus('recording');
        } catch (exception) {
            releaseStream();
            setStatus('idle');
            onRecordingChange?.(false);
            setError(
                exception?.name === 'NotAllowedError'
                    ? 'Microphone permission was denied. Allow microphone access and try again.'
                    : 'The microphone could not be started. Check the selected input device.',
            );
        }
    }

    function stop() {
        if (recorderRef.current?.state === 'recording') {
            recorderRef.current.stop();
        }
    }

    useEffect(() => {
        if (status !== 'recording') {
            return undefined;
        }

        const timer = window.setInterval(() => {
            setSeconds((current) => {
                const next = current + 1;
                if (next >= MAX_RECORDING_SECONDS) {
                    window.clearInterval(timer);
                    stop();
                }
                return next;
            });
        }, 1000);

        return () => window.clearInterval(timer);
    }, [status]);

    useEffect(() => () => {
        if (recorderRef.current?.state === 'recording') {
            recorderRef.current.stop();
        }
        releaseStream();
        if (previewUrl) {
            URL.revokeObjectURL(previewUrl);
        }
    }, [previewUrl]);

    return (
        <div className="announcement-recorder">
            <div className="announcement-recorder-heading">
                <span>
                    <AppIcon name="audio" size={17} />
                    <strong>Record with microphone</strong>
                </span>
                <small>{status === 'recording' ? `Recording ${timeLabel(seconds)}` : 'Maximum 3 minutes'}</small>
            </div>

            <p className="announcement-recorder-guidance">
                Record, preview, then save the graduate. New audio still requires review and approval before stage playback.
            </p>

            {status === 'recorded' && previewUrl && (
                <>
                    <audio aria-label="Recorded announcement preview" controls preload="metadata" src={previewUrl} />
                    <p className="admin-field-hint">
                        This recording will upload when you save the graduate.
                    </p>
                </>
            )}

            <div className="announcement-recorder-actions">
                {status === 'recording' ? (
                    <button className="admin-button admin-button-danger" onClick={stop} type="button">
                        Stop recording
                    </button>
                ) : (
                    <button
                        className="admin-button admin-button-secondary"
                        disabled={disabled || !supported}
                        onClick={start}
                        type="button"
                    >
                        {status === 'recorded' ? 'Record again' : 'Start recording'}
                    </button>
                )}
                {status === 'recorded' && (
                    <button className="admin-text-action" onClick={discard} type="button">
                        Discard recording
                    </button>
                )}
            </div>

            {status === 'recording' && (
                <p className="announcement-recorder-live" role="status">
                    <span aria-hidden="true" /> Recording from this workstation microphone
                </p>
            )}
            {error && <p className="admin-field-error" role="alert">{error}</p>}
            {!supported && !error && (
                <p className="admin-field-hint">Use current Chrome or Edge to record directly.</p>
            )}
        </div>
    );
}
