<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import BodyMap from '../../components/BodyMap.vue';
import TabBar from '../../components/TabBar.vue';
import BarChart from '../../components/charts/BarChart.vue';
import CalendarHeat from '../../components/charts/CalendarHeat.vue';
import LineChart from '../../components/charts/LineChart.vue';
import StatTile from '../../components/charts/StatTile.vue';
import { formatDelta, formatKg, formatMinutesLong, formatNumber, formatSet, formatTonnage } from '../../format';
import { routes } from '../../routes';

const props = defineProps({
    overview: { type: Object, required: true },
    exercises: { type: Array, required: true },
    muscles: { type: Object, required: true },
    body: { type: Object, required: true },
    tab: { type: String, required: true },
});

const tabs = [
    { key: 'apercu', label: 'Aperçu' },
    { key: 'exercices', label: 'Exercices' },
    { key: 'muscles', label: 'Muscles' },
    { key: 'corps', label: 'Corps' },
];

const current = ref(props.tab);

function show(tab) {
    current.value = tab;
    // L'onglet reste dans l'adresse : un rechargement ou un retour y ramène.
    history.replaceState(history.state, '', `${routes.progress}?vue=${tab}`);
}

const totals = computed(() => props.overview.totals);
const weeks = computed(() => props.overview.weeks);
const hasRpe = computed(() => weeks.value.some((w) => w.load > 0));
const empty = computed(() => totals.value.sessions === 0);

// Muscles : la semaine, ou la moyenne hebdomadaire des quatre dernières.
const span = ref('week');
const muscleIntensity = computed(() => {
    const source = span.value === 'week' ? props.muscles.week : props.muscles.month;
    const max = Math.max(1, ...Object.values(source));

    return Object.fromEntries(Object.entries(source).map(([muscle, sets]) => [muscle, sets / max]));
});
const [targetLow, targetHigh] = props.muscles.target;
const meterMax = targetHigh * 1.5;
const muscleValue = (row) => (span.value === 'week' ? row.week : row.weekly_average);

function zone(sets) {
    if (sets === 0) {
        return { text: 'rien', tone: 'text-text-faint' };
    }

    if (sets < targetLow) {
        return { text: 'en dessous', tone: 'text-text-muted' };
    }

    return sets > targetHigh ? { text: 'au-delà', tone: 'text-prep' } : { text: 'dans la cible', tone: 'text-accent' };
}

function balanceText(balance) {
    if (balance.ratio === null) {
        return balance.left_sets ? `Rien en ${balance.right.toLowerCase()} sur 4 semaines.` : 'Pas encore de données.';
    }

    if (balance.ratio > 1.3) {
        return `Nettement plus de ${balance.left.toLowerCase()} que de ${balance.right.toLowerCase()}.`;
    }

    return balance.ratio < 0.77 ? `Nettement plus de ${balance.right.toLowerCase()} que de ${balance.left.toLowerCase()}.` : 'Bien équilibré.';
}

// Corps : une pesée.
const weighing = useForm({ kg: props.body.latest ? String(props.body.latest).replace('.', ',') : '' });

function weigh() {
    weighing
        .transform((data) => ({ kg: String(data.kg).replace(',', '.') }))
        .post(routes.bodyWeights, { preserveScroll: true, preserveState: true, only: ['body'] });
}

const removeWeighing = (entry) => router.delete(`${routes.bodyWeights}/${entry.id}`, { preserveScroll: true, preserveState: true, only: ['body'] });

const trendTone = (trend) => (trend === 'up' ? 'text-accent' : trend === 'down' ? 'text-prep' : 'text-text-soft');
const trendWord = { up: 'monte', keep: 'garde', down: 'allège' };
</script>

