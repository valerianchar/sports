<script setup>
import { computed, onMounted, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import HomeKpis from '../components/HomeKpis.vue';
import TabBar from '../components/TabBar.vue';
import WorkoutActions from '../components/WorkoutActions.vue';
import WorkoutCard from '../components/WorkoutCard.vue';
import { unlockAudio } from '../audio';
import { routes } from '../routes';
import { runningSession } from '../session';
import { bySlug } from '../workout';

/**
 * « Aujourd'hui » : ce qu'on vient faire à la salle, dans l'ordre — reprendre
 * la séance en cours, en composer une, refaire une séance récente — puis la
 * semaine en un coup d'œil.
 */
const props = defineProps({
    workouts: { type: Array, required: true },
    workoutsCount: { type: Number, required: true },
    workoutNames: { type: Object, required: true },
    exercises: { type: Array, required: true },
    kpis: { type: Object, required: true },
});

const page = usePage();
const catalog = computed(() => bySlug(props.exercises));
const today = new Date().toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' });
const more = ref(null);

// La séance laissée en cours sur ce téléphone (lue après le montage : le stockage est local).
const running = ref(null);

onMounted(() => {
    const session = runningSession();

    if (session && props.workoutNames[session.workoutId]) {
        running.value = { ...session, name: props.workoutNames[session.workoutId], ago: Math.max(1, Math.round((Date.now() - session.savedAt) / 60000)) };
    }
});

const kinds = [
    { goal: 'volume', label: 'Muscu', icon: 'M6.5 6.5v11M17.5 6.5v11M3 9v6M21 9v6M6.5 12h11' },
    { goal: 'perte-de-poids', label: 'Perte de poids', icon: 'M12 3c3 4 6 6.5 6 10a6 6 0 0 1-12 0c0-2 1-3.5 2-4.5 0 2 1 3 2 3 0-3 1-6 2-8.5z' },
    { goal: 'cardio', label: 'Cardio', icon: 'M3 12h4l3-7 4 14 3-7h4' },
];

/* Le son n'est permis qu'à partir d'un geste : on le réveille au toucher, avant le lecteur. */
function play(workout) {
    unlockAudio();
    router.visit(workout.items.length ? workout.urls.play : workout.urls.edit);
}

function resume() {
    unlockAudio();
    router.visit(`${routes.workout(running.value.workoutId)}/lancer`);
}
</script>

<template>
    <Head title="Aujourd'hui" />

    <div class="no-scrollbar flex flex-1 flex-col gap-6 overflow-y-auto px-5 pt-3 pb-8">
        <header class="flex items-start justify-between gap-3">
            <div>
                <p class="text-[12px] font-bold tracking-[0.12em] text-text-muted uppercase">{{ today }}</p>
                <h1 class="display mt-1 text-[46px] leading-[0.9] font-extrabold">Salut {{ page.props.auth.user.first_name }}</h1>
            </div>
            <Link :href="routes.settings" class="iconbtn size-11 text-[13px] font-extrabold text-text!" aria-label="Réglages et compte">
                {{ page.props.auth.user.initials }}
            </Link>
        </header>

        <!-- Une séance laissée en route : la reprendre passe avant tout le reste. -->
        <button
            v-if="running"
            type="button"
            class="flex items-center gap-4 rounded-3xl border-[1.5px] border-prep bg-prep/10 p-4 text-left"
            @click="resume"
        >
            <span class="flex size-12 shrink-0 items-center justify-center rounded-full bg-prep text-on-accent" aria-hidden="true">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M7 4.5v15a1 1 0 0 0 1.5.86l12.5-7.5a1 1 0 0 0 0-1.72L8.5 3.64A1 1 0 0 0 7 4.5z" /></svg>
            </span>
            <span class="flex min-w-0 flex-col gap-0.5">
                <span class="text-[11px] font-extrabold tracking-[0.12em] text-prep uppercase">Séance en cours</span>
                <span class="display truncate text-[24px] font-bold">{{ running.name }}</span>
                <span class="text-[12.5px] font-semibold text-text-muted">
                    {{ running.doneSets }} série{{ running.doneSets > 1 ? 's' : '' }} faite{{ running.doneSets > 1 ? 's' : '' }} · il y a {{ running.ago }} min · Reprendre
                </span>
            </span>
        </button>

        <!-- Composer une séance : l'assistant, d'un toucher, déjà orienté. -->
        <section class="flex flex-col gap-3 rounded-3xl bg-accent p-4 text-on-accent" aria-labelledby="compose-title">
            <div class="flex items-start justify-between gap-3">
                <div class="flex flex-col gap-1">
                    <h2 id="compose-title" class="display text-[30px] leading-none font-extrabold">Composer une séance</h2>
                    <p class="text-[13px] font-semibold text-on-accent/75">Dis ce que tu veux travailler et ton temps : l'assistant s'occupe du reste.</p>
                </div>
                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" class="shrink-0" aria-hidden="true"><path d="M12 3l1.8 4.6L18.5 9l-4.7 1.4L12 15l-1.8-4.6L5.5 9l4.7-1.4zM19 15l.8 2.2L22 18l-2.2.8L19 21l-.8-2.2L16 18l2.2-.8z" /></svg>
            </div>
            <div class="grid grid-cols-3 gap-2">
                <Link
                    v-for="kind in kinds"
                    :key="kind.goal"
                    :href="`${routes.assistant}?goal=${kind.goal}`"
                    class="flex flex-col items-start gap-2 rounded-2xl bg-on-accent/90 p-3 text-accent! hover:bg-on-accent"
                >
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path :d="kind.icon" /></svg>
                    <span class="font-display text-[17px] leading-none font-extrabold text-text uppercase">{{ kind.label }}</span>
                </Link>
            </div>
            <Link :href="routes.newWorkout" class="self-start text-[13px] font-bold text-on-accent! underline underline-offset-4">ou partir d'une séance vide</Link>
        </section>

        <!-- Refaire une séance récente. -->
        <section v-if="props.workouts.length" class="flex flex-col gap-3" aria-labelledby="again-title">
            <div class="flex items-baseline justify-between">
                <h2 id="again-title" class="display text-[24px] font-bold">À refaire</h2>
                <Link :href="routes.workouts" class="text-[13px] font-bold">Toutes ({{ props.workoutsCount }}) →</Link>
            </div>
            <WorkoutCard
                v-for="workout in props.workouts"
                :key="workout.id"
                :workout="workout"
                :catalog="catalog"
                :detailed="false"
                @play="play"
                @more="more = $event"
            />
        </section>

        <HomeKpis :kpis="props.kpis" />
    </div>

    <TabBar active="home" />

    <WorkoutActions :workout="more" @close="more = null" />
</template>
