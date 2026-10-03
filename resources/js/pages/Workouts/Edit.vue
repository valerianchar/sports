<script setup>
import { computed, ref } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import BodyMap from '../../components/BodyMap.vue';
import BottomSheet from '../../components/BottomSheet.vue';
import ExerciseLibrary from '../../components/ExerciseLibrary.vue';
import ExerciseSheet from '../../components/ExerciseSheet.vue';
import MuscleSummary from '../../components/MuscleSummary.vue';
import Stepper from '../../components/Stepper.vue';
import WeightInput from '../../components/WeightInput.vue';
import { unlockAudio } from '../../audio';
import { routes } from '../../routes';
import { bySlug, defaultsFor, exerciseIntensity, formatShort, newItem, stepRest, stepValue, summary, usesWeight } from '../../workout';

const props = defineProps({
    workout: { type: Object, default: null },
    exercises: { type: Array, required: true },
    groups: { type: Array, required: true },
    maxItems: { type: Number, required: true },
});

const page = usePage();
const catalog = computed(() => bySlug(props.exercises));

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
    items.value[index] = { ...items.value[index], ...changes };
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
    update(index, {
        mode,
        value: mode === 'reps' ? 10 : defaultsFor({ ...exercise, mode: 'time' }).value,
    });
}

function openPicker() {
    picked.value = [];
    picking.value = true;
}

function togglePick(slug) {
    picked.value = picked.value.includes(slug) ? picked.value.filter((s) => s !== slug) : [...picked.value, slug];
}

function confirmPick() {
    const room = props.maxItems - items.value.length;
    items.value = [...items.value, ...picked.value.slice(0, room).map((slug) => newItem(catalog.value[slug]))];
    picked.value = [];
    picking.value = false;
}

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

                <WeightInput
                    v-if="usesWeight(catalog[item.exercise])"
                    :model-value="item.weight ?? null"
                    label="Charge"
                    @update:model-value="update(index, { weight: $event })"
                />

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
                    <button type="button" class="iconbtn size-10" aria-label="Retour à la séance" @click="picking = false">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M15 5l-7 7 7 7" /></svg>
                    </button>
                    <h1 class="display text-[30px] font-extrabold">Ajouter des exos</h1>
                </div>
            </template>
        </ExerciseLibrary>

        <div class="bottom-bar">
            <button type="button" class="btn-accent h-14 w-full text-[22px]" @click="picked.length ? confirmPick() : (picking = false)">
                {{ picked.length ? `Ajouter (${picked.length})` : 'Retour' }}
            </button>
        </div>
    </div>

    <ExerciseSheet v-if="detailExercise" :exercise="detailExercise" @close="detail = null" />

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
