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

/** Ce qu'on vise sur une série : « 10 reps », « 45 s », « 10 reps / côté ». */
export const targetLabel = (item) => `${item.mode === 'reps' ? `${item.value} reps` : formatShort(item.value)}${item.per_side ? ' / côté' : ''}`;

/** La valeur compte-t-elle par côté par défaut ? Oui dès que l'exercice a des côtés. */
export const perSideDefault = (exercise) => (exercise?.sides && exercise.sides !== 'both' ? true : null);

/** « droite puis gauche », « en alternant » : comment se font les côtés d'un exercice. */
export function sidesLabel(exercise, item) {
    if (exercise?.sides === 'each') {
        return 'droite, puis gauche';
    }

    if (exercise?.sides === 'alternate') {
        return item?.per_side === false ? `en alternant · ${Math.ceil(item.value / 2)} par côté` : 'en alternant · par côté';
    }

    return null;
}

/**
 * Durée estimée : les répétitions au tempo réglé, les séries chronométrées à leur
 * durée, les repos entre séries et entre exercices — pas celui après le dernier.
 */
export function estimate(items, secondsPerRep = 3) {
    return items.reduce(
        (total, item, index) =>
            total +
            item.sets * (item.mode === 'reps' ? item.value * secondsPerRep : item.value) * (item.per_side ? 2 : 1) +
            (item.sets - 1) * item.rest_sets +
            dropReps(item) * secondsPerRep +
            (index < items.length - 1 ? item.rest_after : 0),
        0,
    );
}

/** Répétitions des drop sets d'un exercice, sur toute la séance. */
function dropReps(item) {
    if (!item.drops?.length) {
        return 0;
    }

    const perSet = item.drops.reduce((total, drop) => total + drop.reps, 0);

    return perSet * (item.drop_on === 'all' ? item.sets : 1);
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
        if (exercise.group === 'cardio') {
            return { value: 300, sets: 1, rest_sets: 0, rest_after: 60 };
        }

        return exercise.group === 'mobilite'
            ? { value: 30, sets: 2, rest_sets: 10, rest_after: 15 }
            : { value: 30, sets: 3, rest_sets: 30, rest_after: 60 };
    }

    return { value: 10, sets: 3, rest_sets: 60, rest_after: 90 };
}

/** `machine` : les réglages des machines partagés par le serveur (props `machine_settings`). */
export function newItem(exercise, machine = null) {
    return {
        key: crypto.randomUUID(),
        exercise: exercise.slug,
        mode: exercise.mode,
        per_side: perSideDefault(exercise),
        weight: null,
        ...defaultsFor(exercise),
        ...machineDefaults(exercise, machine),
    };
}

/*
 * Les muscles qu'on choisit dans l'assistant, par région. Tibias et cou
 * restent à part : on ne bâtit pas une séance autour d'eux.
 */
export const MUSCLE_REGIONS = [
    { label: 'Haut du corps', muscles: ['chest', 'front-deltoids', 'rear-deltoids', 'upper-back', 'trapezius', 'biceps', 'triceps', 'forearm'] },
    { label: 'Tronc', muscles: ['abs', 'obliques', 'lower-back'] },
    { label: 'Bas du corps', muscles: ['gluteal', 'quadriceps', 'hamstring', 'adductors', 'calves'] },
];

// ---------------------------------------------------------------- machines de cardio

/** Ce que règle la machine d'un exercice : vitesse, inclinaison, niveau. */
export const machineFields = (exercise, machine) => (exercise && machine ? (machine.equipment[exercise.equipment] ?? []) : []);

/** Les réglages conseillés d'un exercice de cardio, à l'ajout. */
export function machineDefaults(exercise, machine) {
    const fields = machineFields(exercise, machine);
    const defaults = machine?.defaults?.[exercise.slug] ?? {};

    return Object.fromEntries(['speed', 'incline', 'level'].map((field) => [field, fields.includes(field) ? (defaults[field] ?? null) : null]));
}

/** 9.5 → « 9,5 km/h », 1 → « 1 % », 8 → « niv. 8 ». */
export function formatSetting(field, value) {
    const number = Number(value).toLocaleString('fr-FR', { maximumFractionDigits: 1 });

    return { speed: `${number} km/h`, incline: `${number} %`, level: `niv. ${number}` }[field];
}

/** « 9 km/h · 1 % » : les réglages renseignés d'un exercice. */
export const settingsLabel = (item, fields) =>
    fields
        .filter((field) => item[field] !== null && item[field] !== undefined)
        .map((field) => formatSetting(field, item[field]))
        .join(' · ');

