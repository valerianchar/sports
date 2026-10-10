<script setup>
import { onMounted, onUnmounted, ref } from 'vue';
import FlashToast from '../components/FlashToast.vue';
import { flushPendingLogs } from '../pendingLogs';

/*
 * Une séance terminée hors réseau part dès que l'application se rouvre, ou dès
 * que le réseau revient.
 */
const offline = ref(typeof navigator !== 'undefined' && !navigator.onLine);
const onOnline = () => {
    offline.value = false;
    flushPendingLogs();
};
const onOffline = () => (offline.value = true);

onMounted(() => {
    flushPendingLogs();
    window.addEventListener('online', onOnline);
    window.addEventListener('offline', onOffline);
});
onUnmounted(() => {
    window.removeEventListener('online', onOnline);
    window.removeEventListener('offline', onOffline);
});
</script>

<template>
    <!--
        Le cadre de la maquette : une colonne de téléphone, pleine hauteur, dont
        chaque écran gère son propre défilement. Sur un grand écran, la colonne
        reste centrée à sa largeur de téléphone.
    -->
    <div
        class="relative mx-auto flex h-dvh w-full max-w-[480px] flex-col overflow-hidden bg-bg pt-[env(safe-area-inset-top)] text-text min-[520px]:border-x min-[520px]:border-divider"
    >
        <!-- Sans réseau : les séances restent jouables, le reste attend le retour du réseau. -->
        <p v-if="offline" class="shrink-0 bg-prep px-5 py-1.5 text-center text-[12.5px] font-extrabold text-on-accent" role="status">
            Hors ligne : tes séances se lancent quand même, la suite part au retour du réseau.
        </p>
        <slot />
    </div>

    <FlashToast />
</template>
