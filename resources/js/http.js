/**
 * Appels JSON hors Inertia — le journal de fin de séance. Le jeton CSRF est relu
 * dans le cookie XSRF-TOKEN que Laravel pose à chaque réponse.
 */
function csrfToken() {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
}

export class HttpError extends Error {
    constructor(status, message) {
        super(message);
        this.status = status;
    }
}

export async function postJson(url, payload, method = 'POST') {
    const response = await fetch(url, {
        method,
        body: JSON.stringify(payload),
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': csrfToken(),
        },
    });

    if (!response.ok) {
        throw new HttpError(response.status, `Erreur ${response.status}`);
    }

    return response.status === 204 ? null : response.json();
}

export const patchJson = (url, payload) => postJson(url, payload, 'PATCH');

export async function getJson(url) {
    const response = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });

    if (!response.ok) {
        throw new HttpError(response.status, `Erreur ${response.status}`);
    }

    return response.json();
}

/*
 * Un envoi qui doit partir même si la page s'endort aussitôt (l'appli passe en
 * arrière-plan) : `keepalive` laisse le navigateur le terminer.
 */
export function sendKeepalive(url, method, payload = null) {
    return fetch(url, {
        method,
        keepalive: true,
        body: payload === null ? undefined : JSON.stringify(payload),
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': csrfToken(),
        },
    }).catch(() => null);
}
