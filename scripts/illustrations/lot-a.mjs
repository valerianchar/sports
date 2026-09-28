/* Lot A : abdos, cardio, fonctionnel, dos, mobilité, pectoraux-épaules. */
import { C, backrest, circle, dumbbell, figure, line, mat, pad, path, plate, post, rect, ring, shadow } from './lib.mjs';

const round = (v) => Math.round(v);
const rad = (d) => (d * Math.PI) / 180;

/** Point à `len` de `p`, dans la direction `deg` (0 = droite, 90 = bas). */
const seg = (p, len, deg) => [round(p[0] + len * Math.cos(rad(deg))), round(p[1] + len * Math.sin(rad(deg)))];

/** Articulation (coude, genou) entre deux extrémités, pliée du côté `dir` (+1 / -1). */
function joint(a, c, l1, l2, dir) {
    const dx = c[0] - a[0];
    const dy = c[1] - a[1];
    const len = Math.hypot(dx, dy) || 1;
    const d = Math.min(len, l1 + l2 - 0.01);
    const x = (l1 * l1 - l2 * l2 + d * d) / (2 * d);
    const h = Math.sqrt(Math.max(0, l1 * l1 - x * x));
    const ux = dx / len;
    const uy = dy / len;

    return [round(a[0] + ux * x - dir * uy * h), round(a[1] + uy * x + dir * ux * h)];
}

/**
 * Un corps aux proportions de figure() : on donne hanche, cou, mains et pieds,
 * les coudes et genoux se déduisent (e, fe : sens des coudes ; k, fk : des genoux).
 */
function body(p) {
    const b = { ...p };
    const trunk = Math.atan2(p.neck[1] - p.hip[1], p.neck[0] - p.hip[0]) * (180 / Math.PI);
    b.head = p.head ?? seg(p.neck, 38, p.headDeg ?? trunk);
    b.elbow = p.elbow ?? joint(p.neck, p.hand, 55, 55, p.e ?? 1);
    b.knee = p.knee ?? joint(p.hip, p.foot, 82, 84, p.k ?? -1);
    if (p.farHand) b.farElbow = p.farElbow ?? joint(p.neck, p.farHand, 55, 55, p.fe ?? p.e ?? 1);
    if (p.farFoot) b.farKnee = p.farKnee ?? joint(p.hip, p.farFoot, 82, 84, p.fk ?? p.k ?? -1);

    return figure(b);
}

const curve = (d, color = C.accent, w = 8) =>
    `<path d="${d}" fill="none" stroke="${color}" stroke-width="${w}" stroke-linecap="round" stroke-linejoin="round"/>`;

/** Rotation de `p` autour de `c` de `deg` degrés. */
function turn(p, c, deg) {
    const a = rad(deg);
    const dx = p[0] - c[0];
    const dy = p[1] - c[1];

    return [round(c[0] + dx * Math.cos(a) - dy * Math.sin(a)), round(c[1] + dx * Math.sin(a) + dy * Math.cos(a))];
}

const angleOf = (p, c) => Math.atan2(p[1] - c[1], p[0] - c[0]) * (180 / Math.PI);

