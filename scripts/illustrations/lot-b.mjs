/* Lot B : abdos-cardio-fonctionnel, dos-bras, mobilité, pectoraux-épaules. */
import { C, FLOOR_Y, cable, circle, figure, handle, line, mat, path, plate, rect, ring, shadow } from './lib.mjs';

const ARM = 55;
const THIGH = 82;
const SHIN = 84;

/** Point à `len` de `p`, dans la direction `deg` (0 = vers la droite, 90 = vers le bas). */
const at = (p, len, deg) => {
    const r = (deg * Math.PI) / 180;

    return [Math.round(p[0] + len * Math.cos(r)), Math.round(p[1] + len * Math.sin(r))];
};

/** Articulation intermédiaire entre `a` et `b` (segments l1, l2), du côté du point `hint`. */
const joint = (a, b, l1, l2, hint) => {
    const dx = b[0] - a[0];
    const dy = b[1] - a[1];
    const d = Math.min(Math.hypot(dx, dy), l1 + l2 - 0.5);
    const base = Math.atan2(dy, dx);
    const off = Math.acos(Math.max(-1, Math.min(1, (l1 * l1 + d * d - l2 * l2) / (2 * l1 * d))));
    const cands = [base + off, base - off].map((t) => [Math.round(a[0] + l1 * Math.cos(t)), Math.round(a[1] + l1 * Math.sin(t))]);
    const dist = (p) => Math.hypot(p[0] - hint[0], p[1] - hint[1]);

    return dist(cands[0]) <= dist(cands[1]) ? cands[0] : cands[1];
};

const arm = (neck, hand, hint) => joint(neck, hand, ARM, ARM, hint);
const leg = (hip, foot, hint) => joint(hip, foot, THIGH, SHIN, hint);

/** Tête dans le prolongement du tronc, un peu inclinée vers `lean` (en degrés). */
const headOf = (neck, hip, lean = 0) => {
    const deg = (Math.atan2(neck[1] - hip[1], neck[0] - hip[0]) * 180) / Math.PI + lean;

    return at(neck, 38, deg);
};

const shift = (dx, content, dy = 0) => `<g transform="translate(${dx} ${dy})">${content}</g>`;

/* ---------- abdos-cardio-fonctionnel ---------- */

const plancheCommando = [0, 1].map((f) => {
    const straight = f === 1;
    const foot = [500, 404];
    const neckY = straight ? 404 - 2 * ARM : 404 - ARM;
    const tilt = Math.asin((foot[1] - neckY) / 276);
    const neck = [Math.round(foot[0] - 276 * Math.cos(tilt)), neckY];
    const dir = [Math.cos(tilt), Math.sin(tilt)];
    const hip = [Math.round(neck[0] + 110 * dir[0]), Math.round(neck[1] + 110 * dir[1])];
    const knee = [Math.round(neck[0] + 192 * dir[0]), Math.round(neck[1] + 192 * dir[1])];
    const body = straight
        ? { elbow: [neck[0], neck[1] + ARM], hand: [neck[0], 404], farElbow: [neck[0] + 12, neck[1] + ARM], farHand: [neck[0] + 12, 404] }
        : { elbow: [neck[0], 404], hand: [neck[0] - ARM, 404], farElbow: [neck[0] + 12, 404], farHand: [neck[0] - 43, 404] };

    return shift(20, mat(90, 600) + figure({ head: headOf(neck, hip, straight ? 14 : 12), neck, hip, knee, foot,
        farKnee: [knee[0] + 4, knee[1]], farFoot: [foot[0] + 8, foot[1]], ...body }));
});

const lSit = [0, 1].map((f) => {
    const up = f === 1;
    const bars = line([200, 230], [470, 230], C.gearLight, 12)
        + line([226, 236], [226, FLOOR_Y], C.gear, 14) + line([444, 236], [444, FLOOR_Y], C.gear, 14)
        + line([186, FLOOR_Y - 6], [266, FLOOR_Y - 6], C.gear, 12) + line([404, FLOOR_Y - 6], [484, FLOOR_Y - 6], C.gear, 12);
    const body = up
        ? { hip: [318, 218], neck: [336, 110], hand: [350, 222], elbow: [343, 166],
            knee: [399, 204], foot: [481, 190], farKnee: [400, 210], farFoot: [482, 198] }
        : { hip: [326, 224], neck: [330, 114], hand: [352, 222], elbow: [341, 168],
            knee: [330, 306], foot: [334, 390], farKnee: [320, 306], farFoot: [316, 390] };

    return shift(10, bars + figure({ head: headOf(body.neck, body.hip, 4), ...body }));
});

