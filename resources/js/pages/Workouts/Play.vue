<script setup>
import { computed, onMounted, onUnmounted, reactive, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import ExerciseImage from '../../components/ExerciseImage.vue';
import ExerciseSheet from '../../components/ExerciseSheet.vue';
import { useWakeLock } from '../../composables/useWakeLock';
import { beep, countdownSound, goSound, loadCustomSound, restSound, unlockAudio, vibrate } from '../../audio';
import { sendLog } from '../../pendingLogs';
import { routes } from '../../routes';
import { patchJson } from '../../http';
import { formatKg, formatSet, formatTonnage } from '../../format';
import { bySlug, clamp, dropsOn, formatClock, formatWeight, setWeight, stepWeight, targetLabel, usesWeight } from '../../workout';

defineOptions({ layout: null });

const props = defineProps({
    workout: { type: Object, required: true },
    exercises: { type: Array, required: true },
    preferences: { type: Object, required: true },
    history: { type: Object, default: () => ({}) },
});

useWakeLock();

const catalog = bySlug(props.exercises);
// Copie modifiable : la charge peut changer en pleine séance.
const items = reactive(props.workout.items.map((item) => ({ ...item })));
const RING_REST = 722.57;
const RING_WORK = 753.98;

/*
 * La séance se déroule en étapes : compte à rebours, séries, repos entre
 * séries, repos entre exercices. Une série en répétitions attend qu'on la
 * valide ; tout le reste se chronomètre.
 */
function buildSteps() {
    const steps = [];

    if (props.preferences.prep_seconds > 0) {
        steps.push({ kind: 'prep', duration: props.preferences.prep_seconds, item: 0, set: 1 });
    }

    items.forEach((item, index) => {
        for (let set = 1; set <= item.sets; set++) {
            steps.push({ kind: 'work', item: index, set, mode: item.mode, duration: item.mode === 'time' ? item.value : null });

            // Drop set : les paliers suivent la série sans repos.
            if (dropsOn(item, set)) {
                item.drops.forEach((_, drop) => steps.push({ kind: 'work', item: index, set, mode: 'reps', duration: null, drop }));
            }

            if (set < item.sets && item.rest_sets > 0) {
                steps.push({ kind: 'rest', duration: item.rest_sets, item: index, set: set + 1 });
            }
        }

        if (index < items.length - 1 && item.rest_after > 0) {
            steps.push({ kind: 'rest', duration: item.rest_after, item: index + 1, set: 1, between: true });
        }
    });

    return steps;
}

const steps = buildSteps();

/*
 * Une séance ne compte dans les statistiques que menée au bout : toutes les
 * séries prévues faites (les paliers de drop prolongent une série, ils ne
 * s'ajoutent pas au compte).
 */
const plannedSets = steps.filter((s) => s.kind === 'work' && s.drop === undefined).length;

/*
 * Tous les instants sont absolus (Date.now) : un onglet endormi ou un écran
 * éteint ne fait pas dériver les minuteurs, qui rattrapent le temps au réveil.
 */
const state = reactive({
    clientId: crypto.randomUUID(),
    index: 0,
    paused: false,
    pausedAt: null,
    remaining: null,
    endAt: null,
    startAt: 0,
    elapsedBase: 0,
    currentDuration: null,
    doneSets: 0,
    doneItems: {},
    // Chaque série réellement faite, envoyée avec le journal : la matière des statistiques.
    performed: [],
    // Répétitions faites sur l'étape en cours, si elles diffèrent de l'objectif.
    repsDone: null,
    startedAt: Date.now(),
    pausedTotal: 0,
    done: false,
    endedAt: null,
});

const now = ref(Date.now());
const detail = ref(null);
let lastBeep = null;
let timer = null;

// ---------------------------------------------------------------- reprise

/*
 * Un rechargement, un appel qui passe devant, un onglet fermé par erreur : la
 * séance en cours est gardée sur le téléphone et reprend en pause, là où elle
 * s'était arrêtée. Au-delà de quatre heures, on repart de zéro.
 */
const STORAGE_KEY = 'seance.player.v1';
const RESUME_WINDOW = 4 * 60 * 60 * 1000;
let lastSaved = 0;

function persist(force = false) {
    if (!force && Date.now() - lastSaved < 2000) {
        return;
    }

    lastSaved = Date.now();

    try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify({ workoutId: props.workout.id, savedAt: lastSaved, state }));
    } catch {
        // Pas de stockage : la séance ne survivra pas à un rechargement, rien de plus.
    }
}

