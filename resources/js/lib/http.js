export function csrfToken() {
    return document
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute('content');
}

export async function requestJson(url, options = {}) {
    const response = await fetch(url, {
        credentials: 'same-origin',
        ...options,
        headers: {
            Accept: 'application/json',
            ...(options.body ? { 'Content-Type': 'application/json' } : {}),
            ...(options.method && options.method !== 'GET'
                ? { 'X-CSRF-TOKEN': csrfToken() ?? '' }
                : {}),
            ...options.headers,
        },
    });

    const data = await response.json().catch(() => ({
        status: 'request_failed',
        message: 'The server returned an unreadable response.',
    }));

    return { response, data };
}
