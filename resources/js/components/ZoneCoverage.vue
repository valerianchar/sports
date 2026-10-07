<script setup>
import { computed, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import BottomSheet from './BottomSheet.vue';
import { getJson } from '../http';
import { routes } from '../routes';

/**
 * Les zones de chaque muscle travaillé par la séance — le haut, le milieu, le
 * bas, l'intérieur, l'extérieur des pectoraux… — et celles qui manquent :
 * toucher une zone manquante demande à l'assistant de quoi la travailler.
 */
const props = defineProps({
    items: { type: Array, required: true },
    catalog: { type: Object, required: true },
    // Le matériel préféré pour les propositions (« machine », « free », « bodyweight »).
    equipment: { type: String, default: null },
});

const emit = defineEmits(['add', 'info']);

const page = usePage();

const worked = computed(() =>
    props.items.map((item) => props.catalog[item.exercise]).filter((exercise) => exercise && !['cardio', 'mobilite'].includes(exercise.group)),
);

const groups = computed(() =>
    (page.props.muscle_zones ?? [])
        .filter((group) => worked.value.some((exercise) => exercise.primary.some((muscle) => group.muscles.includes(muscle))))
        .map((group) => {
            const zones = group.zones.map((zone) => ({
                ...zone,
                by: worked.value.filter((exercise) => (exercise.zones ?? []).includes(zone.key)).map((exercise) => exercise.name),
            }));

            return { ...group, zones, covered: zones.filter((zone) => zone.by.length).length };
        }),
);

const missing = computed(() => groups.value.reduce((total, group) => total + group.zones.length - group.covered, 0));

// La feuille des propositions pour une zone manquante.
const picking = ref(null);
const open = computed({
    get: () => picking.value !== null,
    set: (value) => {
        if (!value) {
            picking.value = null;
        }
    },
});
const suggestions = ref([]);
const loading = ref(false);
const failed = ref(false);

async function pick(group, zone) {
    picking.value = { group, zone };
    suggestions.value = [];
    failed.value = false;
    loading.value = true;

    try {
        const query = new URLSearchParams(props.items.map((item) => ['exclude[]', item.exercise]));

        if (props.equipment) {
            query.append('equipment', props.equipment);
        }

        suggestions.value = (await getJson(`${routes.zoneExercises(zone.key)}?${query}`)).exercises;
    } catch {
        failed.value = true;
    } finally {
        loading.value = false;
    }
}

function add(exercise) {
    emit('add', exercise);
    picking.value = null;
}

function info(exercise) {
    const back = picking.value;
    picking.value = null;
    // La fiche passe devant ; la feuille revient quand on la ferme.
    emit('info', exercise, () => (picking.value = back));
}
</script>

<template>
    <section v-if="groups.length" class="flex flex-col gap-3 rounded-[22px] bg-surface p-4" aria-labelledby="zones-title">
        <div class="flex items-baseline justify-between gap-3">
            <h2 id="zones-title" class="display text-[22px] font-bold">Zones travaillées</h2>
            <span class="text-[12px] font-bold" :class="missing ? 'text-prep' : 'text-accent'">
                {{ missing ? `${missing} zone${missing > 1 ? 's' : ''} oubliée${missing > 1 ? 's' : ''}` : 'Tout est couvert ✓' }}
            </span>
        </div>

        <div v-for="group in groups" :key="group.key" class="flex flex-col gap-1.5">
            <div class="flex items-baseline justify-between">
                <span class="text-[13.5px] font-extrabold">{{ group.label }}</span>
                <span class="text-[12px] font-bold tabular-nums" :class="group.covered === group.zones.length ? 'text-accent' : 'text-text-muted'">
                    {{ group.covered }}/{{ group.zones.length }}
                </span>
            </div>
            <div class="flex flex-wrap gap-1.5">
                <template v-for="zone in group.zones" :key="zone.key">
                    <span
                        v-if="zone.by.length"
                        class="rounded-full bg-accent/15 px-3 py-1.5 text-[12.5px] font-bold text-text"
                        :title="`${zone.hint} — ${zone.by.join(', ')}`"
                    >
                        <span class="text-accent" aria-hidden="true">✓</span> {{ zone.label }}
                    </span>
                    <button
                        v-else
                        type="button"
                        class="rounded-full border-[1.5px] border-dashed border-prep px-3 py-1 text-[12.5px] font-bold text-prep"
                        :aria-label="`${group.label} : ${zone.label} non travaillé — proposer un exercice`"
                        @click="pick(group, zone)"
                    >
                        + {{ zone.label }}
                    </button>
                </template>
            </div>
        </div>

        <p v-if="missing" class="text-[12px] font-medium text-text-faint">Touche une zone oubliée : l'assistant propose de quoi la travailler.</p>

        <BottomSheet
            v-model:open="open"
            :title="picking ? `${picking.group.label} : ${picking.zone.label.toLowerCase()}` : ''"
            :description="picking ? `${picking.zone.hint.charAt(0).toUpperCase()}${picking.zone.hint.slice(1)}.` : ''"
        >
            <p v-if="loading" class="text-[14px] font-semibold text-text-muted">L'assistant cherche…</p>
            <p v-else-if="failed" class="text-[14px] font-semibold text-danger">Pas de proposition pour le moment : vérifie ta connexion.</p>
            <p v-else-if="!suggestions.length" class="text-[14px] font-semibold text-text-muted">Aucun autre exercice pour cette zone.</p>
            <ul v-else class="flex flex-col gap-2">
                <li v-for="(exercise, rank) in suggestions" :key="exercise.slug" class="flex items-center gap-2">
                    <span class="flex min-w-0 flex-1 items-center gap-3 rounded-2xl bg-surface p-2" :class="rank === 0 ? 'ring-[1.5px] ring-accent' : ''">
                        <span class="size-12 shrink-0 overflow-hidden rounded-[10px]">
                            <img :src="exercise.images[0]" alt="" class="size-full object-cover" />
                        </span>
                        <span class="flex min-w-0 flex-col gap-0.5">
                            <span v-if="rank === 0" class="text-[10.5px] font-extrabold tracking-[0.1em] text-accent uppercase">Conseillé</span>
                            <span class="text-[14.5px] leading-tight font-bold">{{ exercise.name }}</span>
                            <span class="text-[12px] font-medium text-text-muted">{{ exercise.equipment_label }}</span>
                        </span>
                    </span>
                    <button type="button" class="iconbtn size-10 shrink-0 bg-surface-2! font-serif text-[16px] font-extrabold text-accent italic" :aria-label="`Comment faire : ${exercise.name}`" @click="info(exercise)">i</button>
                    <button type="button" class="h-10 shrink-0 rounded-full bg-accent px-3.5 text-[13px] font-extrabold text-on-accent" :aria-label="`Ajouter ${exercise.name}`" @click="add(exercise)">Ajouter</button>
                </li>
            </ul>
        </BottomSheet>
    </section>
</template>