function forget() {
    try {
        localStorage.removeItem(STORAGE_KEY);
    } catch {
        // Rien à oublier.
    }
}

function restore() {
    try {
        const saved = JSON.parse(localStorage.getItem(STORAGE_KEY));

        if (!saved || saved.workoutId !== props.workout.id || saved.state.done || Date.now() - saved.savedAt > RESUME_WINDOW) {
            return false;
        }

        if (saved.state.index >= steps.length) {
            return false;
        }

        Object.assign(state, saved.state);

        // Reprise toujours en pause : on ne relance pas un chrono dans le dos de quelqu'un.
        if (!state.paused) {
            state.paused = true;
            state.pausedAt = saved.savedAt;
            state.remaining = state.endAt != null ? Math.max(1000, state.endAt - saved.savedAt) : null;
            state.elapsedBase += Math.max(0, saved.savedAt - state.startAt);
        }

        return true;
    } catch {
        return false;
    }
}

// ---------------------------------------------------------------- lecture

const volume = () => (props.preferences.volume ?? 80) / 100;
const cue = () => props.preferences.countdown_sound ?? 'bip';

function sound(frequency, duration) {
    if (props.preferences.sound) {
        beep(frequency, duration, volume());
    }
}

function buzz(pattern) {
    if (props.preferences.sound) {
        vibrate(pattern);
    }
}

function goTo(target, countSet = false) {
    const at = Date.now();
    const current = steps[state.index];
    lastBeep = null;

    // Un palier de drop prolonge la série : il ne compte pas comme une série de plus.
    if (countSet && current && current.drop === undefined) {
        state.doneSets += 1;
        state.doneItems = { ...state.doneItems, [current.item]: true };
    }

    if (state.paused) {
        state.pausedTotal += at - state.pausedAt;
        state.paused = false;
    }

    now.value = at;

    if (target >= steps.length) {
        finish(at);

        return;
    }

    const step = steps[target];
    state.repsDone = null;
    Object.assign(state, {
        index: target,
        currentDuration: step.duration,
        endAt: step.duration != null ? at + step.duration * 1000 : null,
        startAt: at,
        elapsedBase: 0,
    });

    if (step.kind === 'work') {
        if (props.preferences.sound) {
            goSound(cue(), volume());
        }

        buzz(200);
    } else if (step.kind === 'rest') {
        if (props.preferences.sound) {
            restSound(cue(), volume());
        }

        buzz(80);
    }

    persist(true);
}

function finish(at) {
    state.done = true;
    state.endedAt = at;
    forget();

    sound(880, 0.15);
    setTimeout(() => sound(1175, 0.3), 180);
    buzz([100, 60, 100]);

    // Envoyé tout de suite ; sans réseau, gardé et renvoyé plus tard.
    sendLog(props.workout.urls.log, {
        client_id: state.clientId,
        duration_seconds: Math.round(activeMilliseconds.value / 1000),
        sets_done: state.doneSets,
        exercises_done: Object.keys(state.doneItems).length,
        finished_at: new Date(at).toISOString(),
        sets: state.performed,
        planned_sets: plannedSets,
    }).then((response) => {
        records.value = response?.records ?? [];
    });
}

/*
 * Note la série qui vient d'être faite : charge, répétitions réellement
 * faites (ajustées au − / + si besoin), objectif, ou durée au chrono.
 */
function perform(s, seconds = null) {
    const it = items[s.item];
    const { reps, weight } = load(s);
    const timed = it.mode === 'time' && s.drop === undefined;

    state.performed.push({
        exercise: it.exercise,
        position: s.item,
        set: s.set,
        drop: s.drop ?? null,
        reps: timed ? null : (state.repsDone ?? reps),
        target_reps: timed ? null : reps,
        seconds: timed ? (seconds ?? it.value) : Math.round((state.elapsedBase + (state.paused ? 0 : Date.now() - state.startAt)) / 1000),
        weight,
        at: new Date().toISOString(),
    });
}

const mainSetsDone = computed(() => state.performed.filter((set) => set.drop === null).length);
const complete = computed(() => mainSetsDone.value >= plannedSets);

const sessionTonnage = computed(() => state.performed.reduce((total, set) => total + (set.weight && set.reps ? set.weight * set.reps : 0), 0));

