/* Lot D : presses, squats machine, hip thrusts, abductions, gainage. */
import { C, bench, cable, circle, figure, line, mat, pad, path, post, rect, ring, shadow } from './lib.mjs';

const rad = (d) => (d * Math.PI) / 180;
const r = (v) => Math.round(v);
const at = (p, len, deg) => [r(p[0] + len * Math.cos(rad(deg))), r(p[1] + len * Math.sin(rad(deg)))];
const add = (p, q, k = 1) => [r(p[0] + k * q[0]), r(p[1] + k * q[1])];

/** Articulation du milieu (genou, coude) entre deux extrémités ; `side` choisit le sens de pliure. */
function joint(a, b, side = 1, l1 = 82, l2 = 84) {
    const d = Math.min(Math.hypot(b[0] - a[0], b[1] - a[1]), l1 + l2 - 1);
    const base = Math.atan2(b[1] - a[1], b[0] - a[0]);
    const k = Math.acos(Math.max(-1, Math.min(1, (l1 * l1 + d * d - l2 * l2) / (2 * l1 * d))));
    const ang = base - side * k;

    return [r(a[0] + l1 * Math.cos(ang)), r(a[1] + l1 * Math.sin(ang))];
}

const elbowOf = (neck, hand, side = 1) => joint(neck, hand, side, 55, 55);

/** Barre rigide épaisse entre deux points, de la couleur voulue. */
const bar = (a, b, color = C.gear, w = 14) => line(a, b, color, w);

/** Rectangle centré et tourné. */
const plateAt = (c, w, h, deg, color = C.accent) => rect(c[0] - w / 2, c[1] - h / 2, w, h, color, h / 2, [deg, c[0], c[1]]);

/* ---------- Presse à cuisses 45° ---------- */

function legPress(extended, high) {
    const hip = [326, 346];
    const u = [Math.cos(rad(-45)), Math.sin(rad(-45))];
    const v = [-u[1], u[0]];
    const reach = extended ? 158 : 104;
    const foot = at(hip, reach, high ? -58 : -44);
    const platform = add(add(foot, u, 14), v, high ? 34 : 0);
    const railStart = add(hip, [60, 84]);
    const railEnd = add(railStart, u, 380);
    const trunk = -142;
    const neck = at(hip, 110, trunk);
    const head = at(neck, 38, -128);
    const back = [Math.cos(rad(trunk + 90)), Math.sin(rad(trunk + 90))];
    const hand = add(hip, [18, 16]);
    const knee = joint(hip, foot, 1);
    const seatBack = add(hip, back, -26);
    const railFoot = add(railStart, u, 280);
    const along = (platform[0] - railStart[0]) * u[0] + (platform[1] - railStart[1]) * u[1];
    const railAt = add(railStart, u, along);

    const machine = bar(railStart, railEnd, C.gear, 18)
        + bar(railFoot, [railFoot[0], 428], C.gear, 14)
        + bar(seatBack, add(neck, back, -26), C.gear, 28)
        + bar(seatBack, add(seatBack, [100, 4]), C.gear, 22)
        + bar([hip[0] - 30, 372], [hip[0] - 30, 428], C.gear, 14)
        + bar([hip[0] - 90, 428], [railStart[0], 428], C.gear, 10)
        + line(add(platform, v, high ? -52 : -86), railAt, C.accent, 16)
        + line(add(railAt, u, -34), add(railAt, u, 34), C.accent, 22);
    const handleBar = line(add(hand, [-14, 16]), add(hand, [8, -10]), C.gearLight, 10);
    const body = { head, neck, hip, knee, foot, elbow: elbowOf(neck, hand, -1), hand };

    if (high) {
        Object.assign(body, { farKnee: add(knee, [8, -6]), farFoot: add(foot, [8, -4]) });
    } else {
        const farFoot = add(hip, [70, 80]);
        Object.assign(body, { farKnee: joint(hip, farFoot, 1), farFoot });
    }

    return machine + handleBar + figure(body);
}

/* ---------- V-squat ---------- */

function vSquat(down) {
    const foot = [395, 400];
    const pivot = [178, 190];
    let knee;
    let hip;
    let neck;
    let head;

    if (down) {
        knee = at(foot, 84, -60);
        hip = [knee[0] - 82, knee[1]];
        neck = at(hip, 110, -60);
        head = at(neck, 38, -64);
    } else {
        hip = [390, 238];
        knee = joint(hip, foot, 1);
        neck = at(hip, 110, -86);
        head = at(neck, 38, -84);
    }

    const padC = add(neck, [-6, -16]);
    const hand = add(padC, [12, 4]);
    const machine = rect(250, 408, 250, 20, C.gear, 6)
        + plateAt([398, 408], 150, 14, -7, C.gearLight)
        + post(pivot[0], pivot[1] - 10, 22)
        + bar([pivot[0], 420], [260, 420], C.gear, 14);
    const lever = bar(pivot, padC, C.accent, 12) + circle(pivot, 13, C.gearLight);

    return machine + lever + figure({ head, neck, hip, knee, foot,
        elbow: elbowOf(neck, hand, -1), hand, farFoot: add(foot, [8, 0]), farKnee: add(knee, [8, 0]) })
        + pad(padC[0] - 26, padC[1] - 12, 52, 24, C.accent);
}

