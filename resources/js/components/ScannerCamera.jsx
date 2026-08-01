import { useCallback, useEffect, useRef, useState } from 'react';
import QrScanner from 'qr-scanner';

const CAMERA_DEVICE_KEY = 'gss.scanner.cameraDeviceId';

const CAMERA_STATES = {
    off: {
        label: 'Camera off',
        detail: 'Start the camera when the operator is ready.',
        tone: 'checking',
    },
    requesting: {
        label: 'Requesting camera',
        detail: 'Approve access on this device.',
        tone: 'checking',
    },
    ready: {
        label: 'Camera ready',
        detail: 'Hold one graduate QR code inside the frame.',
        tone: 'ready',
    },
    paused: {
        label: 'Camera paused',
        detail: 'Return to this screen to continue scanning.',
        tone: 'warning',
    },
    unavailable: {
        label: 'Camera unavailable',
        detail: 'Use USB / keyboard mode or follow the recovery guidance.',
        tone: 'danger',
    },
};

function cameraError(error) {
    if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) {
        return {
            title: 'A trusted HTTPS connection is required',
            message: 'Open the installed HTTPS app after trusting the ceremony server certificate, or switch to USB / keyboard mode.',
        };
    }

    if (error?.name === 'NotAllowedError' || error?.name === 'SecurityError') {
        return {
            title: 'Camera permission is blocked',
            message: 'Allow camera access in this browser’s site settings, then choose Try camera again.',
        };
    }

    if (error?.name === 'NotReadableError' || error?.name === 'AbortError') {
        return {
            title: 'The camera is already in use',
            message: 'Close other camera apps or browser tabs, then choose Try camera again.',
        };
    }

    if (error?.name === 'NotFoundError' || String(error).includes('Camera not found')) {
        return {
            title: 'No available camera was found',
            message: 'Confirm the device has a working camera, or switch to USB / keyboard mode.',
        };
    }

    return {
        title: 'The camera could not start',
        message: 'Check camera permission and close other camera apps, then try again.',
    };
}

function storedCameraPreference() {
    try {
        return window.localStorage.getItem(CAMERA_DEVICE_KEY) || 'environment';
    } catch {
        return 'environment';
    }
}

