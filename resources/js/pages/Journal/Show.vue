<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import BottomSheet from '../../components/BottomSheet.vue';
import TabBar from '../../components/TabBar.vue';
import StatTile from '../../components/charts/StatTile.vue';
import { formatKcal, formatKg, formatMinutesLong, formatNumber, formatTonnage } from '../../format';
import { routes } from '../../routes';

/**
 * Une séance du journal, exercice par exercice dans l'ordre où ils ont été
 * faits, avec chaque série. On peut l'effacer : enregistrée par erreur, elle
 * fausserait les statistiques.
 */
const props = defineProps({
    session: { type: Object, required: true },
});

/** 45 → « 45 s » ; 90 → « 1 min 30 » ; 120 → « 2 min ». */
function formatDuration(seconds) {
    if (seconds < 60) {
        return `${seconds} s`;
    }

    const rest = seconds % 60;

    return `${Math.floor(seconds / 60)} min${rest ? ` ${String(rest).padStart(2, '0')}` : ''}`;
}

/** « 8 × 60 kg », « 12 reps » au poids du corps, « 45 s » au chrono — « par côté » si compté de chaque côté. */
function formatSet(set) {
    let text;

    if (set.reps === null || set.reps === undefined) {
        text = set.seconds ? formatDuration(set.seconds) : '—';
    } else {
        text = set.weight ? `${set.reps} × ${formatNumber(set.weight, 2)} kg` : `${set.reps} rep${set.reps > 1 ? 's' : ''}`;
    }

    return set.per_side ? `${text} par côté` : text;
}

const missed = (set) => set.target && set.reps !== null && set.reps < set.target;

const confirming = ref(false);
const deleting = ref(false);

function destroy() {
    router.delete(routes.journalSession(props.session.id), {
        onStart: () => (deleting.value = true),
        onFinish: () => {
            deleting.value = false;
            confirming.value = false;
        },
    });
}
</script>

<template>
    <Head :title="`Journal — ${props.session.name}`" />

    <div class="no-scrollbar flex-1 overflow-y-auto px-5 pt-3 pb-8">
        <header class="mb-4 flex items-start gap-3">
            <Link :href="routes.journal" class="iconbtn size-12" aria-label="Retour au journal">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 5l-7 7 7 7" /></svg>
            </Link>
            <div class="min-w-0 pt-1">
                <p class="eyebrow text-accent">{{ props.session.day_label }} · {{ props.session.time }}</p>
                <h1 class="display mt-1 text-[40px] leading-[0.92] font-extrabold text-balance">{{ props.session.name }}</h1>
            </div>
        </header>

        <div class="flex flex-col gap-3">
            <p v-if="!props.session.completed" class="flex flex-col gap-1 rounded-[22px] border-[1.5px] border-dashed border-prep/60 p-4">
                <span class="text-[11px] font-extrabold tracking-[0.1em] text-prep uppercase">Séance interrompue</span>
                <span class="text-[13px] font-medium text-text-soft">
                    <template v-if="props.session.planned_sets">{{ props.session.sets }} série{{ props.session.sets > 1 ? 's' : '' }} sur {{ props.session.planned_sets }} prévues. </template>
                    Elle reste au journal, mais ne compte ni dans tes statistiques ni dans tes records.
                </span>
            </p>

            <div class="grid grid-cols-2 gap-2">
                <StatTile label="Durée" :value="formatMinutesLong(props.session.minutes)" :hint="props.session.rpe ? `ressenti ${props.session.rpe}/10` : null" />
                <StatTile label="Séries" :value="formatNumber(props.session.sets)" :hint="`${props.session.exercises.length} exercice${props.session.exercises.length > 1 ? 's' : ''}`" />
                <StatTile label="Tonnage" :value="formatTonnage(props.session.tonnage)" hint="charge × répétitions" />
                <StatTile label="Calories" :value="props.session.kcal === null ? '—' : formatKcal(props.session.kcal)" :hint="props.session.kcal === null ? 'séance non comptée' : 'estimées'" />
            </div>

            <p v-if="!props.session.exercises.length" class="rounded-[22px] bg-surface p-5 text-[14px] leading-normal font-medium text-text-muted">
                Le détail des séries n'a pas été enregistré pour cette séance.
            </p>

            <section v-for="exercise in props.session.exercises" :key="exercise.slug" class="flex flex-col gap-3 rounded-[22px] bg-surface p-4">
                <div class="flex items-center gap-3">
                    <span class="size-14 shrink-0 overflow-hidden rounded-xl bg-surface-2">
                        <img v-if="exercise.images.length" :src="exercise.images[0]" alt="" class="size-full object-cover" loading="lazy" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <h2 class="text-[15px] leading-tight font-bold">{{ exercise.name }}</h2>
                        <p class="mt-0.5 flex flex-wrap gap-x-2 text-[12px] font-semibold text-text-muted">
                            <span v-if="exercise.best_e1rm">1RM {{ formatKg(exercise.best_e1rm, 0) }}</span>
                            <span v-if="exercise.volume > 0">{{ formatTonnage(exercise.volume) }}</span>
                            <span v-if="exercise.record" class="font-extrabold text-prep">★ Record {{ exercise.record === 'e1rm' ? 'de 1RM' : 'de charge' }}</span>
                        </p>
                    </div>
                    <Link v-if="exercise.known" :href="routes.progressExercise(exercise.slug)" class="iconbtn size-11 bg-surface-2!" :aria-label="`Progression : ${exercise.name}`">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 20V10M10 20V4M16 20v-7M22 20H2" /></svg>
                    </Link>
                </div>

                <ol class="flex flex-col">
                    <li v-for="(set, i) in exercise.sets" :key="i" class="flex items-baseline gap-3 border-t border-divider py-2 text-[14px]">
                        <span class="w-6 shrink-0 text-[12px] font-extrabold text-text-faint tabular-nums">{{ i + 1 }}</span>
                        <span class="min-w-0 flex-1 font-bold tabular-nums">
                            <span :class="missed(set) ? 'text-prep' : 'text-text'">{{ formatSet(set) }}</span>
                            <span v-for="(drop, k) in set.drops" :key="k" class="text-text-soft"> → {{ formatSet(drop) }}</span>
                        </span>
                        <span v-if="missed(set)" class="shrink-0 text-[11.5px] font-semibold text-text-faint">objectif {{ set.target }}</span>
                    </li>
                </ol>
            </section>

            <button type="button" class="btn-soft mt-2 h-[54px] text-[15px] text-danger" @click="confirming = true">Supprimer cette séance</button>
        </div>
    </div>

    <TabBar active="progress" />

    <BottomSheet v-model:open="confirming" title="Supprimer ?" :description="`« ${props.session.name} » (${props.session.day_label.toLowerCase()}, ${props.session.time}) disparaîtra du journal avec toutes ses séries, et de tes statistiques.`">
        <button type="button" class="btn-accent h-14 w-full bg-danger! text-[22px]" :disabled="deleting" @click="destroy">Supprimer la séance</button>
        <button type="button" class="btn-soft h-[54px] text-[15px]" @click="confirming = false">Garder</button>
    </BottomSheet>
</template>