/* ---------- Élévations des orteils ---------- */

function toeRaise(up) {
    const wallX = 282;
    const hip = [307, 264];
    const ankle = [382, 404];
    const knee = joint(hip, ankle, 1);
    const neck = [308, 154];
    const heel = [376, 420];
    const toe = up ? at(heel, 58, -34) : [432, 420];
    const cue = up ? `<path d="M 446 420 A 64 64 0 0 0 432 382" fill="none" stroke="${C.accent}" stroke-width="3" stroke-dasharray="5 6"/>` : '';
    const foot = (dx, color) => path([add(ankle, [dx, 0]), add(heel, [dx, 0]), add(toe, [dx, 0])], color, 16);

    return rect(wallX - 8, 40, 16, 388, C.gear, 4) + shadow(410, 90)
        + foot(8, C.far)
        + figure({ head: [312, 116], neck, hip, knee, foot: ankle,
            elbow: [318, 210], hand: [336, 262], farKnee: add(knee, [8, 0]), farFoot: add(ankle, [8, 0]) })
        + foot(0, C.near) + cue;
}

/* ---------- Gainage Copenhague (de face) ---------- */

function copenhagen(up) {
    const elbow = [212, 416];
    const foot = [486, 330];
    const neck = up ? [214, 362] : [238, 370];
    const hip = up ? add(neck, [foot[0] - neck[0], foot[1] - neck[1]], 110 / 274) : [340, 402];
    const knee = joint(hip, foot, up ? 1 : -1);
    const dir = [neck[0] - hip[0], neck[1] - hip[1]];
    const head = add(neck, dir, 38 / Math.hypot(...dir));
    const farKnee = up ? add(knee, [0, 14]) : [420, 408];
    const farFoot = up ? [482, 378] : [502, 412];
    return bench(430, 600, 342)
        + figure({ head, neck, hip, knee, foot, elbow, hand: add(elbow, [-50, 2]),
            farElbow: add(neck, [2, -55]), farHand: add(neck, [4, -110]), farKnee, farFoot });
}

/* ---------- Hip thrusts ---------- */

function hipThrust(up, smith) {
    const neck = up ? [236, 322] : [228, 326];
    const hip = up ? [344, 330] : at(neck, 110, 46);
    const foot = [430, 418];
    const knee = joint(hip, foot, 1);
    const head = up ? [198, 314] : [194, 312];
    const benchSvg = bench(120, 250, 330);
    const bodyBase = { head, neck, hip, knee, foot };

    if (smith) {
        const barC = [326, hip[1] - 30];
        const hand = add(barC, [-8, 4]);

        return post(326, 40, 16)
            + rect(310, barC[1] - 30, 32, 60, C.gearLight, 6)
            + benchSvg
            + circle(barC, 36, C.accent) + circle(barC, 9, C.bg)
            + figure({ ...bodyBase, elbow: elbowOf(neck, hand, -1), hand })
            + circle(barC, 13, C.accent);
    }

    const hand = add(hip, [-18, -20]);
    const trunkDeg = Math.atan2(neck[1] - hip[1], neck[0] - hip[0]) * 180 / Math.PI;
    const farKnee = at(hip, 82, up ? trunkDeg + 90 : -76);
    const farFoot = at(farKnee, 84, up ? trunkDeg + 180 : 4);

    return benchSvg + figure({ ...bodyBase, elbow: elbowOf(neck, hand, 1), hand, farKnee, farFoot });
}

/* ---------- Multi-hip abduction (de face) ---------- */

