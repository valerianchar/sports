<script setup>
import FlashToast from '../components/FlashToast.vue';

const props = defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, required: true },
});
</script>

<template>
    <div class="mx-auto flex min-h-dvh w-full max-w-[480px] flex-col bg-bg px-6 pt-[calc(env(safe-area-inset-top)+3rem)] pb-[calc(env(safe-area-inset-bottom)+2.5rem)] text-text">
        <p class="eyebrow text-accent">Séance</p>
        <h1 class="display mt-3 mb-3 text-[64px] leading-[0.92] font-extrabold">Compose.<br />Lance.<br /><span class="text-accent">Enchaîne.</span></h1>
        <p class="mb-10 max-w-[340px] text-[15px] leading-normal font-medium text-text-muted">
            Tes séances de salle, minutées série par série — repos compris.
        </p>

        <div class="animate-pop rounded-[28px] bg-surface p-6">
            <h2 class="display text-[32px] font-extrabold">{{ props.title }}</h2>
            <p class="mt-1.5 mb-6 text-[14px] font-medium text-text-muted">{{ props.subtitle }}</p>

            <slot />
        </div>

        <p v-if="$slots.footer" class="mt-6 text-center text-[14px] font-medium text-text-muted">
            <slot name="footer" />
        </p>
    </div>

    <FlashToast />
</template>
