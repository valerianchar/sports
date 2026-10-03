<script setup>
import { computed } from 'vue';

/**
 * Les dernières semaines, jour par jour : une case par jour, du lundi au
 * dimanche, allumée s'il y a eu séance — plus vive s'il y en a eu deux.
 */
const props = defineProps({
    days: { type: Array, required: true },
});

const weeks = computed(() => {
    const columns = [];

    props.days.forEach((day, index) => {
        if (index % 7 === 0) {
            columns.push([]);
        }

        columns.at(-1).push(day);
    });

    return columns;
});

const fill = (day) => {
    if (!day.count) {
        return 'var(--color-surface-3)';
    }

    return day.count > 1 ? 'var(--color-accent)' : 'color-mix(in srgb, var(--color-accent) 60%, var(--color-surface-3))';
};

const title = (day) => {
    const date = new Date(`${day.date}T12:00:00`).toLocaleDateString('fr-FR', { weekday: 'short', day: 'numeric', month: 'short' });

    return day.count ? `${date} · ${day.count} séance${day.count > 1 ? 's' : ''}, ${day.minutes} min` : `${date} · repos`;
};

const sessions = computed(() => props.days.filter((d) => d.count).length);
</script>

<template>
    <figure class="m-0">
        <div
            class="grid grid-flow-col grid-rows-7 gap-[3px]"
            :style="{ gridTemplateColumns: `auto repeat(${weeks.length}, minmax(0, 1fr))` }"
            role="img"
            :aria-label="`Calendrier : ${sessions} jours d'entraînement sur les ${props.days.length} derniers jours`"
        >
            <span v-for="(letter, i) in ['L', 'M', 'M', 'J', 'V', 'S', 'D']" :key="`l${i}`" class="flex items-center pr-1 text-[9px] font-bold text-text-faint" aria-hidden="true">{{ letter }}</span>
            <template v-for="(week, w) in weeks" :key="w">
                <span v-for="day in week" :key="day.date" :title="title(day)" class="aspect-square w-full rounded-[3px]" :style="{ background: fill(day) }" />
            </template>
        </div>
        <figcaption class="mt-2 flex items-center gap-3 text-[11px] font-semibold text-text-faint">
            <span class="flex items-center gap-1"><span class="size-2.5 rounded-[2px] bg-surface-3" /> Repos</span>
            <span class="flex items-center gap-1"><span class="size-2.5 rounded-[2px] bg-[color-mix(in_srgb,var(--color-accent)_60%,var(--color-surface-3))]" /> 1 séance</span>
            <span class="flex items-center gap-1"><span class="size-2.5 rounded-[2px] bg-accent" /> 2 et plus</span>
        </figcaption>
    </figure>
</template>
