/**
 * Bips, voix et vibrations du lecteur.
 *
 * iOS n'autorise le son qu'à partir d'un geste : le contexte audio est donc
 * créé — ou réveillé — au toucher « Lancer », puis réutilisé par tout le
 * lecteur. Inertia ne recharge pas la page : le contexte survit à la navigation.
 *
 * Les sons du compte à rebours sont synthétisés ici même — rien à télécharger,
 * rien sous droits — sauf « Mon son », le fichier envoyé par l'utilisateur.
 */
let context = null;
let customBuffer = null;
let customUrl = null;
let mode = 'melange';

/*
 * La place du son de l'appli face à la musique (API Audio Session, Safari
 * 16.4+). « transient » : des sons brefs qui se mêlent à la musique sans
 * l'arrêter — l'iPhone les tait en mode silencieux. « playback » : ils sonnent
 * même en silencieux, mais l'iPhone met la musique en pause. Une page web ne
 * peut pas demander mieux : baisser la musique sous le bip reste l'affaire
 * d'iOS.
 */
function applySession() {
    try {
        if (navigator.audioSession) {
            navigator.audioSession.type = mode === 'prioritaire' ? 'playback' : 'transient';
        }
    } catch {
        // Navigateur sans Audio Session : il décide seul.
    }
}

/** « melange » (par défaut) ou « prioritaire » — le réglage du compte. */
export function setAudioMode(value) {
    mode = value === 'prioritaire' ? 'prioritaire' : 'melange';
    applySession();
}

function ensureContext() {
    context ??= new (window.AudioContext || window.webkitAudioContext)();

    return context;
}

export function unlockAudio() {
    try {
        // Déclarée à chaque réveil : c'est elle qui décide si la musique continue.
        applySession();

        ensureContext();

        if (context.state === 'suspended') {
            context.resume();
        }

        // La synthèse vocale d'iOS ne parle qu'après une première phrase dite
        // pendant un geste : une phrase vide suffit à l'éveiller.
        if (window.speechSynthesis && !window.speechSynthesis.speaking) {
            window.speechSynthesis.speak(new SpeechSynthesisUtterance(''));
        }
    } catch {
        // Pas de Web Audio : le lecteur reste muet, rien de plus.
    }
}

/** Une note : `type` d'onde, montée éclair puis extinction en `duration`. */
function tone(frequency, { at = 0, duration = 0.15, volume = 0.8, type = 'sine', vibrato = 0 } = {}) {
    const now = context.currentTime + at;
    const oscillator = context.createOscillator();
    const gain = context.createGain();

    oscillator.type = type;
    oscillator.frequency.value = frequency;

    // Un sifflet d'arbitre trille : la fréquence oscille rapidement autour de la note.
    if (vibrato) {
        const lfo = context.createOscillator();
        const depth = context.createGain();
        lfo.frequency.value = 28;
        depth.gain.value = vibrato;
        lfo.connect(depth);
        depth.connect(oscillator.frequency);
        lfo.start(now);
        lfo.stop(now + duration + 0.05);
    }

    gain.gain.setValueAtTime(0.0001, now);
    gain.gain.exponentialRampToValueAtTime(Math.max(0.0002, Math.min(1, volume)), now + 0.01);
    gain.gain.exponentialRampToValueAtTime(0.0001, now + duration);
    oscillator.connect(gain);
    gain.connect(context.destination);
    oscillator.start(now);
    oscillator.stop(now + duration + 0.05);
}

/** Une cloche : trois partiels inharmoniques qui s'éteignent lentement. */
function bell(frequency, { duration = 1.1, volume = 0.8 } = {}) {
    [[1, 1], [2.76, 0.45], [5.4, 0.2]].forEach(([ratio, level]) =>
        tone(frequency * ratio, { duration: duration / ratio ** 0.4, volume: volume * level }),
    );
}

/** Un claquement : une bouffée de bruit filtrée. */
function clap(centre, { at = 0, duration = 0.05, volume = 0.8 } = {}) {
    const now = context.currentTime + at;
    const length = Math.ceil(context.sampleRate * duration);
    const buffer = context.createBuffer(1, length, context.sampleRate);
    const data = buffer.getChannelData(0);

    for (let i = 0; i < length; i++) {
        data[i] = (Math.random() * 2 - 1) * (1 - i / length);
    }

    const source = context.createBufferSource();
    const filter = context.createBiquadFilter();
    const gain = context.createGain();
    source.buffer = buffer;
    filter.type = 'bandpass';
    filter.frequency.value = centre;
    filter.Q.value = 1.2;
    gain.gain.value = Math.min(1, volume * 1.6);
    source.connect(filter);
    filter.connect(gain);
    gain.connect(context.destination);
    source.start(now);
}

