<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import BarChart from './charts/BarChart.vue';
import StatTile from './charts/StatTile.vue';
import { formatDelta, formatKg, formatMinutesLong, formatNumber, formatTonnage } from '../format';
import { routes } from '../routes';

/**
 * Les indicateurs de l'accueil : la semaine en cours en un coup d'œil, la
 * série de semaines, le dernier record, et ce qui mérite d'être travaillé.
 */
const props = defineProps({
    kpis: { type: Object, required: true },
});

const week = computed(() => props.kpis.week);
const body = computed(() => props.kpis.body);

// Sans aucune charge soulevée (que du cardio), le graphique montre les minutes de cardio.
const chart = computed(() =>
    props.kpis.tonnage_weeks.some((w) => w.value > 0) || !props.kpis.cardio_weeks.some((w) => w.value > 0)
        ? { title: 'Tonnage par semaine', label: 'Tonnage soulevé par semaine, 8 dernières semaines', data: props.kpis.tonnage_weeks, format: formatTonnage }
        : { title: 'Cardio par semaine', label: 'Minutes de cardio par semaine, 8 dernières semaines', data: props.kpis.cardio_weeks, format: (v) => `${formatNumber(v)} min` },
);

// Le poids : ce qui reste jusqu'à l'objectif s'il y en a un, sinon l'évolution du mois.
const bodyHint = computed(() => {
    if (body.value.latest === null) {
        return 'à noter';
    }

    if (body.value.to_go !== null) {
        if (Math.abs(body.value.to_go) < 0.1) {
            return 'objectif atteint';
        }

        return `encore ${formatNumber(Math.abs(body.value.to_go), 1)} kg`;
    }

    return body.value.change_30d === null ? 'dernière pesée' : `${body.value.change_30d > 0 ? '+' : ''}${formatNumber(body.value.change_30d, 1)} kg / 30 j`;
});
const record = computed(() => props.kpis.latest_record);
const recordText = computed(() => {
    if (!record.value) {
        return null;
    }

    const kind = record.value.kind === 'e1rm' ? '1RM estimé' : 'charge max';

    return `${record.value.name} : ${String(record.value.value).replace('.', ',')} kg en ${kind} (avant ${String(record.value.previous).replace('.', ',')})`;
});
</script>

<template>
    <section class="mb-6 flex flex-col gap-3" aria-labelledby="kpis-title">
        <div class="flex items-baseline justify-between">
            <h2 id="kpis-title" class="display text-[22px] font-bold">Cette semaine</h2>
            <Link :href="routes.progress" class="text-[13px] font-bold">Mes progrès →</Link>
        </div>

        <p v-if="!props.kpis.has_data" class="rounded-[22px] bg-surface p-4 text-[14px] leading-normal font-medium text-text-muted">
            Tes progrès s'afficheront ici dès ta première séance terminée : charges, records, régularité, muscles travaillés.
        </p>

        <template v-else>
            <div class="flex items-end justify-between gap-3 rounded-[22px] bg-surface p-4">
                <div class="flex flex-col gap-1">
                    <span class="font-display text-[52px] leading-[0.85] font-extrabold tabular-nums">{{ week.sessions }}</span>
                    <span class="text-[13px] font-semibold text-text-muted">
                        séance{{ week.sessions > 1 ? 's' : '' }} · {{ week.sessions_last_week }} la semaine dernière
                    </span>
                </div>
                <div class="flex flex-col items-end gap-0.5 text-right">
                    <span class="font-display text-[30px] leading-none font-bold text-accent tabular-nums">{{ props.kpis.streak }}</span>
                    <span class="text-[11.5px] leading-tight font-bold text-text-muted">semaine{{ props.kpis.streak > 1 ? 's' : '' }}<br />d'affilée</span>
                </div>
            </div>

            <div class="grid grid-cols-3 gap-2">
                <StatTile
                    label="Tonnage"
                    :value="formatTonnage(week.tonnage)"
                    :delta="week.tonnage_delta"
                    delta-label="vs sem. dern."
                    :hint="week.tonnage_delta === null ? 'soulevés' : null"
                />
                <StatTile label="Temps" :value="formatMinutesLong(week.minutes)" :hint="`${week.sets} séries`" />
                <StatTile label="Records" :value="String(props.kpis.records_30d)" hint="sur 30 jours" />
            </div>

            <div class="grid grid-cols-3 gap-2">
                <StatTile label="Cardio" :value="formatMinutesLong(week.cardio_minutes)" :hint="week.cardio_minutes ? 'cette semaine' : 'pas encore'" />
                <StatTile label="Calories" :value="formatNumber(week.kcal)" :hint="body.latest === null ? 'kcal, estim. à 75 kg' : 'kcal estimées'" />
                <Link :href="`${routes.progress}?vue=corps`" class="flex min-w-0 rounded-[18px] text-text">
                    <StatTile class="w-full" label="Poids" :value="body.latest === null ? '—' : formatKg(body.latest)" :hint="bodyHint" />
                </Link>
            </div>

            <div class="rounded-[22px] bg-surface px-3 pt-3 pb-1">
                <span class="px-1 text-[11px] font-bold text-text-muted">{{ chart.title }}</span>
                <BarChart :data="chart.data" :label="chart.label" :format="chart.format" :height="96" :axis="false" />
            </div>

            <p v-if="recordText" class="flex items-start gap-2 rounded-2xl bg-surface px-4 py-3 text-[13px] font-semibold">
                <span class="text-prep" aria-hidden="true">★</span>
                <span><span class="font-extrabold">Dernier record</span> {{ record.ago }} — {{ recordText }}</span>
            </p>

            <div v-if="props.kpis.neglected.length" class="flex flex-wrap items-center gap-1.5 rounded-2xl bg-surface px-4 py-3">
                <span class="mr-1 text-[12px] font-extrabold tracking-[0.06em] text-text-muted uppercase">À travailler</span>
                <span v-for="muscle in props.kpis.neglected" :key="muscle.muscle" class="rounded-full bg-surface-2 px-2.5 py-1 text-[12px] font-bold text-text-soft">
                    {{ muscle.label }}
                </span>
                <span class="w-full pt-1 text-[11.5px] font-medium text-text-faint">Aucune série dessus depuis 14 jours.</span>
            </div>
        </template>
    </section>
</template>
