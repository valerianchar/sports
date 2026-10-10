<script setup>
import { computed, onMounted, onUnmounted, reactive, ref } from 'vue';
import BottomSheet from '../../components/BottomSheet.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import ExerciseImage from '../../components/ExerciseImage.vue';
import ExerciseSheet from '../../components/ExerciseSheet.vue';
import FlashToast from '../../components/FlashToast.vue';
import { useWakeLock } from '../../composables/useWakeLock';
import { beep, countdownSound, goSound, loadCustomSound, restSound, unlockAudio, vibrate } from '../../audio';
import { sendLog } from '../../pendingLogs';
import { alertsAllowed, cancelAlerts, clearStatus, resyncAlerts, scheduleAlerts, showStatus } from '../../alerts';
import { describe, nowPlayingSupported, pauseNowPlaying, playTimeline, progress as mediaProgress, startNowPlaying, stopNowPlaying } from '../../nowPlaying';
import { routes } from '../../routes';
import { PLAYER_STORAGE_KEY, RESUME_WINDOW } from '../../session';
import { patchJson } from '../../http';
import { formatKg, formatSet, formatTonnage } from '../../format';
import {
    bySlug,
    clamp,
    dropsOn,
    formatClock,
    formatSetting,
    formatWeight,
    machineFields,
    replaceExercise,
    setWeight,
    settingsLabel,
    stepSetting,
    stepWeight,
    platesLabel,
    roundPlate,
    sidesLabel,
    targetLabel,
    usesWeight,
} from '../../workout';

defineOptions({ layout: null });

const props = defineProps({
    workout: { type: Object, required: true },
    exercises: { type: Array, required: true },
    preferences: { type: Object, required: true },
    history: { type: Object, default: () => ({}) },
    // Les variantes de chaque exercice : la machine est prise, on en change sur place.
    alternatives: { type: Object, default: () => ({}) },
});

useWakeLock();

const catalog = bySlug(props.exercises);
const machine = usePage().props.machine_settings;
// Copie modifiable des exercices : charge, variante et réglages changent en pleine séance.
const items = reactive(props.workout.items.map((item) => ({ ...item, origin: item.exercise })));
const RING_REST = 722.57;
const RING_WORK = 753.98;

/*
 * La séance se déroule en étapes : compte à rebours, séries, repos entre
 * séries, repos entre exercices. Une série en répétitions attend qu'on la
 * valide ; tout le reste se chronomètre.
 *
 * L'ordre des exercices peut changer en route — la machine est prise, on
 * passe au suivant — : les étapes à venir se reconstruisent alors d'après
 * l'ordre voulu et les séries déjà passées de chaque exercice. L'indice d'un
 * exercice reste sa place dans la séance enregistrée.
 */
function upcoming(order, passed = {}) {
    const list = [];
    const pending = order.filter((index) => (passed[index] ?? 0) < items[index].sets);
    const done = (index) => passed[index] ?? 0;

    /*
     * Les groupes : un exercice enchaîné au suivant (superset, circuit) forme un
     * groupe avec lui tant qu'ils se suivent ici. Un groupe se joue par tours —
     * une série de chacun, sans repos entre eux — et le repos tombe après
     * chaque tour, celui du dernier exercice du groupe.
     */
    const groups = [];

    pending.forEach((index, position) => {
        const previous = pending[position - 1];

        if (position > 0 && items[previous].superset && index === previous + 1 && groups.at(-1).includes(previous)) {
            groups.at(-1).push(index);
        } else {
            groups.push([index]);
        }
    });

    groups.forEach((group, number) => {
        const last = items[group.at(-1)];
        const rounds = Math.max(...group.map((index) => items[index].sets));
        const rest = group.length > 1 ? last.rest_sets : items[group[0]].rest_sets;

        for (let set = Math.min(...group.map(done)) + 1; set <= rounds; set++) {
            // Séries d'échauffement avant la première série lourde d'un exercice seul.
            if (group.length === 1 && set === 1 && done(group[0]) === 0) {
                pushWarmups(list, group[0]);
            }

            group
                .filter((index) => set > done(index) && set <= items[index].sets)
                .forEach((index) => pushSet(list, index, set, group.length > 1 ? group : null));

            const nextInGroup = group.find((index) => set + 1 > done(index) && set + 1 <= items[index].sets);

            if (nextInGroup !== undefined && rest > 0) {
                list.push({ kind: 'rest', duration: rest, item: nextInGroup, set: set + 1 });
            }
        }

        const following = groups[number + 1];

        if (following && last.rest_after > 0) {
            list.push({ kind: 'rest', duration: last.rest_after, item: following[0], set: done(following[0]) + 1, between: true });
        }
    });

    return list;
}

/*
 * Avant la première série d'un exercice chargé (30 kg et plus) : 10 reps à
 * 50 %, puis 5 à 70 %, arrondis au disque, 45 s de repos entre chaque. Elles
 * préparent la charge de travail et ne comptent pas comme des séries.
 */
function pushWarmups(list, index) {
    const item = items[index];
    const working = setWeight(item, 1);

    if (!props.preferences.warmup_sets || item.mode !== 'reps' || !usesWeight(catalog[item.exercise]) || !working || working < 30) {
        return;
    }

    [
        [0.5, 10],
        [0.7, 5],
    ].forEach(([ratio, reps], position) => {
        list.push({ kind: 'work', item: index, set: 1, mode: 'reps', duration: null, warmup: position + 1, warmupReps: reps, warmupWeight: roundPlate(working * ratio) });
        list.push({ kind: 'rest', duration: 45, item: index, set: 1, afterWarmup: true });
    });
}

/** Les étapes d'une série : un ou deux côtés, puis les paliers de drop sans repos. */
function pushSet(list, index, set, group) {
    const item = items[index];
    // Un côté puis l'autre : chaque série se joue en deux temps, côté droit puis côté gauche.
    const sides = catalog[item.exercise]?.sides === 'each' ? ['droit', 'gauche'] : [undefined];
    const superset = group ? { superset: group.indexOf(index) + 1, supersetSize: group.length } : {};

    sides.forEach((side) => list.push({ kind: 'work', item: index, set, mode: item.mode, duration: item.mode === 'time' ? item.value : null, side, ...superset }));

    if (dropsOn(item, set)) {
        item.drops.forEach((_, drop) => list.push({ kind: 'work', item: index, set, mode: 'reps', duration: null, drop, ...superset }));
    }
}

function buildSteps() {
    const list = upcoming(items.map((_, index) => index));

    if (props.preferences.prep_seconds > 0 && list.length) {
        list.unshift({ kind: 'prep', duration: props.preferences.prep_seconds, item: list[0].item, set: 1 });
    }

    return list;
}

/** L'étape qui achève une série : ni un palier de drop, ni le côté droit d'une série à deux côtés. */
const isSetEnd = (s) => s.kind === 'work' && s.drop === undefined && s.side !== 'droit' && !s.warmup;

/** Séries principales passées de chaque exercice (les paliers de drop n'en sont pas). */
function countPassed(list) {
    const passed = {};

    for (const s of list) {
        if (isSetEnd(s)) {
            passed[s.item] = (passed[s.item] ?? 0) + 1;
        }
    }

    return passed;
}

const initialSteps = buildSteps();

/*
 * Une séance ne compte dans les statistiques que menée au bout : toutes les
 * séries prévues faites (les paliers de drop prolongent une série, ils ne
 * s'ajoutent pas au compte).
 */
