/* Lot C : gainage, cardio fonctionnel, dos, mobilité, poussées. */
import { C, backrest, bench, circle, dumbbell, figure, handle, line, mat, pad, path, rect, shadow } from './lib.mjs';

const r = (p) => [Math.round(p[0]), Math.round(p[1])];

const at = (a, len, deg) => r([a[0] + len * Math.cos((deg * Math.PI) / 180), a[1] + len * Math.sin((deg * Math.PI) / 180)]);

/** Articulation du milieu (coude, genou) entre a et c ; side = 1 ou -1 choisit le côté du pli. */
function joint(a, c, side = 1, l1 = 55, l2 = 55) {
    const dx = c[0] - a[0];
    const dy = c[1] - a[1];
    const d = Math.hypot(dx, dy);
    const ux = dx / d;
    const uy = dy / d;

    if (d >= l1 + l2) {
        return r([a[0] + (dx * l1) / (l1 + l2), a[1] + (dy * l1) / (l1 + l2)]);
    }

    const x = (d * d + l1 * l1 - l2 * l2) / (2 * d);
    const h = Math.sqrt(Math.max(0, l1 * l1 - x * x));

    return r([a[0] + ux * x - uy * h * side, a[1] + uy * x + ux * h * side]);
}

const elbow = (neck, hand, side) => joint(neck, hand, side, 55, 55);
const knee = (hip, foot, side) => joint(hip, foot, side, 82, 84);

/** Tête dans le prolongement du tronc, à moins qu'on ne la donne. */
function body(p) {
    const d = Math.hypot(p.neck[0] - p.hip[0], p.neck[1] - p.hip[1]);
    const head = p.head ?? r([p.neck[0] + ((p.neck[0] - p.hip[0]) * 38) / d, p.neck[1] + ((p.neck[1] - p.hip[1]) * 38) / d]);

    return figure({ ...p, head });
}

const shift = (pts, dx, dy = 0) => Object.fromEntries(Object.entries(pts).map(([k, v]) => [k, [v[0] + dx, v[1] + dy]]));

/** Ligne ondulée de a à b (corde en mouvement). */
function wave(a, b, { amp = 24, waves = 2.5, phase = 0, color = C.accent, w = 7 } = {}) {
    const pts = [];

    for (let i = 0; i <= 60; i++) {
        const t = i / 60;
        const env = Math.min(1, t * 5) * (1 - t * 0.75);
        pts.push(r([a[0] + (b[0] - a[0]) * t, a[1] + (b[1] - a[1]) * t + amp * env * Math.sin(2 * Math.PI * waves * t + phase)]));
    }

    pts[0] = a;

    return path(pts, color, w);
}

/** Levier de machine : bras citron du pivot à la poignée. */
const lever = (pivot, hand) => line(pivot, hand, C.accent, 10) + handle(hand, 12) + circle(pivot, 13, C.gearLight);

/** Centre d'un arc passant par a et b, à la distance s du milieu (côté donné par le signe). */
function pivotOf(a, b, s) {
    const mx = (a[0] + b[0]) / 2;
    const my = (a[1] + b[1]) / 2;
    const dx = b[0] - a[0];
    const dy = b[1] - a[1];
    const d = Math.hypot(dx, dy);

    return r([mx - (dy / d) * s, my + (dx / d) * s]);
}