// Fin de séance : records battus (réponse du serveur) et difficulté ressentie.
const records = ref(null);
const rpe = ref(null);

function rate(value) {
    rpe.value = value;
    patchJson(props.workout.urls.feeling.replace('__client__', state.clientId), { rpe: value }).catch(() => {
        // Hors réseau : le ressenti est perdu, la séance, elle, est gardée.
    });
}

/** Répétitions faites : on part de l'objectif, on corrige au besoin. */
function adjustReps(direction) {
    const planned = load(step.value).reps;
    state.repsDone = Math.max(0, Math.min(100, (state.repsDone ?? planned) + direction));
}

const shownReps = computed(() => (step.value ? (state.repsDone ?? load(step.value).reps) : 0));

/** La dernière fois sur cet exercice, et le conseil pour aujourd'hui. */
const lastTime = computed(() => (exercise.value ? props.history[exercise.value.slug] ?? null : null));

/** « 4 × 8 à 62,5 kg » quand toutes les séries se ressemblent, sinon le détail. */
const lastTimeText = computed(() => {
    const sets = lastTime.value?.sets ?? [];
    const same = sets.length > 1 && sets.every((x) => x.weight === sets[0].weight && x.reps === sets[0].reps);

    if (same && sets[0].reps !== null) {
        return sets[0].weight ? `${sets.length} × ${sets[0].reps} à ${formatKg(sets[0].weight, 2)}` : `${sets.length} × ${sets[0].reps}`;
    }

    return sets.map(formatSet).join(' · ');
});

function applyAdvice() {
    const target = lastTime.value?.next?.weight;

    if (!target) {
        return;
    }

    // Même chemin qu'un appui sur − / + : la charge est enregistrée pour la suite.
    setWeightTo(target);
}

function tick() {
    if (state.done || state.paused) {
        return;
    }

    const at = Date.now();
    const step = steps[state.index];

    if (step.duration != null) {
        const remaining = state.endAt - at;
        const seconds = Math.ceil(remaining / 1000);

        // Les dernières secondes d'un repos, d'une série chronométrée ou du départ.
        if (seconds <= (props.preferences.countdown_seconds ?? 5) && seconds >= 1 && seconds !== lastBeep) {
            const first = lastBeep === null;
            lastBeep = seconds;

            if (props.preferences.sound) {
                countdownSound(cue(), seconds, volume(), { first });
            }
        }

        if (remaining <= 0) {
            if (step.kind === 'work') {
                perform(step, step.duration);
            }

            goTo(state.index + 1, step.kind === 'work');

            return;
        }
    }

    now.value = at;
    persist();
}

function pause() {
    if (state.paused || state.done) {
        return;
    }

    const at = Date.now();
    Object.assign(state, {
        paused: true,
        pausedAt: at,
        remaining: state.endAt != null ? state.endAt - at : null,
        elapsedBase: state.elapsedBase + (at - state.startAt),
    });
    now.value = at;
    persist(true);
}

function resume() {
    if (!state.paused) {
        return;
    }

    unlockAudio();
    const at = Date.now();
    Object.assign(state, {
        paused: false,
        endAt: state.remaining != null ? at + state.remaining : null,
        startAt: at,
        pausedTotal: state.pausedTotal + (at - state.pausedAt),
    });
    now.value = at;
    persist(true);
}

function addTime(seconds) {
    if (state.endAt == null && state.remaining == null) {
        return;
    }

    state.currentDuration = Math.max(1, state.currentDuration + seconds);

    if (state.paused) {
        state.remaining = Math.max(1000, state.remaining + seconds * 1000);
    } else {
        state.endAt = Math.max(Date.now() + 1000, state.endAt + seconds * 1000);
    }

    persist(true);
}

/** Répétitions et charge d'une étape d'effort : la série, ou un palier de drop. */
function load(s) {
    const it = items[s.item];

    if (s.drop !== undefined) {
        return { reps: it.drops[s.drop].reps, weight: it.drops[s.drop].weight ?? null };
    }

    return { reps: it.value, weight: setWeight(it, s.set) };
}

/** « 8 reps · 60 kg » : la cible d'une étape, charge comprise. */
function target(s) {
    const it = items[s.item];
    const { reps, weight } = load(s);
    const effort = it.mode === 'reps' || s.drop !== undefined ? `${reps} reps` : targetLabel(it);

    return [effort, formatWeight(weight)].filter(Boolean).join(' · ');
}

