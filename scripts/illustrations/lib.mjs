/*
 * Boîte à outils des illustrations d'exercices : un pictogramme de profil sur un
 * cadre 720 × 480 (le format 3:2 des photos), sol à y = 428. Un dessin est une
 * paire de chaînes SVG — position de départ, position d'arrivée — que la fiche
 * fait alterner.
 *
 * Code couleur : le corps en blanc cassé, les membres du fond en gris ; le
 * matériel en gris sombre ; en citron, la seule pièce qui bouge avec le
 * mouvement (bras de levier, poignée, charge, câble).
 */

export const C = {
    bg: '#1A1B17',
    floor: '#2E302A',
    gear: '#3A3D35',
    gearLight: '#6E7068',
    accent: '#D4FF3A',
    near: '#F2F3EE',
    far: '#8A8C82',
};

export const FLOOR_Y = 428;

export const line = (a, b, color = C.gear, w = 12) =>
    `<line x1="${a[0]}" y1="${a[1]}" x2="${b[0]}" y2="${b[1]}" stroke="${color}" stroke-width="${w}" stroke-linecap="round"/>`;

export const path = (pts, color = C.gear, w = 12) =>
    `<polyline points="${pts.map((p) => p.join(',')).join(' ')}" fill="none" stroke="${color}" stroke-width="${w}" stroke-linecap="round" stroke-linejoin="round"/>`;

export const rect = (x, y, w, h, color = C.gear, r = 8, rotate = null) =>
    `<rect x="${x}" y="${y}" width="${w}" height="${h}" rx="${r}" fill="${color}"${rotate ? ` transform="rotate(${rotate[0]} ${rotate[1]} ${rotate[2]})"` : ''}/>`;

export const circle = (c, r, color = C.gear) => `<circle cx="${c[0]}" cy="${c[1]}" r="${r}" fill="${color}"/>`;

export const ring = (c, r, color = C.accent, w = 8) =>
    `<circle cx="${c[0]}" cy="${c[1]}" r="${r}" fill="none" stroke="${color}" stroke-width="${w}"/>`;

/**
 * Un corps de profil. Points attendus : head, neck, hip, elbow, hand, knee,
 * foot ; et, pour les membres du fond, farElbow, farHand, farKnee, farFoot
 * (à défaut, ils se confondent avec ceux du premier plan). Tête de rayon 23,
 * tronc de 34 d'épaisseur : garder neck–hip ≈ 110, cuisse ≈ 82, tibia ≈ 84,
 * bras ≈ 55 + avant-bras ≈ 55.
 */
export function figure(p) {
    const far = [
        path([p.neck, p.farElbow ?? p.elbow, p.farHand ?? p.hand], C.far, 20),
        path([p.hip, p.farKnee ?? p.knee, p.farFoot ?? p.foot], C.far, 22),
    ];
    const near = [
        line(p.neck, p.hip, C.near, 34),
        path([p.hip, p.knee, p.foot], C.near, 23),
        path([p.neck, p.elbow, p.hand], C.near, 20),
        `<circle cx="${p.head[0]}" cy="${p.head[1]}" r="23" fill="${C.near}"/>`,
    ];

    return [...far, ...near].join('');
}

export const floor = `<line x1="40" y1="${FLOOR_Y}" x2="680" y2="${FLOOR_Y}" stroke="${C.floor}" stroke-width="4" stroke-linecap="round"/>`;

export const shadow = (x, w = 110) => `<ellipse cx="${x}" cy="430" rx="${w / 2}" ry="7" fill="${C.floor}"/>`;

/** Tapis de sol entre x1 et x2. */
export const mat = (x1 = 70, x2 = 650) => rect(x1, 414, x2 - x1, 12, C.gear, 6);

/** Banc plat : assise de hauteur `top` (y du dessus), de x1 à x2, sur deux pieds. */
export const bench = (x1, x2, top = 330) =>
    rect(x1, top, x2 - x1, 26, C.gear, 8) + line([x1 + 20, top + 26], [x1 + 20, FLOOR_Y]) + line([x2 - 20, top + 26], [x2 - 20, FLOOR_Y]);

/** Dossier incliné : de `from` (bas) à `to` (haut), épaisseur 26. */
export const backrest = (from, to, color = C.gear) => line(from, to, color, 26);

/** Montant vertical de machine. */
export const post = (x, top = 40, w = 20) => rect(x - w / 2, top, w, FLOOR_Y - top, C.gear, 6);

/** Coussin (siège, appui) : petit rectangle arrondi. */
export const pad = (x, y, w = 70, h = 22, color = C.gearLight, rotate = null) => rect(x, y, w, h, color, h / 2, rotate);

/** Poignée : un rond citron dans la main. */
export const handle = (c, r = 11) => circle(c, r, C.accent);

/** Câble tendu d'une poulie à une main. */
export const cable = (from, to) => line(from, to, C.accent, 3) + circle(from, 11, C.gearLight);

/** Haltère vu de profil : un disque de chaque côté de la main. */
export const dumbbell = (c) => rect(c[0] - 26, c[1] - 12, 52, 24, C.accent, 10);

/** Barre vue de bout : un disque. */
export const plate = (c, r = 34) => circle(c, r, C.accent) + circle(c, 8, C.bg);

export const kettlebell = (c) => circle([c[0], c[1] + 16], 20, C.accent) + ring([c[0], c[1] - 2], 11, C.accent, 6);

export const ball = (c, r = 30) => circle(c, r, C.accent);

/** Box de saut, posée au sol. */
export const box = (x, w = 150, h = 110) => rect(x, FLOOR_Y - h, w, h, C.gear, 6);

/** Mur vertical à l'abscisse x. */
export const wall = (x) => rect(x - 8, 40, 16, FLOOR_Y - 40, C.gear, 4);

export function svg(content, { withFloor = true } = {}) {
    return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 720 480" width="720" height="480">`
        + `<rect width="720" height="480" fill="${C.bg}"/>${withFloor ? floor : ''}${content}</svg>\n`;
}
