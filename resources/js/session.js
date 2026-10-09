/**
 * La séance laissée en cours sur ce téléphone : le lecteur la garde ici pour
 * la reprendre après un rechargement, et l'accueil propose de la reprendre.
 */
export const PLAYER_STORAGE_KEY = 'seance.player.v2';

/** Au-delà, une séance abandonnée ne se reprend plus. */
export const RESUME_WINDOW = 4 * 60 * 60 * 1000;

/** `{ workoutId, savedAt, doneSets }` de la séance en cours, ou null. */
export function runningSession() {
    try {
        const saved = JSON.parse(localStorage.getItem(PLAYER_STORAGE_KEY));

        if (!saved || saved.state?.done || Date.now() - saved.savedAt > RESUME_WINDOW) {
            return null;
        }

        return { workoutId: saved.workoutId, savedAt: saved.savedAt, doneSets: saved.state?.doneSets ?? 0 };
    } catch {
        return null;
    }
}

export function forgetRunningSession() {
    try {
        localStorage.removeItem(PLAYER_STORAGE_KEY);
    } catch {
        // Rien à oublier.
    }
}