export default function ScannerCamera({
    canScan,
    decodingBlocked,
    onDecoded,
    onStatusChange,
}) {
    const videoRef = useRef(null);
    const overlayRef = useRef(null);
    const scannerRef = useRef(null);
    const decodedHandlerRef = useRef(onDecoded);
    const mountedRef = useRef(true);
    const [cameraState, setCameraState] = useState('off');
    const [cameraErrorContent, setCameraErrorContent] = useState(null);
    const [cameras, setCameras] = useState([]);
    const [selectedCamera, setSelectedCamera] = useState(storedCameraPreference);
    const [flashAvailable, setFlashAvailable] = useState(false);
    const [flashOn, setFlashOn] = useState(false);

    useEffect(() => {
        decodedHandlerRef.current = onDecoded;
    }, [onDecoded]);

    const publishState = useCallback((state) => {
        setCameraState(state);
        onStatusChange(CAMERA_STATES[state]);
    }, [onStatusChange]);

    const destroyCamera = useCallback((nextState = 'off') => {
        const scanner = scannerRef.current;

        scannerRef.current = null;

        if (scanner) {
            void scanner.turnFlashOff().catch(() => {});
        }

        if (videoRef.current?.srcObject instanceof MediaStream) {
            videoRef.current.srcObject.getTracks().forEach((track) => track.stop());
            videoRef.current.srcObject = null;
        }

        scanner?.destroy();

        setFlashAvailable(false);
        setFlashOn(false);
        publishState(nextState);
    }, [publishState]);

    const refreshCapabilities = useCallback(async (scanner) => {
        const [availableCameras, hasFlash] = await Promise.all([
            QrScanner.listCameras(true).catch(() => []),
            scanner.hasFlash().catch(() => false),
        ]);

        if (!mountedRef.current || scannerRef.current !== scanner) {
            return;
        }

        setCameras(availableCameras);
        setSelectedCamera((current) => {
            if (availableCameras.some((camera) => camera.id === current)) {
                return current;
            }

            const rearCamera = availableCameras.find((camera) =>
                /back|rear|environment/i.test(camera.label),
            );

            return rearCamera?.id ?? availableCameras.at(-1)?.id ?? current;
        });
        setFlashAvailable(hasFlash);
        setFlashOn(scanner.isFlashOn());
    }, []);

    const startCamera = useCallback(async () => {
        if (!canScan || cameraState === 'requesting') {
            return;
        }

        if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) {
            setCameraErrorContent(cameraError());
            publishState('unavailable');
            return;
        }

        publishState('requesting');
        setCameraErrorContent(null);

        try {
            const permissionProbe = await navigator.mediaDevices.getUserMedia({
                audio: false,
                video: { facingMode: { ideal: 'environment' } },
            });
            permissionProbe.getTracks().forEach((track) => track.stop());

            let scanner = scannerRef.current;

            if (!scanner) {
                scanner = new QrScanner(
                    videoRef.current,
                    (result) => decodedHandlerRef.current(result.data),
                    {
                        highlightCodeOutline: true,
                        highlightScanRegion: true,
                        maxScansPerSecond: 10,
                        overlay: overlayRef.current,
                        preferredCamera: selectedCamera || 'environment',
                        returnDetailedScanResult: true,
                    },
                );
                scannerRef.current = scanner;
            }

            await scanner.start();

            if (!mountedRef.current || scannerRef.current !== scanner) {
                scanner.destroy();
                return;
            }

            publishState('ready');
            await refreshCapabilities(scanner);
        } catch (error) {
            destroyCamera('unavailable');
            setCameraErrorContent(cameraError(error));
        }
    }, [cameraState, canScan, destroyCamera, publishState, refreshCapabilities, selectedCamera]);

    async function changeCamera(event) {
        const cameraId = event.target.value;
        const scanner = scannerRef.current;

        setSelectedCamera(cameraId);
        setFlashAvailable(false);
        setFlashOn(false);

        try {
            window.localStorage.setItem(CAMERA_DEVICE_KEY, cameraId);
        } catch {
            // Camera selection still works when storage is unavailable.
        }

        if (!scanner) {
            return;
        }

        publishState('requesting');

        try {
            await scanner.setCamera(cameraId);
            publishState('ready');
            await refreshCapabilities(scanner);
        } catch (error) {
            destroyCamera('unavailable');
            setCameraErrorContent(cameraError(error));
        }
    }

    async function toggleFlash() {
        const scanner = scannerRef.current;

        if (!scanner || !flashAvailable) {
            return;
        }

        try {
            await scanner.toggleFlash();
            setFlashOn(scanner.isFlashOn());
        } catch {
            setFlashAvailable(false);
            setFlashOn(false);
        }
    }

    useEffect(() => {
        mountedRef.current = true;

        const handleVisibilityChange = () => {
            if (scannerRef.current && document.hidden) {
                publishState('paused');
            }
        };

        document.addEventListener('visibilitychange', handleVisibilityChange);

        return () => {
            mountedRef.current = false;
            document.removeEventListener('visibilitychange', handleVisibilityChange);
            destroyCamera('off');
        };
    }, [destroyCamera, publishState, refreshCapabilities]);

    const isRunning = cameraState === 'ready' || cameraState === 'paused';
    const operationalState = decodingBlocked && cameraState === 'ready'
        ? CAMERA_STATES.paused
        : CAMERA_STATES[cameraState];

    return (
        <section className="scanner-camera" aria-labelledby="camera-capture-heading">
            <header className="scanner-camera-heading">
                <div>
                    <p>Phone camera</p>
                    <h2 id="camera-capture-heading">Live QR view</h2>
                </div>
                <span className={`scanner-camera-state scanner-camera-state-${operationalState.tone}`}>
                    {decodingBlocked && cameraState === 'ready'
                        ? 'Scanning paused'
                        : operationalState.label}
                </span>
            </header>

            <div className={`scanner-camera-preview scanner-camera-preview-${cameraState}`}>
                <video
                    aria-label="Live phone camera preview for QR scanning"
                    muted
                    onPlaying={() => {
                        if (scannerRef.current) {
                            publishState('ready');
                        }
                    }}
                    playsInline
                    ref={videoRef}
                />
                <div aria-hidden="true" className="scanner-camera-overlay" ref={overlayRef}>
                    <span className="scanner-camera-target" />
                </div>
                {cameraState === 'off' && (
                    <div className="scanner-camera-placeholder">
                        <strong>Camera is off</strong>
                        <p>The preview starts only after you choose Start camera.</p>
                    </div>
                )}
                {cameraState === 'requesting' && (
                    <div className="scanner-camera-placeholder" role="status">
                        <strong>Starting camera…</strong>
                        <p>Approve access if this device asks for permission.</p>
                    </div>
                )}
            </div>

            <div className="scanner-camera-guidance" aria-live="polite">
                <span className={`status-marker status-marker-${operationalState.tone}`} aria-hidden="true" />
                <span>
                    <strong>{operationalState.label}</strong>
                    <small>
                        {decodingBlocked && cameraState === 'ready'
                            ? 'Resolve the current scan before showing another QR code.'
                            : operationalState.detail}
                    </small>
                </span>
            </div>

            {cameraErrorContent && (
                <div className="scanner-camera-error" role="alert">
                    <strong>{cameraErrorContent.title}</strong>
                    <p>{cameraErrorContent.message}</p>
                </div>
            )}

            <div className="scanner-camera-controls">
                {!isRunning && (
                    <button
                        className="primary-action"
                        disabled={!canScan || cameraState === 'requesting'}
                        onClick={startCamera}
                        type="button"
                    >
                        {cameraState === 'unavailable' ? 'Try camera again' : 'Start camera'}
                    </button>
                )}
                {isRunning && (
                    <button
                        className="secondary-action"
                        onClick={() => destroyCamera('off')}
                        type="button"
                    >
                        Stop camera
                    </button>
                )}
                {cameras.length > 1 && isRunning && (
                    <label className="scanner-camera-select">
                        <span>Camera</span>
                        <select onChange={changeCamera} value={selectedCamera}>
                            {cameras.map((camera, index) => (
                                <option key={camera.id} value={camera.id}>
                                    {camera.label || `Camera ${index + 1}`}
                                </option>
                            ))}
                        </select>
                    </label>
                )}
                {flashAvailable && isRunning && (
                    <button
                        aria-pressed={flashOn}
                        className="secondary-action"
                        onClick={toggleFlash}
                        type="button"
                    >
                        {flashOn ? 'Turn light off' : 'Turn light on'}
                    </button>
                )}
            </div>
        </section>
    );
}
