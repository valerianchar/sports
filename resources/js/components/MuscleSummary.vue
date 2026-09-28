<script setup>
import { computed, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import BodyMap from './BodyMap.vue';
import { loadIntensity, muscleLoad } from '../workout';

/**
 * « Muscles ciblés » d'une séance : la silhouette, puis le détail muscle par
 * muscle en nombre de séries (une demie pour un muscle secondaire).
 */
const props = defineProps({
    items: { type: Array, required: true },
    catalog: { type: Object, required: true },
    height: { type: Number, default: 230 },
});

const page = usePage();
const labels = computed(() => page.props.muscles);
const load = computed(() => muscleLoad(props.items, props.catalog));
const expanded = ref(false);
const visible = computed(() => (expanded.value ? load.value : load.value.slice(0, 4)));
const intensity = computed(() => loadIntensity(load.value));

const format = (sets) => {
    const value = Number.isInteger(sets) ? String(sets) : sets.toFixed(1).replace('.', ',');

    return `${value} série${sets > 1 ? 's' : ''}`;
};
</script>

<template>
    <section class="flex flex-col gap-4 rounded-[22px] bg-surface p-4">
        <div class="flex items-baseline justify-between">
            <h2 class="display text-[22px] font-bold">Muscles ciblés</h2>
            <span class="text-[11px] font-bold tracking-[0.08em] text-text-faint uppercase">Face · Dos</span>
        </div>

        <BodyMap :intensity="intensity" :height="props.height" :label="`Muscles ciblés : ${load.map((l) => labels[l.muscle]).join(', ')}`" />

        <ul v-if="load.length" class="flex flex-col gap-1.5">
            <li v-for="entry in visible" :key="entry.muscle" class="flex items-center gap-3 text-[13px] font-semibold">
                <span class="w-[110px] shrink-0 text-text-soft">{{ labels[entry.muscle] }}</span>
                <span class="h-1.5 flex-1 overflow-hidden rounded-full bg-surface-3">
                    <span class="block h-full rounded-full bg-accent" :style="{ width: `${Math.round(intensity[entry.muscle] * 100)}%` }" />
                </span>
                <span class="w-[68px] shrink-0 text-right text-text-muted tabular-nums">{{ format(entry.sets) }}</span>
            </li>
        </ul>
        <button
            v-if="load.length > 4"
            type="button"
            class="-mt-1 self-start text-[13px] font-bold text-accent"
            :aria-expanded="expanded"
            @click="expanded = !expanded"
        >
            {{ expanded ? 'Voir moins' : `Voir les ${load.length} muscles` }}
        </button>
        <p v-else-if="!load.length" class="text-center text-[13px] text-text-muted">Ajoute des exercices pour voir les muscles travaillés.</p>
    </section>
</template>
