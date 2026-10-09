/**
 * Alertes hors de l'appli. Quand le téléphone se verrouille ou qu'on passe à
 * une autre appli, iOS endort la page et ses sons : le lecteur confie alors au
 * serveur les fins de repos à venir, qui arrivent en notifications — avec le
 * son du téléphone, par-dessus la musique. Il les reprend dès qu'on revient.
 *
 * Sur iPhone, les notifications web n'existent que pour l'appli ajoutée à
 * l'écran d'accueil (iOS 16.4+).
 */
import { postJson, sendKeepalive } from './http';

const isIos = () => /iPad|iPhone|iPod/.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
const isStandalone = () => window.matchMedia?.('(display-mode: standalone)').matches || navigator.standalone === true;

/** 'ok', 'install' (iPhone hors écran d'accueil), 'denied' ou 'unsupported'. */
export function pushSupport() {
    if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) {
        return isIos() && !isStandalone() ? 'install' : 'unsupported';
    }

    return Notification.permission === 'denied' ? 'denied' : 'ok';
}

/** Les notifications sont-elles permises ici ? (sans réseau, sans attente) */
export const alertsAllowed = () => pushSupport() === 'ok' && Notification.permission === 'granted';

function registration() {
    // Sans service worker (développement), on n'attend pas indéfiniment.
    return Promise.race([navigator.serviceWorker.ready, new Promise((_, reject) => setTimeout(() => reject(new Error('sw')), 4000))]);
}

function keyBytes(base64) {
    const padded = (base64 + '='.repeat((4 - (base64.length % 4)) % 4)).replace(/-/g, '+').replace(/_/g, '/');

    return Uint8Array.from(atob(padded), (char) => char.charCodeAt(0));
}

export async function currentSubscription() {
    if (pushSupport() !== 'ok') {
        return null;
    }

    try {
        return await (await registration()).pushManager.getSubscription();
    } catch {
        return null;
    }
}

/** Demande la permission (à appeler depuis un toucher), abonne ce téléphone. */
export async function enableAlerts(publicKey) {
    if ((await Notification.requestPermission()) !== 'granted') {
        throw new Error('denied');
    }

    const worker = await registration();
    const subscription = (await worker.pushManager.getSubscription()) ?? (await worker.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: keyBytes(publicKey) }));

    await postJson('/notifications/abonnement', subscription.toJSON());
}

export async function disableAlerts() {
    const subscription = await currentSubscription();

    if (subscription) {
        await postJson('/notifications/abonnement', { endpoint: subscription.endpoint }, 'DELETE').catch(() => null);
        await subscription.unsubscribe();
    }
}

export const testAlert = () => postJson('/notifications/essai', {});

/** Confie au serveur les alertes d'une séance : `in` en secondes à partir de maintenant. */
export const scheduleAlerts = (session, alerts) => sendKeepalive(`/seances/alertes/${session}`, 'PUT', { alerts });

export const cancelAlerts = (session) => sendKeepalive(`/seances/alertes/${session}`, 'DELETE');

/*
 * La séance en cours, dans le centre de notifications : une notification
 * silencieuse, remplacée à chaque mise à jour. iOS ne remplace pas toujours
 * une notification de même étiquette : on ferme donc d'abord les anciennes.
 */
async function closeSessionNotifications(worker) {
    const shown = await worker.getNotifications();
    shown.filter((notification) => notification.tag?.startsWith('seance')).forEach((notification) => notification.close());
}

export async function showStatus(title, body) {
    if (!alertsAllowed()) {
        return;
    }

    try {
        const worker = await registration();
        await closeSessionNotifications(worker);
        await worker.showNotification(title, {
            body,
            tag: 'seance-statut',
            silent: true,
            icon: '/icons/icon-192.png',
            badge: '/icons/icon-192.png',
            data: { url: location.pathname },
        });
    } catch {
        // Pas de notification possible ici : le lecteur suffit.
    }
}

export async function clearStatus() {
    if (!alertsAllowed()) {
        return;
    }

    try {
        await closeSessionNotifications(await registration());
    } catch {
        // Rien à fermer.
    }
}