<template>
    <Head title="Progrès" />

    <div class="no-scrollbar flex-1 overflow-y-auto px-5 pt-3 pb-8">
        <h1 class="display mt-1 mb-4 text-[56px] leading-[0.88] font-extrabold">Progrès</h1>

        <div class="no-scrollbar -mx-5 mb-5 flex gap-2 overflow-x-auto px-5" role="tablist" aria-label="Indicateurs">
            <button
                v-for="t in tabs"
                :key="t.key"
                type="button"
                role="tab"
                :aria-selected="current === t.key"
                class="h-[38px] shrink-0 rounded-full px-4 text-[14px] font-bold"
                :class="current === t.key ? 'bg-accent text-on-accent' : 'border-[1.5px] border-line text-text-soft'"
                @click="show(t.key)"
            >
                {{ t.label }}
            </button>
        </div>

        <p v-if="empty && current !== 'corps'" class="rounded-[22px] bg-surface p-5 text-[14px] leading-normal font-medium text-text-muted">
            Rien à mesurer pour l'instant : termine une séance et chaque série — charge, répétitions — viendra nourrir tes courbes, tes records et tes muscles.
        </p>

        <!-- APERÇU -->
        <div v-else-if="current === 'apercu'" class="flex flex-col gap-3">
            <div class="grid grid-cols-2 gap-2">
                <StatTile label="Séances" :value="formatNumber(totals.sessions)" :hint="`${overview.streak} semaine${overview.streak > 1 ? 's' : ''} d'affilée`" />
                <StatTile label="Temps d'entraînement" :value="formatMinutesLong(totals.minutes)" :hint="`${totals.average_minutes} min en moyenne`" />
                <StatTile label="Tonnage total" :value="formatTonnage(totals.tonnage)" :hint="`${formatNumber(totals.reps)} répétitions`" />
                <StatTile label="Séries" :value="formatNumber(totals.sets)" :hint="`${totals.records} record${totals.records > 1 ? 's' : ''} battu${totals.records > 1 ? 's' : ''}`" />
            </div>

            <section class="rounded-[22px] bg-surface p-4">
                <h2 class="mb-1 text-[15px] font-bold">Séances par semaine</h2>
                <BarChart :data="weeks.map((w) => ({ label: w.label, value: w.sessions }))" label="Séances par semaine, 12 dernières semaines" :format="(v) => formatNumber(v)" />
            </section>

            <section class="rounded-[22px] bg-surface p-4">
                <h2 class="mb-1 text-[15px] font-bold">Tonnage par semaine</h2>
                <p class="mb-1 text-[12px] font-medium text-text-muted">Charge × répétitions, toutes séries confondues.</p>
                <BarChart :data="weeks.map((w) => ({ label: w.label, value: w.tonnage }))" label="Tonnage par semaine, 12 dernières semaines" :format="formatTonnage" />
            </section>

            <section class="rounded-[22px] bg-surface p-4">
                <h2 class="mb-3 text-[15px] font-bold">Régularité</h2>
                <CalendarHeat :days="overview.calendar" />
            </section>

            <section class="rounded-[22px] bg-surface p-4">
                <h2 class="mb-1 text-[15px] font-bold">Charge d'entraînement</h2>
                <p class="mb-1 text-[12px] font-medium text-text-muted">
                    Difficulté ressentie × minutes, par semaine. Une hausse brutale (plus de 30 %) annonce souvent la fatigue.
                    <template v-if="totals.average_rpe"> Ressenti moyen : {{ String(totals.average_rpe).replace('.', ',') }}/10.</template>
                </p>
                <BarChart v-if="hasRpe" :data="weeks.map((w) => ({ label: w.label, value: w.load }))" label="Charge d'entraînement par semaine" :format="(v) => formatNumber(v)" />
                <p v-else class="text-[13px] font-medium text-text-faint">Note ta difficulté ressentie en fin de séance pour la voir apparaître.</p>
            </section>

            <section v-if="overview.records.length" class="rounded-[22px] bg-surface p-4">
                <h2 class="mb-2 text-[15px] font-bold">Derniers records</h2>
                <ul class="flex flex-col gap-2">
                    <li v-for="(r, i) in overview.records" :key="i" class="flex items-center gap-3 text-[13px]">
                        <span class="text-prep" aria-hidden="true">★</span>
                        <Link :href="routes.progressExercise(r.exercise)" class="min-w-0 flex-1 truncate font-bold text-text">{{ r.name }}</Link>
                        <span class="font-semibold text-text-soft">{{ formatKg(r.value) }} <span class="text-text-faint">{{ r.kind === 'e1rm' ? '1RM' : 'max' }}</span></span>
                        <span class="w-[70px] text-right text-[11.5px] font-semibold text-text-faint">{{ r.ago }}</span>
                    </li>
                </ul>
            </section>
        </div>

        <!-- EXERCICES -->
        <div v-else-if="current === 'exercices'" class="flex flex-col gap-2">
            <p class="mb-1 text-[12.5px] font-medium text-text-muted">
                1RM : la charge estimée pour une seule répétition (formule d'Epley). Tendance : sur 4 semaines, comparée aux 4 précédentes.
            </p>
            <Link
                v-for="e in exercises"
                :key="e.slug"
                :href="routes.progressExercise(e.slug)"
                class="flex items-center gap-3 rounded-2xl bg-surface p-2.5 text-text hover:bg-surface-hover"
            >
                <span class="size-14 shrink-0 overflow-hidden rounded-xl"><img :src="e.image" alt="" class="size-full object-cover" loading="lazy" /></span>
                <span class="flex min-w-0 flex-1 flex-col gap-0.5">
                    <span class="truncate text-[14.5px] font-bold">{{ e.name }}</span>
                    <span class="truncate text-[12px] font-medium text-text-muted">
                        {{ e.last_ago }}<template v-if="e.last_top"> · {{ formatSet(e.last_top) }}</template>
                    </span>
                    <span v-if="e.next" class="truncate text-[12px] font-bold" :class="trendTone(e.next.trend)">
                        Prochaine fois : {{ formatKg(e.next.weight, 2) }} ({{ trendWord[e.next.trend] }})
                    </span>
                </span>
                <span class="flex shrink-0 flex-col items-end gap-0.5 text-right">
                    <span v-if="e.best_e1rm" class="font-display text-[22px] leading-none font-bold tabular-nums">{{ formatNumber(e.best_e1rm, 0) }}<span class="text-[13px] text-text-muted"> kg</span></span>
                    <span v-if="e.best_e1rm" class="text-[10.5px] font-bold text-text-faint">1RM</span>
                    <span v-if="e.trend !== null" class="text-[11.5px] font-bold" :class="e.trend >= 0 ? 'text-accent' : 'text-danger'">
                        {{ e.trend >= 0 ? '▲' : '▼' }} {{ formatDelta(e.trend) }}
                    </span>
                </span>
            </Link>
        </div>

        <!-- MUSCLES -->
        <div v-else-if="current === 'muscles'" class="flex flex-col gap-3">
            <section class="flex flex-col gap-3 rounded-[22px] bg-surface p-4">
                <div class="grid grid-cols-2 gap-[3px] rounded-xl bg-bg p-[3px]" role="radiogroup" aria-label="Période">
                    <button
                        v-for="o in [{ v: 'week', l: '7 derniers jours' }, { v: 'month', l: '4 dernières semaines' }]"
                        :key="o.v"
                        type="button"
                        role="radio"
                        :aria-checked="span === o.v"
                        class="h-8 rounded-[9px] text-[12.5px] font-bold"
                        :class="span === o.v ? 'bg-surface-3 text-text' : 'text-text-faint'"
                        @click="span = o.v"
                    >
                        {{ o.l }}
                    </button>
                </div>
                <BodyMap :intensity="muscleIntensity" :height="230" label="Muscles travaillés sur la période" />
            </section>

            <section class="flex flex-col gap-2.5 rounded-[22px] bg-surface p-4">
                <h2 class="text-[15px] font-bold">Séries par semaine</h2>
                <p class="text-[12px] font-medium text-text-muted">
                    Repère pour progresser : {{ targetLow }} à {{ targetHigh }} séries par muscle et par semaine (bande claire). Une série compte à moitié pour un muscle secondaire.
                </p>
                <div v-for="row in muscles.rows" :key="row.muscle" class="flex flex-col gap-1">
                    <div class="flex items-baseline justify-between gap-2 text-[13px]">
                        <span class="font-bold">{{ row.label }}</span>
                        <span class="flex items-baseline gap-2">
                            <span v-if="row.strength !== null" class="text-[11px] font-bold" :class="row.strength >= 0 ? 'text-accent' : 'text-danger'" :title="'Force sur 4 semaines'">
                                force {{ row.strength >= 0 ? '▲' : '▼' }} {{ formatDelta(row.strength) }}
                            </span>
                            <span class="font-semibold tabular-nums">{{ formatNumber(muscleValue(row), 1) }}</span>
                            <span class="w-[78px] text-right text-[11px] font-bold" :class="zone(muscleValue(row)).tone">{{ zone(muscleValue(row)).text }}</span>
                        </span>
                    </div>
                    <div class="relative h-2 overflow-hidden rounded-full bg-surface-3" role="presentation">
                        <span
                            class="absolute inset-y-0 bg-[color-mix(in_srgb,var(--color-accent)_18%,transparent)]"
                            :style="{ left: `${(targetLow / meterMax) * 100}%`, width: `${((targetHigh - targetLow) / meterMax) * 100}%` }"
                        />
                        <span class="absolute inset-y-0 left-0 rounded-full bg-accent" :style="{ width: `${Math.min(100, (muscleValue(row) / meterMax) * 100)}%` }" />
                    </div>
                </div>
            </section>

            <section class="flex flex-col gap-3 rounded-[22px] bg-surface p-4">
                <h2 class="text-[15px] font-bold">Équilibres</h2>
                <p class="-mt-2 text-[12px] font-medium text-text-muted">Séries sur 4 semaines. Un déséquilibre durable expose aux douleurs d'épaule et de genou.</p>
                <div v-for="b in muscles.balances" :key="b.title" class="flex flex-col gap-1.5">
                    <div class="flex justify-between text-[13px] font-bold">
                        <span>{{ b.left }} <span class="font-semibold text-text-muted tabular-nums">{{ formatNumber(b.left_sets, 1) }}</span></span>
                        <span><span class="font-semibold text-text-muted tabular-nums">{{ formatNumber(b.right_sets, 1) }}</span> {{ b.right }}</span>
                    </div>
                    <div class="flex h-2.5 gap-[2px] overflow-hidden rounded-full" role="presentation">
                        <span class="rounded-l-full bg-accent" :style="{ flex: b.left_sets || 0.001 }" />
                        <span class="rounded-r-full bg-[color-mix(in_srgb,var(--color-accent)_40%,var(--color-surface-3))]" :style="{ flex: b.right_sets || 0.001 }" />
                    </div>
                    <span class="text-[12px] font-medium text-text-soft">{{ balanceText(b) }}</span>
                </div>
            </section>
        </div>

        <!-- CORPS -->
        <div v-else class="flex flex-col gap-3">
            <form class="flex flex-col gap-3 rounded-[22px] bg-surface p-4" @submit.prevent="weigh">
                <h2 class="text-[15px] font-bold">Pesée du jour</h2>
                <div class="flex items-center gap-2">
                    <label class="flex flex-1 items-baseline gap-2 rounded-2xl bg-bg px-4 py-2.5">
                        <input
                            v-model="weighing.kg"
                            type="text"
                            inputmode="decimal"
                            placeholder="75,5"
                            aria-label="Poids de corps en kilos"
                            class="w-full min-w-0 border-0 bg-transparent p-0 font-display text-[30px] font-bold text-text outline-none placeholder:text-text-faint"
                        />
                        <span class="font-display text-[18px] font-bold text-text-muted">kg</span>
                    </label>
                    <button type="submit" class="btn-accent h-14 px-5 text-[18px]" :disabled="weighing.processing || !weighing.kg">Noter</button>
                </div>
                <p v-if="weighing.errors.kg" class="text-[13px] text-danger">{{ weighing.errors.kg }}</p>
            </form>

            <div v-if="body.latest" class="grid grid-cols-2 gap-2">
                <StatTile label="Poids actuel" :value="formatKg(body.latest)" :hint="body.change_30d === null ? 'première pesée' : null" />
                <StatTile
                    label="Sur 30 jours"
                    :value="body.change_30d === null ? '—' : `${body.change_30d > 0 ? '+' : ''}${formatNumber(body.change_30d, 1)} kg`"
                    hint="d'écart"
                />
            </div>

            <section v-if="body.chart.length > 1" class="rounded-[22px] bg-surface p-4">
                <h2 class="mb-1 text-[15px] font-bold">Poids de corps</h2>
                <LineChart :data="body.chart" label="Évolution du poids de corps" :format="(v) => formatKg(v)" />
            </section>

            <section v-if="body.lifts.length" class="rounded-[22px] bg-surface p-4">
                <h2 class="text-[15px] font-bold">Force relative</h2>
                <p class="mb-3 text-[12px] font-medium text-text-muted">Meilleur 1RM estimé, rapporté à ton poids de corps.</p>
                <ul class="flex flex-col gap-2">
                    <li v-for="lift in body.lifts" :key="lift.slug" class="flex items-baseline justify-between gap-3 text-[13.5px]">
                        <Link :href="routes.progressExercise(lift.slug)" class="font-bold text-text">{{ lift.name }}</Link>
                        <span class="font-semibold text-text-muted">
                            {{ formatKg(lift.e1rm) }}
                            <span v-if="lift.ratio" class="ml-2 font-display text-[20px] font-bold text-text">× {{ formatNumber(lift.ratio, 2) }}</span>
                        </span>
                    </li>
                </ul>
                <p v-if="!body.latest" class="mt-2 text-[12px] font-medium text-text-faint">Note ton poids pour voir le rapport.</p>
            </section>

            <section v-if="body.entries.length" class="rounded-[22px] bg-surface p-4">
                <h2 class="mb-2 text-[15px] font-bold">Pesées</h2>
                <ul class="flex flex-col">
                    <li v-for="entry in body.entries" :key="entry.id" class="flex items-center justify-between border-b border-divider py-2 text-[13.5px] last:border-0">
                        <span class="font-medium text-text-soft">{{ entry.label }}</span>
                        <span class="flex items-center gap-3">
                            <span class="font-bold tabular-nums">{{ formatKg(entry.kg) }}</span>
                            <button type="button" class="text-[12px] font-bold text-danger" :aria-label="`Supprimer la pesée du ${entry.label}`" @click="removeWeighing(entry)">✕</button>
                        </span>
                    </li>
                </ul>
            </section>
        </div>
    </div>

    <TabBar active="progress" />
</template>
