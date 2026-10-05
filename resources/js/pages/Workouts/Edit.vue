<script setup>
import { computed, reactive, ref } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import BodyMap from '../../components/BodyMap.vue';
import BottomSheet from '../../components/BottomSheet.vue';
import ExerciseLibrary from '../../components/ExerciseLibrary.vue';
import ExerciseSheet from '../../components/ExerciseSheet.vue';
import MuscleSummary from '../../components/MuscleSummary.vue';
import Stepper from '../../components/Stepper.vue';
import LoadEditor from '../../components/LoadEditor.vue';
import MachineSettingsEditor from '../../components/MachineSettingsEditor.vue';
import { unlockAudio } from '../../audio';
import { getJson, postJson } from '../../http';
import { routes } from '../../routes';
import {
    MUSCLE_REGIONS,
    bySlug,
    defaultsFor,
    exerciseIntensity,
    formatShort,
    machineFields,
    newItem,
    replaceExercise,
    settingsLabel,
    stepRest,
    stepValue,
    summary,
    usesWeight,
} from '../../workout';

const props = defineProps({
    workout: { type: Object, default: null },
    exercises: { type: Array, required: true },
    groups: { type: Array, required: true },
    maxItems: { type: Number, required: true },
    equipments: { type: Array, default: () => [] },
});

const page = usePage();
const catalog = computed(() => bySlug(props.exercises));
const machine = computed(() => page.props.machine_settings);

/*
 * Le brouillon vit ici jusqu'à « Enregistrer » : on règle, réordonne, retire
 * sans aller-retour, et le serveur reçoit la séance entière d'un coup.
 */
const name = ref(props.workout?.name ?? '');
const items = ref((props.workout?.items ?? []).map((item) => ({ ...item, key: crypto.randomUUID() })));
const snapshot = JSON.stringify({ name: name.value, items: items.value.map(({ key, ...item }) => item) });

const payloadItems = () => items.value.map(({ key, ...item }) => item);
const dirty = computed(() => JSON.stringify({ name: name.value, items: payloadItems() }) !== snapshot);

const picking = ref(false);
const picked = ref([]);
const detail = ref(null);
const detailExercise = computed(() => (detail.value ? catalog.value[detail.value] : null));
const confirmLeave = ref(false);
const confirmDelete = ref(false);
const saving = ref(false);

const muscleNames = (muscles) => muscles.map((muscle) => page.props.muscles[muscle]).join(', ');

const draftSummary = computed(() =>
    items.value.length ? summary(items.value, page.props.seconds_per_rep) : 'Ajoute des exercices pour construire ta séance.',
);

function update(index, changes) {
    const next = { ...items.value[index], ...changes };

    // En dégressif, une charge par série : la liste suit le nombre de séries,
    // une série ajoutée reprenant la charge de la précédente.
    if (next.set_weights?.length && next.set_weights.length !== next.sets) {
        next.set_weights = Array.from({ length: next.sets }, (_, i) => next.set_weights[i] ?? next.set_weights.at(-1) ?? null);
    }

    items.value[index] = next;
}

function move(index, direction) {
    const target = index + direction;

    if (target < 0 || target >= items.value.length) {
        return;
    }

    const list = [...items.value];
    [list[index], list[target]] = [list[target], list[index]];
    items.value = list;
}

function setMode(index, mode) {
    const item = items.value[index];
    const exercise = catalog.value[item.exercise];

    if (item.mode === mode) {
        return;
    }

    // Passer un exercice en « durée » repart des réglages chronométrés par défaut.
    // Une série chronométrée n'a ni charge par série ni drop set.
    update(index, {
        mode,
        value: mode === 'reps' ? 10 : defaultsFor({ ...exercise, mode: 'time' }).value,
        ...(mode === 'time' ? { set_weights: null, drops: null, drop_on: null } : {}),
    });
}

function openPicker() {
    picked.value = [];
    picking.value = true;
}

