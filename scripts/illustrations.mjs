/*
 * Illustrations des exercices absents des banques de photos libres : un
 * pictogramme en deux positions (départ, arrivée), au même format 3:2 que les
 * photos, pour que la fiche les fasse alterner de la même façon.
 *
 * Chaque fichier de scripts/illustrations/ (hors lib.mjs) exporte par défaut
 * un objet { slug: [départ, arrivée] } ; les outils de dessin sont dans lib.mjs.
 *
 *   node scripts/illustrations.mjs            → public/images/exercices/<slug>/{0,1}.svg
 *   node scripts/illustrations.mjs <slug>…    → seulement ceux-là
 */
import fs from 'node:fs';
import { svg } from './illustrations/lib.mjs';

const dir = new URL('./illustrations/', import.meta.url);
const drawings = {};

for (const file of fs.readdirSync(dir).filter((f) => f.endsWith('.mjs') && f !== 'lib.mjs').sort()) {
    let set;

    try {
        ({ default: set } = await import(new URL(file, dir)));
    } catch (error) {
        // Aperçu pendant qu'un autre fichier est en chantier : on l'ignore.
        if (!process.env.ILLUSTRATIONS_TOLERANT) {
            throw error;
        }

        console.error(`${file} ignoré : ${error.message}`);
        continue;
    }

    for (const slug of Object.keys(set)) {
        if (drawings[slug]) {
            throw new Error(`Illustration en double : ${slug} (${file})`);
        }
    }

    Object.assign(drawings, set);
}

const only = process.argv.slice(2);

for (const [slug, frames] of Object.entries(drawings)) {
    if (only.length && !only.includes(slug)) {
        continue;
    }

    const out = new URL(`../public/images/exercices/${slug}/`, import.meta.url);
    fs.mkdirSync(out, { recursive: true });
    frames.forEach((content, i) => fs.writeFileSync(new URL(`${i}.svg`, out), svg(content)));
}

console.log(`${only.length || Object.keys(drawings).length} illustrations écrites.`);
