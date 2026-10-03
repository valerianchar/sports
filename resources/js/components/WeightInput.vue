<script setup>
import { ref, watch } from 'vue';
import { stepWeight } from '../workout';

/**
 * La charge d'un exercice, en kilos : « − / + » au pas des haltères puis des
 * disques, ou saisie directe (62,5). Vide : au poids du corps.
 */
const props = defineProps({
    modelValue: { type: [Number, null], default: null },
    label: { type: String, default: 'Charge' },
});

const emit = defineEmits(['update:modelValue']);

const text = ref('');
const show = (value) => (value === null || value === undefined ? '' : String(value).replace('.', ','));
watch(() => props.modelValue, (value) => (text.value = show(value)), { immediate: true });

function commit() {
    const cleaned = text.value.replace(',', '.').replace(/[^\d.]/g, '');
    const value = cleaned === '' ? null : Math.min(999, Math.round(Number(cleaned) * 100) / 100);
    const next = value === null || Number.isNaN(value) || value <= 0 ? null : value;

    emit('update:modelValue', next);
    text.value = show(next);
}

const step = (direction) => emit('update:modelValue', stepWeight(props.modelValue, direction));
</script>

<template>
    <div class="flex flex-col gap-1.5">
        <span class="text-[10.5px] font-extrabold tracking-[0.1em] text-text-muted uppercase">{{ props.label }}</span>
        <div class="flex items-center justify-between gap-2">
            <button
                type="button"
                class="iconbtn size-9 bg-surface-2! text-[20px] font-semibold active:bg-line-strong! disabled:opacity-35"
                :aria-label="`${props.label} : moins`"
                :disabled="props.modelValue === null"
                @click="step(-1)"
            >
                −
            </button>
            <label class="flex min-w-0 flex-1 items-baseline justify-center gap-1">
                <input
                    v-model="text"
                    type="text"
                    inputmode="decimal"
                    placeholder="—"
                    :aria-label="`${props.label} en kilos`"
                    class="w-[86px] border-0 bg-transparent p-0 text-right font-display text-[28px] font-bold text-text tabular-nums outline-none placeholder:text-text-faint focus:text-accent"
                    @blur="commit"
                    @keydown.enter.prevent="$event.target.blur()"
                />
                <span class="font-display text-[18px] font-bold text-text-muted">kg</span>
            </label>
            <button
                type="button"
                class="iconbtn size-9 bg-surface-2! text-[20px] font-semibold active:bg-line-strong!"
                :aria-label="`${props.label} : plus`"
                @click="step(1)"
            >
                +
            </button>
        </div>
    </div>
</template>