/*
 * La charge se règle sans quitter la séance ; elle part au serveur une fois
 * le doigt posé (un court délai regroupe les appuis successifs) et devient
 * celle de l'exercice pour la prochaine fois.
 */
const weightTimers = {};

function changeWeight(direction) {
    setWeightTo(stepWeight(load(step.value).weight, direction));
}

/*
 * Fixe la charge de l'étape en cours — la charge fixe, celle de la série en
 * dégressif, ou celle du palier de drop — et l'envoie au serveur.
 */
function setWeightTo(weight) {
    const current = step.value;
    const position = current.item;
    const it = items[position];
    let payload;

    if (current.drop !== undefined) {
        it.drops[current.drop].weight = weight;
        payload = { position, drop: current.drop, weight };
    } else if (it.set_weights?.length) {
        it.set_weights[current.set - 1] = weight;
        payload = { position, set: current.set - 1, weight };
    } else {
        it.weight = weight;
        payload = { position, weight };
    }

    const key = JSON.stringify([position, current.drop, current.set]);
    clearTimeout(weightTimers[key]);
    weightTimers[key] = setTimeout(() => {
        patchJson(props.workout.urls.weight, payload).catch(() => {
            // Hors réseau : la séance continue avec la nouvelle charge ; elle ne sera
            // simplement pas retenue pour la prochaine fois.
        });
    }, 700);
}

const completeSet = () => {
    perform(step.value);
    unlockAudio();
    goTo(state.index + 1, true);
};
const next = () => goTo(state.index + 1);
const previous = () => goTo(Math.max(0, state.index - 1));
const restartStep = () => goTo(state.index);
const finishNow = () => goTo(steps.length);

function quit() {
    forget();
    router.visit(routes.home);
}

function openDetail() {
    pause();
    detail.value = step.value ? items[step.value.item].exercise : null;
}

// ---------------------------------------------------------------- affichage

const step = computed(() => (state.done ? null : steps[state.index]));
const item = computed(() => (step.value ? items[step.value.item] : null));
const exercise = computed(() => (item.value ? catalog[item.value.exercise] : null));

const activeMilliseconds = computed(() => {
    const end = state.done ? state.endedAt : now.value;

    return end - state.startedAt - state.pausedTotal - (state.paused && !state.done ? now.value - state.pausedAt : 0);
});

const remaining = computed(() => {
    if (!step.value || step.value.duration == null) {
        return 0;
    }

    return Math.max(0, state.paused ? state.remaining : state.endAt - now.value);
});

const fraction = computed(() => (state.currentDuration ? clamp(remaining.value / (state.currentDuration * 1000), 0, 1) : 0));
const seconds = computed(() => Math.ceil(remaining.value / 1000));
const elapsed = computed(() => formatClock((state.elapsedBase + (state.paused ? 0 : now.value - state.startAt)) / 1000));
const setLabel = computed(() => (item.value ? `Série ${step.value.set} / ${item.value.sets}` : ''));
const counter = computed(() => (step.value ? `Exercice ${step.value.item + 1} / ${items.length}` : ''));
const progress = computed(() => `${((state.index / steps.length) * 100).toFixed(1)}%`);

const nextLabel = computed(() => {
    const upcoming = steps.findIndex((s, index) => index > state.index && s.kind === 'work');

    if (upcoming === -1) {
        return 'Fin de la séance';
    }

    const s = steps[upcoming];
    const i = items[s.item];

    const label = s.drop !== undefined ? `drop ${s.drop + 1}/${i.drops.length}` : `série ${s.set}/${i.sets}`;

    return `${catalog[i.exercise].name} · ${label} · ${target(s)}`;
});

const detailExercise = computed(() => (detail.value ? catalog[detail.value] : null));

// ---------------------------------------------------------------- clavier

/*
 * Espace : valide la série en répétitions, sinon met en pause ou reprend —
 * pratique sur une tablette posée avec un clavier, ou avec une télécommande
 * Bluetooth.
 */
function onKey(event) {
    if (state.done || detail.value || event.code !== 'Space' || /INPUT|TEXTAREA/.test(document.activeElement?.tagName)) {
        return;
    }

    event.preventDefault();

    if (state.paused) {
        resume();
    } else if (step.value.kind === 'work' && step.value.mode === 'reps') {
        completeSet();
    } else {
        pause();
    }
}