const veloBiking = [0, 1].map((f) => {
    const standing = f === 1;
    const crank = [400, 356];
    const a = standing ? 90 : 0;
    const pedal = at(crank, 32, a);
    const farPedal = at(crank, 32, a + 180);
    const wheel = [542, 356];
    const bar = [505, 200];
    const frame = line([290, FLOOR_Y - 8], [620, FLOOR_Y - 8], C.gear, 12)
        + path([[318, FLOOR_Y - 8], crank, wheel, [590, FLOOR_Y - 8]], C.gear, 16)
        + line(crank, [336, 262], C.gear, 16)
        + line([524, 330], [498, 214], C.gear, 16)
        + rect(296, 250, 78, 18, C.gearLight, 9)
        + rect(484, 190, 42, 18, C.gearLight, 9);
    const spin = standing ? 30 : 0;
    const fly = ring(wheel, 50, C.accent, 10) + circle(wheel, 10, C.accent)
        + [0, 60, 120].map((d) => line(at(wheel, 46, d + spin), at(wheel, 46, d + 180 + spin), C.gearLight, 5)).join('');
    const crankArms = line(crank, pedal, C.gearLight, 8) + line(crank, farPedal, C.gearLight, 8) + circle(crank, 12, C.gearLight);
    const hip = standing ? [362, 226] : [330, 244];
    const neck = at(hip, 110, standing ? -52 : -40);
    const fwd = [hip[0] + 100, hip[1] - 30];

    return shift(-20, frame + fly + figure({ head: headOf(neck, hip, 12), neck, hip,
        hand: bar, elbow: arm(neck, bar, [neck[0] + 20, neck[1] + 60]),
        knee: leg(hip, pedal, fwd), foot: pedal, farKnee: leg(hip, farPedal, fwd), farFoot: farPedal }) + crankArms);
});

const shadowBoxing = [0, 1].map((f) => {
    const jab = f === 1;
    const hip = [352, 256];
    const neck = jab ? [372, 146] : [362, 144];
    const foot = [410, 418];
    const farFoot = [296, 418];
    const legs = { foot, farFoot, knee: leg(hip, foot, [460, 330]), farKnee: leg(hip, farFoot, [360, 330]) };
    const rear = { farHand: [neck[0] + 30, neck[1] - 8] };
    rear.farElbow = arm(neck, rear.farHand, [neck[0] + 20, neck[1] + 60]);
    const lead = jab ? { hand: [neck[0] + 110, neck[1] - 4] } : { hand: [neck[0] + 46, neck[1] - 10] };
    lead.elbow = jab ? [neck[0] + 55, neck[1] - 2] : arm(neck, lead.hand, [neck[0] + 30, neck[1] + 60]);
    const fists = circle(rear.farHand, 13, C.far) + circle(lead.hand, 14, C.near);

    return shift(0, figure({ head: headOf(neck, hip, 8), neck, hip, ...legs, ...rear, ...lead }) + fists);
});

const sandbagCarry = [0, 1].map((f) => {
    const walk = f === 1;
    const hip = walk ? [360, 262] : [356, 252];
    const neck = walk ? [364, 152] : [360, 142];
    const legs = walk
        ? { foot: [424, 418], knee: leg(hip, [424, 418], [440, 330]), farFoot: [300, 412], farKnee: leg(hip, [300, 412], [360, 330]) }
        : { foot: [364, 418], knee: [362, 335], farFoot: [352, 418], farKnee: [352, 335] };
    const bag = rect(neck[0] + 12, neck[1] + 16, 98, 72, C.accent, 26);
    const elbow = [neck[0] + 12, neck[1] + 54];
    const hand = [elbow[0] + 54, elbow[1] - 6];

    return bag + figure({ head: headOf(neck, hip, 4), neck, hip, hand, elbow, ...legs });
});

const thruster = [0, 1].map((f) => {
    const up = f === 1;
    const foot = [390, 418];

    if (up) {
        const hip = [366, 252];
        const neck = [370, 142];
        const hand = [378, 36];

        return figure({ head: headOf(neck, hip, 18), neck, hip, knee: [372, 335], foot, farFoot: [380, 418],
            hand, elbow: [375, 89] }) + plate(hand, 28);
    }

    const hip = [372, 376];
    const knee = leg(hip, foot, [470, 340]);
    const neck = at(hip, 110, -70);
    const hand = [neck[0] + 20, neck[1] + 2];
    const elbow = [neck[0] + 55, neck[1] - 8];

    return figure({ head: headOf(neck, hip, -12), neck, hip, knee, foot, farFoot: [380, 418], farKnee: [knee[0] - 8, knee[1] + 4], hand, elbow })
        + plate(hand, 24);
});

