<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import ExerciseImage from '../../components/ExerciseImage.vue';
import BarChart from '../../components/charts/BarChart.vue';
import LineChart from '../../components/charts/LineChart.vue';
import StatTile from '../../components/charts/StatTile.vue';
import { formatKg, formatNumber, formatSet, formatTonnage } from '../../format';
import { routes } from '../../routes';

const props = defineProps({
    exercise: { type: Object, required: true },
    stats: { type: Object, required: true },
});

const records = computed(() => props.stats.records);
const chart = computed(() => props.stats.chart);
const hasE1rm = computed(() => chart.value.some((p) => p.e1rm));
const hasVolume = computed(() => chart.value.some((p) => p.volume));

const advice = {
    up: 'Toutes tes répétitions sont passées la dernière fois : monte d’un cran.',
    keep: 'Il t’a manqué une répétition : garde cette charge et vise toutes les répétitions.',
    down: 'Plusieurs répétitions ont manqué : allège un peu pour retrouver une exécution propre.',
};
</script>

<template>
    <Head :title="`Progrès — ${props.exercise.name}`" />

    <div class="no-scrollbar flex-1 overflow-y-auto pb-8">
        <div class="relative aspect-[3/2] max-h-[240px] w-full bg-surface">
            <ExerciseImage :images="props.exercise.images" :alt="props.exercise.name" />
            <Link :href="`${routes.progress}?vue=exercices`" class="iconbtn absolute top-4 left-5 size-10 bg-[rgb(14_15_12/0.8)]!" aria-label="Retour aux progrès">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M15 5l-7 7 7 7" /></svg>
            </Link>
        </div>

        <div class="flex flex-col gap-3 px-5 pt-5">
            <div>
                <p class="eyebrow text-accent">Progression</p>
                <h1 class="display mt-1 text-[40px] leading-[0.92] font-extrabold text-balance">{{ props.exercise.name }}</h1>
                <p class="mt-1 text-[13px] font-semibold text-text-muted">
                    {{ props.stats.total.sessions }} séance{{ props.stats.total.sessions > 1 ? 's' : '' }} · {{ props.stats.total.sets }} séries · {{ formatNumber(props.stats.total.reps) }} répétitions
                </p>
            </div>

            <section v-if="props.stats.next" class="flex flex-col gap-1 rounded-[22px] border-[1.5px] border-accent bg-accent/8 p-4">
                <span class="text-[11px] font-extrabold tracking-[0.1em] text-accent uppercase">La prochaine fois</span>
                <span class="font-display text-[40px] leading-none font-extrabold tabular-nums">{{ formatKg(props.stats.next.weight, 2) }}</span>
                <span class="text-[13px] font-medium text-text-soft">{{ advice[props.stats.next.trend] }}</span>
            </section>

            <div class="grid grid-cols-2 gap-2">
                <StatTile label="Charge max" :value="records.weight ? formatKg(records.weight.value, 2) : '—'" :hint="records.weight ? `× ${records.weight.reps ?? '?'} · ${records.weight.ago}` : 'au poids du corps'" />
                <StatTile label="1RM estimé" :value="records.e1rm ? formatKg(records.e1rm.value) : '—'" :hint="records.e1rm ? `${formatSet(records.e1rm)} · ${records.e1rm.ago}` : 'au-delà de 12 reps'" />
                <StatTile label="Répétitions max" :value="records.reps ? String(records.reps.value) : '—'" :hint="records.reps ? `${records.reps.weight ? formatKg(records.reps.weight, 2) : 'poids du corps'} · ${records.reps.ago}` : null" />
                <StatTile label="Volume max" :value="records.volume ? formatTonnage(records.volume.value) : '—'" :hint="records.volume ? `en une séance · ${records.volume.label}` : null" />
            </div>

            <section v-if="hasE1rm" class="rounded-[22px] bg-surface p-4">
                <h2 class="text-[15px] font-bold">1RM estimé</h2>
                <p class="mb-1 text-[12px] font-medium text-text-muted">Le meilleur de chaque séance : charge × (1 + reps / 30).</p>
                <LineChart :data="chart.map((p) => ({ label: p.label, value: p.e1rm }))" label="1RM estimé par séance" :format="(v) => formatKg(v, 0)" />
            </section>

            <section v-if="hasVolume" class="rounded-[22px] bg-surface p-4">
                <h2 class="mb-1 text-[15px] font-bold">Volume par séance</h2>
                <BarChart :data="chart.map((p) => ({ label: p.label, value: p.volume }))" label="Volume soulevé par séance" :format="formatTonnage" />
            </section>

            <section class="rounded-[22px] bg-surface p-4">
                <h2 class="mb-2 text-[15px] font-bold">Historique</h2>
                <ol class="flex flex-col">
                    <li v-for="session in props.stats.sessions" :key="session.date" class="flex flex-col gap-1.5 border-b border-divider py-3 first:pt-0 last:border-0 last:pb-0">
                        <div class="flex items-baseline justify-between text-[13px]">
                            <span class="font-bold">{{ new Date(session.date).toLocaleDateString('fr-FR', { weekday: 'short', day: 'numeric', month: 'short' }) }}</span>
                            <span class="font-semibold text-text-muted">
                                <template v-if="session.best_e1rm">1RM {{ formatKg(session.best_e1rm, 0) }} · </template>{{ formatTonnage(session.volume) }}
                            </span>
                        </div>
                        <div class="flex flex-wrap gap-1.5">
                            <span
                                v-for="(set, i) in session.sets"
                                :key="i"
                                class="rounded-lg bg-surface-2 px-2 py-1 text-[12px] font-bold tabular-nums"
                                :class="set.target && set.reps < set.target ? 'text-prep' : 'text-text-soft'"
                                :title="set.target ? `objectif ${set.target}` : ''"
                            >
                                {{ formatSet(set) }}<template v-for="(d, k) in set.drops" :key="k"> → {{ formatSet(d) }}</template>
                            </span>
                        </div>
                    </li>
                </ol>
            </section>
        </div>
    </div>
</template>
