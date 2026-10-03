<script setup>
import { computed, ref } from 'vue';

/**
 * Courbe d'une série unique : trait de 2 px, aire en lavis à 10 %, le dernier
 * point marqué et étiqueté. Toucher le graphique place un repère sur le point
 * le plus proche et affiche sa valeur. Un tableau caché reprend les chiffres.
 */
const props = defineProps({
    data: { type: Array, required: true },
    label: { type: String, required: true },
    format: { type: Function, default: (value) => String(value) },
    height: { type: Number, default: 170 },
});

const WIDTH = 320;
const LEFT = 38;
const RIGHT = 14;
const TOP = 26;
const BOTTOM = 20;
const active = ref(null);
const svg = ref(null);

const points = computed(() => props.data.map((d, index) => ({ ...d, index })).filter((d) => d.value !== null && d.value !== undefined));

const range = computed(() => {
    const values = points.value.map((p) => p.value);
    let low = Math.min(...values);
    let high = Math.max(...values);

    if (low === high) {
        low -= low * 0.1 || 1;
        high += high * 0.1 || 1;
    }

    const pad = (high - low) * 0.15;

    return [Math.max(0, low - pad), high + pad];
});

const plotWidth = WIDTH - LEFT - RIGHT;
const plotHeight = computed(() => props.height - TOP - BOTTOM);
const x = (index) => LEFT + (props.data.length > 1 ? (index / (props.data.length - 1)) * plotWidth : plotWidth / 2);
const y = (value) => TOP + plotHeight.value * (1 - (value - range.value[0]) / (range.value[1] - range.value[0]));

const line = computed(() => points.value.map((p, i) => `${i ? 'L' : 'M'}${x(p.index).toFixed(1)},${y(p.value).toFixed(1)}`).join(''));
const area = computed(() => {
    if (points.value.length < 2) {
        return '';
    }

    const base = TOP + plotHeight.value;

    return `${line.value}L${x(points.value.at(-1).index)},${base}L${x(points.value[0].index)},${base}Z`;
});

const ticks = computed(() => [0, 0.5, 1].map((f) => {
    const value = range.value[0] + (range.value[1] - range.value[0]) * f;

    return { value, y: y(value) };
}));

const last = computed(() => points.value.at(-1));
const tip = computed(() => (active.value === null ? null : points.value.find((p) => p.index === active.value)));

function pick(event) {
    const box = svg.value.getBoundingClientRect();
    const px = ((event.clientX - box.left) / box.width) * WIDTH;
    const nearest = points.value.reduce((best, p) => (Math.abs(x(p.index) - px) < Math.abs(x(best.index) - px) ? p : best), points.value[0]);
    active.value = nearest?.index ?? null;
}
</script>

<template>
    <figure class="m-0">
        <div class="relative">
            <svg
                ref="svg"
                :viewBox="`0 0 ${WIDTH} ${props.height}`"
                class="block w-full cursor-crosshair touch-pan-y"
                role="img"
                :aria-label="props.label"
                @mousemove="pick"
                @click="pick"
                @mouseleave="active = null"
            >
                <template v-for="tick in ticks" :key="tick.y">
                    <line :x1="LEFT" :x2="WIDTH - RIGHT" :y1="tick.y" :y2="tick.y" stroke="var(--color-divider)" stroke-width="1" />
                    <text :x="LEFT - 6" :y="tick.y + 3.5" text-anchor="end" font-size="9.5" fill="var(--color-text-faint)" font-weight="600">
                        {{ props.format(tick.value) }}
                    </text>
                </template>
                <text v-if="props.data.length" :x="LEFT" :y="props.height - 5" font-size="9.5" fill="var(--color-text-faint)" font-weight="600">{{ props.data[0].label }}</text>
                <text v-if="props.data.length > 1" :x="WIDTH - RIGHT" :y="props.height - 5" text-anchor="end" font-size="9.5" fill="var(--color-text-faint)" font-weight="600">
                    {{ props.data.at(-1).label }}
                </text>

                <path v-if="area" :d="area" fill="var(--color-accent)" fill-opacity="0.1" />
                <path :d="line" fill="none" stroke="var(--color-accent)" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" />

                <line v-if="tip" :x1="x(tip.index)" :x2="x(tip.index)" :y1="TOP" :y2="TOP + plotHeight" stroke="var(--color-line-strong)" stroke-width="1" />
                <circle v-if="tip" :cx="x(tip.index)" :cy="y(tip.value)" r="5" fill="var(--color-accent)" stroke="var(--color-surface)" stroke-width="2" />

                <template v-if="last">
                    <circle :cx="x(last.index)" :cy="y(last.value)" r="4.5" fill="var(--color-accent)" stroke="var(--color-surface)" stroke-width="2" />
                    <text :x="x(last.index)" :y="y(last.value) - 10" text-anchor="end" font-size="11" font-weight="800" fill="var(--color-text)">
                        {{ props.format(last.value) }}
                    </text>
                </template>
            </svg>
            <div
                v-if="tip"
                class="pointer-events-none absolute -translate-x-1/2 rounded-lg border border-line bg-surface-2 px-2 py-1 text-[11.5px] font-bold whitespace-nowrap shadow-lg"
                :style="{ left: `${Math.min(85, Math.max(15, (x(tip.index) / WIDTH) * 100))}%`, top: '0' }"
            >
                {{ tip.label }} · {{ props.format(tip.value) }}
            </div>
        </div>
        <table class="sr-only">
            <caption>{{ props.label }}</caption>
            <tr v-for="d in props.data" :key="d.label + d.value"><th scope="row">{{ d.label }}</th><td>{{ d.value === null ? '—' : props.format(d.value) }}</td></tr>
        </table>
    </figure>
</template>
