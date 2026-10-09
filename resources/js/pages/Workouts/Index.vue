<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import BottomSheet from '../../components/BottomSheet.vue';
import TabBar from '../../components/TabBar.vue';
import WorkoutActions from '../../components/WorkoutActions.vue';
import WorkoutCard from '../../components/WorkoutCard.vue';
import { unlockAudio } from '../../audio';
import { routes } from '../../routes';
import { bySlug, normalize } from '../../workout';

/**
 * Toutes les séances enregistrées : les lancer, les modifier, en créer.
 */
const props = defineProps({
    workouts: { type: Array, required: true },
    exercises: { type: Array, required: true },
});

const catalog = computed(() => bySlug(props.exercises));
const more = ref(null);
const creating = ref(false);
const query = ref('');

const shown = computed(() => {
    const q = normalize(query.value.trim());

    return q ? props.workouts.filter((workout) => normalize(workout.name).includes(q)) : props.workouts;
});

/* Le son n'est permis qu'à partir d'un geste : on le réveille au toucher, avant le lecteur. */
function play(workout) {
    unlockAudio();
    router.visit(workout.items.length ? workout.urls.play : workout.urls.edit);
}
</script>

<template>
    <Head title="Mes séances" />

    <div class="no-scrollbar flex flex-1 flex-col gap-4 overflow-y-auto px-5 pt-3 pb-8">
        <header class="flex items-end justify-between gap-3">
            <h1 class="display text-[46px] leading-[0.9] font-extrabold">Séances</h1>
            <button type="button" class="btn-accent h-11 shrink-0 px-4 text-[18px]" @click="creating = true">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.8" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14" /></svg>
                Nouvelle
            </button>
        </header>

        <label v-if="props.workouts.length > 5" class="flex h-12 items-center gap-2.5 rounded-2xl bg-surface px-4">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" class="text-text-muted" aria-hidden="true"><circle cx="11" cy="11" r="7" /><path d="M20 20l-3.5-3.5" /></svg>
            <input v-model="query" type="search" placeholder="Chercher une séance" aria-label="Chercher une séance" class="min-w-0 flex-1 border-none bg-transparent text-[16px] font-medium text-text outline-none" />
        </label>

        <WorkoutCard v-for="workout in shown" :key="workout.id" :workout="workout" :catalog="catalog" @play="play" @more="more = $event" />

        <p v-if="props.workouts.length && !shown.length" class="px-2 py-6 text-center text-[14px] font-semibold text-text-muted">Aucune séance ne s'appelle ainsi.</p>

        <div v-if="!props.workouts.length" class="flex flex-col items-center gap-4 rounded-3xl bg-surface px-6 py-10 text-center">
            <p class="display text-[30px] font-extrabold">Pas encore de séance</p>
            <p class="text-[14px] font-medium text-text-muted">L'assistant en compose une à partir de tes muscles et de ton temps.</p>
            <Link :href="routes.assistant" class="btn-accent h-12 px-5 text-[20px] text-on-accent!">Composer une séance</Link>
        </div>
    </div>

    <TabBar active="workouts" />

    <WorkoutActions :workout="more" @close="more = null" />

    <BottomSheet v-model:open="creating" title="Nouvelle séance" description="L'assistant compose pour toi, ou tu pars de zéro.">
        <Link :href="routes.assistant" class="flex items-center gap-4 rounded-2xl border-[1.5px] border-accent bg-accent/8 p-4 text-text!">
            <span class="flex size-11 shrink-0 items-center justify-center rounded-full bg-accent text-on-accent" aria-hidden="true">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l1.8 4.6L18.5 9l-4.7 1.4L12 15l-1.8-4.6L5.5 9l4.7-1.4zM19 15l.8 2.2L22 18l-2.2.8L19 21l-.8-2.2L16 18l2.2-.8z" /></svg>
            </span>
            <span class="flex flex-col gap-0.5">
                <span class="font-display text-[22px] leading-none font-extrabold uppercase">Avec l'assistant</span>
                <span class="text-[13px] font-medium text-text-muted">Muscles, temps, objectif : il compose.</span>
            </span>
        </Link>
        <Link :href="routes.newWorkout" class="flex items-center gap-4 rounded-2xl bg-surface p-4 text-text!">
            <span class="flex size-11 shrink-0 items-center justify-center rounded-full bg-surface-2" aria-hidden="true">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14" /></svg>
            </span>
            <span class="flex flex-col gap-0.5">
                <span class="font-display text-[22px] leading-none font-extrabold uppercase">Séance vide</span>
                <span class="text-[13px] font-medium text-text-muted">Tu choisis chaque exercice toi-même.</span>
            </span>
        </Link>
    </BottomSheet>
</template>
