<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import Stepper from '../../components/Stepper.vue';
import TabBar from '../../components/TabBar.vue';
import { routes } from '../../routes';

/**
 * Ma semaine : une séance par jour, à une heure, avec un rappel par
 * notification ; et l'objectif de séances par semaine.
 */
const props = defineProps({
    days: { type: Array, required: true },
    workouts: { type: Array, required: true },
    weeklyGoal: { type: Number, required: true },
});

const form = useForm({
    weekly_goal: props.weeklyGoal,
    days: props.days.map((day) => ({ weekday: day.weekday, workout_id: day.workout_id, time: day.time ?? '', remind: day.remind })),
});

function save() {
    form.transform((data) => ({ ...data, days: data.days.map((day) => ({ ...day, time: day.time || null })) })).put(routes.schedule, { preserveScroll: true });
}
</script>

<template>
    <Head title="Ma semaine" />

    <div class="no-scrollbar flex flex-1 flex-col gap-4 overflow-y-auto px-5 pt-3 pb-[120px]">
        <header class="flex items-center gap-3">
            <Link :href="routes.workouts" class="iconbtn size-11" aria-label="Retour aux séances">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M15 5l-7 7 7 7" /></svg>
            </Link>
            <h1 class="display text-[40px] leading-none font-extrabold">Ma semaine</h1>
        </header>
        <p class="text-[14px] font-medium text-text-muted">
            Prévois une séance par jour : l'accueil te la propose, et une notification te la rappelle à l'heure dite.
        </p>

        <section class="rounded-[22px] bg-surface p-4">
            <Stepper
                label="Objectif de séances par semaine"
                :display="form.weekly_goal ? String(form.weekly_goal) : 'Aucun'"
                :can-decrease="form.weekly_goal > 0"
                :can-increase="form.weekly_goal < 7"
                @decrease="form.weekly_goal--"
                @increase="form.weekly_goal++"
            />
        </section>

        <p v-if="!props.workouts.length" class="rounded-[22px] bg-surface p-4 text-[14px] font-medium text-text-muted">
            Crée d'abord une séance : tu pourras ensuite la placer dans ta semaine.
        </p>

        <section v-for="(day, index) in form.days" :key="day.weekday" class="flex flex-col gap-3 rounded-[22px] bg-surface p-4">
            <div class="flex items-center justify-between gap-3">
                <h2 class="display text-[22px] font-bold">{{ props.days[index].label }}</h2>
                <span class="text-[12px] font-bold" :class="day.workout_id ? 'text-accent' : 'text-text-faint'">{{ day.workout_id ? 'Séance prévue' : 'Repos' }}</span>
            </div>
            <label class="flex flex-col gap-1.5">
                <span class="text-[10.5px] font-extrabold tracking-[0.1em] text-text-muted uppercase">Séance</span>
                <select
                    v-model="day.workout_id"
                    class="h-12 rounded-2xl border-0 bg-surface-2 px-3 text-[15px] font-semibold text-text outline-none focus:ring-2 focus:ring-accent"
                    :aria-label="`Séance du ${props.days[index].label.toLowerCase()}`"
                >
                    <option :value="null">Repos</option>
                    <option v-for="workout in props.workouts" :key="workout.id" :value="workout.id">{{ workout.name }}</option>
                </select>
            </label>
            <div v-if="day.workout_id" class="flex items-end gap-3">
                <label class="flex flex-1 flex-col gap-1.5">
                    <span class="text-[10.5px] font-extrabold tracking-[0.1em] text-text-muted uppercase">Heure</span>
                    <input
                        v-model="day.time"
                        type="time"
                        class="h-12 rounded-2xl border-0 bg-surface-2 px-3 text-[16px] font-semibold text-text outline-none focus:ring-2 focus:ring-accent"
                        :aria-label="`Heure du ${props.days[index].label.toLowerCase()}`"
                    />
                </label>
                <button
                    type="button"
                    class="h-12 rounded-2xl px-4 text-[13px] font-extrabold disabled:opacity-40"
                    :class="day.remind && day.time ? 'bg-accent text-on-accent' : 'bg-surface-2 text-text-soft'"
                    :aria-pressed="day.remind && Boolean(day.time)"
                    :disabled="!day.time"
                    @click="day.remind = !day.remind"
                >
                    {{ day.remind && day.time ? 'Rappel ✓' : 'Rappel' }}
                </button>
            </div>
            <p v-if="form.errors[`days.${index}.time`]" class="text-[13px] text-danger">{{ form.errors[`days.${index}.time`] }}</p>
        </section>

        <p class="text-[12.5px] font-medium text-text-faint">Les rappels arrivent par notification : active « Alertes hors de l'appli » dans les réglages.</p>
    </div>

    <div v-if="form.isDirty" class="pointer-events-none absolute inset-x-0 bottom-[calc(env(safe-area-inset-bottom)+74px)] px-5">
        <button type="button" class="btn-accent pointer-events-auto h-14 w-full text-[22px] shadow-[0_8px_24px_rgb(0_0_0/0.5)]" :disabled="form.processing" @click="save">Enregistrer</button>
    </div>

    <TabBar active="workouts" />
</template>
