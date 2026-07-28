import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

let echoInstance;

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
    });

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
