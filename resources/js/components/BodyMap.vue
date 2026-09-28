<script setup>
import { computed } from 'vue';
import { BODY } from '../data/body';

/**
 * Silhouette de face et de dos, muscles allumés selon leur sollicitation :
 * `intensity` associe à chaque muscle une valeur de 0 à 1. Trois paliers de
 * citron suffisent à lire « beaucoup, un peu, à peine » d'un coup d'œil.
 */
const props = defineProps({
    intensity: { type: Object, required: true },
    height: { type: Number, default: 220 },
    label: { type: String, default: 'Muscles sollicités' },
});

const sides = [BODY.front, BODY.back];

function fill(slug) {
    const value = props.intensity[slug] ?? 0;

    if (value <= 0) {
        return 'var(--color-surface-3)';
    }

    if (value > 0.67) {
        return 'var(--color-accent)';
    }

    return value > 0.34
        ? 'color-mix(in srgb, var(--color-accent) 62%, var(--color-surface-3))'
        : 'color-mix(in srgb, var(--color-accent) 32%, var(--color-surface-3))';
}

const width = computed(() => props.height / 2);
</script>

<template>
    <div
        class="flex items-center justify-center gap-[6%]"
        :role="props.label ? 'img' : undefined"
        :aria-label="props.label || undefined"
        :aria-hidden="props.label ? undefined : 'true'"
    >
        <svg
            v-for="(side, index) in sides"
            :key="index"
            :viewBox="side.viewBox"
            :width="width"
            :height="props.height"
            class="block shrink-0"
            aria-hidden="true"
        >
            <path v-for="(d, i) in side.decor" :key="`d${i}`" :d="d" fill="var(--color-divider)" />
            <template v-for="(paths, slug) in side.muscles" :key="slug">
                <path v-for="(d, i) in paths" :key="i" :d="d" :fill="fill(slug)" class="transition-[fill] duration-300" />
            </template>
            <path :d="side.outline" fill="none" stroke="var(--color-line-strong)" stroke-width="2" vector-effect="non-scaling-stroke" />
        </svg>
    </div>
</template>