function multiHip(open) {
    const hip = [340, 252];
    const deg = open ? 36 : 4;
    const knee = at(hip, 82, 90 - deg);
    const foot = at(knee, 84, 90 - deg);
    const hub = [352, 258];
    const thighMid = add(hip, [knee[0] - hip[0], knee[1] - hip[1]], 0.75);
    const out = [Math.cos(rad(-deg)), Math.sin(rad(-deg))];
    const padC = add(thighMid, out, 26);
    const leftHand = [240, 236];
    const machine = rect(210, 408, 300, 20, C.gear, 6)
        + post(560, 150, 26)
        + bar([560, 170], [352, 170], C.gear, 16)
        + bar([352, 170], [352, 258], C.gear, 14)
        + post(232, 200, 14);

    return machine
        + figure({ head: [338, 102], neck: [338, 140], hip,
            elbow: [372, 192], hand: [356, 236], farElbow: elbowOf([338, 140], leftHand, -1), farHand: leftHand,
            knee, foot, farKnee: [334, 334], farFoot: [332, 408] })
        + bar(hub, padC, C.accent, 12)
        + plateAt(padC, 20, 66, -deg, C.accent)
        + circle(hub, 14, C.gearLight);
}

/* ---------- Abduction à la poulie basse (de face) ---------- */

function cableAbduction(open) {
    const hip = [320, 252];
    const neck = [320, 140];
    const pulley = [196, 404];
    const foot = open ? at(hip, 164, 90 - 34) : [282, 404];
    const knee = open ? at(hip, 82, 90 - 34) : joint(hip, foot, -1);
    const handle = [214, 196];

    return post(196, 60, 30)
        + cable(pulley, foot)
        + figure({ head: [320, 102], neck, hip,
            elbow: [362, 194], hand: [344, 240], farElbow: elbowOf(neck, handle, 1), farHand: handle,
            knee, foot, farKnee: [314, 334], farFoot: [312, 418] })
        + circle(foot, 10, C.accent) + circle(handle, 9, C.gearLight);
}

/* ---------- Clamshell (de face, allongé sur le côté) ---------- */

function clamshell(open) {
    const neck = [196, 338];
    const hip = [306, 342];
    const foot = [424, 346];
    const lowKnee = [364, 404];
    const knee = open ? [360, 282] : [364, 396];
    const band = open
        ? line(add(lowKnee, [-2, -8]), add(knee, [0, 8]), C.accent, 6)
        : ring([364, 400], 15, C.accent, 5);

    return rect(70, 318, 580, 108, C.gear, 10)
        + figure({ head: [156, 326], neck, hip,
            elbow: [236, 372], hand: [284, 390], farElbow: [146, 350], farHand: [92, 348],
            knee, foot, farKnee: lowKnee, farFoot: add(foot, [0, 6]) })
        + band;
}

/* ---------- Frog pump ---------- */

function frogPump(up) {
    const foot = [410, 414];
    const neck = up ? [214, 402] : [204, 402];
    const hip = up ? at(neck, 110, -28) : [314, 402];
    const head = add(neck, [-38, -4]);
    const farFoot = add(foot, [-6, -2]);

    return mat(80, 640)
        + figure({ head, neck, hip, elbow: add(neck, [55, 10]), hand: add(neck, [108, 12]),
            knee: joint(hip, foot, 1, 64, 66), foot, farKnee: joint(hip, farFoot, 1), farFoot });
}

/* ---------- Fentes croisées (de face) ---------- */

const dbFront = (c) => rect(c[0] - 13, c[1] - 20, 26, 40, C.accent, 10);

function curtsy(down) {
    if (!down) {
        return shadow(360, 140) + figure({ head: [360, 102], neck: [360, 140], hip: [360, 252],
            elbow: [380, 196], hand: [386, 252], farElbow: [340, 196], farHand: [334, 252],
            knee: [348, 334], foot: [346, 418], farKnee: [372, 334], farFoot: [374, 418] })
            + dbFront([388, 258]) + dbFront([332, 258]);
    }

    const hip = [360, 334];

    return shadow(400, 170) + path([[348, 338], [404, 402], [452, 414]], C.far, 22) + figure({ head: [360, 184], neck: [360, 222], hip,
        elbow: [380, 278], hand: [386, 334], farElbow: [340, 278], farHand: [334, 334],
        knee: [390, 364], foot: [376, 418], farKnee: [390, 364], farFoot: [376, 418] })
        + dbFront([388, 340]) + dbFront([332, 340]);
}

const pair = (fn, ...args) => [fn(false, ...args), fn(true, ...args)];

export default {
    'presse-a-cuisses-unilaterale': pair(legPress, false),
    'v-squat': pair(vSquat),
    'elevations-des-orteils': pair(toeRaise),
    'gainage-copenhague': pair(copenhagen),
    'hip-thrust-smith': pair(hipThrust, true),
    'hip-thrust-unilateral': pair(hipThrust, false),
    'presse-a-cuisses-pieds-hauts': pair(legPress, true),
    'multi-hip-abduction': pair(multiHip),
    'abduction-poulie': pair(cableAbduction),
    clamshell: pair(clamshell),
    'frog-pump': pair(frogPump),
    'fentes-croisees': pair(curtsy),
};
