<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import BodyMap from './BodyMap.vue';
import { groupsOf, loadIntensity, muscleLoad, summary } from '../workout';

/**
 * Une séance enregistrée : son nom, ce qu'elle contient, la dernière fois,
 * ses muscles — et l'action principale, la lancer. Modifier et le reste
 * restent à portée sans voler la vedette.
 */
const props = defineProps({
    workout: { type: Object, required: true },
    catalog: { type: Object, required: true },
    // Montre la silhouette des muscles (liste complète) ou non (accueil, plus compact).
    detailed: { type: Boolean, default: true },
});

const emit = defineEmits(['play', 'more']);

const page = usePage();
const meta = computed(() => summary(props.workout.items, page.props.seconds_per_rep));
const groups = computed(() => groupsOf(props.workout.items, props.catalog));
const intensity = computed(() => loadIntensity(muscleLoad(props.workout.items, props.catalog)));
</script>

<template>
    <article class="flex flex-col gap-3 rounded-3xl bg-surface p-4">
        <div class="flex items-start gap-3">
            <div class="flex min-w-0 flex-1 flex-col gap-1">
                <h3 class="display text-[26px] leading-[0.95] font-bold text-pretty">{{ props.workout.name }}</h3>
                <p class="text-[13px] font-semibold text-text-muted">{{ meta }}</p>
                <p class="text-[12.5px] font-semibold text-text-soft">{{ groups }}</p>
                <p class="text-[12px] font-semibold" :class="props.workout.last_done ? 'text-text-faint' : 'text-accent'">
                    {{ props.workout.last_done ? `Faite ${props.workout.last_done}` : 'Jamais faite' }}
                </p>
            </div>
            <BodyMap v-if="props.detailed && props.workout.items.length" :intensity="intensity" :height="84" :label="`Muscles de ${props.workout.name}`" />
        </div>

        <div class="flex items-center gap-2">
            <button type="button" class="btn-accent h-12 flex-1 text-[20px]" @click="emit('play', props.workout)">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M7 4.5v15a1 1 0 0 0 1.5.86l12.5-7.5a1 1 0 0 0 0-1.72L8.5 3.64A1 1 0 0 0 7 4.5z" /></svg>
                Lancer
            </button>
            <Link :href="props.workout.urls.edit" class="iconbtn size-12 bg-surface-2! text-text" :aria-label="`Modifier ${props.workout.name}`">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20h4L19 9l-4-4L4 16v4z" /></svg>
            </Link>
            <button type="button" class="iconbtn size-12 bg-surface-2! text-text-muted" :aria-label="`Autres actions pour ${props.workout.name}`" @click="emit('more', props.workout)">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="5" cy="12" r="1.8" /><circle cx="12" cy="12" r="1.8" /><circle cx="19" cy="12" r="1.8" /></svg>
            </button>
        </div>
    </article>
</template>
