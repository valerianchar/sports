<script setup>
import { computed } from 'vue';
import { SwitchRoot, SwitchThumb } from 'reka-ui';
import Stepper from './Stepper.vue';
import WeightInput from './WeightInput.vue';
import { clamp, degressive, roundPlate } from '../workout';

/**
 * La charge d'un exercice : fixe, ou une par série (dégressif, pyramide) ; et,
 * pour un exercice en répétitions, des paliers de drop set enchaînés sans
 * repos, sur la dernière série ou sur chacune.
 */
const props = defineProps({
    item: { type: Object, required: true },
});

const emit = defineEmits(['update']);

const reps = computed(() => props.item.mode === 'reps');
const perSet = computed(() => Boolean(props.item.set_weights?.length));
const lastWeight = computed(() => (perSet.value ? props.item.set_weights.at(-1) : props.item.weight) ?? null);

function setPerSet(on) {
    if (on === perSet.value) {
        return;
    }

    emit('update', on
        ? { set_weights: Array.from({ length: props.item.sets }, () => props.item.weight ?? null) }
        : { set_weights: null, weight: props.item.set_weights[0] ?? null });
}

function setSetWeight(index, weight) {
    const weights = [...props.item.set_weights];
    weights[index] = weight;
    emit('update', { set_weights: weights });
}

const autoDegressive = () => emit('update', { set_weights: degressive(props.item.set_weights[0] ?? props.item.weight ?? null, props.item.sets) });

/* Un palier de drop : 60 % des répétitions, trois quarts de la charge précédente. */
function newDrop(previous) {
    return {
        reps: clamp(Math.round(props.item.value * 0.6), 1, 100),
        weight: previous ? roundPlate(previous * 0.75) : null,
    };
}

function setDrops(on) {
    emit('update', on ? { drops: [newDrop(lastWeight.value)], drop_on: props.item.drop_on ?? 'last' } : { drops: null, drop_on: null });
}

function updateDrop(index, changes) {
    emit('update', { drops: props.item.drops.map((drop, i) => (i === index ? { ...drop, ...changes } : drop)) });
}

const addDrop = () => emit('update', { drops: [...props.item.drops, newDrop(props.item.drops.at(-1)?.weight)] });
const removeDrop = (index) => {
    const drops = props.item.drops.filter((_, i) => i !== index);
    emit('update', drops.length ? { drops } : { drops: null, drop_on: null });
};
</script>

<template>
    <div class="flex flex-col gap-3 rounded-2xl bg-bg p-3">
        <div v-if="reps" class="grid grid-cols-2 gap-[3px] rounded-xl bg-surface p-[3px]" role="radiogroup" aria-label="Type de charge">
            <button
                v-for="option in [{ value: false, label: 'Charge fixe' }, { value: true, label: 'Par série' }]"
                :key="option.label"
                type="button"
                role="radio"
                :aria-checked="perSet === option.value"
                class="h-8 rounded-[9px] text-[12.5px] font-bold"
                :class="perSet === option.value ? 'bg-surface-3 text-text' : 'bg-transparent text-text-faint'"
                @click="setPerSet(option.value)"
            >
                {{ option.label }}
            </button>
        </div>

        <WeightInput v-if="!perSet" :model-value="props.item.weight ?? null" label="Charge" @update:model-value="emit('update', { weight: $event })" />

        <template v-else>
            <WeightInput
                v-for="(weight, index) in props.item.set_weights"
                :key="index"
                :model-value="weight ?? null"
                :label="`Série ${index + 1}`"
                @update:model-value="setSetWeight(index, $event)"
            />
            <button type="button" class="self-start text-[13px] font-bold text-accent" @click="autoDegressive">
                Dégressif auto (−10 % par série)
            </button>
        </template>

        <template v-if="reps">
            <label class="flex cursor-pointer items-center justify-between gap-3 border-t border-divider pt-3">
                <span class="flex flex-col gap-0.5">
                    <span class="text-[14px] font-bold">Drop set</span>
                    <span class="text-[12px] font-medium text-text-muted">Paliers enchaînés sans repos, charge en baisse</span>
                </span>
                <SwitchRoot
                    :model-value="Boolean(props.item.drops?.length)"
                    class="relative h-7 w-12 shrink-0 rounded-full transition-colors"
                    :class="props.item.drops?.length ? 'bg-accent' : 'bg-surface-3'"
                    aria-label="Drop set"
                    @update:model-value="setDrops"
                >
                    <SwitchThumb
                        class="absolute top-1 block size-5 rounded-full transition-[left]"
                        :class="props.item.drops?.length ? 'left-6 bg-on-accent' : 'left-1 bg-text-muted'"
                    />
                </SwitchRoot>
            </label>

            <template v-if="props.item.drops?.length">
                <div class="grid grid-cols-2 gap-[3px] rounded-xl bg-surface p-[3px]" role="radiogroup" aria-label="Séries en drop set">
                    <button
                        v-for="option in [{ value: 'last', label: 'Dernière série' }, { value: 'all', label: 'Chaque série' }]"
                        :key="option.value"
                        type="button"
                        role="radio"
                        :aria-checked="(props.item.drop_on ?? 'last') === option.value"
                        class="h-8 rounded-[9px] text-[12.5px] font-bold"
                        :class="(props.item.drop_on ?? 'last') === option.value ? 'bg-surface-3 text-text' : 'bg-transparent text-text-faint'"
                        @click="emit('update', { drop_on: option.value })"
                    >
                        {{ option.label }}
                    </button>
                </div>

                <div v-for="(drop, index) in props.item.drops" :key="index" class="flex flex-col gap-2 rounded-xl bg-surface p-3">
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-extrabold tracking-[0.1em] text-prep uppercase">Palier {{ index + 1 }}</span>
                        <button type="button" class="text-[12.5px] font-bold text-danger" :aria-label="`Retirer le palier ${index + 1}`" @click="removeDrop(index)">Retirer</button>
                    </div>
                    <Stepper
                        label="Répétitions"
                        :display="String(drop.reps)"
                        :can-decrease="drop.reps > 1"
                        :can-increase="drop.reps < 100"
                        @decrease="updateDrop(index, { reps: drop.reps - 1 })"
                        @increase="updateDrop(index, { reps: drop.reps + 1 })"
                    />
                    <WeightInput :model-value="drop.weight ?? null" label="Charge du palier" @update:model-value="updateDrop(index, { weight: $event })" />
                </div>

                <button v-if="props.item.drops.length < 4" type="button" class="self-start text-[13px] font-bold text-accent" @click="addDrop">
                    + Ajouter un palier
                </button>
            </template>
        </template>
    </div>
</template>