function togglePick(slug) {
    // Choisir une variante dans toute la bibliothèque : un toucher suffit.
    if (replacing.value !== null) {
        replaceAt(replacing.value, slug);
        replacing.value = null;
        picking.value = false;

        return;
    }

    picked.value = picked.value.includes(slug) ? picked.value.filter((s) => s !== slug) : [...picked.value, slug];
}

function confirmPick() {
    const room = props.maxItems - items.value.length;
    items.value = [...items.value, ...picked.value.slice(0, room).map((slug) => newItem(catalog.value[slug], machine.value))];
    picked.value = [];
    picking.value = false;
}

// ---------------------------------------------------------------- variantes

/*
 * La machine manque ou ne plaît pas : les variantes qui travaillent les mêmes
 * muscles, classées par le serveur, puis au besoin toute la bibliothèque.
 */
const swapping = ref(null);
const swapOpen = computed({
    get: () => swapping.value !== null,
    set: (open) => {
        if (!open) {
            swapping.value = null;
        }
    },
});
const equivalents = ref([]);
const loadingEquivalents = ref(false);
const replacing = ref(null);

async function openSwap(index) {
    const item = items.value[index];
    const exclude = items.value.map((other) => other.exercise).filter((slug) => slug !== item.exercise);

    swapping.value = index;
    equivalents.value = [];
    loadingEquivalents.value = true;

    try {
        const query = new URLSearchParams(exclude.map((slug) => ['exclude[]', slug]));
        equivalents.value = (await getJson(`${routes.exerciseEquivalents(item.exercise)}?${query}`)).equivalents.filter((slug) => catalog.value[slug]);
    } catch {
        equivalents.value = [];
    } finally {
        loadingEquivalents.value = false;
    }
}

function replaceAt(index, slug) {
    const item = items.value[index];
    items.value[index] = replaceExercise(item, catalog.value[slug], catalog.value[item.exercise], machine.value);
}

function chooseEquivalent(slug) {
    replaceAt(swapping.value, slug);
    swapping.value = null;
}

function browseAll() {
    replacing.value = swapping.value;
    swapping.value = null;
    picked.value = [];
    picking.value = true;
}

// ---------------------------------------------------------------- compléter

/*
 * L'assistant complète la séance commencée : quelques exercices de plus, dans
 * l'esprit de ceux qu'elle a déjà (mêmes répétitions, séries et repos), pour
 * les muscles qu'elle travaille — ou d'autres, au choix.
 */
const completing = ref(false);
const completion = reactive({ minutes: 15, muscles: [], equipment: '', variant: 0, proposal: null, chosen: [], loading: false, error: null });
const choosableMuscles = MUSCLE_REGIONS.flatMap((region) => region.muscles);

const sessionMuscles = computed(() => {
    const worked = items.value
        .map((item) => catalog.value[item.exercise])
        .filter((exercise) => exercise && !['cardio', 'mobilite', 'fonctionnel'].includes(exercise.group))
        .flatMap((exercise) => exercise.primary);

    return [...new Set(worked)].filter((muscle) => choosableMuscles.includes(muscle));
});

function openCompletion() {
    Object.assign(completion, { muscles: [...sessionMuscles.value], proposal: null, chosen: [], error: null, variant: 0 });
    completing.value = true;
}

function toggleCompletionMuscle(muscle) {
    completion.muscles = completion.muscles.includes(muscle) ? completion.muscles.filter((m) => m !== muscle) : [...completion.muscles, muscle];
}

async function proposeCompletion(variant = 0) {
    Object.assign(completion, { loading: true, error: null, variant });

    try {
        const proposal = await postJson(routes.assistantComplete, {
            items: items.value.map(({ exercise, mode, value, sets, rest_sets, rest_after }) => ({ exercise, mode, value, sets, rest_sets, rest_after })),
            minutes: completion.minutes,
            muscles: completion.muscles,
            equipment: completion.equipment || undefined,
            variant,
        });

        completion.proposal = { ...proposal, items: proposal.items.filter((item) => catalog.value[item.exercise]) };
        completion.chosen = completion.proposal.items.map((_, index) => index);
    } catch {
        completion.error = 'Pas de proposition pour le moment : vérifie ta connexion et réessaie.';
    } finally {
        completion.loading = false;
    }
}