onMounted(() => {
    // Le son personnel se charge pendant le compte à rebours de départ.
    if (props.preferences.sound && cue() === 'perso') {
        loadCustomSound(props.preferences.custom_sound_url);
    }

    if (!restore()) {
        goTo(0);
    }

    timer = setInterval(tick, 200);
    window.addEventListener('keydown', onKey);
});

onUnmounted(() => {
    clearInterval(timer);
    window.removeEventListener('keydown', onKey);
});
</script>

<template>
    <Head :title="props.workout.name" />

    <div
        class="relative mx-auto flex h-dvh w-full max-w-[480px] flex-col overflow-hidden bg-bg pt-[env(safe-area-inset-top)] text-text select-none min-[520px]:border-x min-[520px]:border-divider"
    >
        <template v-if="!state.done">
            <!-- En-tête : quitter (met en pause), exercice courant, temps total. -->
            <div class="flex items-center justify-between px-5 pt-2 pb-3">
                <button type="button" class="iconbtn size-10" aria-label="Mettre en pause" @click="pause">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18" /></svg>
                </button>
                <span class="text-[12px] font-extrabold tracking-[0.12em] text-text-soft uppercase">{{ counter }}</span>
                <span class="min-w-10 text-right font-display text-[20px] font-semibold text-text-muted tabular-nums">
                    {{ formatClock(activeMilliseconds / 1000) }}
                </span>
            </div>
            <div class="mx-5 h-1 overflow-hidden rounded-sm bg-surface-2" role="progressbar" :aria-valuenow="state.index" :aria-valuemax="steps.length">
                <div class="h-full rounded-sm bg-text transition-[width] duration-300" :style="{ width: progress }" />
            </div>

            <main class="flex min-h-0 flex-1 flex-col items-center justify-center gap-1.5 px-6 py-3 text-center">
                <!-- Compte à rebours de départ -->
                <template v-if="step.kind === 'prep'">
                    <span class="eyebrow text-prep">Prépare-toi</span>
                    <span class="font-display text-[190px] leading-[0.9] font-extrabold text-prep tabular-nums" aria-live="polite">{{ seconds }}</span>
                    <span class="mt-2.5 text-[13px] font-semibold text-text-muted">Premier exercice</span>
                    <span class="display text-[34px] font-extrabold">{{ exercise.name }}</span>
                    <span class="text-[14px] font-semibold text-text-soft">{{ target(step) }}</span>
                </template>

                <!-- Repos -->
                <template v-else-if="step.kind === 'rest'">
                    <span class="eyebrow text-rest">{{ step.between ? "Repos · changement d'exo" : 'Repos entre les séries' }}</span>
                    <div class="relative my-1.5 size-[250px]">
                        <svg width="250" height="250" viewBox="0 0 250 250" class="block" aria-hidden="true">
                            <circle cx="125" cy="125" r="115" fill="none" stroke="var(--color-rest-track)" stroke-width="10" />
                            <circle
                                cx="125"
                                cy="125"
                                r="115"
                                fill="none"
                                stroke="var(--color-rest)"
                                stroke-width="10"
                                stroke-linecap="round"
                                :stroke-dasharray="RING_REST"
                                :stroke-dashoffset="(RING_REST * (1 - fraction)).toFixed(2)"
                                transform="rotate(-90 125 125)"
                            />
                        </svg>
                        <span class="absolute inset-0 flex items-center justify-center font-display text-[84px] font-bold tabular-nums">{{ formatClock(seconds) }}</span>
                    </div>
                    <div class="flex gap-2">
                        <button type="button" class="btn-soft h-10 px-4 text-[14px]" @click="addTime(-15)">−15 s</button>
                        <button type="button" class="btn-soft h-10 px-4 text-[14px]" @click="addTime(15)">+15 s</button>
                        <button type="button" class="h-10 rounded-full bg-rest px-4 text-[14px] font-extrabold text-on-accent" @click="next">Passer</button>
                    </div>
                    <div class="mt-[18px] flex w-full items-center gap-3 rounded-[20px] bg-surface p-2.5 text-left">
                        <span class="size-[60px] shrink-0 overflow-hidden rounded-xl">
                            <ExerciseImage :images="exercise.images" :alt="exercise.name" />
                        </span>
                        <span class="flex min-w-0 flex-1 flex-col gap-[3px]">
                            <span class="text-[10.5px] font-extrabold tracking-[0.12em] text-text-muted">À SUIVRE</span>
                            <span class="text-[16px] font-extrabold">{{ exercise.name }}</span>
                            <span class="text-[12.5px] font-semibold text-text-soft">{{ setLabel }} · {{ target({ ...step, kind: 'work' }) }}</span>
                        </span>
                        <button type="button" class="iconbtn size-[38px] bg-surface-2! font-serif text-[16px] font-extrabold text-accent italic" aria-label="Comment faire" @click="openDetail">i</button>
                    </div>
                </template>

                <!-- Effort -->
                <template v-else>
                    <span v-if="step.drop !== undefined" class="eyebrow text-prep">Drop {{ step.drop + 1 }}/{{ item.drops.length }} · {{ setLabel }} · sans repos</span>
                    <span v-else class="eyebrow text-accent">Effort · {{ setLabel }}</span>
                    <h1 class="display mt-1.5 text-[40px] leading-[0.95] font-extrabold text-balance">{{ exercise.name }}</h1>
                    <div class="mt-2.5 flex w-full items-center gap-3 rounded-[18px] bg-surface p-2 text-left">
                        <span class="size-[60px] shrink-0 overflow-hidden rounded-xl">
                            <ExerciseImage :images="exercise.images" :alt="exercise.name" />
                        </span>
                        <span class="flex min-w-0 flex-1 flex-col gap-0.5">
                            <span class="truncate text-[13px] font-semibold text-text-soft">{{ exercise.equipment_label }}</span>
                            <span v-if="lastTime" class="truncate text-[11.5px] font-semibold text-text-muted">
                                Dernière fois : {{ lastTimeText }}
                            </span>
                        </span>
                        <button type="button" class="h-[38px] shrink-0 rounded-full bg-surface-2 px-3.5 text-[13px] font-extrabold text-accent" @click="openDetail">
                            Comment faire ?
                        </button>
                    </div>

                    <template v-if="step.mode === 'reps'">
                        <div class="mt-2.5 flex items-center gap-4">
                            <button type="button" class="iconbtn size-11 bg-surface! text-[22px] font-semibold disabled:opacity-35" aria-label="Une répétition de moins" :disabled="shownReps === 0" @click="adjustReps(-1)">−</button>
                            <div class="flex items-baseline gap-2" :class="state.repsDone !== null && state.repsDone < load(step).reps ? 'text-prep' : 'text-accent'">
                                <span class="font-display leading-[0.85] font-extrabold tabular-nums" :class="usesWeight(exercise) ? 'text-[100px]' : 'text-[120px]'" aria-live="polite">{{ shownReps }}</span>
                                <span class="font-display text-[28px] font-bold">REPS</span>
                            </div>
                            <button type="button" class="iconbtn size-11 bg-surface! text-[22px] font-semibold" aria-label="Une répétition de plus" @click="adjustReps(1)">+</button>
                        </div>
                        <span class="mt-1.5 text-[13px] font-semibold text-text-muted">
                            <template v-if="state.repsDone !== null && state.repsDone !== load(step).reps">Objectif {{ load(step).reps }} · </template>Temps sur la série · {{ elapsed }}
                        </span>
                    <div v-if="usesWeight(exercise)" class="mt-2.5 flex items-center gap-3 rounded-full bg-surface p-1.5">
                        <button type="button" class="iconbtn size-10 bg-surface-2! text-[22px] font-semibold disabled:opacity-35" aria-label="Charge : moins" :disabled="load(step).weight === null" @click="changeWeight(-1)">−</button>
                        <span class="min-w-[130px] text-center font-display text-[34px] leading-none font-extrabold tabular-nums" :class="load(step).weight === null ? 'text-[18px]! text-text-muted' : 'text-text'" aria-live="polite">
                            {{ formatWeight(load(step).weight) ?? 'Poids du corps' }}
                        </span>
                        <button type="button" class="iconbtn size-10 bg-surface-2! text-[22px] font-semibold" aria-label="Charge : plus" @click="changeWeight(1)">+</button>
                    </div>
                    <button
                        v-if="lastTime?.next && step.drop === undefined && lastTime.next.weight !== load(step).weight"
                        type="button"
                        class="mt-2 rounded-full border-[1.5px] border-accent/50 px-3.5 py-1.5 text-[12.5px] font-bold text-text-soft"
                        @click="applyAdvice"
                    >
                        Conseil : <span class="text-accent">{{ formatWeight(lastTime.next.weight) }} {{ { up: '▲', keep: '=', down: '▼' }[lastTime.next.trend] }}</span> · Appliquer
                    </button>

                        <button
                            type="button"
                            class="btn-accent mt-[18px] h-[84px] w-full rounded-[26px]! text-[30px] shadow-[0_10px_30px_rgb(212_255_58/0.18)] active:scale-[0.97]"
                            @click="completeSet"
                        >
                            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5" /></svg>
                            {{ step.drop !== undefined ? 'Palier terminé' : 'Série terminée' }}
                        </button>
                        <span class="hidden text-[12px] font-semibold text-text-faint [@media(hover:hover)]:inline">ou touche Espace</span>
                    </template>

                    <div v-else class="relative mt-3.5 size-[210px]">
                        <svg width="210" height="210" viewBox="0 0 260 260" class="block" aria-hidden="true">
                            <circle cx="130" cy="130" r="120" fill="none" stroke="var(--color-accent-track)" stroke-width="12" />
                            <circle
                                cx="130"
                                cy="130"
                                r="120"
                                fill="none"
                                stroke="var(--color-accent)"
                                stroke-width="12"
                                stroke-linecap="round"
                                :stroke-dasharray="RING_WORK"
                                :stroke-dashoffset="(RING_WORK * (1 - fraction)).toFixed(2)"
                                transform="rotate(-90 130 130)"
                            />
                        </svg>
                        <span class="absolute inset-0 flex items-center justify-center font-display text-[76px] font-extrabold text-accent tabular-nums">{{ formatClock(seconds) }}</span>
                    </div>
                </template>
            </main>

            <p v-if="step.kind === 'work'" class="mx-5 mb-3.5 flex items-center gap-2.5 text-[13px] font-semibold text-text-muted">
                <span class="text-[10.5px] font-extrabold tracking-[0.12em] text-text-faint">ENSUITE</span>
                <span class="truncate text-text-soft">{{ nextLabel }}</span>
            </p>

            <div class="flex items-center justify-center gap-7 px-5 pt-1.5 pb-[calc(env(safe-area-inset-bottom)+24px)]">
                <button type="button" class="iconbtn size-[54px]" aria-label="Étape précédente" @click="previous">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M6 5h2v14H6zM20 5.5v13a.8.8 0 0 1-1.2.7L9.5 12.7a.8.8 0 0 1 0-1.4l9.3-6.5a.8.8 0 0 1 1.2.7z" /></svg>
                </button>
                <button type="button" class="iconbtn size-[76px] bg-text! text-bg! active:scale-95" aria-label="Pause" @click="pause">
                    <svg width="26" height="26" viewBox="0 0 24 24" fill="currentColor"><rect x="6" y="4.5" width="4" height="15" rx="1.2" /><rect x="14" y="4.5" width="4" height="15" rx="1.2" /></svg>
                </button>
                <button type="button" class="iconbtn size-[54px]" aria-label="Étape suivante" @click="next">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M16 5h2v14h-2zM4 5.5v13a.8.8 0 0 0 1.2.7l9.3-6.5a.8.8 0 0 0 0-1.4L5.2 4.8A.8.8 0 0 0 4 5.5z" /></svg>
                </button>
            </div>

            <!-- Pause -->
            <div
                v-if="state.paused && !detail"
                class="animate-pop absolute inset-0 z-10 flex flex-col justify-center gap-3 bg-[rgb(10_10_8/0.9)] px-7 py-8 backdrop-blur-md"
                role="dialog"
                aria-modal="true"
                aria-label="En pause"
            >
                <span class="eyebrow text-text-muted">{{ counter }}</span>
                <h1 class="display mb-1 text-[72px] leading-[0.9] font-extrabold">En pause</h1>
                <p class="mb-[22px] text-[14px] font-semibold text-text-soft">{{ exercise.name }} · {{ setLabel }}</p>
                <button type="button" class="btn-accent h-[60px] text-[24px]" @click="resume">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M7 4.5v15a1 1 0 0 0 1.5.86l12.5-7.5a1 1 0 0 0 0-1.72L8.5 3.64A1 1 0 0 0 7 4.5z" /></svg>
                    Reprendre
                </button>
                <button type="button" class="btn-soft h-[54px] text-[15px]" @click="restartStep">Recommencer cette étape</button>
                <button type="button" class="btn-soft h-[54px] flex-col gap-0 text-[15px]" @click="finishNow">
                    Terminer la séance
                    <span v-if="!complete" class="text-[11.5px] font-semibold text-prep">
                        {{ plannedSets - mainSetsDone }} série{{ plannedSets - mainSetsDone > 1 ? 's' : '' }} restante{{ plannedSets - mainSetsDone > 1 ? 's' : '' }} : elle ne comptera pas
                    </span>
                </button>
                <button type="button" class="h-12 text-[14px] font-bold text-danger" @click="quit">Abandonner sans enregistrer</button>
            </div>
        </template>

        <!-- Séance terminée -->
        <div v-else class="animate-pop no-scrollbar flex flex-1 flex-col justify-[safe_center] gap-2 overflow-y-auto px-7 pt-6 pb-10">
            <span class="eyebrow" :class="complete ? 'text-accent' : 'text-prep'">{{ complete ? 'Séance terminée' : 'Séance interrompue' }}</span>
            <h1 v-if="complete" class="display text-[84px] leading-[0.86] font-extrabold">Bien<br />joué.</h1>
            <h1 v-else class="display text-[64px] leading-[0.88] font-extrabold">Arrêtée<br />en route.</h1>
            <p class="mt-2.5 mb-4 text-[15px] font-semibold text-text-soft">
                {{ props.workout.name }}<template v-if="sessionTonnage"> · {{ formatTonnage(sessionTonnage) }} soulevés</template>
            </p>

            <p v-if="!complete" class="mb-4 rounded-[20px] border-[1.5px] border-line bg-surface p-4 text-[14px] leading-normal font-medium text-text-soft">
                <span class="font-extrabold text-prep">Séance incomplète</span> — {{ mainSetsDone }} série{{ mainSetsDone > 1 ? 's' : '' }} sur {{ plannedSets }}.
                Elle est gardée, mais ne compte ni dans tes progrès ni dans tes records.
            </p>

            <section v-if="records?.length" class="mb-4 flex flex-col gap-2 rounded-[20px] border-[1.5px] border-prep/60 bg-prep/8 p-4" aria-live="polite">
                <span class="eyebrow text-prep">★ Nouveau{{ records.length > 1 ? 'x' : '' }} record{{ records.length > 1 ? 's' : '' }}</span>
                <p v-for="(rec, i) in records" :key="i" class="text-[14px] font-semibold">
                    <span class="font-extrabold">{{ rec.name }}</span> —
                    {{ formatKg(rec.value) }} {{ rec.kind === 'e1rm' ? 'en 1RM estimé' : 'de charge' }}
                    <span class="text-text-muted">(avant {{ formatKg(rec.previous) }})</span>
                </p>
            </section>

            <section v-if="complete" class="mb-4 flex flex-col gap-2">
                <span class="text-[11px] font-extrabold tracking-[0.1em] text-text-muted uppercase">Difficulté ressentie</span>
                <div class="grid grid-cols-10 gap-1" role="radiogroup" aria-label="Difficulté ressentie, de 1 facile à 10 maximale">
                    <button
                        v-for="n in 10"
                        :key="n"
                        type="button"
                        role="radio"
                        :aria-checked="rpe === n"
                        class="h-10 rounded-lg text-[14px] font-extrabold tabular-nums"
                        :class="rpe === n ? 'bg-accent text-on-accent' : 'bg-surface text-text-soft'"
                        @click="rate(n)"
                    >
                        {{ n }}
                    </button>
                </div>
                <span class="flex justify-between text-[11px] font-semibold text-text-faint"><span>facile</span><span>maximale</span></span>
            </section>

            <div class="mb-6 grid grid-cols-3 gap-2">
                <div v-for="stat in [
                    { label: 'Durée', value: formatClock(activeMilliseconds / 1000) },
                    { label: 'Séries', value: state.doneSets },
                    { label: 'Exercices', value: Object.keys(state.doneItems).length },
                ]" :key="stat.label" class="rounded-[18px] bg-surface p-3.5">
                    <div class="font-display text-[34px] leading-none font-bold tabular-nums">{{ stat.value }}</div>
                    <div class="mt-1.5 text-[11px] font-bold text-text-muted">{{ stat.label }}</div>
                </div>
            </div>
            <button type="button" class="btn-accent h-[58px] text-[22px]" @click="quit">Retour aux séances</button>
        </div>

        <ExerciseSheet
            v-if="detailExercise"
            :exercise="detailExercise"
            :close-label="state.done ? 'Fermer' : 'Retour à la séance'"
            @close="detail = null"
        />
    </div>
</template>