export default {
    'bird-dog': [0, 1].map((f) => {
        const ext = f === 1;
        const neck = [430, 298];
        const hip = [320, 318];
        const hand = ext ? at(neck, 110, -3) : [434, 406];
        const farHand = [444, 406];
        const farFoot = ext ? at(hip, 166, 182) : [248, 408];

        return mat(120, 610) + body({ neck, hip,
            hand, elbow: ext ? at(neck, 55, -3) : [432, 352],
            farHand, farElbow: [438, 352],
            knee: [322, 402], foot: [238, 408],
            farKnee: ext ? at(hip, 82, 182) : [332, 402], farFoot });
    }),

    'dragon-flag': [0, 1].map((f) => {
        const up = f === 0;
        const neck = [300, 313];
        const deg = up ? -88 : -12;
        const hip = at(neck, 110, deg);
        const kneeP = at(hip, 82, deg);
        const foot = at(kneeP, 84, deg);
        const hand = [208, 328];

        return bench(196, 540, 330) + body({ head: [262, 306], neck, hip,
            hand, elbow: elbow(neck, hand, 1),
            knee: kneeP, foot, farKnee: [kneeP[0] + 6, kneeP[1] + 2], farFoot: [foot[0] + 8, foot[1] + 2] });
    }),

    'ergometre-a-bras': [0, 1].map((f) => {
        const hub = [398, 222];
        const a = f === 0 ? -115 : 65;
        const hand = at(hub, 40, a);
        const farHand = at(hub, 40, a + 180);
        const neck = [300, 224];
        const hip = [292, 334];
        const foot = [382, 418];
        const farFoot = [372, 418];
        const machine = rect(462, 196, 22, 232, C.gear, 6) + line([396, 222], [472, 222], C.gear, 24)
            + line([420, 424], [540, 424], C.gear, 10)
            + pad(242, 346, 96, 20) + line([292, 366], [292, 428], C.gear, 12) + line([252, 424], [334, 424], C.gear, 10)
            + `<circle cx="${hub[0]}" cy="${hub[1]}" r="40" fill="none" stroke="${C.gearLight}" stroke-width="3" stroke-dasharray="6 8"/>`;

        return machine + line(hub, farHand, C.accent, 8) + handle(farHand, 10)
            + body({ neck, hip,
                hand, elbow: elbow(neck, hand, 1), farHand, farElbow: elbow(neck, farHand, 1),
                knee: knee(hip, foot, -1), foot, farKnee: knee(hip, farFoot, -1), farFoot })
            + line(hub, hand, C.accent, 8) + circle(hub, 12, C.gearLight) + handle(hand, 11);
    }),

    'sled-pull-corde': [0, 1].map((f) => {
        const pull = f === 1;
        const sx = pull ? 506 : 540;
        const sled = path([[sx - 14, 390], [sx, 418], [sx + 140, 418]], C.gear, 10)
            + rect(sx + 58, 280, 20, 130, C.gear, 5) + rect(sx + 28, 318, 80, 28, C.gearLight, 10) + rect(sx + 22, 352, 92, 30, C.gearLight, 10)
            + rect(sx + 10, 388, 120, 18, C.gear, 6);
        const hitch = [sx - 12, 392];
        const hip = pull ? [236, 296] : [226, 300];
        const neck = at(hip, 110, pull ? -112 : -115);
        const foot = [306, 418];
        const farFoot = [270, 418];
        const reach = at(neck, 110, 12);
        const hand = pull ? [hip[0] + 22, hip[1] - 36] : reach;
        const farHand = pull ? at(neck, 110, 8) : [reach[0] - 12, reach[1] - 2];
        const front = pull ? farHand : hand;
        const back = pull ? hand : farHand;
        const tail = path([back, [back[0] - 40, back[1] + 60], [back[0] - 70, 420], [120, 422]], C.accent, 5);
        const rope = line(hitch, front, C.accent, 5) + line(front, back, C.accent, 5);

        return sled + tail + rope + body({ neck, hip,
            hand, elbow: pull ? elbow(neck, hand, -1) : at(neck, 55, 12),
            farHand, farElbow: elbow(neck, farHand, 1),
            knee: knee(hip, foot, -1), foot, farKnee: knee(hip, farFoot, -1), farFoot });
    }),

    'battle-rope-ondulations': [0, 1].map((f) => {
        const hip = [262, 318];
        const neck = at(hip, 110, -76);
        const foot = [316, 418];
        const farFoot = [296, 418];
        const hi = [384, 196];
        const lo = [382, 300];
        const hand = f === 0 ? hi : lo;
        const farHand = f === 0 ? lo : hi;
        const anchor = [646, 404];
        const anchorPost = rect(636, 380, 26, 48, C.gear, 6);

        return anchorPost
            + wave(farHand, anchor, { phase: f === 0 ? 0 : Math.PI, amp: 26, w: 5 })
            + body({ neck, hip,
                hand, elbow: elbow(neck, hand, 1), farHand, farElbow: elbow(neck, farHand, 1),
                knee: knee(hip, foot, -1), foot, farKnee: knee(hip, farFoot, -1), farFoot })
            + wave(hand, anchor, { phase: f === 0 ? Math.PI : 0, amp: 26, w: 7 });
    }),

    'devil-press': [
        body({ head: [214, 404], neck: [252, 410], hip: [362, 411],
            elbow: [285, 364], hand: [282, 412],
            knee: [444, 413], foot: [526, 414] }) + dumbbell([282, 414]),
        shadow(360) + dumbbell([346, 30]) + body({ head: [360, 102], neck: [360, 140], hip: [360, 252],
            elbow: [362, 86], hand: [364, 32], farElbow: [350, 86], farHand: [346, 32],
            knee: [352, 334], foot: [360, 418], farKnee: [364, 334], farFoot: [372, 418] }) + dumbbell([364, 32]),
    ],

    'bear-crawl': [0, 1].map((f) => {
        const step = f === 1;
        const neck = step ? [456, 312] : [446, 310];
        const hip = step ? [346, 306] : [336, 304];
        const hand = step ? [490, 418] : [450, 418];
        const farHand = [462, 418];
        const foot = [262, 418];
        const farFoot = step ? [318, 418] : [274, 418];

        return body({ neck, hip,
            hand, elbow: elbow(neck, hand, 1), farHand, farElbow: elbow(neck, farHand, 1),
            knee: knee(hip, foot, -1), foot, farKnee: knee(hip, farFoot, -1), farFoot });
    }),

    'tirage-vertical-divergent': [0, 1].map((f) => {
        const down = f === 1;
        const neck = [314, 224];
        const hip = [322, 333];
        const foot = [410, 418];
        const h0 = [332, 116];
        const h1 = [364, 228];
        const pivot = pivotOf(h0, h1, -210);
        const hand = down ? h1 : h0;
        const farHand = [hand[0] - 8, hand[1] + 2];
        const machine = rect(556, 60, 22, 368, C.gear, 6) + line([567, 72], [340, 60], C.gear, 16)
            + line([pivot[0], pivot[1]], [567, pivot[1]], C.gear, 14)
            + line([452, 309], [567, 309], C.gear, 12) + pad(386, 297, 70, 24)
            + pad(270, 346, 100, 20) + line([320, 366], [320, 428], C.gear, 12) + line([278, 424], [560, 424], C.gear, 10);

        return machine + body({ neck, hip,
            hand, elbow: down ? [334, 276] : elbow(neck, hand, -1),
            farHand, farElbow: down ? [326, 278] : elbow(neck, farHand, -1),
            knee: knee(hip, foot, -1), foot }) + lever(pivot, hand);
    }),

    'rowing-assis-prise-large': [0, 1].map((f) => {
        const back = f === 1;
        const hip = [266, 343];
        const neck = back ? at(hip, 110, -94) : at(hip, 110, -72);
        const hand = back ? [neck[0] + 28, neck[1] + 54] : at(neck, 108, 6);
        const foot = [404, 352];
        const pulley = [572, 356];
        const machine = rect(586, 230, 24, 198, C.gear, 6) + line([180, 424], [598, 424], C.gear, 10)
            + rect(170, 355, 250, 20, C.gear, 8) + line([200, 375], [200, 424]) + line([390, 375], [390, 424])
            + line([414, 418], [428, 318], C.gearLight, 16);

        return machine + line(pulley, hand, C.accent, 3) + circle(pulley, 12, C.gearLight)
            + body({ neck, hip,
                hand, elbow: elbow(neck, hand, back ? 1 : 1),
                knee: knee(hip, foot, -1), foot, farKnee: knee(hip, [foot[0] - 4, foot[1] - 6], -1), farFoot: [foot[0] - 4, foot[1] - 6] })
            + handle(hand, 12);
    }),

    superman: [0, 1].map((f) => {
        const lift = f === 1;
        const hip = [330, 402];
        if (!lift) {
            return mat(110, 610) + body({ head: [478, 392], neck: [440, 400], hip,
                elbow: [494, 402], hand: [548, 402], knee: [248, 404], foot: [164, 406] });
        }
        const neck = at(hip, 110, -16);
        const elbowP = at(neck, 55, -30);
        const kneeP = at(hip, 82, 190);
        return mat(110, 610) + body({ neck, hip,
            elbow: elbowP, hand: at(elbowP, 55, -30),
            knee: kneeP, foot: at(kneeP, 84, 196) });
    }),
    'mobilite-hanches-90-90': [0, 1].map((f) => {
        const m = f === 0 ? 1 : -1;
        const X = (x) => 360 + (x - 360) * m;
        const hip = [360, 352];
        const neck = [360, 242];
        const kneeF = [X(302), 410];
        const footF = [X(386), 416];
        const kneeR = [X(441), 364];
        const footR = [X(456), 302];
        const floorMat = rect(170, 286, 380, 140, C.gear, 12);

        return floorMat + body({ head: [360, 204], neck, hip,
            elbow: [316, 284], hand: [338, 332], farElbow: [404, 284], farHand: [382, 332],
            knee: kneeF, foot: footF, farKnee: kneeR, farFoot: footR });
    }),

    'etirement-pectoraux-mur': [0, 1].map((f) => {
        const step = f === 1;
        const wx = 280;
        const neck = step ? [wx + 56, 146] : [wx + 40, 140];
        const hip = step ? [wx + 68, 254] : [wx + 40, 252];
        const el = [wx + 4, neck[1] + 4];
        const foot = [wx + 46, 418];
        const farFoot = step ? [wx + 150, 418] : [wx + 56, 418];

        return rect(wx - 70, 40, 74, 388, C.gear, 4) + body({ neck, hip,
            elbow: el, hand: [wx + 4, el[1] - 55],
            farElbow: [neck[0] + 6, neck[1] + 55], farHand: [neck[0] + 12, neck[1] + 110],
            knee: knee(hip, foot, -1), foot, farKnee: knee(hip, farFoot, -1), farFoot });
    }),

    'super-incline': [0, 1].map((f) => {
        const up = f === 1;
        const hip = [300, 334];
        const neck = at(hip, 110, -120);
        const low = at(neck, 55, 110);
        const h0 = at(low, 55, -57);
        const h1 = at(neck, 108, -50);
        const pivot = pivotOf(h0, h1, 200);
        const hand = up ? h1 : h0;
        const foot = [390, 418];
        const seat = pad(250, 350, 100, 20) + backrest(at(hip, 26, 150), at(at(hip, 26, 150), 150, -120))
            + line([300, 370], [300, 428], C.gear, 12) + line([230, 424], [pivot[0] + 20, 424], C.gear, 10)
            + line([pivot[0], pivot[1]], [pivot[0] + 10, 424], C.gear, 16);

        return seat + body({ neck, hip,
            hand, elbow: up ? elbow(neck, hand, -1) : low, farHand: [hand[0] - 6, hand[1] + 2], farElbow: up ? elbow(neck, [hand[0] - 6, hand[1] + 2], -1) : [low[0] - 4, low[1] + 2],
            knee: knee(hip, foot, -1), foot }) + lever(pivot, hand);
    }),

    'pike-push-up': [0, 1].map((f) => {
        const down = f === 1;
        const hand = [470, 418];
        const foot = [270, 418];
        const hip = down ? [372, 282] : [318, 259];
        const neck = down ? at(hip, 110, 45) : [396, 336];
        const kn = knee(hip, foot, -1);

        return body({ neck, hip,
            hand, elbow: elbow(neck, hand, 1), farHand: [hand[0] + 12, hand[1]], farElbow: elbow(neck, [hand[0] + 12, hand[1]], 1),
            knee: kn, foot, farKnee: [kn[0] + 6, kn[1]], farFoot: [foot[0] + 12, foot[1]] });
    }),
};
