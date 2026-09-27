import { onMounted, onUnmounted } from 'vue';

/**
 * Garde l'écran allumé tant que le lecteur est ouvert : un téléphone qui se
 * verrouille au milieu d'un repos suspend les minuteurs et coupe les bips.
 *
 * Le verrou tombe dès que la page passe en arrière-plan ; on le reprend au
 * retour. Sans l'API (anciens navigateurs), l'écran se verrouille comme
 * d'habitude, et les minuteurs rattrapent le temps au retour.
 */
export function useWakeLock() {
    let lock = null;

    async function acquire() {
        if (!('wakeLock' in navigator) || document.visibilityState !== 'visible') {
            return;
        }

        try {
            lock = await navigator.wakeLock.request('screen');
        } catch {
            lock = null;
        }
    }

    const onVisibility = () => {
        if (document.visibilityState === 'visible') {
            acquire();
        }
    };

    onMounted(() => {
        acquire();
        document.addEventListener('visibilitychange', onVisibility);
    });

    onUnmounted(() => {
        document.removeEventListener('visibilitychange', onVisibility);
        lock?.release().catch(() => {});
        lock = null;
    });
}
