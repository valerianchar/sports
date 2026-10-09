/*
 * Service worker de Séance.
 *
 * Parti pris : les pages HTML ne sont jamais mises en cache — les séances
 * changent à chaque modification et un cache survivrait à la déconnexion. Sont
 * conservés : les assets construits (nom empreinté, donc immuables), la
 * coquille hors ligne, et les images d'exercices déjà vues, pour qu'une salle
 * au sous-sol sans réseau n'affiche pas de trous.
 */

const CACHE_VERSION = 'seance-v3';
const IMAGE_CACHE = 'seance-exercices-v2';
const OFFLINE_PAGE = '/offline.html';

const SHELL_ASSETS = [
    OFFLINE_PAGE,
    '/manifest.webmanifest',
    '/icons/icon.svg',
    '/icons/icon-192.png',
    '/icons/icon-512.png',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_VERSION)
            .then((cache) => cache.addAll(SHELL_ASSETS))
            .then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((names) => Promise.all(
                names.filter((name) => name !== CACHE_VERSION && name !== IMAGE_CACHE).map((name) => caches.delete(name)),
            ))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);

    if (request.method !== 'GET' || url.origin !== self.location.origin) {
        return;
    }

    // Les visites Inertia attendent du JSON : leur répondre du HTML en cache
    // casserait la navigation. Elles restent donc strictement en ligne.
    if (request.headers.get('X-Inertia')) {
        return;
    }

    if (url.pathname.startsWith('/build/')) {
        event.respondWith(cacheFirst(request, CACHE_VERSION));

        return;
    }

    // ~730 images, ~40 Ko en moyenne : le catalogue entier tient en 30 Mo, et
    // seules les images réellement vues sont gardées.
    if (url.pathname.startsWith('/images/exercices/') && /\.(jpg|svg)$/.test(url.pathname)) {
        event.respondWith(cacheFirst(request, IMAGE_CACHE));

        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(networkThenOfflinePage(request));
    }
});

async function cacheFirst(request, cacheName) {
    const cache = await caches.open(cacheName);
    const cached = await cache.match(request);

    if (cached) {
        return cached;
    }

    const response = await fetch(request);

    if (response.ok) {
        cache.put(request, response.clone());
    }

    return response;
}

async function networkThenOfflinePage(request) {
    try {
        return await fetch(request);
    } catch {
        const cache = await caches.open(CACHE_VERSION);

        return (
            (await cache.match(OFFLINE_PAGE)) ??
            new Response('Hors ligne', { status: 503, headers: { 'Content-Type': 'text/plain; charset=utf-8' } })
        );
    }
}

/*
 * Alertes de séance envoyées par le serveur quand l'appli dort en
 * arrière-plan : fin d'un repos, reprise de l'effort. Le son est celui des
 * notifications du téléphone ; une alerte remplace la précédente de la même
 * séance (même étiquette) au lieu de s'empiler.
 */
self.addEventListener('push', (event) => {
    let data = {};

    try {
        data = event.data ? event.data.json() : {};
    } catch {
        data = { title: 'Séance', body: event.data?.text() };
    }

    // iOS ne remplace pas toujours une notification de même étiquette : on ferme
    // d'abord celles de la séance, pour n'en garder qu'une, à jour.
    event.waitUntil(
        self.registration.getNotifications()
            .then((shown) => shown.filter((notification) => notification.tag?.startsWith('seance')).forEach((notification) => notification.close()))
            .then(() => self.registration.showNotification(data.title || 'Séance', {
            body: data.body || '',
            tag: data.tag || 'seance',
            renotify: true,
            silent: false,
            vibrate: [200, 80, 200],
            icon: '/icons/icon-192.png',
            badge: '/icons/icon-192.png',
            data: { url: data.url || '/' },
        })),
    );
});

// Toucher l'alerte ramène dans l'appli — dans la fenêtre déjà ouverte si possible.
self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windows) => {
            const open = windows.find((client) => 'focus' in client);

            return open ? open.focus() : self.clients.openWindow(event.notification.data?.url || '/');
        }),
    );
});