const burpeeBroadJump = [
    figure({ head: [500, 400], neck: [462, 408], hip: [352, 412], knee: [270, 414], foot: [186, 416],
        elbow: [438, 364], hand: [476, 404], farElbow: [448, 366], farHand: [488, 406] }),
    (() => {
        const hip = [352, 236];
        const neck = at(hip, 110, -62);
        const hands = [at(neck, 110, -24), at(neck, 110, -14)];
        const knee = at(hip, THIGH, 62);
        const farKnee = at(hip, THIGH, 72);

        return shadow(380, 150) + figure({ head: headOf(neck, hip, -4), neck, hip,
            hand: hands[0], elbow: at(neck, ARM, -24), farHand: hands[1], farElbow: at(neck, ARM, -14),
            knee, foot: at(knee, SHIN, 132), farKnee, farFoot: at(farKnee, SHIN, 142) });
    })(),
];

const squatTrx = [0, 1].map((f) => {
    const low = f === 1;
    const anchor = [640, 34];
    const mount = rect(600, 18, 80, 16, C.gear, 6) + circle(anchor, 10, C.gearLight);
    const foot = [382, 418];
    const hip = low ? [338, 380] : [362, 252];
    const knee = leg(hip, foot, low ? [470, 340] : [420, 330]);
    const neck = at(hip, 110, low ? -80 : -95);
    const dir = (Math.atan2(anchor[1] - neck[1], anchor[0] - neck[0]) * 180) / Math.PI;
    const hand = at(neck, 108, dir);
    const straps = line(anchor, hand, C.accent, 5) + handle(hand, 10);

    return mount + straps + figure({ head: headOf(neck, hip, 10), neck, hip, knee, foot, farFoot: [372, 418],
        farKnee: [knee[0] - 6, knee[1] + 2], hand, elbow: at(neck, ARM, dir), farHand: at(neck, 108, dir + 3), farElbow: at(neck, ARM, dir + 3) });
});

/* ---------- dos-bras ---------- */

const tractionsSupination = [0, 1].map((f) => {
    const up = f === 1;
    const hand = [390, 72];
    const neck = up ? [356, 86] : [378, 182];
    const hip = [neck[0] - 2, neck[1] + 110];
    const knee = [hip[0] + 12, hip[1] + 80];
    const padY = knee[1] + 8;
    const machine = rect(470, 30, 18, 398, C.gear, 4)
        + line([290, 72], [480, 72], C.gearLight, 12)
        + line([478, padY + 20], [400, padY + 20], C.gear, 14)
        + rect(284, padY, 138, 20, C.accent, 10);
    const elbow = up ? arm(neck, hand, [neck[0] + 50, neck[1] + 50]) : [384, 127];

    return machine + figure({ head: headOf(neck, hip, up ? 2 : 4), neck, hip, hand, elbow,
        knee, foot: [knee[0] - 82, knee[1] + 12] });
});

const pulloverMachine = [0, 1].map((f) => {
    const down = f === 1;
    const hip = [320, 334];
    const neck = [322, 224];
    const ang = down ? 62 : -150;
    const elbow = at(neck, ARM, ang);
    const hand = at(elbow, ARM, down ? 30 : -80);
    const machine = rect(196, 60, 22, FLOOR_Y - 60, C.gear, 6)
        + line([207, 80], [322, 80], C.gear, 14) + line([322, 80], [322, 190], C.gear, 14)
        + circle(neck, 28, C.gear)
        + line([284, 352], [280, 250], C.gear, 26)
        + rect(250, 346, 120, 22, C.gearLight, 11)
        + line([300, 368], [300, FLOOR_Y], C.gear, 14)
        + line([240, FLOOR_Y - 6], [360, FLOOR_Y - 6], C.gear, 12);
    const end = at(neck, 100, ang);
    const lever = line(neck, end, C.accent, 14) + line(end, hand, C.accent, 7);

    return shift(50, machine + lever + figure({ head: headOf(neck, hip, 2), neck, hip, elbow, hand,
        knee: [402, 334], foot: [410, 418] }) + handle(hand, 10) + circle(neck, 8, C.gearLight));
});

