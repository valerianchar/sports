<script setup>
/**
 * Un chiffre clé : libellé, valeur, et, si on l'a, une évolution signée
 * comparée à une période nommée — citron quand c'est bon signe, rouge sinon,
 * toujours avec sa flèche pour ne pas reposer sur la seule couleur.
 */
const props = defineProps({
    label: { type: String, required: true },
    value: { type: String, required: true },
    delta: { type: Number, default: null },
    deltaLabel: { type: String, default: null },
    hint: { type: String, default: null },
    upIsGood: { type: Boolean, default: true },
});

const good = () => (props.delta > 0) === props.upIsGood;
const deltaText = () => `${props.delta > 0 ? '▲ +' : props.delta < 0 ? '▼ −' : ''}${Math.abs(props.delta).toLocaleString('fr-FR', { maximumFractionDigits: 1 })} %`;
</script>

<template>
    <div class="flex min-w-0 flex-col gap-1 rounded-[18px] bg-surface p-3.5">
        <span class="truncate text-[11px] font-bold text-text-muted">{{ props.label }}</span>
        <span class="truncate font-display text-[28px] leading-none font-bold tabular-nums">{{ props.value }}</span>
        <span v-if="props.delta !== null && props.delta !== undefined" class="truncate text-[11.5px] font-bold" :class="props.delta === 0 ? 'text-text-muted' : good() ? 'text-accent' : 'text-danger'">
            {{ deltaText() }}<span v-if="props.deltaLabel" class="font-semibold text-text-faint"> {{ props.deltaLabel }}</span>
        </span>
        <span v-else-if="props.hint" class="truncate text-[11.5px] font-semibold text-text-faint">{{ props.hint }}</span>
    </div>
</template>
