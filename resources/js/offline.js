/**
 * Hors ligne : le service worker garde les pages de l'accueil, de la liste et
 * du lecteur de chaque séance déjà visitées. On le fait aussi d'avance, en
 * arrière-plan, pour toutes les séances — au plus une fois par heure — afin
 * qu'une séance jamais ouverte se lance quand même au sous-sol sans réseau.
 */
const WARMED_KEY = 'seance.offline.warmed';
const EVERY = 60 * 60 * 1000;

export function warmOfflinePages(urls, version) {
    if (!('serviceWorker' in navigator) || !navigator.serviceWorker.controller || !navigator.onLine) {
        return;
    }

    try {
        if (Date.now() - Number(localStorage.getItem(WARMED_KEY) ?? 0) < EVERY) {
            return;
        }

        localStorage.setItem(WARMED_KEY, String(Date.now()));
    } catch {
        return;
    }

    // Les deux formes de chaque page : le HTML (ouverture à froid) et le JSON Inertia (navigation).
    const load = (url) =>
        Promise.allSettled([
            fetch(url, { credentials: 'same-origin' }),
            fetch(url, { credentials: 'same-origin', headers: { 'X-Inertia': 'true', 'X-Inertia-Version': version ?? '', 'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html, application/xhtml+xml' } }),
        ]);

    // L'une après l'autre, quand le navigateur souffle : pas d'à-coups pendant qu'on navigue.
    const queue = [...urls];
    const next = () => {
        const url = queue.shift();

        if (url) {
            load(url).finally(() => (window.requestIdleCallback ?? setTimeout)(next));
        }
    };

    next();
}

/** À la déconnexion : les pages gardées appartiennent au compte. */
export function forgetOfflinePages() {
    navigator.serviceWorker?.controller?.postMessage('logout');

    try {
        localStorage.removeItem(WARMED_KEY);
    } catch {
        // Rien à oublier.
    }
}