const plannedSets = initialSteps.filter(isSetEnd).length;

/*
 * Tous les instants sont absolus (Date.now) : un onglet endormi ou un écran
 * éteint ne fait pas dériver les minuteurs, qui rattrapent le temps au réveil.
 */
const state = reactive({
    clientId: crypto.randomUUID(),
    steps: initialSteps,
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
const STORAGE_KEY = PLAYER_STORAGE_KEY;
let lastSaved = 0;

function persist(force = false) {
    if (!force && Date.now() - lastSaved < 2000) {
        return;
    }

    lastSaved = Date.now();

    try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify({ workoutId: props.workout.id, savedAt: lastSaved, state, items }));
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

        if (!saved.state.steps?.length || saved.state.index >= saved.state.steps.length || saved.items?.length !== items.length) {
            return false;
        }

        Object.assign(state, saved.state);
        // Variantes et charges choisies en route.
        saved.items.forEach((item, index) => Object.assign(items[index], item));

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

/*
 * « Comme une vidéo » : la séance joue sa propre piste (bips compris), affichée
 * sur l'écran verrouillé. Les bips du Web Audio se taisent alors pour ne pas
 * sonner deux fois.
 */
const videoMode = props.preferences.sound && props.preferences.audio_mode === 'prioritaire' && nowPlayingSupported();
const beeps = () => props.preferences.sound && !videoMode;

function sound(frequency, duration) {
    if (beeps()) {
        beep(frequency, duration, volume());
    }
}

function buzz(pattern) {
    if (props.preferences.sound) {
        vibrate(pattern);
    }
}

/*
 * `startAt` : l'instant où l'étape aurait dû commencer. Une étape chronométrée
 * finie pendant que l'appli dormait en arrière-plan enchaîne sur la suivante à
 * l'heure prévue — celle des alertes envoyées —, pas à l'heure du retour ; les
 * sons de ce rattrapage se taisent.
 */
function goTo(target, countSet = false, startAt = null) {
    const at = Date.now();
    const begin = startAt ?? at;
    const late = at - begin > 1500;
    const current = state.steps[state.index];
    lastBeep = null;

    // Un palier de drop prolonge la série : il ne compte pas comme une série de plus.
    if (countSet && current && isSetEnd(current)) {
        state.doneSets += 1;
        state.doneItems = { ...state.doneItems, [current.item]: true };
    }

    if (state.paused) {
        state.pausedTotal += at - state.pausedAt;
        state.paused = false;
    }

    now.value = at;

    if (target >= state.steps.length) {
        finish(begin);

        return;
    }

    const step = state.steps[target];
    state.repsDone = null;
    Object.assign(state, {
        index: target,
        currentDuration: step.duration,
        endAt: step.duration != null ? begin + step.duration * 1000 : null,
        startAt: begin,
        elapsedBase: 0,
    });

    if (late) {
        syncMedia(false);
        persist(true);

        return;
    }

    syncMedia(true);

    if (step.kind === 'work') {
        if (beeps()) {
            goSound(cue(), volume());
        }

        buzz(200);
    } else if (step.kind === 'rest') {
        if (beeps()) {
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
    stopNowPlaying();
    clearStatus();

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
        kcal.value = response?.kcal ?? null;
    });
}

/*
 * Note la série qui vient d'être faite : charge, répétitions réellement
 * faites (ajustées au − / + si besoin), objectif, ou durée au chrono.
 */
function perform(s, seconds = null) {
    // Le côté droit n'est que la moitié de la série : elle se note une fois le côté gauche fait.
    // Une série d'échauffement ne se note pas.
    if (s.side === 'droit' || s.warmup) {
        return;
    }

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
        per_side: Boolean(it.per_side),
        at: new Date().toISOString(),
    });
}

const mainSetsDone = computed(() => state.performed.filter((set) => set.drop === null).length);
const complete = computed(() => mainSetsDone.value >= plannedSets);

const sessionTonnage = computed(() => state.performed.reduce((total, set) => total + (set.weight && set.reps ? set.weight * set.reps : 0), 0));

