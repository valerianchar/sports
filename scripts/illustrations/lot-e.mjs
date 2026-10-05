/* Lot E : machines de la passe « plaques des machines » — fentes guidées, hip thrust debout. */
import { C, circle, figure, line, path, plate, post, rect } from './lib.mjs';

const rad = (d) => (d * Math.PI) / 180;
const r = (v) => Math.round(v);
const at = (p, len, deg) => [r(p[0] + len * Math.cos(rad(deg))), r(p[1] + len * Math.sin(rad(deg)))];
const add = (p, q, k = 1) => [r(p[0] + k * q[0]), r(p[1] + k * q[1])];
const angle = (from, to) => (Math.atan2(to[1] - from[1], to[0] - from[0]) * 180) / Math.PI;

/** Articulation du milieu (genou, coude) entre deux extrémités ; `side` choisit le sens de pliure. */
function joint(a, b, side = 1, l1 = 82, l2 = 84) {
    const d = Math.min(Math.hypot(b[0] - a[0], b[1] - a[1]), l1 + l2 - 1);
    const base = Math.atan2(b[1] - a[1], b[0] - a[0]);
    const k = Math.acos(Math.max(-1, Math.min(1, (l1 * l1 + d * d - l2 * l2) / (2 * l1 * d))));
    const ang = base - side * k;

    return [r(a[0] + l1 * Math.cos(ang)), r(a[1] + l1 * Math.sin(ang))];
}

const elbowOf = (neck, hand, side = 1) => joint(neck, hand, side, 55, 55);

/** Plaque arrondie centrée et tournée. */
const padAt = (c, w, h, deg, color = C.accent) => rect(c[0] - w / 2, c[1] - h / 2, w, h, color, h / 2, [deg, c[0], c[1]]);

/* ---------- Fentes à la machine (squat lunge) ---------- */

function squatLunge(down) {
    const front = [430, 418];
    const rear = [252, 410];
    const pivot = [172, 168];
    const hip = down ? [334, 338] : [344, 272];
    const neck = add(hip, [6, -110]);
    const head = at(neck, 38, -84);
    const padC = add(neck, [-4, -16]);
    const hand = add(padC, [16, 8]);
    const knee = joint(hip, front, 1);
    const farKnee = joint(hip, rear, 1);

    const machine = rect(150, 414, 360, 14, C.gear, 6)
        + post(pivot[0], pivot[1] - 12, 22)
        + rect(rear[0] - 28, 400, 44, 14, C.gearLight, 6);
    const lever = line(pivot, padC, C.accent, 12) + plate(at(pivot, 52, angle(pivot, padC)), 28)
        + circle(pivot, 13, C.gearLight);

    return machine + lever
        + figure({ head, neck, hip, knee, foot: front, farKnee, farFoot: rear,
            elbow: elbowOf(neck, hand, -1), hand, farElbow: elbowOf(neck, add(hand, [-6, 0]), -1), farHand: add(hand, [-6, 0]) })
        + padAt(padC, 56, 22, angle(pivot, padC));
}

/* ---------- Hip thrust debout ---------- */

function standingThrust(up) {
    const foot = [372, 418];
    const pivot = [418, 420];
    const reach = 167;
    // Le coussin tourne autour du pivot posé au sol, devant l'utilisateur.
    const padC = at(pivot, reach, up ? -97.5 : -114.7);
    const hip = add(padC, [-30, 0]);
    const trunk = up ? -88 : -36;
    const neck = at(hip, 110, trunk);
    const head = at(neck, 38, trunk + (up ? 2 : 10));
    const knee = joint(hip, foot, 1);
    const hand = add(padC, [14, -18]);
    const leverAngle = angle(pivot, padC);
    // Levier coudé : il passe au-dessus des genoux avant de revenir vers le coussin.
    const bend = at(pivot, reach * 0.58, leverAngle + 24);

    const machine = rect(280, 414, 200, 14, C.gear, 6)
        + rect(pivot[0] - 30, 396, 60, 32, C.gear, 6);
    const lever = path([pivot, bend, padC], C.accent, 12) + circle(pivot, 13, C.gearLight);

    return machine + lever
        + figure({ head, neck, hip, knee, foot, farKnee: add(knee, [-8, 0]), farFoot: add(foot, [-8, 0]),
            elbow: elbowOf(neck, hand, 1), hand })
        + padAt(padC, 22, 64, 0);
}

const pair = (fn) => [fn(false), fn(true)];

export default {
    'fentes-machine': pair(squatLunge),
    'hip-thrust-debout-machine': pair(standingThrust),
};