function toggleChosen(index) {
    completion.chosen = completion.chosen.includes(index) ? completion.chosen.filter((i) => i !== index) : [...completion.chosen, index];
}

function addCompletion() {
    const room = props.maxItems - items.value.length;
    const additions = completion.proposal.items
        .filter((_, index) => completion.chosen.includes(index))
        .slice(0, room)
        .map((item) => ({ weight: null, set_weights: null, drops: null, drop_on: null, ...item, ...machineDefaultsOf(item), key: crypto.randomUUID() }));

    // Avant les étirements de fin de séance, s'il y en a.
    let at = items.value.length;

    while (at > 0 && catalog.value[items.value[at - 1].exercise]?.group === 'mobilite') {
        at--;
    }

    const list = [...items.value];
    list.splice(at, 0, ...additions);
    items.value = list;
    completing.value = false;
}

const machineDefaultsOf = (item) => {
    const fields = machineFields(catalog.value[item.exercise], machine.value);

    return Object.fromEntries(['speed', 'incline', 'level'].map((field) => [field, fields.includes(field) ? (item[field] ?? null) : null]));
};

const completionLine = (item) => {
    const effort = item.mode === 'reps' ? `${item.value} reps` : formatShort(item.value);

    return [`${item.sets} × ${effort}`, settingsLabel(item, machineFields(catalog.value[item.exercise], machine.value))].filter(Boolean).join(' · ');
};

function save({ start = false } = {}) {
    if (start) {
        unlockAudio();
    }

    const data = { name: name.value, items: payloadItems(), start };
    const options = { onStart: () => (saving.value = true), onFinish: () => (saving.value = false) };

    if (props.workout) {
        router.put(props.workout.urls.update, data, options);
    } else {
        router.post(routes.workouts, data, options);
    }
}

function leave() {
    if (dirty.value) {
        confirmLeave.value = true;
    } else {
        router.visit(routes.home);
    }
}

function destroy() {
    router.delete(routes.workout(props.workout.id));
}
</script>