export default {
    'sit-up-ghd': [0, 1].map((f) => {
        const up = f === 1;
        const y = (p) => [p[0], p[1] - 44];
        const hip = y([400, 272]);
        const machine = line([300, 420], [620, 420], C.gear, 14)
            + line([410, 250], [410, 420], C.gear, 18)
            + line([410, 320], [598, 290], C.gear, 14)
            + line([598, 180], [598, 420], C.gear, 16)
            + pad(346, 246, 110, 28)
            + circle(y([562, 246]), 14, C.gearLight) + circle(y([548, 292]), 14, C.gearLight);
        const legs = { knee: y([480, 258]), foot: y([566, 270]) };
        const person = up
            ? body({ hip, neck: y([478, 194]), headDeg: -35, hand: y([548, 256]), e: 1, ...legs })
            : body({ hip, neck: y([290, 282]), head: y([252, 288]), elbow: y([238, 262]), hand: y([184, 256]), ...legs });

        return machine + person;
    }),
    'rotation-du-buste-machine': [0, 1].map((f) => {
        const turned = f === 1;
        const machine = line([360, 300], [360, 428], C.gear, 26) + line([300, 426], [420, 426], C.gear, 10)
            + rect(292, 296, 136, 24, C.gearLight, 12)
            + line([300, 356], [420, 356], C.gearLight, 20);
        const legs = path([[360, 284], [394, 318], [402, 418]], C.far, 22) + path([[360, 284], [326, 318], [318, 418]], C.near, 23);
        const pose = turned
            ? { sh: [[344, 184], [396, 178]], el: [[352, 232], [424, 222]], ha: [[392, 204], [422, 200]], pad: [362, 64], head: [368, 134] }
            : { sh: [[322, 182], [398, 182]], el: [[306, 230], [414, 230]], ha: [[334, 204], [386, 204]], pad: [312, 96], head: [360, 134] };
        const upper = [0, 1].map((i) => line(pose.sh[i], pose.el[i], C.near, 20)).join('');
        const fore = [0, 1].map((i) => line(pose.el[i], pose.ha[i], C.near, 20)).join('');
        const arrow = turned
            ? curve('M 296 100 Q 360 68 424 100', C.accent, 4) + path([[408, 86], [426, 101], [404, 106]], C.accent, 4)
            : '';

        return machine + legs + line([360, 176], [360, 284], C.near, 34) + line(pose.sh[0], pose.sh[1], C.near, 24)
            + upper + rect(pose.pad[0], 190, pose.pad[1], 24, C.accent, 12) + fore + circle(pose.head, 23, C.near) + arrow;
    }),
    'tapis-curve': [0, 1].map((f) => {
        const run = f === 1;
        const deck = curve('M 180 352 Q 360 452 560 342', C.accent, 12);
        const frame = curve('M 186 380 Q 360 470 552 370', C.gear, 16)
            + line([220, 396], [214, 428], C.gear, 14) + line([520, 396], [530, 428], C.gear, 14)
            + line([556, 350], [574, 206], C.gear, 14) + line([574, 206], [520, 196], C.gearLight, 12);
        const person = run
            ? body({ hip: [372, 226], neck: seg([372, 226], 110, -72), hand: [452, 190], farHand: [318, 262],
                e: 1, fe: 1, foot: [438, 380], farFoot: [276, 318] })
            : body({ hip: [348, 224], neck: [352, 114], hand: [380, 262], farHand: [320, 262],
                foot: [382, 390], farFoot: [300, 378] });

        return frame + deck + person;
    }),
    'montees-de-genoux': [0, 1].map((f) => {
        const hip = [360, 252];
        const neck = [364, 142];
        const upLeg = { knee: [440, 250], foot: [430, 334] };
        const standLeg = { knee: [368, 334], foot: [360, 418] };
        const frontArm = { elbow: seg(neck, 55, 70), hand: seg(seg(neck, 55, 70), 55, -40) };
        const backArm = { elbow: seg(neck, 55, 140), hand: seg(seg(neck, 55, 140), 55, 70) };
        const near = f === 0
            ? { ...upLeg, ...backArm }
            : { ...standLeg, ...frontArm };
        const far = f === 0
            ? { farKnee: standLeg.knee, farFoot: standLeg.foot, farElbow: frontArm.elbow, farHand: frontArm.hand }
            : { farKnee: upLeg.knee, farFoot: upLeg.foot, farElbow: backArm.elbow, farHand: backArm.hand };

        return shadow(362) + figure({ head: [370, 104], neck, hip, ...near, ...far });
    }),
    'sandbag-lunges': [0, 1].map((f) => {
        const lunge = f === 1;
        const hip = lunge ? [380, 334] : [360, 252];
        const neck = [hip[0] + 2, hip[1] - 110];
        const bag = rect(neck[0] - 46, neck[1] - 32, 64, 54, C.accent, 24);
        const head = [neck[0] + 8, neck[1] - 38];
        const hand = [neck[0] + 4, neck[1] - 12];
        const elbow = [neck[0] + 46, neck[1] + 24];
        const legs = lunge
            ? { knee: [462, 336], foot: [468, 418], farKnee: [334, 404], farFoot: [252, 414] }
            : { foot: [364, 418], farFoot: [352, 418] };

        return shadow(360, lunge ? 260 : 110) + body({ hip, neck, head, hand, elbow, ...legs }) + bag
            + circle(head, 23, C.near) + path([neck, elbow, hand], C.near, 20);
    }),
    'battle-rope-slams': [0, 1].map((f) => {
        const down = f === 1;
        const anchor = [650, 412];
        const machine = rect(634, 380, 36, 48, C.gear, 6);
        const person = down
            ? body({ hip: [224, 306], neck: seg([224, 306], 110, -30), headDeg: -10, hand: [356, 352], farHand: [348, 356],
                foot: [290, 418], farFoot: [278, 418] })
            : body({ hip: [240, 252], neck: [244, 142], hand: [284, 38], farHand: [272, 36], foot: [244, 418], farFoot: [232, 418] });
        const h = down ? [356, 352] : [284, 38];
        const ropes = down
            ? curve(`M ${h[0]} ${h[1]} Q 380 400 420 410 T 500 396 T 580 412 T ${anchor[0]} ${anchor[1]}`)
                + curve(`M ${h[0] - 8} ${h[1] + 4} Q 372 404 412 414 T 492 402 T 572 416 T ${anchor[0]} ${anchor[1] + 4}`, C.gearLight, 6)
            : curve(`M ${h[0]} ${h[1]} Q 420 40 470 250 T ${anchor[0]} ${anchor[1]}`)
                + curve(`M ${h[0] - 10} ${h[1] + 2} Q 410 44 462 256 T ${anchor[0]} ${anchor[1] + 4}`, C.gearLight, 6);

        return machine + (down ? ropes + person : person + ropes);
    }),
    'man-maker': [0, 1].map((f) => {
        if (f === 1) {
            const neck = [340, 140];
            const hands = { hand: [364, 34], farHand: [354, 34] };

            return shadow(340) + body({ hip: [340, 252], neck, head: [346, 102], ...hands,
                foot: [344, 418], farFoot: [332, 418] }) + dumbbell([354, 32]) + dumbbell([364, 32]);
        }
        const hand = [236, 404];
        const neck = [236, 294];
        const foot = [488, 414];
        const u = [(foot[0] - neck[0]) / 276, (foot[1] - neck[1]) / 276];
        const hip = [round(neck[0] + u[0] * 110), round(neck[1] + u[1] * 110)];
        const knee = [round(neck[0] + u[0] * 192), round(neck[1] + u[1] * 192)];

        return dumbbell([hand[0] + 14, 414]) + body({ hip, neck, head: [neck[0] - 36, neck[1] - 12], hand, elbow: [236, 349],
            farHand: [hand[0] + 14, 404], farElbow: [244, 349], knee, foot, farKnee: [knee[0] + 4, knee[1] + 2], farFoot: [foot[0] + 10, 416] })
            + dumbbell([hand[0], 414]);
    }),
    'sac-de-frappe-enchainements': [0, 1].map((f) => {
        const hit = f === 1;
        const bag = line([420, 28], [640, 28], C.gear, 12) + line([520, 28], [520, 110], C.gearLight, 5)
            + rect(478, 104, 84, 220, C.gear, 30);
        const glove = (c) => circle(c, 17, C.accent);
        const person = hit
            ? { hip: [358, 262], neck: [384, 156], head: [406, 124], hand: [478, 140], e: 1, farHand: [414, 124], fe: 1,
                foot: [290, 414], farFoot: [412, 418], k: -1 }
            : { hip: [350, 262], neck: [360, 152], head: [370, 114], hand: [396, 132], e: 1, farHand: [424, 120], fe: 1,
                foot: [296, 418], farFoot: [412, 418], k: -1 };

        return bag + shadow(354, 160) + glove(person.farHand) + body(person) + glove(person.hand);
    }),
    'tirage-iso-lateral': [0, 1].map((f) => {
        const pivot = [180, 180];
        const h1 = [432, 116];
        const hand = f === 1 ? turn(h1, pivot, 30) : h1;
        const tip = turn(hand, pivot, -4);
        const horn = seg(pivot, 70, angleOf(hand, pivot));
        const machine = post(180, 170, 24) + line([160, 428], [520, 428], C.gear, 10)
            + line([180, 350], [390, 350], C.gear, 14) + pad(330, 334, 110, 24)
            + line([480, 350], [480, 300], C.gear, 12) + pad(446, 290, 72, 22);
        const lever = line(pivot, tip, C.accent, 12) + line(tip, hand, C.accent, 8) + plate(horn, 30);
        const person = body({ hip: [390, 322], neck: [394, 212], hand, e: 1, knee: [472, 326], foot: [478, 416] });

        return machine + lever + circle(pivot, 12, C.gearLight) + person + circle(hand, 11, C.accent);
    }),
    'rowing-pendlay': [0, 1].map((f) => {
        // Buste à l'horizontale, la barre repart du sol à chaque répétition.
        const up = f === 1;
        const hand = up ? [388, 306] : [414, 394];
        const body = figure({
            head: [447, 282], neck: [410, 284], hip: [300, 272],
            elbow: up ? [352, 246] : [412, 340], hand,
            knee: [346, 344], foot: [332, 426], farKnee: [338, 346], farFoot: [318, 426],
        });

        return shadow(360, 200) + plate(hand, 34) + body + circle(hand, 9, C.accent);
    }),
    'dips-assistes': [0, 1].map((f) => {
        const down = f === 1;
        const padY = down ? 406 : 346;
        const machine = rect(470, 30, 18, 398, C.gear, 4)
            + line([290, 72], [480, 72], C.gearLight, 12)
            + line([330, 264], [480, 264], C.gearLight, 12)
            + line([478, padY + 12], [400, padY + 12], C.gear, 14)
            + rect(260, padY, 150, 20, C.accent, 10);
        const hand = [374, 256];
        const person = down
            ? { hip: [366, 312], neck: [384, 204], hand, elbow: [334, 216], knee: [368, 394], foot: [284, 396] }
            : { hip: [360, 252], neck: [358, 142], hand, elbow: [366, 199], knee: [362, 334], foot: [278, 336] };

        return machine + body(person) + circle(hand, 9, C.gearLight);
    }),
    'ouverture-thoracique': [0, 1].map((f) => {
        // Vue de dessus : allongé sur le côté, dos vers le haut de l'image.
        const open = f === 1;
        const neck = [250, 250];
        const hip = [362, 250];
        const legs = path([hip, [368, 332], [452, 334]], C.far, 22) + path([hip, [362, 326], [446, 328]], C.near, 23);
        const bottomArm = path([neck, [256, 305], [262, 360]], C.far, 20);
        const topShoulder = open ? [250, 212] : neck;
        const topArm = open
            ? path([topShoulder, [246, 157], [242, 102]], C.near, 20)
            : path([neck, [250, 305], [254, 360]], C.near, 20);
        const arrow = open ? curve('M 300 370 Q 420 236 300 102', C.accent, 4) + path([[286, 116], [300, 100], [310, 120]], C.accent, 4) : '';

        return rect(20, 416, 680, 24, C.bg, 0) + `<g transform="translate(34 8) rotate(-12 326 232)">`
            + rect(136, 72, 380, 318, C.floor, 18) + '<g transform="translate(20 -18)">' + legs + bottomArm
            + line(neck, hip, C.near, 34) + (open ? line([250, 254], topShoulder, C.near, 30) : '') + topArm
            + circle([212, 252], 23, C.near) + arrow + '</g></g>';
    }),
    'etirement-tibias': [0, 1].map((f) => {
        const back = f === 1;
        const foot = [336, 414];
        const person = back
            ? { hip: [344, 360], neck: seg([344, 360], 110, -150), hand: [232, 412], knee: [418, 396], foot, e: -1 }
            : { hip: [338, 380], neck: seg([338, 380], 110, -125), hand: [248, 402], knee: [418, 406], foot, e: -1 };

        return mat(150, 560) + line(foot, [300, 418], C.near, 16) + body(person);
    }),
    'wide-chest': [0, 1].map((f) => {
        const push = f === 1;
        const h1 = [366, 244];
        const h2 = [432, 232];
        const pivot = [362, 40];
        const hand = push ? h2 : h1;
        const machine = post(270, 40, 22) + line([270, 40], [460, 40], C.gear, 16)
            + rect(250, 344, 140, 24, C.gearLight, 12) + line([320, 368], [320, 428], C.gear, 14)
            + backrest([296, 344], [296, 176], C.gearLight);
        const lever = line(pivot, hand, C.accent, 10) + plate(seg(pivot, 80, angleOf(hand, pivot)), 30)
            + circle(pivot, 12, C.gearLight);
        const person = push
            ? { hip: [320, 330], neck: [322, 220], head: [328, 182], elbow: [376, 228], hand: [430, 234] }
            : { hip: [320, 330], neck: [322, 220], head: [328, 182], elbow: [304, 258], hand: [362, 246] };

        return machine + lever + figure({ ...person, knee: [402, 334], foot: [406, 418] }) + circle(hand, 11, C.accent);
    }),
    'elevations-frontales-barre': [0, 1].map((f) => {
        const up = f === 1;
        const neck = [360, 140];
        const hand = up ? seg(neck, 110, -14) : [380, 248];

        return shadow(362) + body({ hip: [360, 252], neck, head: [364, 102], hand, e: 1,
            foot: [364, 418], farFoot: [352, 418] }) + plate(hand, 26);
    }),
};
