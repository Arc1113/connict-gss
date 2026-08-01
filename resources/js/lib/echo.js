import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

let echoInstance;
let recoveryTimer;
let recoveryAttempt = 0;
let recoveryActive = false;
let recoveryListenersBound = false;

const RECOVERABLE_STATES = new Set(['disconnected', 'failed', 'unavailable']);
const MAX_RECOVERY_DELAY = 8000;

function stopRecovery() {
    if (recoveryTimer) {
        window.clearTimeout(recoveryTimer);
        recoveryTimer = undefined;
    }

    recoveryAttempt = 0;
    recoveryActive = false;
}

function scheduleRecovery(immediate = false) {
    if (
        !recoveryActive
        || recoveryTimer
        || !echoInstance
        || navigator.onLine === false
    ) {
        return;
    }

    const pusher = echoInstance.connector.pusher;

    if (pusher.connection.state === 'connected') {
        stopRecovery();

        return;
    }

    const delay = immediate
        ? 0
        : Math.min(1000 * (2 ** recoveryAttempt), MAX_RECOVERY_DELAY);

    recoveryTimer = window.setTimeout(() => {
        recoveryTimer = undefined;

        if (pusher.connection.state === 'connected') {
            stopRecovery();

            return;
        }

        recoveryAttempt += 1;
        pusher.disconnect();
        pusher.connect();
        scheduleRecovery();
    }, delay);
}

function handleRecoveryStateChange({ current }) {
    if (current === 'connected') {
        stopRecovery();

        return;
    }

    if (RECOVERABLE_STATES.has(current)) {
        recoveryActive = true;
    }

    if (recoveryActive) {
        scheduleRecovery();
    }
}

function startRecoveryPolicy() {
    if (recoveryListenersBound || !echoInstance) {
        return;
    }

    recoveryListenersBound = true;
    echoInstance.connector.pusher.connection.bind(
        'state_change',
        handleRecoveryStateChange,
    );

    window.addEventListener('online', () => {
        if (echoInstance.connector.pusher.connection.state !== 'connected') {
            recoveryActive = true;
            scheduleRecovery(true);
        }
    });
    document.addEventListener('visibilitychange', () => {
        if (
            document.visibilityState === 'visible'
            && echoInstance.connector.pusher.connection.state !== 'connected'
        ) {
            recoveryActive = true;
            scheduleRecovery(true);
        }
    });
}

export function getEcho() {
    if (echoInstance) {
        return echoInstance;
    }

    const key = import.meta.env.VITE_REVERB_APP_KEY;

    if (!key) {
        return null;
    }

    const scheme =
        import.meta.env.VITE_REVERB_SCHEME ||
        (window.location.protocol === 'https:' ? 'https' : 'http');
    const host =
        import.meta.env.VITE_REVERB_HOST?.trim() || window.location.hostname;
    const configuredPort = Number.parseInt(
        import.meta.env.VITE_REVERB_PORT,
        10,
    );
    const port = Number.isNaN(configuredPort)
        ? scheme === 'https'
            ? 443
            : 80
        : configuredPort;

    window.Pusher = Pusher;
    echoInstance = new Echo({
        broadcaster: 'reverb',
        key,
        wsHost: host,
        wsPort: port,
        wssPort: port,
        forceTLS: scheme === 'https',
        enabledTransports: ['ws', 'wss'],
        authEndpoint: '/broadcasting/auth',
        disableStats: true,
        unavailableTimeout: 5000,
    });
    startRecoveryPolicy();

    return echoInstance;
}

export function observeEchoConnection(callback) {
    const echo = getEcho();

    if (!echo) {
        callback('unavailable');

        return () => {};
    }

    const connection = echo.connector.pusher.connection;
    const handleStateChange = ({ current }) => callback(current);

    callback(connection.state);
    connection.bind('state_change', handleStateChange);

    return () => {
        connection.unbind('state_change', handleStateChange);
    };
}
