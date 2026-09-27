<script setup>
/**
 * Réglage « − valeur + » de la maquette : gros boutons ronds pour des doigts
 * pleins de magnésie.
 */
const props = defineProps({
    label: { type: String, required: true },
    display: { type: String, required: true },
    tone: { type: String, default: 'text' },
    canDecrease: { type: Boolean, default: true },
    canIncrease: { type: Boolean, default: true },
});

const emit = defineEmits(['decrease', 'increase']);
</script>

<template>
    <div class="flex flex-col gap-1.5">
        <span class="text-[10.5px] font-extrabold tracking-[0.1em] text-text-muted uppercase">{{ props.label }}</span>
        <div class="flex items-center justify-between">
            <button
                type="button"
                class="iconbtn size-9 bg-surface-2! text-[20px] font-semibold active:bg-line-strong! disabled:opacity-35"
                :aria-label="`${props.label} : moins`"
                :disabled="!props.canDecrease"
                @click="emit('decrease')"
            >
                −
            </button>
            <span
                class="font-display text-[28px] font-bold tabular-nums"
                :class="props.tone === 'rest' ? 'text-rest' : 'text-text'"
                aria-live="polite"
            >
                {{ props.display }}
            </span>
            <button
                type="button"
                class="iconbtn size-9 bg-surface-2! text-[20px] font-semibold active:bg-line-strong! disabled:opacity-35"
                :aria-label="`${props.label} : plus`"
                :disabled="!props.canIncrease"
                @click="emit('increase')"
            >
                +
            </button>
        </div>
    </div>
</template>
