<script setup>
import { computed, ref } from 'vue';

/**
 * Histogramme d'une série unique : barres de 24 px au plus, coin arrondi au
 * bout de la donnée, quadrillage discret, la dernière barre (la période en
 * cours) en citron et les autres atténuées. Toucher une barre affiche sa
 * valeur. Un tableau caché donne les mêmes chiffres aux lecteurs d'écran.
 */
const props = defineProps({
    data: { type: Array, required: true },
    label: { type: String, required: true },
    format: { type: Function, default: (value) => String(value) },
    height: { type: Number, default: 150 },
    axis: { type: Boolean, default: true },
    highlightLast: { type: Boolean, default: true },
});

const WIDTH = 320;
const LEFT = computed(() => (props.axis ? 34 : 0));
const TOP = 22;
const BOTTOM = computed(() => (props.axis ? 20 : 4));
const active = ref(null);

const max = computed(() => {
    const peak = Math.max(0, ...props.data.map((d) => d.value ?? 0));

    if (!peak) {
        return 1;
    }

    // Une borne ronde : 1, 2 ou 5 × une puissance de dix.
    const power = 10 ** Math.floor(Math.log10(peak));

    return [1, 2, 2.5, 5, 10].map((m) => m * power).find((m) => m >= peak);
});

const plotHeight = computed(() => props.height - TOP - BOTTOM.value);
const slot = computed(() => (WIDTH - LEFT.value) / Math.max(1, props.data.length));
const barWidth = computed(() => Math.min(24, slot.value - 2));

const bars = computed(() =>
    props.data.map((d, index) => {
        const h = d.value ? Math.max(3, (d.value / max.value) * plotHeight.value) : 0;
        const x = LEFT.value + index * slot.value + (slot.value - barWidth.value) / 2;
        const y = TOP + plotHeight.value - h;
        const r = Math.min(4, h, barWidth.value / 2);

        return {
            ...d,
            index,
            x,
            y,
            h,
            // Coin arrondi en haut seulement : la barre part carrée de la ligne de base.
            path: h ? `M${x},${y + h}V${y + r}Q${x},${y} ${x + r},${y}H${x + barWidth.value - r}Q${x + barWidth.value},${y} ${x + barWidth.value},${y + r}V${y + h}Z` : '',
        };
    }),
);

const ticks = computed(() => [0, 0.5, 1].map((f) => ({ value: max.value * f, y: TOP + plotHeight.value * (1 - f) })));

const labelled = (index) => index === 0 || index === props.data.length - 1 || index === Math.floor((props.data.length - 1) / 2);

const fill = (bar) =>
    active.value === bar.index || (props.highlightLast && bar.index === props.data.length - 1)
        ? 'var(--color-accent)'
        : 'color-mix(in srgb, var(--color-accent) 45%, var(--color-surface-3))';

const tip = computed(() => (active.value === null ? null : bars.value[active.value]));
</script>

<template>
    <figure class="m-0">
        <div class="relative">
            <svg :viewBox="`0 0 ${WIDTH} ${props.height}`" class="block w-full" role="img" :aria-label="props.label" @mouseleave="active = null">
                <g v-if="props.axis">
                    <template v-for="tick in ticks" :key="tick.y">
                        <line :x1="LEFT" :x2="WIDTH" :y1="tick.y" :y2="tick.y" stroke="var(--color-divider)" stroke-width="1" />
                        <text :x="LEFT - 6" :y="tick.y + 3.5" text-anchor="end" font-size="9.5" fill="var(--color-text-faint)" font-weight="600">
                            {{ tick.value ? props.format(tick.value) : '0' }}
                        </text>
                    </template>
                </g>
                <g v-for="bar in bars" :key="bar.index">
                    <path v-if="bar.path" :d="bar.path" :fill="fill(bar)" class="transition-[fill] duration-150" />
                    <!-- Cible de toucher plus large que la barre. -->
                    <rect
                        :x="LEFT + bar.index * slot"
                        :y="TOP"
                        :width="slot"
                        :height="plotHeight"
                        fill="transparent"
                        class="cursor-pointer"
                        @mouseenter="active = bar.index"
                        @click="active = active === bar.index ? null : bar.index"
                    />
                    <text
                        v-if="props.axis && labelled(bar.index)"
                        :x="bar.index === 0 ? bar.x : bar.index === props.data.length - 1 ? bar.x + barWidth : bar.x + barWidth / 2"
                        :y="props.height - 5"
                        :text-anchor="bar.index === 0 ? 'start' : bar.index === props.data.length - 1 ? 'end' : 'middle'"
                        font-size="9.5"
                        fill="var(--color-text-faint)"
                        font-weight="600"
                    >
                        {{ bar.label }}
                    </text>
                </g>
            </svg>
            <div
                v-if="tip"
                class="pointer-events-none absolute -translate-x-1/2 rounded-lg border border-line bg-surface-2 px-2 py-1 text-[11.5px] font-bold whitespace-nowrap shadow-lg"
                :style="{ left: `${((tip.x + barWidth / 2) / WIDTH) * 100}%`, top: '0' }"
            >
                {{ tip.label }} · {{ props.format(tip.value ?? 0) }}
            </div>
        </div>
        <table class="sr-only">
            <caption>{{ props.label }}</caption>
            <tr v-for="d in props.data" :key="d.label"><th scope="row">{{ d.label }}</th><td>{{ props.format(d.value ?? 0) }}</td></tr>
        </table>
    </figure>
</template>