/** Un cran de plus ou de moins sur un réglage, dans ses bornes ; vide, on part d'une valeur moyenne. */
export function stepSetting(field, value, direction, machine) {
    const { min, max, step } = machine.fields[field];
    const start = value ?? { speed: 8, incline: 1, level: 8 }[field] - direction * step;

    return clamp(Math.round((Number(start) + direction * step) * 10) / 10, min, max);
}

/**
 * Remplace l'exercice d'une ligne par une variante : séries et repos restent ;
 * la mesure, la charge et les réglages de machine repartent de zéro quand
 * l'exercice ne s'y prête plus.
 */
export function replaceExercise(item, exercise, previous, machine) {
    const next = { ...item, exercise: exercise.slug, mode: exercise.mode, per_side: perSideDefault(exercise), ...machineDefaults(exercise, machine) };

    if (exercise.mode !== item.mode) {
        Object.assign(next, { value: defaultsFor(exercise).value, set_weights: null, drops: null, drop_on: null });
    }

    // Une autre machine n'a pas la même pile de poids : la charge est à refaire.
    if (!usesWeight(exercise) || previous?.equipment !== exercise.equipment) {
        Object.assign(next, { weight: null, set_weights: null, drops: null, drop_on: null });
    }

    return next;
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

/** Un exercice seul : ses muscles principaux à fond, les secondaires à moitié. */
export function exerciseIntensity(exercise) {
    return {
        ...Object.fromEntries(exercise.secondary.map((muscle) => [muscle, 0.5])),
        ...Object.fromEntries(exercise.primary.map((muscle) => [muscle, 1])),
    };
}

/**
 * La charge de chaque muscle sur une séance, comptée en séries : une série
 * pleine pour un muscle principal, une demie pour un secondaire. Rendu trié du
 * plus sollicité au moins sollicité.
 */
export function muscleLoad(items, catalog) {
    const load = {};

    for (const item of items) {
        const exercise = catalog[item.exercise];

        if (!exercise) {
            continue;
        }

        for (const muscle of exercise.primary) {
            load[muscle] = (load[muscle] ?? 0) + item.sets;
        }

        for (const muscle of exercise.secondary) {
            load[muscle] = (load[muscle] ?? 0) + item.sets / 2;
        }
    }

    return Object.entries(load)
        .map(([muscle, sets]) => ({ muscle, sets }))
        .sort((a, b) => b.sets - a.sets);
}

/** La charge ramenée de 0 à 1, le muscle le plus sollicité valant 1. */
export function loadIntensity(load) {
    const max = Math.max(0, ...load.map((entry) => entry.sets));

    return Object.fromEntries(load.map((entry) => [entry.muscle, max ? entry.sets / max : 0]));
}

/** 62.5 → « 62,5 kg » ; vide → null (poids du corps). */
export function formatWeight(weight) {
    if (weight === null || weight === undefined || weight === '') {
        return null;
    }

    return `${Number(weight).toLocaleString('fr-FR', { maximumFractionDigits: 2 })} kg`;
}

/**
 * Pas du « + / − » de la charge : 1 kg sous 10 kg (haltères), 2,5 kg au-delà
 * (les disques). En descendant sous zéro, on revient au poids du corps.
 */
export function stepWeight(weight, direction) {
    const current = Number(weight) || 0;
    const reference = direction < 0 ? current - 0.01 : current;
    const step = reference < 10 ? 1 : 2.5;
    const next = Math.round((current + direction * step) * 100) / 100;

    return next <= 0 ? null : Math.min(999, next);
}

/** Une charge a du sens partout sauf au cardio et en mobilité. */
export const usesWeight = (exercise) => exercise && !['cardio', 'mobilite'].includes(exercise.group);

/** La charge d'une série (dès 1) : celle de la série en dégressif, sinon la charge fixe. */
export const setWeight = (item, set) => (item.set_weights?.length ? (item.set_weights[set - 1] ?? null) : (item.weight ?? null));

/** Arrondi au disque le plus proche : 2,5 kg (1 kg sous 10 kg). */
export function roundPlate(weight) {
    const step = weight < 10 ? 1 : 2.5;

    return Math.max(step, Math.round(weight / step) * step);
}

/** Dégressif auto : chaque série 10 % plus légère que la précédente. */
export function degressive(start, sets) {
    const weights = [start];

    for (let set = 1; set < sets; set++) {
        weights.push(start ? roundPlate(weights[set - 1] * 0.9) : null);
    }

    return weights;
}

/** Les séries d'un exercice qui se prolongent en drop set (dès 1). */
export const dropsOn = (item, set) => Boolean(item.drops?.length) && (item.drop_on === 'all' || set === item.sets);
