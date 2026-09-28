/* Les neuf premières illustrations, de la maquette d'origine. */
import { C, figure, line, path, shadow } from './lib.mjs';

export default {
    'jumping-jacks': [
        figure({ head: [360, 102], neck: [360, 140], hip: [360, 252],
            elbow: [378, 196], hand: [384, 252], farElbow: [342, 196], farHand: [336, 252],
            knee: [370, 334], foot: [372, 418], farKnee: [350, 334], farFoot: [348, 418] }),
        shadow(360, 220) + figure({ head: [360, 92], neck: [360, 130], hip: [360, 242],
            elbow: [410, 86], hand: [440, 40], farElbow: [310, 86], farHand: [280, 40],
            knee: [402, 322], foot: [432, 404], farKnee: [318, 322], farFoot: [288, 404] }),
    ],
    burpees: [
        figure({ head: [168, 286], neck: [205, 302], hip: [392, 318],
            elbow: [208, 358], hand: [210, 418], farElbow: [228, 360], farHand: [232, 418],
            knee: [486, 348], foot: [586, 414], farKnee: [490, 352], farFoot: [596, 416] }),
        shadow(360) + figure({ head: [362, 84], neck: [360, 122], hip: [360, 232],
            elbow: [380, 72], hand: [392, 22], farElbow: [340, 72], farHand: [330, 22],
            knee: [368, 312], foot: [372, 392], farKnee: [352, 312], farFoot: [350, 392] }),
    ],
    'hollow-hold': [
        `<rect x="70" y="414" width="560" height="12" rx="6" fill="${C.gear}"/>`
        + figure({ head: [150, 390], neck: [190, 398], hip: [360, 402],
            elbow: [120, 396], hand: [66, 396], knee: [470, 402], foot: [584, 402] }),
        `<rect x="70" y="414" width="560" height="12" rx="6" fill="${C.gear}"/>`
        + figure({ head: [170, 346], neck: [206, 368], hip: [360, 402],
            elbow: [150, 342], hand: [96, 318], knee: [472, 370], foot: [580, 336] }),
    ],
    chaise: [
        `<rect x="228" y="40" width="16" height="388" fill="${C.gear}"/>`
        + figure({ head: [284, 102], neck: [272, 140], hip: [268, 252],
            elbow: [276, 198], hand: [282, 252], knee: [276, 334], foot: [290, 418], farFoot: [300, 418] }),
        `<rect x="228" y="40" width="16" height="388" fill="${C.gear}"/>`
        + `<line x1="250" y1="322" x2="394" y2="322" stroke="${C.accent}" stroke-width="3" stroke-dasharray="6 8"/>`
        + figure({ head: [284, 176], neck: [270, 212], hip: [268, 322],
            elbow: [300, 262], hand: [344, 300], farHand: [352, 306], knee: [390, 322], foot: [390, 418], farKnee: [396, 326], farFoot: [398, 418] }),
    ],
    'tractions-assistees': [0, 1].map((f) => {
        const up = f === 1;
        const pad = up ? 290 : 360;
        const machine = `<rect x="470" y="30" width="18" height="398" fill="${C.gear}"/>`
            + line([290, 72], [480, 72], C.gearLight, 12)
            + line([478, pad + 20], [400, pad + 20], C.gear, 14)
            + `<rect x="290" y="${pad}" width="132" height="20" rx="10" fill="${C.accent}"/>`;
        const body = up
            ? { head: [372, 58], neck: [362, 100], hip: [362, 212], elbow: [330, 128], hand: [352, 74],
                knee: [376, 284], foot: [296, 296] }
            : { head: [372, 138], neck: [360, 172], hip: [360, 282], elbow: [352, 122], hand: [352, 74],
                knee: [374, 354], foot: [294, 366] };

        return machine + figure(body);
    }),
    'hip-thrust': [0, 1].map((f) => {
        const up = f === 1;
        const hip = up ? [342, 298] : [330, 380];
        const bench = `<rect x="130" y="300" width="130" height="30" rx="8" fill="${C.gear}"/>`
            + line([150, 330], [150, 428], C.gear, 12) + line([240, 330], [240, 428], C.gear, 12)
            + line([180, 410], [hip[0], hip[1] - 22], C.gearLight, 10);
        const padBar = `<rect x="${hip[0] - 40}" y="${hip[1] - 38}" width="80" height="22" rx="11" fill="${C.accent}"/>`;
        const body = up
            ? { head: [196, 284], neck: [232, 294], hip, elbow: [284, 318], hand: [316, 290], knee: [442, 298], foot: [462, 418] }
            : { head: [198, 282], neck: [232, 296], hip, elbow: [276, 336], hand: [306, 364], knee: [432, 318], foot: [462, 418] };

        return bench + figure(body) + padBar;
    }),
    'kickback-machine': [0, 1].map((f) => {
        const back = f === 1;
        const foot = back ? [584, 292] : [440, 342];
        const machine = `<rect x="160" y="160" width="20" height="268" fill="${C.gear}"/>`
            + `<rect x="236" y="186" width="26" height="80" rx="10" fill="${C.gearLight}" transform="rotate(-28 249 226)"/>`
            + line([170, 330], [foot[0] - 10, foot[1] + 18], C.gear, 12)
            + `<rect x="${foot[0] - 34}" y="${foot[1] + 6}" width="60" height="18" rx="9" fill="${C.accent}"/>`;
        const leg = back ? { knee: [504, 238], foot } : { knee: [388, 312], foot };

        return machine + figure({ head: [262, 150], neck: [298, 176], hip: [410, 246],
            elbow: [270, 222], hand: [228, 236], ...leg, farKnee: [412, 332], farFoot: [412, 418] });
    }),
    'air-bike': [0, 1].map((f) => {
        const a = f === 0;
        const fan = `<circle cx="570" cy="330" r="88" fill="none" stroke="${C.accent}" stroke-width="10"/>`
            + `<circle cx="570" cy="330" r="12" fill="${C.accent}"/>`
            + [0, 60, 120].map((d) => `<line x1="570" y1="258" x2="570" y2="402" stroke="${C.gearLight}" stroke-width="5" transform="rotate(${d + (a ? 0 : 30)} 570 330)"/>`).join('');
        const frame = path([[330, 250], [430, 372], [570, 330]], C.gear, 16)
            + line([400, 250], [430, 372], C.gear, 14)
            + `<rect x="300" y="236" width="86" height="18" rx="9" fill="${C.gearLight}"/>`
            + line([430, 372], [470, 428], C.gear, 14) + line([570, 330], [610, 428], C.gear, 14)
            + line([520, 290], a ? [470, 160] : [506, 158], C.gearLight, 10);
        const body = a
            ? { head: [454, 112], neck: [424, 146], hip: [350, 234],
                elbow: [446, 206], hand: [470, 166], farElbow: [470, 196], farHand: [506, 164],
                knee: [452, 262], foot: [470, 356], farKnee: [420, 300], farFoot: [398, 388] }
            : { head: [454, 112], neck: [424, 146], hip: [350, 234],
                elbow: [470, 196], hand: [506, 164], farElbow: [446, 206], farHand: [470, 166],
                knee: [420, 300], foot: [398, 388], farKnee: [452, 262], farFoot: [470, 356] };

        return frame + fan + figure(body);
    }),
    skierg: [0, 1].map((f) => {
        const up = f === 0;
        const hands = up ? [[452, 64], [466, 70]] : [[372, 322], [388, 318]];
        const tower = `<rect x="518" y="30" width="30" height="398" rx="6" fill="${C.gear}"/>`
            + `<circle cx="512" cy="56" r="12" fill="${C.gearLight}"/>`
            + hands.map((h) => line([512, 56], h, C.accent, 3)).join('');
        const body = up
            ? { head: [392, 104], neck: [388, 142], hip: [384, 252], elbow: [418, 100], hand: hands[0],
                farElbow: [430, 106], farHand: hands[1], knee: [388, 334], foot: [384, 418] }
            : { head: [470, 180], neck: [438, 204], hip: [356, 272], elbow: [410, 268], hand: hands[0],
                farElbow: [420, 272], farHand: hands[1], knee: [400, 338], foot: [380, 418] };

        return tower + figure(body);
    }),
};
