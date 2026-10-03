/**
 * Bips et vibrations du lecteur.
 *
 * iOS n'autorise le son qu'à partir d'un geste : le contexte audio est donc
 * créé — ou réveillé — au toucher « Lancer », puis réutilisé par tout le
 * lecteur. Inertia ne recharge pas la page : le contexte survit à la navigation.
 */
let context = null;

export function unlockAudio() {
    try {
        // iPhone en mode silencieux : sans cette déclaration (Safari 17+), Safari
        // coupe les sons de la page — le minuteur resterait muet en pleine séance.
        if (navigator.audioSession) {
            navigator.audioSession.type = 'playback';
        }

        context ??= new (window.AudioContext || window.webkitAudioContext)();

        if (context.state === 'suspended') {
            context.resume();
        }
    } catch {
        // Pas de Web Audio : le lecteur reste muet, rien de plus.
    }
}

/**
 * Un bip. `volume` va de 0 à 1 (réglage « Volume des bips ») ; l'onde
 * triangle, plus riche en harmoniques qu'une sinusoïde, porte mieux sur un
 * haut-parleur de téléphone dans une salle bruyante.
 */
export function beep(frequency, duration = 0.15, volume = 0.8, type = 'sine') {
    if (!context || volume <= 0) {
        return;
    }

    try {
        const now = context.currentTime;
        const oscillator = context.createOscillator();
        const gain = context.createGain();

        oscillator.type = type;
        oscillator.frequency.value = frequency;
        gain.gain.setValueAtTime(0.0001, now);
        gain.gain.exponentialRampToValueAtTime(Math.min(1, volume), now + 0.01);
        gain.gain.exponentialRampToValueAtTime(0.0001, now + duration);
        oscillator.connect(gain);
        gain.connect(context.destination);
        oscillator.start(now);
        oscillator.stop(now + duration + 0.05);
    } catch {
        // Un bip manqué ne doit jamais interrompre la séance.
    }
}

export function vibrate(pattern) {
    try {
        navigator.vibrate?.(pattern);
    } catch {
        // Safari ne vibre pas : tant pis.
    }
}

/**
 * Le compte à rebours de fin de repos : un bip par seconde, le dernier plus
 * aigu et plus long pour annoncer la reprise.
 */
export function countdownBeep(secondsLeft, volume) {
    if (secondsLeft === 1) {
        beep(1320, 0.28, volume, 'triangle');
    } else {
        beep(880, 0.12, volume, 'triangle');
    }
}