// Fin de séance : records battus (réponse du serveur) et difficulté ressentie.
const records = ref(null);
// Calories estimées par le serveur, au poids de la dernière pesée.
const kcal = ref(null);
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
    const step = state.steps[state.index];

    if (step.duration != null) {
        const remaining = state.endAt - at;
        const seconds = Math.ceil(remaining / 1000);

        // Les dernières secondes d'un repos, d'une série chronométrée ou du départ.
        if (seconds <= (props.preferences.countdown_seconds ?? 5) && seconds >= 1 && seconds !== lastBeep) {
            const first = lastBeep === null;
            lastBeep = seconds;

            if (beeps()) {
                countdownSound(cue(), seconds, volume(), { first });
            }
        }

        if (remaining <= 0) {
            if (step.kind === 'work') {
                perform(step, step.duration);
            }

            goTo(state.index + 1, step.kind === 'work', state.endAt);

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
    pauseNowPlaying();
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
    syncMedia(true);
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

    // Le décompte bouge : la piste de l'écran verrouillé aussi.
    syncMedia(true);
    persist(true);
}

/** Répétitions et charge d'une étape d'effort : la série, ou un palier de drop. */
function load(s) {
    const it = items[s.item];

    if (s.warmup) {
        return { reps: s.warmupReps, weight: s.warmupWeight };
    }

    if (s.drop !== undefined) {
        return { reps: it.drops[s.drop].reps, weight: it.drops[s.drop].weight ?? null };
    }

    return { reps: it.value, weight: setWeight(it, s.set) };
}

/** « 8 reps · 60 kg » : la cible d'une étape, charge comprise. */
function target(s) {
    const it = items[s.item];
    const { reps, weight } = load(s);
    const effort = it.mode === 'reps' || s.drop !== undefined ? `${reps} reps${it.per_side && s.drop === undefined ? ' / côté' : ''}` : targetLabel(it);

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
    setWeightFor(step.value, weight);
}

/** Fixe la charge d'une série donnée (`{ item, set, drop }`), en cours ou à venir. */
function setWeightFor(current, weight) {
    // L'échauffement se règle pour cette fois seulement : sa charge découle de celle de travail.
    if (current.warmup) {
        const warmup = state.steps.find((s) => s.item === current.item && s.warmup === current.warmup);
        warmup.warmupWeight = weight;
        current.warmupWeight = weight;

        return;
    }

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

    if (!isOriginal(position)) {
        return;
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

// ---------------------------------------------------------------- saisie directe

/*
 * Taper une valeur plutôt que d'enchaîner les − / + : la charge (vide = poids
 * du corps) ou les répétitions, à l'effort comme pendant le repos — pour la
 * série qui vient, ou pour corriger celle qu'on vient de faire.
 */
const quick = reactive({ open: false, kind: 'weight', title: '', value: '', apply: null, error: null });

function openQuick(kind, title, value, apply) {
    Object.assign(quick, {
        open: true,
        kind,
        title,
        value: value === null || value === undefined ? '' : String(value).replace('.', ','),
        apply,
        error: null,
    });
}

function applyQuick() {
    const raw = String(quick.value).trim().replace(',', '.');

    if (quick.kind === 'weight') {
        const weight = raw === '' ? null : Number(raw);

        if (weight !== null && (!Number.isFinite(weight) || weight < 0 || weight > 999)) {
            quick.error = 'Une charge entre 0 et 999 kg — vide pour le poids du corps.';

            return;
        }

        quick.apply(weight === 0 ? null : weight === null ? null : Math.round(weight * 100) / 100);
    } else {
        const reps = Number(raw);

        if (!Number.isInteger(reps) || reps < 0 || reps > 100) {
            quick.error = 'Un nombre de répétitions entre 0 et 100.';

            return;
        }

        quick.apply(reps);
    }

    quick.open = false;
}

/** La dernière série principale faite, si elle précède le repos en cours : on peut la corriger. */
const lastSet = computed(() => {
    if (step.value?.kind !== 'rest') {
        return null;
    }

    for (let i = state.performed.length - 1; i >= 0; i--) {
        if (state.performed[i].drop === null) {
            return { index: i, ...state.performed[i] };
        }
    }

    return null;
});

function correctLastReps(reps) {
    state.performed[lastSet.value.index].reps = reps;
    persist(true);
}

function correctLastWeight(weight) {
    const entry = lastSet.value;
    state.performed[entry.index].weight = weight;
    // La charge corrigée devient aussi celle de cette série pour la prochaine fois.
    setWeightFor({ item: entry.position, set: entry.set }, weight);
    persist(true);
}

/** La série qui suit le repos en cours, pour régler sa charge pendant qu'on charge la barre. */
const upcomingSet = computed(() => (step.value?.kind === 'rest' && !step.value.afterWarmup ? { item: step.value.item, set: step.value.set } : null));

const completeSet = () => {
    perform(step.value);
    unlockAudio();
    goTo(state.index + 1, true);
};
const next = () => goTo(state.index + 1);
const previous = () => goTo(Math.max(0, state.index - 1));
const restartStep = () => goTo(state.index);
const finishNow = () => goTo(state.steps.length);

function quit() {
    forget();
    stopNowPlaying();
    clearStatus();
    router.visit(routes.home);
}

/*
 * La fiche d'un exercice (comment s'en servir) s'ouvre aussi depuis une
 * feuille de choix : la feuille s'efface le temps de la lecture et revient
 * quand on ferme la fiche.
 */
let reopen = null;

function explain(slug, close = null, back = null) {
    close?.();
    reopen = back;
    detail.value = slug;
}

function closeDetail() {
    detail.value = null;
    reopen?.();
    reopen = null;
}

function openDetail() {
    pause();
    detail.value = step.value ? items[step.value.item].exercise : null;
}

// ---------------------------------------------------------------- ordre des exercices

/*
 * Le programme de la séance : les exercices passés, puis ceux à venir dans
 * l'ordre où le lecteur les jouera — le premier est celui en cours (ou celui
 * qui suit le repos).
 */
const program = computed(() => {
    const past = state.steps.slice(0, state.index);
    const ahead = [...new Set(state.steps.slice(state.index).map((s) => s.item))];
    const done = [...new Set(past.map((s) => s.item))].filter((index) => !ahead.includes(index));

    return { done, ahead, passed: countPassed(past) };
});

/** La dernière étape (exclue) du bloc de l'exercice en cours, repos entre séries compris. */
function blockEnd(at) {
    // Le bloc en cours court jusqu'au prochain repos « changement d'exo » : un superset reste entier.
    let end = at + 1;

    while (end < state.steps.length && !state.steps[end].between) {
        end++;
    }

    return end;
}

/*
 * Rejoue la suite de la séance dans un nouvel ordre. Si l'exercice en cours
 * reste en tête, on ne touche qu'à ce qui le suit : son chrono continue. Sinon
 * le nouvel exercice démarre aussitôt — pendant un repos ou le compte à
 * rebours, ceux-ci continuent et y mènent.
 */
function rearrange(order, { restart = false, redo = null } = {}) {
    const at = state.index;
    const current = state.steps[at];

    if (!current) {
        return;
    }

    if (order[0] === current.item && !restart) {
        const end = blockEnd(at);
        const passed = countPassed(state.steps.slice(0, end));
        let tail = upcoming(order.slice(1), passed);
        const head = items[current.item];

        if (tail.length && head.rest_after > 0) {
            tail = [{ kind: 'rest', duration: head.rest_after, item: tail[0].item, set: tail[0].set, between: true }, ...tail];
        }

        state.steps = [...state.steps.slice(0, end), ...tail];
        syncMedia(true);
        persist(true);

        return;
    }

    const passed = countPassed(state.steps.slice(0, at));

    // Un exercice sauté qu'on reprend : seules ses séries vraiment faites comptent.
    if (redo !== null) {
        passed[redo] = performedByItem.value[redo] ?? 0;
    }

    let future = upcoming(order, passed);

    if (future.length && (current.kind === 'rest' || current.kind === 'prep')) {
        const left = Math.max(1, Math.ceil(remaining.value / 1000));
        future = [{ ...current, duration: left, item: future[0].item, set: future[0].set, between: current.kind === 'rest' ? true : undefined }, ...future];
    }

    state.steps = [...state.steps.slice(0, at), ...future];
    goTo(at);
}

/** Cet exercice maintenant : il passe en tête, les autres gardent leur ordre. */
function doNow(index) {
    rearrange([index, ...program.value.ahead.filter((other) => other !== index)]);
    programOpen.value = false;
    toast(`C'est parti : ${catalog[items[index].exercise].name}`);
}

/*
 * La machine est prise : l'exercice passe après les autres, mais avant les
 * étirements de fin.
 */
function later(index) {
    const others = program.value.ahead.filter((other) => other !== index);
    let at = others.length;

    while (at > 0 && catalog[items[others[at - 1]].exercise]?.group === 'mobilite') {
        at--;
    }

    if (at === 0) {
        toast('Plus rien d’autre avant : il reste en tête.');

        return;
    }

    rearrange([...others.slice(0, at), index, ...others.slice(at)]);
    programOpen.value = false;
    toast(`${catalog[items[index].exercise].name} : plus tard`);
}

/** Monte ou descend un exercice à venir, sans toucher à celui en cours. */
function shift(position, direction) {
    const ahead = [...program.value.ahead];
    const target = position + direction;

    if (position < 1 || target < 1 || target >= ahead.length) {
        return;
    }

    [ahead[position], ahead[target]] = [ahead[target], ahead[position]];
    rearrange(ahead);
}

const programOpen = ref(false);

/*
 * Ce qui est fait et ce qui reste, exercice par exercice : d'après les séries
 * réellement validées (pas l'ordre de passage), pour que l'état reste juste
 * quand l'ordre change ou qu'on saute un exercice.
 */
const performedByItem = computed(() => {
    const counts = {};

    for (const set of state.performed) {
        if (set.drop === null) {
            counts[set.position] = (counts[set.position] ?? 0) + 1;
        }
    }

    return counts;
});

/** fait, en cours, commencé, sauté (passé sans être fait) ou à faire. */
function statusOf(index) {
    const done = performedByItem.value[index] ?? 0;

    if (done >= items[index].sets) {
        return 'done';
    }

    if (step.value && step.value.item === index) {
        return 'current';
    }

    if (program.value.done.includes(index)) {
        return 'skipped';
    }

    return done > 0 ? 'partial' : 'todo';
}

const STATUS_LABELS = { done: 'fait', current: 'en cours', partial: 'commencé', skipped: 'sauté', todo: 'à faire' };

/** Un segment par exercice, dans l'ordre où la séance se joue. */
const strip = computed(() =>
    [...program.value.done, ...program.value.ahead].map((index) => ({
        index,
        status: statusOf(index),
        progress: Math.min(1, (performedByItem.value[index] ?? 0) / items[index].sets),
    })),
);

const doneCount = computed(() => strip.value.filter((segment) => segment.status === 'done').length);
const leftCount = computed(() => items.length - doneCount.value);

/** Reprendre maintenant un exercice sauté ou laissé en route. */
function redo(index) {
    rearrange([index, ...program.value.ahead.filter((other) => other !== index)], { redo: index });
    programOpen.value = false;
    toast(`On reprend : ${catalog[items[index].exercise].name}`);
}

function toast(message) {
    document.dispatchEvent(new CustomEvent('seance:toast', { detail: { message, error: false } }));
}

// ---------------------------------------------------------------- variantes

/*
 * Changer un exercice pour aujourd'hui : les variantes préparées par le
 * serveur (mêmes muscles), sans réseau. La séance enregistrée, elle, ne
 * change pas.
 */
const swapping = ref(null);
// La ligne dont la feuille de variantes revient après la lecture d'une fiche.
let reopenIndex = null;
const swapOpen = computed({
    get: () => swapping.value !== null,
    set: (open) => {
        if (!open) {
            swapping.value = null;
        }
    },
});

const swapChoices = computed(() => {
    if (swapping.value === null) {
        return [];
    }

    const item = items[swapping.value];
    const inSession = items.map((other) => other.exercise);

    return [item.origin, ...(props.alternatives[item.origin] ?? [])]
        .filter((slug) => slug !== item.exercise && !inSession.includes(slug) && catalog[slug])
        .map((slug) => catalog[slug]);
});

function swap(slug) {
    const index = swapping.value;
    const item = items[index];
    const before = catalog[item.exercise].name;

    Object.assign(items[index], replaceExercise(item, catalog[slug], catalog[item.exercise], machine));
    swapping.value = null;
    programOpen.value = false;

    // La mesure a pu changer (durée ↔ répétitions) : les étapes de cet exercice se refont.
    rearrange(program.value.ahead, { restart: state.steps[state.index]?.item === index });
    toast(`${before} → ${catalog[slug].name}`);
}

/** Une variante du jour n'est pas l'exercice enregistré : sa charge et ses réglages ne se gardent pas. */
const isOriginal = (index) => items[index].exercise === items[index].origin;

// ---------------------------------------------------------------- réglages de machine

const settingFields = computed(() => machineFields(exercise.value, machine));
const settingTimers = {};

function changeSetting(field, direction) {
    const position = step.value.item;
    const it = items[position];
    it[field] = stepSetting(field, it[field], direction, machine);

    if (!isOriginal(position)) {
        return;
    }

    clearTimeout(settingTimers[`${position}-${field}`]);
    settingTimers[`${position}-${field}`] = setTimeout(() => {
        patchJson(props.workout.urls.settings, { position, [field]: it[field] }).catch(() => {
            // Hors réseau : le réglage vaut pour aujourd'hui seulement.
        });
    }, 700);
}

const itemSettings = (index) => settingsLabel(items[index], machineFields(catalog[items[index].exercise], machine));

// ---------------------------------------------------------------- affichage

const step = computed(() => (state.done ? null : state.steps[state.index]));
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
const setLabel = computed(() =>
    step.value?.warmup
        ? `Échauffement ${step.value.warmup}/2 · ${step.value.warmup === 1 ? '50' : '70'} %`
        : item.value
        ? `Série ${step.value.set} / ${item.value.sets}${step.value.side ? ` · côté ${step.value.side}` : ''}${step.value.superset ? ` · ${step.value.supersetSize > 2 ? 'circuit' : 'superset'} ${step.value.superset}/${step.value.supersetSize}` : ''}`
        : '',
);
// « en alternant les côtés », « tout d'un côté, puis l'autre » : rappelé sous l'exercice.
// L'heure de fin du repos en cours : on sait quand repartir sans regarder le chrono.
const resumeAt = computed(() =>
    step.value?.kind === 'rest' && !state.paused && state.endAt ? new Date(state.endAt).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit', second: '2-digit' }) : null,
);
const sidesHint = computed(() => (item.value ? sidesLabel(exercise.value, item.value) : null));
const counter = computed(() => (step.value ? `Exercice ${Math.min(items.length, program.value.done.length + 1)} / ${items.length}` : ''));

const nextLabel = computed(() => {
    const upcoming = state.steps.findIndex((s, index) => index > state.index && s.kind === 'work');

    if (upcoming === -1) {
        return 'Fin de la séance';
    }

    const s = state.steps[upcoming];
    const i = items[s.item];

    const label = s.drop !== undefined ? `drop ${s.drop + 1}/${i.drops.length}` : `série ${s.set}/${i.sets}${s.side ? ` · côté ${s.side}` : ''}`;

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
    if (state.done || detail.value || programOpen.value || swapping.value !== null || event.code !== 'Space' || /INPUT|TEXTAREA/.test(document.activeElement?.tagName)) {
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

// ---------------------------------------------------------------- comme une vidéo

/*
 * La piste de la séance depuis maintenant : les bips de début d'étape et du
 * décompte de chaque étape chronométrée, jusqu'à la prochaine série en
 * répétitions (qui attend qu'on la valide) ou vingt minutes.
 */
function timeline() {
    const current = state.steps[state.index];
    const events = [];

    if (!current || state.paused || state.done) {
        return { seconds: 1, events };
    }

    const lead = props.preferences.countdown_seconds ?? 5;
    const startBeep = (s, at) => events.push(s.kind === 'work' ? { at, frequency: 990, duration: 0.35 } : { at, frequency: 520, duration: 0.3 });

    // L'étape vient de commencer : son bip d'entrée ouvre la piste.
    if (current.kind !== 'prep' && Date.now() - state.startAt < 1500) {
        startBeep(current, 0);
    }

    let index = state.index;
    let s = current;
    let end = s.duration != null ? (state.endAt - Date.now()) / 1000 : 0;

    while (s && s.duration != null && end <= 20 * 60) {
        for (let k = lead; k >= 1; k--) {
            if (end - k >= 0) {
                events.push({ at: end - k, frequency: k === 1 ? 1320 : 880, duration: k === 1 ? 0.28 : 0.12 });
            }
        }

        const following = state.steps[index + 1];

        if (!following) {
            events.push({ at: end, frequency: 1175, duration: 0.45 });
            break;
        }

        startBeep(following, end);

        if (following.duration == null) {
            break;
        }

        index++;
        s = following;
        end += s.duration;
    }

    return { seconds: Math.min(end, 20 * 60) + 1, events };
}

/** L'écran verrouillé suit l'étape : exercice, série, reprise, progression. */
function syncMedia(rebuild) {
    if (!videoMode || state.done) {
        return;
    }

    const s = state.steps[state.index];

    if (!s) {
        return;
    }

    const it = items[s.item];
    const exercise = catalog[it.exercise];
    const clock = (ms) => new Date(ms).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    const label = s.drop !== undefined ? `drop ${s.drop + 1}` : `série ${s.set}/${it.sets}${s.side ? ` · côté ${s.side}` : ''}`;
    const artist = {
        prep: `Prépare-toi · ${target(s)}`,
        rest: `Repos jusqu'à ${clock(state.endAt)} · puis ${label}`,
        work: `${label.charAt(0).toUpperCase()}${label.slice(1)} · ${target(s)}`,
    }[s.kind];

    describe({ title: exercise.name, artist, album: `${props.workout.name} · ${counter.value}`, image: exercise.images[0] });
    mediaProgress(s.duration ?? 0, (Date.now() - state.startAt) / 1000, !state.paused);

    // Une nouvelle piste ne se lance qu'appli à l'écran ou depuis un bouton de
    // l'écran verrouillé : en arrière-plan, la piste en cours porte déjà la suite.
    if (rebuild && (!document.hidden || mediaAction)) {
        playTimeline(timeline(), volume());
    }
}

let mediaAction = false;

/** Un bouton de l'écran verrouillé : il vaut un geste, la piste peut repartir. */
function fromLockScreen(action) {
    mediaAction = true;

    try {
        action();
    } finally {
        mediaAction = false;
    }
}

function startVideoMode() {
    startNowPlaying({
        play: () => fromLockScreen(resume),
        pause: () => fromLockScreen(pause),
        // « Suivant » : la série en répétitions est faite ; ailleurs, l'étape suivante.
        next: () => fromLockScreen(() => (step.value?.kind === 'work' && step.value.mode === 'reps' && step.value.duration == null ? completeSet() : next())),
        previous: () => fromLockScreen(previous),
        tick: () => tick(),
    });
}

/** « 18:42 » : l'heure de fin d'une étape chronométrée, pour le centre de notifications. */
function statusLine() {
    const s = state.steps[state.index];

    if (!s || state.done) {
        return null;
    }

    const it = items[s.item];
    const name = catalog[it.exercise].name;
    const label = s.drop !== undefined ? `drop ${s.drop + 1}` : `série ${s.set}/${it.sets}${s.side ? ` · côté ${s.side}` : ''}`;
    const until = s.duration != null && !state.paused ? ` jusqu'à ${new Date(state.endAt).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })}` : '';

    if (state.paused) {
        return `En pause · ${name}, ${label}`;
    }

    if (s.kind === 'prep') {
        return `Départ${until.replace('jusqu\'à', 'à')} · ${name}, ${label} · ${target(s)}`;
    }

    return s.kind === 'rest' ? `Repos${until} · puis ${name}, ${label} · ${target(s)}` : `${name} · ${label} · ${target(s)}${until}`;
}

// ---------------------------------------------------------------- alertes en arrière-plan

/*
 * L'appli passe en arrière-plan en plein repos : iOS va endormir la page et ses
 * bips. On confie au serveur les fins d'étapes chronométrées à venir — jusqu'à
 * la prochaine série en répétitions, qui attend qu'on revienne —, avec un
 * avertissement avant la fin des repos d'au moins 20 s. Au retour, on les
 * reprend : le lecteur sonne de nouveau lui-même.
 */
let alertsOut = false;

function announce(s) {
    if (!s) {
        return { title: 'Séance terminée', body: 'Reviens dans l’appli pour l’enregistrer.' };
    }

    const it = items[s.item];
    const name = catalog[it.exercise].name;

    if (s.kind === 'rest') {
        return { title: 'Repos', body: `${formatClock(s.duration)} de repos, puis ${name}` };
    }

    const label = s.drop !== undefined ? `drop ${s.drop + 1}` : `série ${s.set}/${it.sets}${s.side ? ` · côté ${s.side}` : ''}`;

    return { title: 'C’est reparti !', body: [name, label, target(s), itemSettings(s.item)].filter(Boolean).join(' · ') };
}

function upcomingAlerts() {
    const current = state.steps[state.index];

    if (!current || current.duration == null || state.paused || state.done) {
        return [];
    }

    const lead = Math.max(5, props.preferences.countdown_seconds || 5);
    const alerts = [];
    let index = state.index;
    let s = current;
    let end = (state.endAt - Date.now()) / 1000;

    while (s && s.duration != null && alerts.length < 38 && end <= 7200) {
        const following = state.steps[index + 1];

        if (s.kind === 'rest' && s.duration >= 20 && end - lead > 1) {
            alerts.push({ in: end - lead, title: `Plus que ${lead} s de repos`, body: announce(following).body });
        }

        alerts.push({ in: Math.max(0, end), ...announce(following) });

        if (!following || following.duration == null) {
            break;
        }

        index++;
        s = following;
        end += s.duration;
    }

    return alerts;
}

function onVisibility() {
    // Comme une vidéo : au retour, la piste se resynchronise. Les notifications
    // partent quand même — iOS peut couper le son d'une appli d'écran d'accueil.
    if (videoMode && document.visibilityState === 'visible') {
        syncMedia(true);
    }

    if (!alertsAllowed()) {
        return;
    }

    // La séance en cours, dans le centre de notifications.
    if (document.visibilityState === 'hidden' && !state.done) {
        showStatus(`Séance en cours · ${props.workout.name}`, statusLine());
    }

    if (!props.preferences.sound) {
        return;
    }

    if (document.visibilityState === 'hidden') {
        const alerts = upcomingAlerts();

        if (alerts.length) {
            scheduleAlerts(state.clientId, alerts);
            alertsOut = true;
        }
    } else if (alertsOut) {
        cancelAlerts(state.clientId);
        alertsOut = false;
    }
}

onMounted(() => {
    // Le son personnel se charge pendant le compte à rebours de départ.
    if (props.preferences.sound && cue() === 'perso') {
        loadCustomSound(props.preferences.custom_sound_url);
    }

    if (videoMode) {
        startVideoMode();
    }

    if (!restore()) {
        goTo(0);
    } else {
        syncMedia(false);
    }

    // Le téléphone redonne son abonnement au serveur : un abonnement perdu se répare seul.
    resyncAlerts();

    // La séance lancée apparaît dans le centre de notifications.
    if (alertsAllowed()) {
        showStatus(`Séance lancée · ${props.workout.name}`, `${items.length} exercice${items.length > 1 ? 's' : ''} · ${statusLine() ?? ''}`);
    }

    timer = setInterval(tick, 200);
    window.addEventListener('keydown', onKey);
    document.addEventListener('visibilitychange', onVisibility);
});

onUnmounted(() => {
    clearInterval(timer);
    window.removeEventListener('keydown', onKey);
    document.removeEventListener('visibilitychange', onVisibility);

    if (alertsOut) {
        cancelAlerts(state.clientId);
    }

    stopNowPlaying();
});
</script>

<template>
    <Head :title="props.workout.name" />

    <div
        class="relative mx-auto flex h-dvh w-full max-w-[480px] flex-col overflow-hidden bg-bg pt-[env(safe-area-inset-top)] text-text select-none min-[520px]:border-x min-[520px]:border-divider"
    >
        <template v-if="!state.done">
            <!-- En-tête : quitter (met en pause), exercice courant, temps total. -->
            <!-- Assez bas et assez gros pour un pouce, sous l'encoche et la barre d'état. -->
            <div class="flex items-center justify-between px-4 pt-3 pb-2.5">
                <button type="button" class="iconbtn size-12 bg-surface-2!" aria-label="Mettre en pause ou quitter" @click="pause">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18" /></svg>
                </button>
                <button
                    type="button"
                    class="flex h-11 items-center gap-1.5 rounded-full bg-surface px-4 text-[12px] font-extrabold tracking-[0.12em] text-text-soft uppercase"
                    aria-label="Programme de la séance : changer l'ordre des exercices"
                    @click="programOpen = true"
                >
                    {{ counter }}
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6" /></svg>
                </button>
                <span class="min-w-10 text-right font-display text-[20px] font-semibold text-text-muted tabular-nums">
                    {{ formatClock(activeMilliseconds / 1000) }}
                </span>
            </div>
            <!-- Un segment par exercice : fait (plein), en cours, commencé, sauté (orange), à faire. -->
            <button
                type="button"
                class="mx-5 flex h-3 items-center gap-[2px]"
                :aria-label="`${doneCount} exercice${doneCount > 1 ? 's' : ''} fait${doneCount > 1 ? 's' : ''} sur ${items.length} : voir le programme`"
                @click="programOpen = true"
            >
                <span
                    v-for="segment in strip"
                    :key="segment.index"
                    class="relative h-1.5 min-w-[3px] flex-1 overflow-hidden rounded-full"
                    :class="{
                        'bg-accent': segment.status === 'done',
                        'bg-text/30': segment.status === 'current',
                        'bg-prep': segment.status === 'skipped',
                        'bg-surface-3': segment.status === 'partial' || segment.status === 'todo',
                    }"
                    :data-status="segment.status"
                >
                    <span
                        v-if="segment.status === 'current' || segment.status === 'partial'"
                        class="absolute inset-y-0 left-0 bg-accent transition-[width] duration-300"
                        :style="{ width: `${segment.progress * 100}%` }"
                    />
                </span>
            </button>

            <main class="flex min-h-0 flex-1 flex-col items-center justify-center gap-1.5 px-6 py-3 text-center">
                <!-- Compte à rebours de départ -->
                <template v-if="step.kind === 'prep'">
                    <span class="eyebrow text-prep">Prépare-toi</span>
                    <span class="font-display text-[190px] leading-[0.9] font-extrabold text-prep tabular-nums" aria-live="polite">{{ seconds }}</span>
                    <span class="mt-2.5 text-[13px] font-semibold text-text-muted">Premier exercice</span>
                    <span class="display text-[34px] font-extrabold">{{ exercise.name }}</span>
                    <span class="text-[14px] font-semibold text-text-soft">{{ target(step) }}</span>
                    <span v-if="itemSettings(step.item)" class="text-[13px] font-bold text-accent">{{ itemSettings(step.item) }}</span>
                    <button type="button" class="mt-3 h-10 rounded-full bg-surface px-4 text-[13px] font-extrabold text-accent" @click="programOpen = true">
                        Machine prise ? Changer d'exercice
                    </button>
                </template>

                <!-- Repos -->
                <template v-else-if="step.kind === 'rest'">
                    <span class="eyebrow text-rest">{{ step.afterWarmup ? 'Récup · échauffement' : step.between ? "Repos · changement d'exo" : 'Repos entre les séries' }}</span>
                    <span v-if="resumeAt" class="-mt-1 text-[12.5px] font-bold text-text-muted">Reprise à {{ resumeAt }}</span>
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
                            <span v-if="itemSettings(step.item)" class="text-[12px] font-bold text-accent">{{ itemSettings(step.item) }}</span>
                        </span>
                        <button type="button" class="iconbtn size-[38px] bg-surface-2! font-serif text-[16px] font-extrabold text-accent italic" aria-label="Comment faire" @click="openDetail">i</button>
                    </div>
                    <!-- Pendant le repos : la charge de la série qui vient, et la série faite à corriger. -->
                    <div v-if="upcomingSet && usesWeight(exercise)" class="mt-2.5 flex w-full items-center justify-between gap-2 rounded-[18px] bg-surface p-1.5 pl-4">
                        <span class="text-[11px] font-extrabold tracking-[0.1em] text-text-muted uppercase">Charge à venir</span>
                        <span class="flex items-center gap-1.5">
                            <button type="button" class="iconbtn size-10 bg-surface-2! text-[20px] font-semibold disabled:opacity-35" aria-label="Charge à venir : moins" :disabled="load(upcomingSet).weight === null" @click="setWeightFor(upcomingSet, stepWeight(load(upcomingSet).weight, -1))">−</button>
                            <button
                                type="button"
                                class="min-w-[96px] text-center font-display text-[24px] leading-none font-extrabold tabular-nums underline decoration-line-strong decoration-2 underline-offset-[5px]"
                                :class="load(upcomingSet).weight === null ? 'text-[15px]! text-text-muted' : ''"
                                :aria-label="`Charge à venir ${formatWeight(load(upcomingSet).weight) ?? 'poids du corps'} : toucher pour la taper`"
                                @click="openQuick('weight', 'Charge de la prochaine série', load(upcomingSet).weight, (weight) => setWeightFor(upcomingSet, weight))"
                            >
                                {{ formatWeight(load(upcomingSet).weight) ?? 'Poids du corps' }}
                            </button>
                            <button type="button" class="iconbtn size-10 bg-surface-2! text-[20px] font-semibold" aria-label="Charge à venir : plus" @click="setWeightFor(upcomingSet, stepWeight(load(upcomingSet).weight, 1))">+</button>
                        </span>
                    </div>
                    <p v-if="upcomingSet && usesWeight(exercise) && platesLabel(load(upcomingSet).weight, exercise.equipment)" class="mt-1.5 text-[12.5px] font-semibold text-text-muted">
                        {{ platesLabel(load(upcomingSet).weight, exercise.equipment) }}
                    </p>
                    <p v-if="lastSet && lastSet.reps !== null" class="mt-2 flex flex-wrap items-center justify-center gap-x-2 gap-y-1 text-[13px] font-semibold text-text-muted">
                        <span>Série faite :</span>
                        <button type="button" class="rounded-lg bg-surface px-2.5 py-1 font-bold text-text" :aria-label="`${lastSet.reps} répétitions faites : corriger`" @click="openQuick('reps', 'Répétitions faites (série précédente)', lastSet.reps, correctLastReps)">
                            {{ lastSet.reps }} reps ✎
                        </button>
                        <button
                            v-if="usesWeight(catalog[lastSet.exercise])"
                            type="button"
                            class="rounded-lg bg-surface px-2.5 py-1 font-bold text-text"
                            :aria-label="`Charge ${formatWeight(lastSet.weight) ?? 'poids du corps'} : corriger`"
                            @click="openQuick('weight', 'Charge utilisée (série précédente)', lastSet.weight, correctLastWeight)"
                        >
                            {{ formatWeight(lastSet.weight) ?? 'poids du corps' }} ✎
                        </button>
                    </p>
                    <button v-if="step.between" type="button" class="mt-2 h-10 rounded-full bg-surface px-4 text-[13px] font-extrabold text-accent" @click="programOpen = true">
                        Machine prise ? Changer d'exercice
                    </button>
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
                            <span v-if="sidesHint" class="text-[11.5px] leading-tight font-bold text-accent">{{ sidesHint }}</span>
                            <span v-if="lastTime" class="truncate text-[11.5px] font-semibold text-text-muted">
                                Dernière fois : {{ lastTimeText }}
                            </span>
                        </span>
                        <button type="button" class="iconbtn size-[38px] shrink-0 bg-surface-2! font-serif text-[16px] font-extrabold text-accent italic" aria-label="Comment faire" @click="openDetail">i</button>
                        <button
                            v-if="step.drop === undefined"
                            type="button"
                            class="h-[38px] shrink-0 rounded-full bg-surface-2 px-3.5 text-[13px] font-extrabold text-accent"
                            aria-label="Machine prise : changer d'exercice ou d'ordre"
                            @click="programOpen = true"
                        >
                            Changer
                        </button>
                    </div>

                    <!-- Réglages de la machine de cardio : vitesse, inclinaison, niveau. -->
                    <div v-if="settingFields.length" class="mt-2.5 flex w-full justify-center gap-2">
                        <div v-for="field in settingFields" :key="field" class="flex items-center gap-1 rounded-full bg-surface p-1">
                            <button type="button" class="iconbtn size-9 bg-surface-2! text-[18px] font-semibold" :aria-label="`${machine.fields[field].label} : moins`" @click="changeSetting(field, -1)">−</button>
                            <span class="flex min-w-[64px] flex-col items-center leading-none">
                                <span class="font-display text-[20px] font-extrabold tabular-nums" aria-live="polite">{{ item[field] === null || item[field] === undefined ? '—' : formatSetting(field, item[field]) }}</span>
                                <span class="mt-0.5 text-[9.5px] font-extrabold tracking-[0.08em] text-text-muted uppercase">{{ machine.fields[field].label }}</span>
                            </span>
                            <button type="button" class="iconbtn size-9 bg-surface-2! text-[18px] font-semibold" :aria-label="`${machine.fields[field].label} : plus`" @click="changeSetting(field, 1)">+</button>
                        </div>
                    </div>

                    <template v-if="step.mode === 'reps'">
                        <div class="mt-2.5 flex items-center gap-4">
                            <button type="button" class="iconbtn size-11 bg-surface! text-[22px] font-semibold disabled:opacity-35" aria-label="Une répétition de moins" :disabled="shownReps === 0" @click="adjustReps(-1)">−</button>
                            <div class="flex items-baseline gap-2" :class="state.repsDone !== null && state.repsDone < load(step).reps ? 'text-prep' : 'text-accent'">
                                <button
                                    type="button"
                                    class="font-display leading-[0.85] font-extrabold tabular-nums"
                                    :class="usesWeight(exercise) ? 'text-[100px]' : 'text-[120px]'"
                                    aria-live="polite"
                                    :aria-label="`${shownReps} répétitions : toucher pour taper le nombre fait`"
                                    @click="openQuick('reps', 'Répétitions faites', shownReps, (reps) => (state.repsDone = reps))"
                                >{{ shownReps }}</button>
                                <span class="flex flex-col font-display leading-none font-bold">
                                    <span class="text-[28px]">REPS</span>
                                    <span v-if="item.per_side && step.drop === undefined" class="text-[17px]">{{ step.side ? `CÔTÉ ${step.side.toUpperCase()}` : 'PAR CÔTÉ' }}</span>
                                    <span v-else-if="item.per_side === false" class="text-[17px]">AU TOTAL</span>
                                </span>
                            </div>
                            <button type="button" class="iconbtn size-11 bg-surface! text-[22px] font-semibold" aria-label="Une répétition de plus" @click="adjustReps(1)">+</button>
                        </div>
                        <span class="mt-1.5 text-[13px] font-semibold text-text-muted">
                            <template v-if="state.repsDone !== null && state.repsDone !== load(step).reps">Objectif {{ load(step).reps }} · </template>Temps sur la série · {{ elapsed }}
                        </span>
                    <div v-if="usesWeight(exercise)" class="mt-2.5 flex items-center gap-3 rounded-full bg-surface p-1.5">
                        <button type="button" class="iconbtn size-10 bg-surface-2! text-[22px] font-semibold disabled:opacity-35" aria-label="Charge : moins" :disabled="load(step).weight === null" @click="changeWeight(-1)">−</button>
                        <button
                            type="button"
                            class="min-w-[130px] rounded-xl text-center font-display text-[34px] leading-none font-extrabold tabular-nums underline decoration-line-strong decoration-2 underline-offset-[6px]"
                            :class="load(step).weight === null ? 'text-[18px]! text-text-muted' : 'text-text'"
                            aria-live="polite"
                            :aria-label="`Charge ${formatWeight(load(step).weight) ?? 'poids du corps'} : toucher pour la taper`"
                            @click="openQuick('weight', 'Charge de la série', load(step).weight, setWeightTo)"
                        >
                            {{ formatWeight(load(step).weight) ?? 'Poids du corps' }}
                        </button>
                        <button type="button" class="iconbtn size-10 bg-surface-2! text-[22px] font-semibold" aria-label="Charge : plus" @click="changeWeight(1)">+</button>
                    </div>
                    <!-- Les disques à charger, pour ne pas calculer de tête. -->
                    <p v-if="platesLabel(load(step).weight, exercise.equipment)" class="mt-1.5 text-[12.5px] font-semibold text-text-muted">{{ platesLabel(load(step).weight, exercise.equipment) }}</p>
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
            <p v-if="kcal" class="-mt-3 mb-6 text-center text-[13px] font-semibold text-text-muted">
                ≈ <span class="font-bold text-text">{{ kcal.toLocaleString('fr-FR') }} kcal</span> dépensées (estimation)
            </p>
            <button type="button" class="btn-accent h-[58px] text-[22px]" @click="quit">Retour aux séances</button>
        </div>

        <BottomSheet
            v-model:open="programOpen"
            title="Programme"
            description="Machine prise ? Passe un exercice plus tard, fais-en un autre maintenant, ou prends une variante. Les étirements restent pour la fin."
        >
            <p class="-mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-[12.5px] font-bold text-text-soft">
                <span><span class="text-accent">{{ doneCount }}</span> fait{{ doneCount > 1 ? 's' : '' }} · {{ leftCount }} restant{{ leftCount > 1 ? 's' : '' }}</span>
                <span class="flex items-center gap-1 text-[11px] font-semibold text-text-muted"><span class="size-2 rounded-full bg-accent" />fait</span>
                <span class="flex items-center gap-1 text-[11px] font-semibold text-text-muted"><span class="size-2 rounded-full bg-prep" />sauté</span>
                <span class="flex items-center gap-1 text-[11px] font-semibold text-text-muted"><span class="size-2 rounded-full bg-surface-3" />à faire</span>
            </p>

            <template v-if="program.ahead.length">
                <div class="flex flex-col gap-2.5 rounded-2xl border-[1.5px] border-accent bg-accent/8 p-3">
                    <div class="flex items-center gap-3">
                        <span class="size-12 shrink-0 overflow-hidden rounded-[10px]">
                            <img :src="catalog[items[program.ahead[0]].exercise].images[0]" alt="" class="size-full object-cover" />
                        </span>
                        <span class="flex min-w-0 flex-1 flex-col gap-0.5">
                            <span class="text-[10.5px] font-extrabold tracking-[0.12em] text-accent uppercase">{{ step?.kind === 'work' ? 'En cours' : 'À suivre' }}</span>
                            <span class="text-[15px] leading-tight font-extrabold">{{ catalog[items[program.ahead[0]].exercise].name }}</span>
                        </span>
                        <button type="button" class="iconbtn size-10 shrink-0 bg-surface-2! font-serif text-[16px] font-extrabold text-accent italic" :aria-label="`Comment faire : ${catalog[items[program.ahead[0]].exercise].name}`" @click="explain(items[program.ahead[0]].exercise, () => (programOpen = false), () => (programOpen = true))">i</button>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <button type="button" class="btn-soft h-11 text-[14px]" :disabled="program.ahead.length < 2" @click="later(program.ahead[0])">Plus tard</button>
                        <button type="button" class="btn-soft h-11 text-[14px] text-accent" @click="swapping = program.ahead[0]">Une variante</button>
                    </div>
                </div>

                <ol v-if="program.ahead.length > 1" class="flex flex-col gap-2" aria-label="Exercices à venir">
                    <li v-for="(index, position) in program.ahead.slice(1)" :key="index" class="flex flex-col gap-1.5 rounded-2xl bg-surface p-2.5">
                        <div class="flex items-center gap-2">
                            <span class="w-6 shrink-0 text-center font-display text-[18px] font-extrabold text-text-muted">{{ program.done.length + position + 2 }}</span>
                            <span data-name class="min-w-0 flex-1 text-[14px] leading-tight font-bold">{{ catalog[items[index].exercise].name }}</span>
                            <button type="button" class="iconbtn size-10 shrink-0 bg-surface-2! font-serif text-[16px] font-extrabold text-accent italic" :aria-label="`Comment faire : ${catalog[items[index].exercise].name}`" @click="explain(items[index].exercise, () => (programOpen = false), () => (programOpen = true))">i</button>
                            <button type="button" class="h-9 shrink-0 rounded-full bg-accent px-3.5 text-[12.5px] font-extrabold text-on-accent" @click="doNow(index)">Maintenant</button>
                        </div>
                        <div class="flex items-center gap-1.5 pl-8">
                            <span class="min-w-0 flex-1 truncate text-[11.5px] font-medium text-text-muted">
                                <template v-if="statusOf(index) === 'partial'"><span class="font-bold text-prep">commencé {{ performedByItem[index] }}/{{ items[index].sets }}</span> · </template>{{ items[index].sets - (program.passed[index] ?? 0) }} série{{ items[index].sets - (program.passed[index] ?? 0) > 1 ? 's' : '' }} · {{ targetLabel(items[index]) }}<template v-if="itemSettings(index)"> · {{ itemSettings(index) }}</template>
                            </span>
                            <button type="button" class="iconbtn size-9 bg-surface-2! text-text-soft disabled:opacity-30" :aria-label="`Monter ${catalog[items[index].exercise].name}`" :disabled="position === 0" @click="shift(position + 1, -1)">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M6 15l6-6 6 6" /></svg>
                            </button>
                            <button type="button" class="iconbtn size-9 bg-surface-2! text-text-soft disabled:opacity-30" :aria-label="`Descendre ${catalog[items[index].exercise].name}`" :disabled="position === program.ahead.length - 2" @click="shift(position + 1, 1)">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6" /></svg>
                            </button>
                            <button type="button" class="iconbtn size-9 bg-surface-2! text-accent" :aria-label="`Variante de ${catalog[items[index].exercise].name}`" @click="swapping = index">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 9a8 8 0 0 1 14.3-3.3M20 4v5h-5M20 15a8 8 0 0 1-14.3 3.3M4 20v-5h5" /></svg>
                            </button>
                        </div>
                    </li>
                </ol>
            </template>

            <section v-if="program.done.length" class="flex flex-col gap-2" aria-label="Exercices passés">
                <span class="text-[10.5px] font-extrabold tracking-[0.1em] text-text-muted uppercase">Passés</span>
                <ul class="flex flex-col gap-1.5">
                    <li v-for="index in program.done" :key="index" class="flex items-center gap-2.5 rounded-2xl bg-surface px-3 py-2" :data-status="statusOf(index)">
                        <span
                            class="flex size-6 shrink-0 items-center justify-center rounded-full text-[13px] font-extrabold"
                            :class="statusOf(index) === 'done' ? 'bg-accent text-on-accent' : 'bg-prep text-on-accent'"
                            aria-hidden="true"
                        >{{ statusOf(index) === 'done' ? '✓' : '!' }}</span>
                        <span class="flex min-w-0 flex-1 flex-col">
                            <span data-name class="text-[13.5px] leading-tight font-bold" :class="statusOf(index) === 'done' ? 'text-text-soft' : 'text-text'">{{ catalog[items[index].exercise].name }}</span>
                            <span class="text-[11.5px] font-semibold" :class="statusOf(index) === 'done' ? 'text-text-muted' : 'text-prep'">
                                {{ statusOf(index) === 'done' ? 'Fait' : (performedByItem[index] ? 'Commencé' : 'Sauté') }} · {{ performedByItem[index] ?? 0 }}/{{ items[index].sets }} séries
                            </span>
                        </span>
                        <button v-if="statusOf(index) !== 'done'" type="button" class="h-9 shrink-0 rounded-full bg-surface-2 px-3.5 text-[12.5px] font-extrabold text-accent" @click="redo(index)">
                            Reprendre
                        </button>
                    </li>
                </ul>
            </section>
        </BottomSheet>

        <BottomSheet
            v-model:open="swapOpen"
            title="Une variante"
            :description="swapping !== null ? `À la place de « ${catalog[items[swapping].exercise].name} », pour aujourd'hui : mêmes muscles, autre machine.` : ''"
        >
            <p v-if="!swapChoices.length" class="text-[14px] font-semibold text-text-muted">Pas de variante pour cet exercice.</p>
            <ul class="flex flex-col gap-2">
                <li v-for="choice in swapChoices" :key="choice.slug" class="flex items-center gap-2">
                    <button type="button" class="flex min-w-0 flex-1 items-center gap-3 rounded-2xl bg-surface p-2 text-left hover:bg-surface-hover" @click="swap(choice.slug)">
                        <span class="size-12 shrink-0 overflow-hidden rounded-[10px]">
                            <img :src="choice.images[0]" alt="" class="size-full object-cover" />
                        </span>
                        <span class="flex min-w-0 flex-col gap-0.5">
                            <span class="text-[14.5px] leading-tight font-bold">{{ choice.name }}<span v-if="choice.slug === items[swapping].origin" class="text-text-muted"> (prévu)</span></span>
                            <span class="text-[12px] font-medium text-text-muted">{{ choice.equipment_label }}</span>
                        </span>
                    </button>
                    <button type="button" class="iconbtn size-10 shrink-0 bg-surface-2! font-serif text-[16px] font-extrabold text-accent italic" :aria-label="`Comment faire : ${choice.name}`" @click="explain(choice.slug, () => (reopenIndex = swapping, swapping = null), () => (swapping = reopenIndex))">i</button>
                </li>
            </ul>
        </BottomSheet>

        <BottomSheet v-model:open="quick.open" :title="quick.title" :description="quick.kind === 'weight' ? 'En kilos ; laisse vide pour le poids du corps.' : null">
            <form class="flex flex-col gap-3" @submit.prevent="applyQuick">
                <label class="flex items-baseline gap-2 rounded-2xl bg-surface px-4 py-3">
                    <input
                        v-model="quick.value"
                        type="text"
                        :inputmode="quick.kind === 'weight' ? 'decimal' : 'numeric'"
                        :placeholder="quick.kind === 'weight' ? '62,5' : '8'"
                        :aria-label="quick.title"
                        autofocus
                        class="w-full min-w-0 border-0 bg-transparent p-0 font-display text-[44px] font-bold text-text outline-none placeholder:text-text-faint"
                    />
                    <span class="font-display text-[22px] font-bold text-text-muted">{{ quick.kind === 'weight' ? 'kg' : 'reps' }}</span>
                </label>
                <p v-if="quick.error" class="text-[13px] text-danger">{{ quick.error }}</p>
                <button type="submit" class="btn-accent h-14 w-full text-[22px]">Valider</button>
            </form>
        </BottomSheet>

        <!-- Le lecteur n'a pas de mise en page : il porte lui-même les messages. -->
        <FlashToast />

        <ExerciseSheet
            v-if="detailExercise"
            :exercise="detailExercise"
            :close-label="state.done ? 'Fermer' : 'Retour à la séance'"
            @close="closeDetail"
        />
    </div>
</template>