const kickbackPoulie = [0, 1].map((f) => {
    const ext = f === 1;
    const pulley = [610, 404];
    const hip = [300, 250];
    const foot = [334, 418];
    const knee = leg(hip, foot, [380, 330]);
    const neck = at(hip, 110, -26);
    const back = (Math.atan2(hip[1] - neck[1], hip[0] - neck[0]) * 180) / Math.PI;
    const elbow = ext ? at(neck, ARM, back + 12) : at(neck, ARM, back);
    const hand = ext ? at(elbow, ARM, 180) : at(elbow, ARM, back - 90);
    const farHand = [knee[0] + 18, knee[1] - 20];
    const tower = rect(626, 60, 24, FLOOR_Y - 60, C.gear, 6);

    return shift(10, tower + cable(pulley, hand) + figure({ head: headOf(neck, hip, 14), neck, hip, knee, foot, farFoot: [290, 418],
        farKnee: leg(hip, [290, 418], [330, 330]), elbow, hand, farHand, farElbow: arm(neck, farHand, [neck[0] + 20, neck[1] + 60]) })
        + handle(hand, 10));
});

/* ---------- mobilité ---------- */

const squatProfond = [
    figure({ head: [366, 104], neck: [364, 142], hip: [362, 252], elbow: [378, 196], hand: [386, 250],
        knee: [366, 335], foot: [372, 418], farKnee: [352, 335], farFoot: [350, 418] }),
    (() => {
        const foot = [360, 418];
        const hip = [348, 372];
        const knee = leg(hip, foot, [440, 340]);
        const neck = at(hip, 110, -76);
        const hand = [neck[0] + 30, neck[1] + 4];
        const elbow = arm(neck, hand, [knee[0] - 10, knee[1] - 30]);

        return figure({ head: headOf(neck, hip, 4), neck, hip, knee, foot, farFoot: [350, 418], farKnee: [knee[0] - 8, knee[1] + 2],
            elbow, hand });
    })(),
];

/* ---------- pectoraux-épaules ---------- */

const pressePectorale = [0, 1].map((f) => {
    const ext = f === 1;
    const hip = [300, 334];
    const neck = [302, 224];
    const elbow = ext ? [357, 230] : at(neck, ARM, 140);
    const hand = ext ? [410, 236] : at(elbow, ARM, -10);
    const pivot = [339, 74];
    const machine = rect(238, 60, 22, FLOOR_Y - 60, C.gear, 6)
        + line([249, 70], pivot, C.gear, 14)
        + line([272, 350], [272, 168], C.gear, 26)
        + rect(240, 346, 116, 22, C.gearLight, 11)
        + line([300, 368], [300, FLOOR_Y], C.gear, 14)
        + line([240, FLOOR_Y - 6], [360, FLOOR_Y - 6], C.gear, 12);
    const lever = line(pivot, hand, C.accent, 10) + circle(pivot, 12, C.gearLight);
    const farHand = [352, 324];

    return shift(20, machine + lever + figure({ head: headOf(neck, hip, 4), neck, hip, knee: [382, 334], foot: [388, 418],
        hand, elbow, farHand, farElbow: arm(neck, farHand, [neck[0] + 10, neck[1] + 60]) }) + handle(hand, 12));
});

const decline = [0, 1].map((f) => {
    const ext = f === 1;
    const hip = [300, 334];
    const neck = at(hip, 110, -110);
    const elbow = ext ? at(neck, ARM, 25) : at(neck, ARM, 120);
    const hand = ext ? at(neck, 108, 25) : at(elbow, ARM, -15);
    const far = (p) => [p[0] + 6, p[1] + 4];
    const pivot = [354, 83];
    const machine = rect(186, 60, 22, FLOOR_Y - 60, C.gear, 6)
        + line([197, 70], pivot, C.gear, 14)
        + line([276, 352], [222, 200], C.gear, 26)
        + rect(240, 346, 116, 22, C.gearLight, 11)
        + line([300, 368], [300, FLOOR_Y], C.gear, 14)
        + line([240, FLOOR_Y - 6], [360, FLOOR_Y - 6], C.gear, 12);
    const lever = line(pivot, hand, C.accent, 10) + circle(pivot, 12, C.gearLight);

    return shift(40, machine + lever + figure({ head: headOf(neck, hip, 6), neck, hip, knee: [382, 334], foot: [388, 418],
        hand, elbow, farHand: far(hand), farElbow: far(elbow) }) + handle(hand, 12));
});

export default {
    'planche-commando': plancheCommando,
    'l-sit': lSit,
    'velo-de-biking': veloBiking,
    'shadow-boxing': shadowBoxing,
    'sandbag-carry': sandbagCarry,
    thruster,
    'burpee-broad-jump': burpeeBroadJump,
    'squat-trx': squatTrx,
    'tractions-assistees-supination': tractionsSupination,
    'pullover-machine': pulloverMachine,
    'kickback-triceps-poulie': kickbackPoulie,
    'squat-profond-tenu': squatProfond,
    'presse-pectorale-unilaterale': pressePectorale,
    'developpe-decline-iso-lateral': decline,
};