function speak(text, volume) {
    if (!window.speechSynthesis) {
        return;
    }

    const utterance = new SpeechSynthesisUtterance(text);
    utterance.lang = 'fr-FR';
    utterance.rate = 1.2;
    utterance.volume = Math.min(1, volume);
    window.speechSynthesis.cancel();
    window.speechSynthesis.speak(utterance);
}

function playBuffer(buffer, volume) {
    const source = context.createBufferSource();
    const gain = context.createGain();
    source.buffer = buffer;
    gain.gain.value = Math.min(1, volume);
    source.connect(gain);
    gain.connect(context.destination);
    source.start();
}

/** Charge et décode le son personnel ; sans effet s'il est déjà prêt. */
export async function loadCustomSound(url) {
    if (!url || url === customUrl) {
        return;
    }

    try {
        ensureContext();
        const response = await fetch(url, { credentials: 'same-origin' });
        const data = await response.arrayBuffer();
        customBuffer = await new Promise((resolve, reject) => context.decodeAudioData(data, resolve, reject));
        customUrl = url;
    } catch {
        // Fichier illisible par ce navigateur : on retombera sur le bip.
        customBuffer = null;
        customUrl = null;
    }
}

export function beep(frequency, duration = 0.15, volume = 0.8, type = 'sine') {
    if (!context || volume <= 0) {
        return;
    }

    try {
        tone(frequency, { duration, volume, type });
    } catch {
        // Un bip manqué ne doit jamais interrompre la séance.
    }
}

/**
 * Une seconde du compte à rebours de fin de repos. Le dernier son, plus aigu
 * ou plus long, annonce la reprise. Un son personnel court (moins d'une
 * seconde) sonne à chaque seconde ; un plus long — une phrase enregistrée —,
 * une seule fois, au début du décompte.
 */
export function countdownSound(sound, secondsLeft, volume, { first = false } = {}) {
    if (!context || volume <= 0) {
        return;
    }

    const last = secondsLeft === 1;

    try {
        switch (sound) {
            case 'double':
                tone(1000, { duration: 0.06, volume, type: 'square' });
                tone(last ? 1500 : 1000, { at: 0.12, duration: last ? 0.3 : 0.06, volume, type: 'square' });
                break;
            case 'cloche':
                bell(last ? 1568 : 1046, { duration: last ? 1.6 : 0.9, volume });
                break;
            case 'sifflet':
                tone(last ? 2600 : 2400, { duration: last ? 0.55 : 0.12, volume: volume * 0.7, vibrato: 140 });
                break;
            case 'claquement':
                clap(last ? 1000 : 2600, { duration: last ? 0.09 : 0.04, volume });

                if (last) {
                    clap(1000, { at: 0.14, duration: 0.09, volume });
                }

                break;
            case 'voix':
                speak(String(secondsLeft), volume);
                break;
            case 'perso':
                if (customBuffer && (customBuffer.duration <= 0.9 || first)) {
                    playBuffer(customBuffer, volume);
                } else if (!customBuffer) {
                    countdownSound('bip', secondsLeft, volume);
                }

                break;
            default:
                tone(last ? 1320 : 880, { duration: last ? 0.28 : 0.12, volume, type: 'triangle' });
        }
    } catch {
        // Un son manqué ne doit jamais interrompre la séance.
    }
}

/** Le signal de reprise de l'effort : la voix dit « Go », les autres sons bipent. */
export function goSound(sound, volume) {
    if (sound === 'voix') {
        speak('Go !', volume);
    } else {
        beep(990, 0.35, volume);
    }
}

/** Le signal du début d'un repos. */
export function restSound(sound, volume) {
    if (sound === 'voix') {
        speak('Repos', volume);
    } else {
        beep(520, 0.3, volume);
    }
}

/** Trois secondes de décompte, pour entendre un réglage. */
export function previewCountdown(sound, volume) {
    unlockAudio();
    [3, 2, 1].forEach((second, index) => setTimeout(() => countdownSound(sound, second, volume, { first: index === 0 }), index * 900));
    setTimeout(() => goSound(sound, volume), 2700);
}

export function vibrate(pattern) {
    try {
        navigator.vibrate?.(pattern);
    } catch {
        // Safari ne vibre pas : tant pis.
    }
}
