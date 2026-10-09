/**
 * Mode « comme une vidéo » : la séance devient un média en lecture.
 *
 * iOS ne garde une page web éveillée en arrière-plan, et ne l'affiche sur
 * l'écran verrouillé et dans le centre de contrôle, que si elle joue un vrai
 * son (élément <audio>) ; le Web Audio et les minuteurs s'endorment. Le
 * lecteur joue donc une piste fabriquée ici même : du silence, avec les bips
 * du décompte placés à la seconde près. Elle couvre les étapes chronométrées
 * à venir, jusqu'à la prochaine série en répétitions, puis se tait — sans
 * s'arrêter — le temps de la faire.
 *
 * L'écran verrouillé montre l'exercice, la série, la progression de l'étape
 * et les boutons : pause, « suivant » (série terminée), « précédent ».
 * La contrepartie : iOS ne joue qu'un média à la fois, la musique se met en
 * pause.
 */

const RATE = 8000; // Hz : assez pour des bips jusqu'à 1,5 kHz, léger en mémoire.
const MAX_SECONDS = 20 * 60;
const TAIL_SECONDS = 10 * 60;

let audio = null;
let url = null;
let active = false;

function element() {
    if (!audio) {
        audio = document.createElement('audio');
        audio.setAttribute('playsinline', '');
        audio.preload = 'auto';
        audio.style.display = 'none';
        document.body.appendChild(audio);
    }

    return audio;
}

export const nowPlayingSupported = () => typeof window !== 'undefined' && 'mediaSession' in navigator;

/**
 * Une piste WAV 8 bits mono : `seconds` de silence et des bips (`events` :
 * { at, frequency, duration } en secondes), au volume voulu.
 */
function render(seconds, events, volume) {
    const length = Math.ceil(Math.min(seconds, MAX_SECONDS + TAIL_SECONDS) * RATE);
    const buffer = new ArrayBuffer(44 + length);
    const view = new DataView(buffer);
    const text = (offset, value) => [...value].forEach((char, i) => view.setUint8(offset + i, char.charCodeAt(0)));

    text(0, 'RIFF');
    view.setUint32(4, 36 + length, true);
    text(8, 'WAVE');
    text(12, 'fmt ');
    view.setUint32(16, 16, true);
    view.setUint16(20, 1, true); // PCM
    view.setUint16(22, 1, true); // mono
    view.setUint32(24, RATE, true);
    view.setUint32(28, RATE, true);
    view.setUint16(32, 1, true);
    view.setUint16(34, 8, true);
    text(36, 'data');
    view.setUint32(40, length, true);

    const samples = new Uint8Array(buffer, 44);
    samples.fill(128);
    const amplitude = 120 * Math.max(0.05, Math.min(1, volume));

    for (const { at, frequency, duration } of events) {
        const start = Math.round(at * RATE);
        const count = Math.round(duration * RATE);

        for (let i = 0; i < count && start + i < length; i++) {
            if (start + i < 0) {
                continue;
            }

            // Attaque et extinction courtes : un bip net, sans claquement.
            const envelope = Math.min(1, i / 80, (count - i) / 400);
            samples[start + i] = 128 + Math.round(amplitude * envelope * Math.sin((2 * Math.PI * frequency * i) / RATE));
        }
    }

    return URL.createObjectURL(new Blob([buffer], { type: 'audio/wav' }));
}

/**
 * Prépare l'élément audio pendant un geste (le toucher « Lancer ») : iOS
 * n'autorisera ensuite sa lecture sans geste que s'il a déjà joué.
 */
export function primeNowPlaying() {
    if (!nowPlayingSupported()) {
        return;
    }

    const player = element();

    if (!player.src) {
        player.src = render(0.2, [], 0);
    }

    player.play().catch(() => null);
}

/**
 * Joue une nouvelle piste, depuis maintenant : `timeline` donne sa durée et ses
 * bips. Sans effet si le mode n'est pas actif.
 */
export function playTimeline({ seconds, events }, volume) {
    if (!active) {
        return;
    }

    const player = element();
    const previous = url;

    url = render(Math.max(1, seconds) + TAIL_SECONDS, events, volume);
    player.loop = false;
    player.src = url;
    player.play().catch(() => null);

    if (previous) {
        URL.revokeObjectURL(previous);
    }
}

/**
 * Active le mode : branche les boutons de l'écran verrouillé sur le lecteur.
 *
 * @param {{ play: Function, pause: Function, next: Function, previous: Function, tick: Function }} actions
 */
export function startNowPlaying(actions) {
    if (!nowPlayingSupported()) {
        return false;
    }

    active = true;
    const player = element();

    // En arrière-plan, les minuteurs s'endorment ; le média, lui, avance : il fait tourner le lecteur.
    player.ontimeupdate = () => actions.tick();
    // Fin de piste (série en répétitions très longue) : on relance du silence pour rester affiché.
    player.onended = () => {
        if (active) {
            playTimeline({ seconds: 60, events: [] }, 0);
        }
    };

    const session = navigator.mediaSession;
    const handle = (action, handler) => {
        try {
            session.setActionHandler(action, handler);
        } catch {
            // Action inconnue de ce navigateur.
        }
    };

    handle('play', () => actions.play());
    handle('pause', () => actions.pause());
    handle('nexttrack', () => actions.next());
    handle('previoustrack', () => actions.previous());
    handle('seekbackward', null);
    handle('seekforward', null);

    return true;
}

/** Ce que montre l'écran verrouillé : titre, sous-titre, séance, image. */
export function describe({ title, artist, album, image }) {
    if (!active) {
        return;
    }

    try {
        navigator.mediaSession.metadata = new MediaMetadata({
            title,
            artist,
            album,
            artwork: image ? [{ src: new URL(image, location.origin).href, sizes: '512x512' }] : [],
        });
    } catch {
        // MediaMetadata absent : le titre de la page s'affiche à la place.
    }
}

/** La barre de progression de l'étape en cours ; `null` pour une série qui attend. */
export function progress(duration, position, playing) {
    if (!active) {
        return;
    }

    try {
        navigator.mediaSession.playbackState = playing ? 'playing' : 'paused';

        if (duration > 0) {
            navigator.mediaSession.setPositionState({ duration, position: Math.min(Math.max(0, position), duration - 0.01), playbackRate: 1 });
        } else {
            navigator.mediaSession.setPositionState(null);
        }
    } catch {
        // Position refusée : la barre reste figée, rien de plus.
    }
}

export function pauseNowPlaying() {
    if (active) {
        element().pause();
        progress(0, 0, false);
    }
}

export function stopNowPlaying() {
    if (!active) {
        return;
    }

    active = false;
    const player = element();
    player.ontimeupdate = null;
    player.onended = null;
    player.pause();
    player.removeAttribute('src');
    player.load();

    if (url) {
        URL.revokeObjectURL(url);
        url = null;
    }

    try {
        navigator.mediaSession.metadata = null;
        navigator.mediaSession.playbackState = 'none';
        ['play', 'pause', 'nexttrack', 'previoustrack'].forEach((action) => navigator.mediaSession.setActionHandler(action, null));
    } catch {
        // Rien à défaire.
    }
}

export const nowPlayingActive = () => active;
