/**
 * Calculs d'une séance partagés par l'accueil, l'éditeur et le lecteur — repris
 * tels quels de la maquette pour que les chiffres affichés soient les mêmes.
 */

export const clamp = (value, min, max) => Math.max(min, Math.min(max, value));

/** 95 → « 1:35 » */
export function formatClock(seconds) {
    const s = Math.max(0, Math.round(seconds));

    return `${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`;
}

/** 0 → « 0 », 45 → « 45 s », 90 → « 1:30 » */
export function formatShort(seconds) {
    if (seconds === 0) {
        return '0';
    }

    return seconds < 60 ? `${seconds} s` : formatClock(seconds);
}

export const formatMinutes = (seconds) => `${Math.max(1, Math.round(seconds / 60))} min`;

/** Ce qu'on vise sur une série : « 10 reps » ou « 45 s ». */
export const targetLabel = (item) => (item.mode === 'reps' ? `${item.value} reps` : formatShort(item.value));

/**
 * Durée estimée : les répétitions au tempo réglé, les séries chronométrées à leur
 * durée, les repos entre séries et entre exercices — pas celui après le dernier.
 */
export function estimate(items, secondsPerRep = 3) {
    return items.reduce(
        (total, item, index) =>
            total +
            item.sets * (item.mode === 'reps' ? item.value * secondsPerRep : item.value) +
            (item.sets - 1) * item.rest_sets +
            (index < items.length - 1 ? item.rest_after : 0),
        0,
    );
}

/** « 6 exos · 20 séries · ~45 min » */
export function summary(items, secondsPerRep) {
    const sets = items.reduce((total, item) => total + item.sets, 0);

    return `${items.length} exo${items.length > 1 ? 's' : ''} · ${sets} séries · ~${formatMinutes(estimate(items, secondsPerRep))}`;
}

/**
 * Réglages d'un exercice qu'on vient d'ajouter — les mêmes que
 * App\Support\WorkoutDefaults côté serveur.
 */
export function defaultsFor(exercise) {
    if (exercise.mode === 'time') {
        return exercise.group === 'cardio'
            ? { value: 300, sets: 1, rest_sets: 0, rest_after: 60 }
            : { value: 30, sets: 3, rest_sets: 30, rest_after: 60 };
    }

    return { value: 10, sets: 3, rest_sets: 60, rest_after: 90 };
}

export function newItem(exercise) {
    return { key: crypto.randomUUID(), exercise: exercise.slug, mode: exercise.mode, ...defaultsFor(exercise) };
}

/**
 * Pas du « + / − » : une répétition à la fois ; pour une durée, 5 s sous la
 * minute, 15 s jusqu'à 5 min, 30 s au-delà. En descendant, le pas est celui de
 * la valeur d'arrivée, pour retomber exactement sur les paliers.
 */
export function stepValue(item, direction) {
    if (item.mode === 'reps') {
        return clamp(item.value + direction, 1, 100);
    }

    const reference = direction < 0 ? item.value - 1 : item.value;
    const step = reference < 60 ? 5 : reference < 300 ? 15 : 30;

    return clamp(item.value + direction * step, 5, 3600);
}

export function stepRest(value, direction) {
    const reference = direction < 0 ? value - 1 : value;

    return clamp(value + direction * (reference < 30 ? 5 : 15), 0, 900);
}

/** Les groupes musculaires d'une séance, dans l'ordre d'apparition. */
export function groupsOf(items, catalog) {
    const labels = items.map((item) => catalog[item.exercise]?.group_label).filter(Boolean);

    return [...new Set(labels)].join(' · ') || 'Aucun exercice';
}

export const bySlug = (exercises) => Object.fromEntries(exercises.map((exercise) => [exercise.slug, exercise]));

/** Minuscules sans accents, pour une recherche qui pardonne « developpe ». */
export const normalize = (text) =>
    text
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase();
