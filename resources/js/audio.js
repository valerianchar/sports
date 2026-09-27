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
        context ??= new (window.AudioContext || window.webkitAudioContext)();

        if (context.state === 'suspended') {
            context.resume();
        }
    } catch {
        // Pas de Web Audio : le lecteur reste muet, rien de plus.
    }
}

export function beep(frequency, duration = 0.15) {
    if (!context) {
        return;
    }

    try {
        const now = context.currentTime;
        const oscillator = context.createOscillator();
        const gain = context.createGain();

        oscillator.type = 'sine';
        oscillator.frequency.value = frequency;
        gain.gain.setValueAtTime(0.0001, now);
        gain.gain.exponentialRampToValueAtTime(0.3, now + 0.01);
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
