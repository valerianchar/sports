<script setup>
import { computed, ref } from 'vue';
import { normalize } from '../workout';

/**
 * La bibliothèque, rangée par muscle ou par machine, filtrée par groupe et par
 * recherche. En mode sélection (depuis l'éditeur), toucher un exercice le coche
 * et le « i » ouvre sa fiche ; sinon, toucher ouvre directement la fiche.
 */
const props = defineProps({
    exercises: { type: Array, required: true },
    groups: { type: Array, required: true },
    picking: { type: Boolean, default: false },
    picked: { type: Array, default: () => [] },
    // Groupe présélectionné à l'ouverture (« Choisir un remplaçant » : celui de l'exercice remplacé).
    initialGroup: { type: String, default: 'all' },
});

const emit = defineEmits(['info', 'toggle']);

const query = ref('');
const byMuscle = ref(true);
const group = ref(props.initialGroup);

const machines = computed(() =>
    [...new Map(props.exercises.map((e) => [e.equipment, e.equipment_label])).entries()]
        .map(([value, label]) => ({ value, label }))
        .sort((a, b) => a.label.localeCompare(b.label, 'fr')),
);

const stats = computed(() => `${props.exercises.length} exercices · ${machines.value.length} machines et équipements`);

const sections = computed(() => {
    const q = normalize(query.value.trim());
    const list = props.exercises.filter(
        (e) =>
            (group.value === 'all' || e.group === group.value) &&
            (!q || normalize(`${e.name} ${e.equipment_label}`).includes(q)),
    );
    const order = byMuscle.value ? props.groups : machines.value;
    const key = byMuscle.value ? 'group' : 'equipment';

    return order
        .map((section) => ({ ...section, items: list.filter((e) => e[key] === section.value) }))
        .filter((section) => section.items.length);
});

function tap(exercise) {
    if (props.picking) {
        emit('toggle', exercise.slug);
    } else {
        emit('info', exercise.slug);
    }
}

const chips = computed(() => [{ value: 'all', label: 'Tous' }, ...props.groups]);
</script>

<template>
    <div class="flex min-h-0 flex-1 flex-col">
        <div class="flex flex-col gap-3.5 px-5 pt-2">
            <slot name="header">
                <div>
                    <h1 class="display m-0 text-[56px] leading-[0.88] font-extrabold">Exercices</h1>
                    <p class="mt-2 text-[13px] font-medium text-text-muted">{{ stats }}</p>
                </div>
            </slot>

            <label class="flex h-[46px] items-center gap-2.5 rounded-full bg-surface px-4 text-text-muted focus-within:outline-2 focus-within:outline-accent">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7" /><path d="M20 20l-3.5-3.5" /></svg>
                <input
                    v-model="query"
                    type="search"
                    enterkeyhint="search"
                    placeholder="Rechercher un exercice, une machine…"
                    aria-label="Rechercher un exercice"
                    class="min-w-0 flex-1 border-none bg-transparent text-[16px] font-medium text-text outline-none [&::-webkit-search-cancel-button]:hidden"
                />
            </label>

            <div class="grid grid-cols-2 gap-1 rounded-[14px] bg-surface p-1" role="radiogroup" aria-label="Ranger">
                <button
                    v-for="option in [{ muscle: true, label: 'Par muscle' }, { muscle: false, label: 'Par machine' }]"
                    :key="option.label"
                    type="button"
                    role="radio"
                    :aria-checked="byMuscle === option.muscle"
                    class="h-9 rounded-[10px] text-[13px] font-bold"
                    :class="byMuscle === option.muscle ? 'bg-text text-bg' : 'bg-transparent text-text-muted'"
                    @click="byMuscle = option.muscle"
                >
                    {{ option.label }}
                </button>
            </div>
        </div>

        <div class="no-scrollbar flex shrink-0 gap-2 overflow-x-auto px-5 pt-3.5 pb-1">
            <button
                v-for="chip in chips"
                :key="chip.value"
                type="button"
                class="h-[34px] shrink-0 rounded-full px-3.5 text-[13px] font-bold whitespace-nowrap"
                :class="
                    group === chip.value
                        ? 'bg-accent text-on-accent'
                        : 'border-[1.5px] border-line bg-transparent text-text-soft hover:border-text-faint'
                "
                :aria-pressed="group === chip.value"
                @click="group = chip.value"
            >
                {{ chip.label }}
            </button>
        </div>

        <div class="no-scrollbar flex flex-1 flex-col gap-[22px] overflow-y-auto px-5 pt-2.5" :class="props.picking ? 'pb-[110px]' : 'pb-6'">
            <section v-for="section in sections" :key="section.value" class="flex flex-col gap-2">
                <div class="flex items-baseline justify-between px-0.5">
                    <h2 class="display text-[22px] font-bold">{{ section.label }}</h2>
                    <span class="text-[12px] font-bold text-text-faint">{{ section.items.length }}</span>
                </div>

                <div v-for="exercise in section.items" :key="exercise.slug" class="flex items-center gap-1 rounded-2xl bg-surface p-2">
                    <button
                        type="button"
                        class="flex min-w-0 flex-1 items-center gap-3 rounded-xl bg-transparent px-1.5 py-1 text-left text-text hover:bg-surface-hover"
                        :aria-pressed="props.picking ? props.picked.includes(exercise.slug) : undefined"
                        @click="tap(exercise)"
                    >
                        <span class="size-12 shrink-0 overflow-hidden rounded-[10px]">
                            <img :src="exercise.images[0]" alt="" loading="lazy" decoding="async" class="size-full object-cover" />
                        </span>
                        <span class="flex min-w-0 flex-1 flex-col gap-0.5">
                            <span class="text-[15px] font-bold">{{ exercise.name }}</span>
                            <span class="text-[12.5px] font-medium text-text-muted">
                                {{ byMuscle ? exercise.equipment_label : exercise.group_label }}
                            </span>
                        </span>

                        <span v-if="!props.picking" class="tag">{{ exercise.mode === 'time' ? 'DURÉE' : 'REPS' }}</span>
                        <span
                            v-else-if="props.picked.includes(exercise.slug)"
                            class="flex size-7 shrink-0 items-center justify-center rounded-full bg-accent text-on-accent"
                        >
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.5 4.5L19 7.5" /></svg>
                        </span>
                        <span v-else class="size-7 shrink-0 rounded-full border-[1.5px] border-line-strong" />
                    </button>

                    <button
                        v-if="props.picking"
                        type="button"
                        class="iconbtn size-9 bg-surface-2! font-serif text-[15px] font-extrabold text-text-soft italic"
                        :aria-label="`Comment faire : ${exercise.name}`"
                        @click="emit('info', exercise.slug)"
                    >
                        i
                    </button>
                </div>
            </section>

            <p v-if="!sections.length" class="py-10 text-center text-[14px] text-text-muted">Aucun exercice trouvé.</p>
        </div>
    </div>
</template>
