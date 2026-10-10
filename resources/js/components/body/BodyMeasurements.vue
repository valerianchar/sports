<script setup>
import { computed, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import LineChart from '../charts/LineChart.vue';
import { formatNumber } from '../../format';
import { routes } from '../../routes';

/**
 * Les mensurations de l'onglet « Corps » : une mesure à la fois au mètre
 * ruban, la dernière valeur de chacune avec son évolution, une courbe par
 * mesure et les saisies jour par jour. Pour une perte de poids, le tour de
 * taille baisse souvent quand la balance stagne.
 */
const props = defineProps({
    measurements: { type: Object, required: true },
});

const kinds = computed(() => props.measurements.kinds);
const byKey = computed(() => Object.fromEntries(kinds.value.map((k) => [k.key, k])));
const measured = computed(() => kinds.value.filter((k) => k.latest !== null));
const charted = computed(() => kinds.value.filter((k) => k.chart.length > 1));

const formatCm = (cm) => `${formatNumber(cm, 1)} cm`;
const formatChange = (cm) => (cm === null ? '—' : `${cm > 0 ? '+' : cm < 0 ? '−' : ''}${formatNumber(Math.abs(cm), 1)} cm`);

// Saisie : la mesure choisie, en centimètres, virgule acceptée.
const form = useForm({ kind: 'waist', value: '' });
const selected = computed(() => byKey.value[form.kind]);
const error = computed(() => Object.values(form.errors)[0] ?? null);

function pick(kind) {
    form.kind = kind;
    form.value = '';
    form.clearErrors();
}

function save() {
    form.transform((data) => ({ [data.kind]: String(data.value).replace(',', '.').trim() })).post(routes.bodyMeasurements, {
        preserveScroll: true,
        preserveState: true,
        only: ['body'],
        onSuccess: () => {
            chartKey.value = form.kind;
            form.reset('value');
        },
    });
}

// Courbe : la mesure choisie, parmi celles qui ont au moins deux points.
const chartKey = ref('waist');
const chartKind = computed(() => charted.value.find((k) => k.key === chartKey.value) ?? charted.value[0] ?? null);

const shortValues = (entry) =>
    Object.entries(entry.values)
        .map(([key, cm]) => `${byKey.value[key]?.label ?? key} ${formatNumber(cm, 1)}`)
        .join(' · ');

const remove = (entry) => {
    if (confirm(`Supprimer les mensurations du ${entry.label} ?`)) {
        router.delete(`${routes.bodyMeasurements}/${entry.id}`, { preserveScroll: true, preserveState: true, only: ['body'] });
    }
};
</script>

<template>
    <form class="flex flex-col gap-3 rounded-[22px] bg-surface p-4" @submit.prevent="save">
        <div>
            <h2 class="text-[15px] font-bold">Mensurations</h2>
            <p class="text-[12px] font-medium text-text-muted">Au mètre ruban, le matin, toujours au même endroit. Une mesure à la fois suffit.</p>
        </div>
        <div class="no-scrollbar -mx-4 flex gap-1.5 overflow-x-auto px-4" role="radiogroup" aria-label="Mesure à noter">
            <button
                v-for="k in kinds"
                :key="k.key"
                type="button"
                role="radio"
                :aria-checked="form.kind === k.key"
                class="h-11 shrink-0 rounded-full px-3.5 text-[13.5px] font-bold"
                :class="form.kind === k.key ? 'bg-accent text-on-accent' : 'bg-surface-2 text-text-soft'"
                @click="pick(k.key)"
            >
                {{ k.label }}
            </button>
        </div>
        <p class="text-[12px] font-medium text-text-faint">{{ selected.hint }}</p>
        <div class="flex items-center gap-2">
            <label class="flex flex-1 items-baseline gap-2 rounded-2xl bg-bg px-4 py-2.5">
                <input
                    v-model="form.value"
                    type="text"
                    inputmode="decimal"
                    :placeholder="selected.latest === null ? '80' : formatNumber(selected.latest, 1)"
                    :aria-label="`${selected.label} en centimètres`"
                    class="w-full min-w-0 border-0 bg-transparent p-0 font-display text-[30px] font-bold text-text outline-none placeholder:text-text-faint"
                />
                <span class="font-display text-[18px] font-bold text-text-muted">cm</span>
            </label>
            <button type="submit" class="btn-accent h-14 px-5 text-[18px]" :disabled="form.processing || !form.value">Noter</button>
        </div>
        <p v-if="error" class="text-[13px] text-danger">{{ error }}</p>
    </form>

    <div v-if="measured.length" class="grid grid-cols-2 gap-2">
        <div v-for="k in measured" :key="k.key" class="flex min-w-0 flex-col gap-1 rounded-[18px] bg-surface p-3.5">
            <span class="truncate text-[11px] font-bold text-text-muted">{{ k.label }}</span>
            <span class="truncate font-display text-[28px] leading-none font-bold tabular-nums">{{ formatCm(k.latest) }}</span>
            <span class="truncate text-[11.5px] font-semibold text-text-soft">
                <template v-if="k.change_30d !== null">{{ formatChange(k.change_30d) }} <span class="text-text-faint">sur 30 jours</span></template>
                <template v-else>le {{ k.latest_label }}</template>
            </span>
            <span v-if="k.change_total !== null" class="truncate text-[11.5px] font-semibold text-text-soft">
                {{ formatChange(k.change_total) }} <span class="text-text-faint">depuis le {{ k.first_label }}</span>
            </span>
        </div>
    </div>

    <section v-if="chartKind" class="flex flex-col gap-2 rounded-[22px] bg-surface p-4">
        <h2 class="text-[15px] font-bold">Évolution, en centimètres</h2>
        <div v-if="charted.length > 1" class="no-scrollbar -mx-4 flex gap-[3px] overflow-x-auto px-4" role="radiogroup" aria-label="Mesure affichée">
            <div class="flex gap-[3px] rounded-xl bg-bg p-[3px]">
                <button
                    v-for="k in charted"
                    :key="k.key"
                    type="button"
                    role="radio"
                    :aria-checked="chartKind.key === k.key"
                    class="h-11 shrink-0 rounded-[9px] px-3 text-[12.5px] font-bold"
                    :class="chartKind.key === k.key ? 'bg-surface-3 text-text' : 'text-text-faint'"
                    @click="chartKey = k.key"
                >
                    {{ k.label }}
                </button>
            </div>
        </div>
        <LineChart :key="chartKind.key" :data="chartKind.chart" :label="`Évolution : ${chartKind.label.toLowerCase()}, en centimètres`" :format="(v) => formatNumber(v, 1)" />
    </section>

    <section v-if="measurements.entries.length" class="rounded-[22px] bg-surface p-4">
        <h2 class="mb-1 text-[15px] font-bold">Mesures prises <span class="text-[12px] font-semibold text-text-faint">en cm</span></h2>
        <ul class="flex flex-col">
            <li v-for="entry in measurements.entries" :key="entry.id" class="flex items-center gap-3 border-b border-divider py-1 text-[13.5px] last:border-0">
                <span class="flex min-w-0 flex-1 flex-col">
                    <span class="font-medium text-text-soft">{{ entry.label }}</span>
                    <span class="text-[12.5px] font-bold tabular-nums">{{ shortValues(entry) }}</span>
                </span>
                <button
                    type="button"
                    class="flex size-11 shrink-0 items-center justify-center text-[13px] font-bold text-danger"
                    :aria-label="`Supprimer les mensurations du ${entry.label}`"
                    @click="remove(entry)"
                >
                    ✕
                </button>
            </li>
        </ul>
    </section>
</template>
