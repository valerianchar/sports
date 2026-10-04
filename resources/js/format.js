/**
 * Formats des indicateurs de progression, en français.
 */
const number = (value, digits = 0) => Number(value).toLocaleString('fr-FR', { maximumFractionDigits: digits });

/** 2080 → « 2,1 t » ; 850 → « 850 kg ». */
export function formatTonnage(kg) {
    if (!kg) {
        return '0 kg';
    }

    return kg >= 1000 ? `${number(kg / 1000, kg >= 10000 ? 0 : 1)} t` : `${number(kg)} kg`;
}

/** 80 → « 1 h 20 » ; 45 → « 45 min ». */
export function formatMinutesLong(minutes) {
    if (minutes < 60) {
        return `${minutes} min`;
    }

    const rest = minutes % 60;

    return `${Math.floor(minutes / 60)} h${rest ? ` ${String(rest).padStart(2, '0')}` : ''}`;
}

/** 8.3 → « +8,3 % » ; −4 → « −4 % ». */
export function formatDelta(delta) {
    if (delta === null || delta === undefined) {
        return null;
    }

    return `${delta > 0 ? '+' : delta < 0 ? '−' : ''}${number(Math.abs(delta), 1)} %`;
}

export const formatKg = (kg, digits = 1) => (kg === null || kg === undefined ? '—' : `${number(kg, digits)} kg`);

export const formatNumber = number;

export const formatKcal = (kcal) => `${number(kcal, 0)} kcal`;

/** « 60 × 8 » ; « 45 s » au chrono ; « PdC × 12 » au poids du corps. */
export function formatSet(set) {
    if (set.reps === null || set.reps === undefined) {
        return set.seconds ? `${set.seconds} s` : '—';
    }

    return set.weight ? `${number(set.weight, 2)} × ${set.reps}` : `PdC × ${set.reps}`;
}