<template>
    <Head :title="props.workout ? props.workout.name : 'Nouvelle séance'" />

    <!-- ÉDITEUR -->
    <div v-show="!picking" class="relative flex min-h-0 flex-1 flex-col">
        <div class="flex items-center justify-between px-5 pt-1 pb-2">
            <button type="button" class="iconbtn size-10" aria-label="Retour aux séances" @click="leave">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M15 5l-7 7 7 7" /></svg>
            </button>
            <button
                type="button"
                class="h-10 rounded-full bg-surface-2 px-[18px] text-[14px] font-extrabold text-accent disabled:opacity-50"
                :disabled="saving"
                @click="save()"
            >
                Enregistrer
            </button>
        </div>

        <div class="no-scrollbar flex flex-1 flex-col gap-3 overflow-y-auto px-5 pt-1 pb-[110px]">
            <input
                v-model="name"
                maxlength="80"
                placeholder="Nom de la séance"
                aria-label="Nom de la séance"
                class="display w-full border-0 border-b-[1.5px] border-line bg-transparent pt-1 pb-2.5 text-[38px] font-extrabold text-text outline-none focus:border-accent"
            />
            <p class="mb-1.5 text-[13px] font-semibold text-text-muted">{{ draftSummary }}</p>

            <MuscleSummary v-if="items.length" :items="items" :catalog="catalog" :height="200" />

            <article v-for="(item, index) in items" :key="item.key" class="flex flex-col gap-3.5 rounded-[22px] bg-surface p-4">
                <div class="flex items-start gap-3">
                    <span class="w-[30px] font-display text-[26px] leading-none font-extrabold text-accent">{{ String(index + 1).padStart(2, '0') }}</span>
                    <button type="button" class="flex min-w-0 flex-1 flex-col gap-0.5 text-left" @click="detail = item.exercise">
                        <span class="text-[16px] leading-tight font-extrabold">{{ catalog[item.exercise].name }}</span>
                        <span class="text-[12.5px] font-medium text-text-muted">
                            {{ catalog[item.exercise].equipment_label }} · <span class="font-bold text-accent">Comment faire</span>
                        </span>
                    </button>
                    <div class="flex gap-1">
                        <button type="button" class="iconbtn size-[30px] bg-surface-2! text-accent" :aria-label="`Changer ${catalog[item.exercise].name} pour une variante`" @click="openSwap(index)">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 9a8 8 0 0 1 14.3-3.3M20 4v5h-5M20 15a8 8 0 0 1-14.3 3.3M4 20v-5h5" /></svg>
                        </button>
                        <button type="button" class="iconbtn size-[30px] bg-surface-2! text-text-soft disabled:opacity-35" aria-label="Monter" :disabled="index === 0" @click="move(index, -1)">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 15l6-6 6 6" /></svg>
                        </button>
                        <button type="button" class="iconbtn size-[30px] bg-surface-2! text-text-soft disabled:opacity-35" aria-label="Descendre" :disabled="index === items.length - 1" @click="move(index, 1)">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6" /></svg>
                        </button>
                        <button type="button" class="iconbtn size-[30px] bg-surface-2! text-danger" :aria-label="`Retirer ${catalog[item.exercise].name}`" @click="items.splice(index, 1)">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.8" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18" /></svg>
                        </button>
                    </div>
                </div>

                <button
                    type="button"
                    class="-mt-1 flex items-center gap-3 rounded-2xl bg-bg px-3 py-2 text-left"
                    :aria-label="`Muscles : ${muscleNames(catalog[item.exercise].primary)}`"
                    @click="detail = item.exercise"
                >
                    <BodyMap :intensity="exerciseIntensity(catalog[item.exercise])" :height="64" label="" />
                    <span class="flex min-w-0 flex-col gap-0.5 text-[12.5px]">
                        <span class="font-bold text-text">{{ muscleNames(catalog[item.exercise].primary) }}</span>
                        <span v-if="catalog[item.exercise].secondary.length" class="font-medium text-text-muted">
                            + {{ muscleNames(catalog[item.exercise].secondary) }}
                        </span>
                    </span>
                </button>

                <div class="grid grid-cols-2 gap-[3px] rounded-xl bg-bg p-[3px]" role="radiogroup" aria-label="Mesure des séries">
                    <button
                        v-for="option in [{ mode: 'reps', label: 'Répétitions' }, { mode: 'time', label: 'Durée' }]"
                        :key="option.mode"
                        type="button"
                        role="radio"
                        :aria-checked="item.mode === option.mode"
                        class="h-8 rounded-[9px] text-[12.5px] font-bold"
                        :class="item.mode === option.mode ? 'bg-surface-3 text-text' : 'bg-transparent text-text-faint'"
                        @click="setMode(index, option.mode)"
                    >
                        {{ option.label }}
                    </button>
                </div>

                <LoadEditor v-if="usesWeight(catalog[item.exercise])" :item="item" @update="update(index, $event)" />
                <MachineSettingsEditor :item="item" :exercise="catalog[item.exercise]" @update="update(index, $event)" />

                <div class="grid grid-cols-2 gap-x-3 gap-y-3.5">
                    <Stepper
                        :label="item.mode === 'reps' ? 'Répétitions' : 'Durée effort'"
                        :display="item.mode === 'reps' ? String(item.value) : formatShort(item.value)"
                        @decrease="update(index, { value: stepValue(item, -1) })"
                        @increase="update(index, { value: stepValue(item, 1) })"
                    />
                    <Stepper
                        label="Séries"
                        :display="String(item.sets)"
                        :can-decrease="item.sets > 1"
                        :can-increase="item.sets < 20"
                        @decrease="update(index, { sets: item.sets - 1 })"
                        @increase="update(index, { sets: item.sets + 1 })"
                    />
                    <Stepper
                        label="Repos entre séries"
                        tone="rest"
                        :display="formatShort(item.rest_sets)"
                        @decrease="update(index, { rest_sets: stepRest(item.rest_sets, -1) })"
                        @increase="update(index, { rest_sets: stepRest(item.rest_sets, 1) })"
                    />
                    <Stepper
                        label="Repos après l'exo"
                        tone="rest"
                        :display="formatShort(item.rest_after)"
                        @decrease="update(index, { rest_after: stepRest(item.rest_after, -1) })"
                        @increase="update(index, { rest_after: stepRest(item.rest_after, 1) })"
                    />
                </div>
            </article>

            <button
                v-if="items.length < props.maxItems"
                type="button"
                class="dashed h-[60px] shrink-0 rounded-[22px]"
                @click="openPicker"
            >
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14" /></svg>
                Ajouter des exercices
            </button>

            <button
                v-if="items.length && items.length < props.maxItems"
                type="button"
                class="flex h-[60px] shrink-0 items-center justify-center gap-2.5 rounded-[22px] border-[1.5px] border-accent bg-accent/8 text-[15px] font-bold text-text"
                @click="openCompletion"
            >
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" class="text-accent" aria-hidden="true"><path d="M12 3l1.8 4.6L18.5 9l-4.7 1.4L12 15l-1.8-4.6L5.5 9l4.7-1.4zM19 15l.8 2.2L22 18l-2.2.8L19 21l-.8-2.2L16 18l2.2-.8z" /></svg>
                Compléter avec l'assistant
            </button>

            <button v-if="props.workout" type="button" class="h-11 shrink-0 text-[14px] font-bold text-danger" @click="confirmDelete = true">
                Supprimer la séance
            </button>
        </div>

        <div class="bottom-bar">
            <button v-if="items.length" type="button" class="btn-accent h-14 w-full text-[22px]" :disabled="saving" @click="save({ start: true })">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M7 4.5v15a1 1 0 0 0 1.5.86l12.5-7.5a1 1 0 0 0 0-1.72L8.5 3.64A1 1 0 0 0 7 4.5z" /></svg>
                Lancer la séance
            </button>
            <button v-else type="button" class="btn-accent h-14 w-full bg-surface-2! text-[22px] text-text-muted!" @click="openPicker">
                Ajoute un exercice
            </button>
        </div>
    </div>

    <!-- SÉLECTION D'EXERCICES -->
    <div v-if="picking" class="relative flex min-h-0 flex-1 flex-col">
        <ExerciseLibrary
            :exercises="props.exercises"
            :groups="props.groups"
            :picked="picked"
            picking
            @toggle="togglePick"
            @info="detail = $event"
        >
            <template #header>
                <div class="flex items-center gap-3">
                    <button type="button" class="iconbtn size-10" aria-label="Retour à la séance" @click="picking = false; replacing = null">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M15 5l-7 7 7 7" /></svg>
                    </button>
                    <h1 class="display text-[30px] font-extrabold">{{ replacing !== null ? 'Choisir une variante' : 'Ajouter des exos' }}</h1>
                </div>
            </template>
        </ExerciseLibrary>

        <div class="bottom-bar">
            <button type="button" class="btn-accent h-14 w-full text-[22px]" @click="picked.length ? confirmPick() : ((picking = false), (replacing = null))">
                {{ picked.length ? `Ajouter (${picked.length})` : 'Retour' }}
            </button>
        </div>
    </div>

    <ExerciseSheet v-if="detailExercise" :exercise="detailExercise" @close="detail = null" />

    <BottomSheet
        v-model:open="swapOpen"
        title="Changer d'exercice"
        :description="swapping !== null ? `À la place de « ${catalog[items[swapping].exercise].name} » : des variantes qui travaillent les mêmes muscles.` : ''"
    >
        <p v-if="loadingEquivalents" class="text-[14px] font-semibold text-text-muted">Recherche des variantes…</p>
        <p v-else-if="!equivalents.length" class="text-[14px] font-semibold text-text-muted">Pas de variante proposée : parcours la bibliothèque.</p>
        <ul v-else class="flex flex-col gap-2">
            <li v-for="slug in equivalents" :key="slug">
                <button type="button" class="flex w-full items-center gap-3 rounded-2xl bg-surface p-2 text-left hover:bg-surface-hover" @click="chooseEquivalent(slug)">
                    <span class="size-12 shrink-0 overflow-hidden rounded-[10px]">
                        <img :src="catalog[slug].images[0]" alt="" class="size-full object-cover" />
                    </span>
                    <span class="flex min-w-0 flex-col gap-0.5">
                        <span class="text-[14.5px] leading-tight font-bold">{{ catalog[slug].name }}</span>
                        <span class="text-[12px] font-medium text-text-muted">{{ catalog[slug].equipment_label }} · {{ muscleNames(catalog[slug].primary) }}</span>
                    </span>
                </button>
            </li>
        </ul>
        <button type="button" class="btn-soft h-12 text-[14px]" @click="browseAll">Parcourir toute la bibliothèque</button>
    </BottomSheet>

    <BottomSheet
        v-model:open="completing"
        title="Compléter la séance"
        :description="completion.proposal ? 'Garde ce qui te plaît : les exercices s\'ajoutent avant les étirements.' : 'L\'assistant ajoute des exercices dans l\'esprit de ta séance : mêmes répétitions, mêmes séries, mêmes repos.'"
    >
        <template v-if="!completion.proposal">
            <div class="flex flex-col gap-2">
                <span class="text-[10.5px] font-extrabold tracking-[0.1em] text-text-muted uppercase">Temps à ajouter</span>
                <div class="grid grid-cols-4 gap-2" role="radiogroup" aria-label="Temps à ajouter">
                    <button
                        v-for="minutes in [10, 15, 20, 30]"
                        :key="minutes"
                        type="button"
                        role="radio"
                        :aria-checked="completion.minutes === minutes"
                        class="h-10 rounded-xl text-[13px] font-bold"
                        :class="completion.minutes === minutes ? 'bg-text text-bg' : 'bg-surface-2 text-text-soft'"
                        @click="completion.minutes = minutes"
                    >
                        + {{ minutes }} min
                    </button>
                </div>
            </div>
            <div class="flex flex-col gap-2">
                <span class="text-[10.5px] font-extrabold tracking-[0.1em] text-text-muted uppercase">Matériel</span>
                <div class="grid grid-cols-2 gap-2" role="radiogroup" aria-label="Matériel">
                    <button
                        v-for="choice in [{ value: '', label: 'Tout' }, ...props.equipments]"
                        :key="choice.value"
                        type="button"
                        role="radio"
                        :aria-checked="completion.equipment === choice.value"
                        class="h-10 rounded-xl px-3 text-[13px] font-bold"
                        :class="completion.equipment === choice.value ? 'bg-text text-bg' : 'bg-surface-2 text-text-soft'"
                        @click="completion.equipment = choice.value"
                    >
                        {{ choice.label }}
                    </button>
                </div>
            </div>
            <div v-for="region in MUSCLE_REGIONS" :key="region.label" class="flex flex-col gap-2">
                <span class="text-[10.5px] font-extrabold tracking-[0.1em] text-text-muted uppercase">{{ region.label }}</span>
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="muscle in region.muscles"
                        :key="muscle"
                        type="button"
                        class="h-[34px] rounded-full px-3.5 text-[13px] font-bold"
                        :class="completion.muscles.includes(muscle) ? 'bg-accent text-on-accent' : 'bg-surface-2 text-text-soft'"
                        :aria-pressed="completion.muscles.includes(muscle)"
                        @click="toggleCompletionMuscle(muscle)"
                    >
                        {{ page.props.muscles[muscle] }}
                    </button>
                </div>
            </div>
            <p class="text-[12.5px] font-medium text-text-muted">
                {{ completion.muscles.length ? 'Préremplis avec les muscles de ta séance : ajoute ou retire-en.' : 'Sans muscle choisi : ceux que ta séance travaille déjà.' }}
            </p>
            <p v-if="completion.error" class="text-[13px] text-danger">{{ completion.error }}</p>
            <button type="button" class="btn-accent h-14 w-full text-[22px]" :disabled="completion.loading" @click="proposeCompletion()">
                {{ completion.loading ? 'Je cherche…' : 'Proposer' }}
            </button>
        </template>

        <template v-else>
            <p v-if="!completion.proposal.items.length" class="text-[14px] font-semibold text-text-muted">Rien de plus à proposer pour ces muscles : essaie d'en choisir d'autres.</p>
            <ul class="flex flex-col gap-2">
                <li v-for="(item, index) in completion.proposal.items" :key="`${completion.variant}-${index}`">
                    <button
                        type="button"
                        role="checkbox"
                        :aria-checked="completion.chosen.includes(index)"
                        class="flex w-full items-center gap-3 rounded-2xl p-2 text-left"
                        :class="completion.chosen.includes(index) ? 'bg-surface ring-[1.5px] ring-accent' : 'bg-surface opacity-60'"
                        @click="toggleChosen(index)"
                    >
                        <span class="size-12 shrink-0 overflow-hidden rounded-[10px]">
                            <img :src="catalog[item.exercise].images[0]" alt="" class="size-full object-cover" />
                        </span>
                        <span class="flex min-w-0 flex-1 flex-col gap-0.5">
                            <span class="text-[14.5px] leading-tight font-bold">{{ catalog[item.exercise].name }}</span>
                            <span class="text-[12px] font-medium text-text-muted">{{ completionLine(item) }} · {{ muscleNames(catalog[item.exercise].primary) }}</span>
                        </span>
                        <span
                            class="flex size-6 shrink-0 items-center justify-center rounded-full text-[13px] font-extrabold"
                            :class="completion.chosen.includes(index) ? 'bg-accent text-on-accent' : 'bg-surface-2 text-transparent'"
                            aria-hidden="true"
                        >✓</span>
                    </button>
                </li>
            </ul>
            <button type="button" class="btn-accent h-14 w-full text-[22px]" :disabled="!completion.chosen.length" @click="addCompletion">
                Ajouter {{ completion.chosen.length }} exercice{{ completion.chosen.length > 1 ? 's' : '' }}
            </button>
            <div class="grid grid-cols-2 gap-2">
                <button type="button" class="btn-soft h-12 text-[14px]" :disabled="completion.loading" @click="proposeCompletion(completion.variant + 1)">Autre proposition</button>
                <button type="button" class="btn-soft h-12 text-[14px]" @click="completion.proposal = null">Modifier</button>
            </div>
        </template>
    </BottomSheet>

    <BottomSheet v-model:open="confirmLeave" title="Quitter ?" description="Tes modifications ne sont pas enregistrées.">
        <button type="button" class="btn-accent h-14 w-full text-[22px]" @click="confirmLeave = false; save()">Enregistrer</button>
        <button type="button" class="btn-soft h-[54px] text-[15px]" @click="router.visit(routes.home)">Quitter sans enregistrer</button>
    </BottomSheet>

    <BottomSheet
        v-if="props.workout"
        v-model:open="confirmDelete"
        title="Supprimer ?"
        :description="`« ${props.workout.name} » disparaîtra. Les séances déjà faites restent dans ton historique.`"
    >
        <button type="button" class="btn-accent h-14 w-full bg-danger! text-[22px]" @click="destroy">Supprimer la séance</button>
        <button type="button" class="btn-soft h-[54px] text-[15px]" @click="confirmDelete = false">Garder</button>
    </BottomSheet>
</template>
