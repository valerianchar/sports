<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import BodyMap from '../../components/BodyMap.vue';
import BottomSheet from '../../components/BottomSheet.vue';
import SettingsSheet from '../../components/SettingsSheet.vue';
import TabBar from '../../components/TabBar.vue';
import { unlockAudio } from '../../audio';
import { routes } from '../../routes';
import { bySlug, groupsOf, loadIntensity, muscleLoad, summary } from '../../workout';

const props = defineProps({
    workouts: { type: Array, required: true },
    exercises: { type: Array, required: true },
});

const page = usePage();
const catalog = computed(() => bySlug(props.exercises));
const settingsOpen = ref(false);
const deleting = ref(null);
const deleteOpen = computed({
    get: () => deleting.value !== null,
    set: (open) => {
        if (!open) {
            deleting.value = null;
        }
    },
});

function destroy() {
    router.delete(deleting.value.urls.destroy, { preserveScroll: true, onFinish: () => (deleting.value = null) });
}

const today = new Date().toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' });

const cards = computed(() =>
    props.workouts.map((workout) => ({
        ...workout,
        meta: summary(workout.items, page.props.seconds_per_rep),
        groups: groupsOf(workout.items, catalog.value),
        intensity: loadIntensity(muscleLoad(workout.items, catalog.value)),
    })),
);

/*
 * Le son n'est permis qu'à partir d'un geste : on réveille le contexte audio
 * au toucher, avant la navigation vers le lecteur.
 */
function play(workout) {
    unlockAudio();
    router.visit(workout.items.length ? workout.urls.play : workout.urls.edit);
}
</script>

<template>
    <Head title="Mes séances" />

    <div class="no-scrollbar flex-1 overflow-y-auto px-5 pt-3 pb-6">
        <div class="flex items-start justify-between gap-3">
            <p class="pt-1 text-[12px] font-bold tracking-[0.12em] text-text-muted uppercase">{{ today }}</p>
            <button
                type="button"
                class="iconbtn size-10 text-[13px] font-extrabold"
                aria-label="Réglages et compte"
                @click="settingsOpen = true"
            >
                {{ page.props.auth.user.initials }}
            </button>
        </div>
        <h1 class="display mt-1 mb-6 text-[56px] leading-[0.88] font-extrabold tracking-[-0.01em]">Mes<br />séances</h1>

        <div class="flex flex-col gap-3">
            <article v-for="workout in cards" :key="workout.id" class="flex flex-col gap-3.5 rounded-3xl bg-surface p-[18px]">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex min-w-0 flex-col gap-1">
                        <h2 class="display text-[28px] font-bold text-pretty">{{ workout.name }}</h2>
                        <p class="text-[13px] font-medium text-text-muted">{{ workout.meta }}</p>
                    </div>
                    <div class="flex shrink-0 gap-2">
                        <button type="button" class="iconbtn size-10 bg-surface-2! text-text-muted hover:text-danger" :aria-label="`Supprimer ${workout.name}`" @click="deleting = workout">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16M10 11v6M14 11v6M6 7l1 12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-12M9 7V4h6v3" /></svg>
                        </button>
                        <Link :href="workout.urls.edit" class="iconbtn size-10 bg-surface-2!" :aria-label="`Modifier ${workout.name}`">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20h4L19 9l-4-4L4 16v4z" /></svg>
                        </Link>
                    </div>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <p class="text-[12px] font-semibold tracking-[0.02em] text-text-soft">
                        {{ workout.groups }}
                        <span v-if="workout.last_done" class="block pt-1 text-text-faint">Faite {{ workout.last_done }}</span>
                    </p>
                    <BodyMap v-if="workout.items.length" :intensity="workout.intensity" :height="84" :label="`Muscles de ${workout.name}`" />
                </div>
                <button type="button" class="btn-accent h-[52px] text-[22px]" @click="play(workout)">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M7 4.5v15a1 1 0 0 0 1.5.86l12.5-7.5a1 1 0 0 0 0-1.72L8.5 3.64A1 1 0 0 0 7 4.5z" /></svg>
                    Lancer
                </button>
            </article>

            <p v-if="!cards.length" class="px-2 py-7 text-center text-[14px] leading-normal text-text-muted">
                Aucune séance pour l'instant.<br />Crée ta première séance.
            </p>

            <Link :href="routes.assistant" class="flex items-center gap-4 rounded-3xl border-[1.5px] border-accent bg-accent/8 p-[18px] text-text hover:bg-accent/12">
                <span class="flex size-11 shrink-0 items-center justify-center rounded-full bg-accent text-on-accent" aria-hidden="true">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l1.8 4.6L18.5 9l-4.7 1.4L12 15l-1.8-4.6L5.5 9l4.7-1.4zM19 15l.8 2.2L22 18l-2.2.8L19 21l-.8-2.2L16 18l2.2-.8z" /></svg>
                </span>
                <span class="flex min-w-0 flex-col gap-0.5">
                    <span class="font-display text-[22px] leading-none font-extrabold uppercase">Assistant</span>
                    <span class="text-[13px] font-medium text-text-muted">Choisis tes muscles et ton temps, je compose la séance.</span>
                </span>
            </Link>

            <Link :href="routes.newWorkout" class="dashed h-[60px] rounded-3xl">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14" /></svg>
                Nouvelle séance vide
            </Link>
        </div>
    </div>

    <TabBar active="home" />

    <SettingsSheet v-model:open="settingsOpen" />

    <BottomSheet
        v-model:open="deleteOpen"
        title="Supprimer ?"
        :description="deleting ? `« ${deleting.name} » disparaîtra. Les séances déjà faites restent dans ton historique.` : ''"
    >
        <button type="button" class="btn-accent h-14 w-full bg-danger! text-[22px]" @click="destroy">Supprimer la séance</button>
        <button type="button" class="btn-soft h-[54px] text-[15px]" @click="deleting = null">Garder</button>
    </BottomSheet>
</template>
