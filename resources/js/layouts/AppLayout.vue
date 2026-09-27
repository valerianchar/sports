<script setup>
import { onMounted, onUnmounted } from 'vue';
import FlashToast from '../components/FlashToast.vue';
import { flushPendingLogs } from '../pendingLogs';

/*
 * Une séance terminée hors réseau part dès que l'application se rouvre, ou dès
 * que le réseau revient.
 */
const onOnline = () => flushPendingLogs();

onMounted(() => {
    flushPendingLogs();
    window.addEventListener('online', onOnline);
});
onUnmounted(() => window.removeEventListener('online', onOnline));
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
        <slot />
    </div>

    <FlashToast />
</template>
