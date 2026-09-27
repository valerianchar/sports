/*
 * Service worker de Séance.
 *
 * Parti pris : les pages HTML ne sont jamais mises en cache — les séances
 * changent à chaque modification et un cache survivrait à la déconnexion. Sont
 * conservés : les assets construits (nom empreinté, donc immuables), la
 * coquille hors ligne, et les images d'exercices déjà vues, pour qu'une salle
 * au sous-sol sans réseau n'affiche pas de trous.
 */

const CACHE_VERSION = 'seance-v1';
const IMAGE_CACHE = 'seance-exercices-v1';
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

    // 172 images au plus, ~45 Ko chacune : le catalogue entier tient en 8 Mo.
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
