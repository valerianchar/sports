/*
 * Parcours dans un vrai navigateur (Chrome sans écran, Puppeteer), contre une
 * application qui tourne : BASE=http://localhost:8091 node tests/e2e/regression.mjs
 * La CI les joue avant chaque déploiement ; ils créent leurs propres comptes.
 */
import puppeteer from 'puppeteer';
const BASE = process.env.BASE || 'http://localhost:8091';
const b = await puppeteer.launch({ args: ['--no-sandbox'] }); const p = await b.newPage();
await p.setViewport({ width: 390, height: 844, deviceScaleFactor: 2, isMobile: true, hasTouch: true });
const errors = []; p.on('pageerror', (e) => errors.push(e.message)); p.on('console', (m) => m.type() === 'error' && errors.push(m.text()));
p.on('response', (r) => r.status() >= 500 && errors.push(r.status() + ' ' + r.url()));
const wait = (ms) => new Promise((r) => setTimeout(r, ms));
const has = (t, sel = 'button, a') => p.evaluate((t, sel) => [...document.querySelectorAll(sel)].some((e) => e.textContent.replace(/\s+/g, ' ').trim().includes(t) && e.offsetParent !== null), t, sel);
const clickText = async (t, sel = 'button, a') => { const ok = await p.evaluate((t, sel) => { const e = [...document.querySelectorAll(sel)].find((e) => e.textContent.replace(/\s+/g, ' ').trim().startsWith(t) && e.offsetParent !== null); e?.click(); return !!e; }, t, sel); if (!ok) throw new Error('introuvable : ' + t); await wait(600); };
const clickLabel = async (l) => { const ok = await p.evaluate((l) => { const e = [...document.querySelectorAll('[aria-label]')].find((e) => e.getAttribute('aria-label').startsWith(l) && e.offsetParent !== null); e?.click(); return !!e; }, l); if (!ok) throw new Error('introuvable : ' + l); await wait(600); };
const h1 = () => p.$eval('h1', (h) => h.textContent.replace(/\s+/g, ' ').trim()).catch(() => null);
const out = {};
const step = async (name, fn) => { try { out[name] = (await fn()) ?? 'ok'; } catch (e) { out[name] = 'ÉCHEC ' + e.message; await p.screenshot({ path: `${process.env.SHOTS ?? '/w'}/x-${name}.png` }); } };

const email = `kpi${Date.now()}@exemple.fr`;
await step('inscription', async () => {
  await p.goto(BASE + '/inscription'); await p.type('input[autocomplete="given-name"]', 'Valérian'); await p.type('input[type=email]', email); await p.type('input[type=password]', 'motdepasse');
  await clickText('Créer'); await p.waitForFunction(() => location.pathname === '/'); await wait(800);
  return [await h1(), await has('Composer une séance', 'h2')];
});
await step('assistant', async () => {
  await clickText('Muscu'); await p.waitForFunction(() => location.pathname === '/seances/assistant'); await wait(500);
  await clickText('Suivant'); await clickText('Push'); await clickText('Suivant'); await clickText('Proposer une séance');
  await p.waitForFunction(() => location.pathname.endsWith('/proposition')); await wait(800);
  const name = await h1();
  await clickLabel('Comment faire : '); const fiche = await p.$eval('[role=dialog][aria-modal=true][aria-label]', (d) => d.getAttribute('aria-label'));
  await p.evaluate(() => document.querySelector('[role=dialog][aria-modal=true][aria-label] button[aria-label="Fermer"]').click()); await wait(500);
  const zone = await p.$('section[aria-labelledby="zones-title"] button[aria-label*="non travaillé"]');
  if (zone) { await zone.click(); await wait(1200); await p.evaluate(() => document.querySelector('[role=dialog] button[aria-label^="Ajouter"]')?.click()); await wait(600); }
  await clickText('Enregistrer'); await p.waitForFunction(() => location.pathname === '/seances'); await wait(600);
  return { name, fiche, zoneAdded: Boolean(zone) };
});
await step('editeur', async () => {
  await clickLabel('Modifier '); await p.waitForFunction(() => location.pathname.endsWith('/modifier')); await wait(800);
  const rows = await p.$$eval('ol[aria-label="Exercices de la séance"] li', (l) => l.length);
  await clickLabel('Régler '); await clickLabel('Répétitions : plus'); const title = await p.$eval('[role=dialog] h2', (h) => h.textContent.trim());
  await clickText('OK', '[role=dialog] button'); await wait(400);
  await clickText('Compléter'); await clickText('Proposer', '[role=dialog] button'); await wait(1500);
  const proposed = await p.$$eval('[role=dialog] ul li', (l) => l.length);
  await clickText('Ajouter', '[role=dialog] button'); await wait(500);
  const after = await p.$$eval('ol[aria-label="Exercices de la séance"] li', (l) => l.length);
  await clickText('Enregistrer'); await p.waitForFunction(() => location.pathname === '/seances'); await wait(600);
  return { rows, title, proposed, after };
});
await step('lecteur', async () => {
  await clickText('Lancer'); await p.waitForFunction(() => location.pathname.endsWith('/lancer')); await wait(6500);
  const first = await p.$eval('main .eyebrow', (x) => x.textContent.trim());
  await clickText('Série terminée'); await wait(500);
  const rest = await p.$eval('main .eyebrow', (x) => x.textContent.trim());
  const restWeight = await has('Charge à venir', 'span');
  await clickLabel('Programme de la séance'); const programme = await p.$$eval('[role=dialog] ol li', (l) => l.length);
  await clickText('Plus tard', '[role=dialog] button').catch(() => null);
  await p.keyboard.press('Escape'); await wait(400);
  await p.goto(BASE + '/', { waitUntil: 'networkidle0' }); await wait(600);
  const resume = await has('Séance en cours', 'span');
  return { first, rest, restWeight, programme, resume };
});
await step('reglages', async () => {
  await clickLabel('Réglages et compte'); await p.waitForFunction(() => location.pathname === '/reglages'); await wait(800);
  await clickText('Comme une vidéo'); await clickText('Enregistrer'); await wait(1200);
  return { titre: await h1(), alertes: await has("Alertes hors de l'appli", 'span'), enregistre: !(await has('Enregistrer')) };
});
await step('cardio', async () => {
  await p.goto(BASE + '/seances/assistant?goal=cardio', { waitUntil: 'networkidle0' });
  await clickText('Fractionné'); await clickText('Suivant'); await clickText('Proposer une séance');
  await p.waitForFunction(() => location.pathname.endsWith('/proposition')); await wait(600);
  return await h1();
});
await step('progres', async () => {
  await p.goto(BASE + '/progres', { waitUntil: 'networkidle0' }); await wait(500);
  for (const tab of ['Exercices', 'Muscles', 'Corps', 'Aperçu']) await clickText(tab);
  return await h1();
});
await step('bibliotheque', async () => {
  await p.goto(BASE + '/exercices', { waitUntil: 'networkidle0' }); await wait(500);
  await p.type('input[type=search]', 'calf press'); await wait(500);
  return await p.$$eval('section button', (bs) => bs.map((x) => x.innerText.split('\n')[0]).slice(0, 2));
});
console.log(JSON.stringify({ ...out, errors }, null, 1)); await b.close();

// La CI échoue si une étape a échoué ou si la page a levé une erreur.
const failed = Object.entries(out).filter(([, value]) => String(value).startsWith('ÉCHEC')).map(([name]) => name);

if (failed.length || errors.length) {
    console.error(`Échec : ${[...failed, ...errors].join(' ; ')}`);
    process.exitCode = 1;
}
