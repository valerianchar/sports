<script setup>
import { computed, onMounted, onUnmounted, reactive, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import ExerciseImage from '../../components/ExerciseImage.vue';
import ExerciseSheet from '../../components/ExerciseSheet.vue';
import { useWakeLock } from '../../composables/useWakeLock';
import { beep, unlockAudio, vibrate } from '../../audio';
import { sendLog } from '../../pendingLogs';
import { routes } from '../../routes';
import { bySlug, clamp, formatClock, targetLabel } from '../../workout';

defineOptions({ layout: null });

const props = defineProps({
    workout: { type: Object, required: true },
    exercises: { type: Array, required: true },
    preferences: { type: Object, required: true },
});

useWakeLock();

const catalog = bySlug(props.exercises);
const items = props.workout.items;
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

function sound(frequency, duration) {
    if (props.preferences.sound) {
        beep(frequency, duration);
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

    if (countSet && current) {
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
    Object.assign(state, {
        index: target,
        currentDuration: step.duration,
        endAt: step.duration != null ? at + step.duration * 1000 : null,
        startAt: at,
        elapsedBase: 0,
    });

    if (step.kind === 'work') {
        sound(990, 0.35);
        buzz(200);
    } else if (step.kind === 'rest') {
        sound(520, 0.3);
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
    });
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

        if (seconds <= 3 && seconds >= 1 && seconds !== lastBeep) {
            lastBeep = seconds;
            sound(660, 0.1);
        }

        if (remaining <= 0) {
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

const completeSet = () => {
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

    return `${catalog[i.exercise].name} · série ${s.set}/${i.sets} · ${targetLabel(i)}`;
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
                    <span class="text-[14px] font-semibold text-text-soft">{{ targetLabel(item) }}</span>
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
                            <span class="text-[12.5px] font-semibold text-text-soft">{{ setLabel }} · {{ targetLabel(item) }}</span>
                        </span>
                        <button type="button" class="iconbtn size-[38px] bg-surface-2! font-serif text-[16px] font-extrabold text-accent italic" aria-label="Comment faire" @click="openDetail">i</button>
                    </div>
                </template>

                <!-- Effort -->
                <template v-else>
                    <span class="eyebrow text-accent">Effort · {{ setLabel }}</span>
                    <h1 class="display mt-1.5 text-[40px] leading-[0.95] font-extrabold text-balance">{{ exercise.name }}</h1>
                    <div class="mt-2.5 flex w-full items-center gap-3 rounded-[18px] bg-surface p-2 text-left">
                        <span class="size-[60px] shrink-0 overflow-hidden rounded-xl">
                            <ExerciseImage :images="exercise.images" :alt="exercise.name" />
                        </span>
                        <span class="min-w-0 flex-1 text-[13px] font-semibold text-text-soft">{{ exercise.equipment_label }}</span>
                        <button type="button" class="h-[38px] shrink-0 rounded-full bg-surface-2 px-3.5 text-[13px] font-extrabold text-accent" @click="openDetail">
                            Comment faire ?
                        </button>
                    </div>

                    <template v-if="step.mode === 'reps'">
                        <div class="mt-2.5 flex items-baseline gap-2 text-accent">
                            <span class="font-display text-[120px] leading-[0.85] font-extrabold">{{ item.value }}</span>
                            <span class="font-display text-[28px] font-bold">REPS</span>
                        </div>
                        <span class="mt-1.5 text-[13px] font-semibold text-text-muted">Temps sur la série · {{ elapsed }}</span>
                        <button
                            type="button"
                            class="btn-accent mt-[18px] h-[84px] w-full rounded-[26px]! text-[30px] shadow-[0_10px_30px_rgb(212_255_58/0.18)] active:scale-[0.97]"
                            @click="completeSet"
                        >
                            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5" /></svg>
                            Série terminée
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
                <button type="button" class="btn-soft h-[54px] text-[15px]" @click="finishNow">Terminer la séance</button>
                <button type="button" class="h-12 text-[14px] font-bold text-danger" @click="quit">Abandonner sans enregistrer</button>
            </div>
        </template>

        <!-- Séance terminée -->
        <div v-else class="animate-pop flex flex-1 flex-col justify-center gap-2 px-7 pt-6 pb-10">
            <span class="eyebrow text-accent">Séance terminée</span>
            <h1 class="display text-[84px] leading-[0.86] font-extrabold">Bien<br />joué.</h1>
            <p class="mt-2.5 mb-[26px] text-[15px] font-semibold text-text-soft">{{ props.workout.name }}</p>
            <div class="mb-7 grid grid-cols-3 gap-2">
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
