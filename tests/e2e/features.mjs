/*
 * Parcours dans un vrai navigateur (Chrome sans écran, Puppeteer), contre une
 * application qui tourne : BASE=http://localhost:8091 node tests/e2e/features.mjs
 * La CI les joue avant chaque déploiement ; ils créent leurs propres comptes.
 */
import puppeteer from 'puppeteer';
const BASE = process.env.BASE || 'http://localhost:8091';
const b = await puppeteer.launch({ args: ['--no-sandbox'] }); const p = await b.newPage();
await p.setViewport({ width: 390, height: 844, deviceScaleFactor: 2, isMobile: true, hasTouch: true });
const errors = []; p.on('pageerror', (e) => errors.push(e.message)); p.on('console', (m) => m.type() === 'error' && !/net::ERR_INTERNET_DISCONNECTED|Failed to fetch|ERR_FAILED/.test(m.text()) && errors.push(m.text()));
p.on('response', (r) => r.status() >= 500 && errors.push(r.status() + ' ' + r.url()));
const wait = (ms) => new Promise((r) => setTimeout(r, ms));
const has = (t, sel = '*') => p.evaluate((t, sel) => [...document.querySelectorAll(sel)].some((e) => e.textContent.replace(/\s+/g, ' ').includes(t) && e.offsetParent !== null), t, sel);
const clickText = async (t, sel = 'button, a') => { const ok = await p.evaluate((t, sel) => { const e = [...document.querySelectorAll(sel)].find((e) => e.textContent.replace(/\s+/g, ' ').trim().startsWith(t) && e.offsetParent !== null); e?.click(); return !!e; }, t, sel); if (!ok) throw new Error('introuvable : ' + t); await wait(600); };
const clickLabel = async (l) => { const ok = await p.evaluate((l) => { const e = [...document.querySelectorAll('[aria-label]')].find((e) => e.getAttribute('aria-label').startsWith(l) && e.offsetParent !== null); e?.click(); return !!e; }, l); if (!ok) throw new Error('introuvable : ' + l); await wait(600); };
const text = (sel) => p.$eval(sel, (x) => x.textContent.replace(/\s+/g, ' ').trim()).catch(() => null);
const out = {};
const step = async (name, fn) => { try { out[name] = (await fn()) ?? 'ok'; } catch (e) { out[name] = 'ÉCHEC ' + e.message; await p.screenshot({ path: `${process.env.SHOTS ?? '/w'}/f-${name}.png` }); } };
const email = `kpi${Date.now()}@exemple.fr`;
await p.goto(BASE + '/inscription'); await p.type('input[autocomplete="given-name"]', 'Valérian'); await p.type('input[type=email]', email); await p.type('input[type=password]', 'motdepasse');
await clickText('Créer'); await p.waitForFunction(() => location.pathname === '/'); await wait(800);
await p.evaluate(async () => {
  const xsrf = decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)[1]);
  const items = [
    { exercise: 'squat', mode: 'reps', value: 8, sets: 2, rest_sets: 5, rest_after: 5, weight: 60 },
    { exercise: 'curl-barre', mode: 'reps', value: 10, sets: 2, rest_sets: 5, rest_after: 5, superset: true },
    { exercise: 'extension-triceps-poulie', mode: 'reps', value: 12, sets: 2, rest_sets: 6, rest_after: 5 },
  ];
  await fetch('/seances', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', Accept: 'text/html', 'X-XSRF-TOKEN': xsrf }, body: JSON.stringify({ name: 'Complète', items, }) });
});
await step('programme', async () => {
  await p.goto(BASE + '/programme', { waitUntil: 'networkidle0' });
  const today = await p.evaluate(() => (new Date().getDay() + 6) % 7);
  await p.evaluate((i) => { const s = document.querySelectorAll('section select')[i]; s.value = s.options[1].value; s.dispatchEvent(new Event('change')); }, today); await wait(300);
  await p.evaluate((i) => { const input = document.querySelectorAll('section')[i + 1].querySelector('input[type=time]'); input.value = '18:30'; input.dispatchEvent(new Event('input')); }, today); await wait(200);
  await clickLabel('Objectif de séances par semaine : plus'); await clickLabel('Objectif de séances par semaine : plus');
  await clickText('Enregistrer'); await wait(1200);
  await p.goto(BASE + '/', { waitUntil: 'networkidle0' }); await wait(500);
    return { plan: await has("Au programme aujourd'hui · 18:30"), goal: await has('0 / 2 séances') };
});
await step('lecteur', async () => {
  await p.goto(BASE + '/seances', { waitUntil: 'networkidle0' });
  await clickText('Lancer'); await p.waitForFunction(() => location.pathname.endsWith('/lancer')); await wait(6500);
  const warm1 = [await text('main .eyebrow'), await has('Barre 20 kg + 5 de chaque côté', 'p')];
    await clickText('Série terminée'); await wait(400);
  const rest = [await text('main .eyebrow'), await has('Reprise à', 'span')];
  await clickText('Passer'); await wait(300);
  const warm2 = await text('main .eyebrow');
  await clickText('Série terminée'); await clickText('Passer');
  const set1 = [await text('main .eyebrow'), await has('Barre 20 kg + 20 de chaque côté', 'p')];
  await clickText('Série terminée'); await clickText('Passer'); await clickText('Série terminée'); await clickText('Passer');
  const ss = []; for (let i = 0; i < 3; i++) { ss.push(await text('main .eyebrow')); await clickText('Série terminée'); await wait(300); if (await has('Passer', 'button')) await clickText('Passer'); }
  return { warm1, rest, warm2, set1, ss };
});
await step('partage', async () => {
  await p.goto(BASE + '/seances', { waitUntil: 'networkidle0' });
  await clickLabel('Autres actions'); await clickText('Partager', '[role=dialog] button');
  await p.type('#share-email', 'ami@exemple.fr'); await clickText('Envoyer', '[role=dialog] button'); await wait(1200);
  const mail = await has('Envoyée à ami@exemple.fr');
  await clickText('Retour', '[role=dialog] button'); await clickText('Dupliquer', '[role=dialog] button');
  await p.waitForFunction(() => location.pathname.endsWith('/modifier')); await wait(500);
  return { mail, copy: await p.$eval('input[aria-label="Nom de la séance"]', (i) => i.value) };
});
await step('journal', async () => {
  await p.goto(BASE + '/journal', { waitUntil: 'networkidle0' }); await wait(400);
  return await text('h1');
});
await step('horsligne', async () => {
  await p.evaluate(() => localStorage.removeItem('seance.offline.warmed'));
  await p.goto(BASE + '/', { waitUntil: 'networkidle0' }); await wait(4000);
  await p.goto(BASE + '/', { waitUntil: 'networkidle0' }); await wait(4000);
  await p.setOfflineMode(true);
  await clickText('Séances', 'nav a'); await wait(1500);
  const list = await text('h1');
  await clickText('Lancer'); await wait(2500);
  const player = [new URL(p.url()).pathname, await text('main .eyebrow'), await has('Hors ligne')];
  await p.reload({ waitUntil: 'domcontentloaded' }).catch(() => null); await wait(1500);
  const reload = await text('main .eyebrow');
  await p.setOfflineMode(false);
  return { list, player, reload };
});
console.log(JSON.stringify({ ...out, errors }, null, 1)); await b.close();

// La CI échoue si une étape a échoué ou si la page a levé une erreur.
const failed = Object.entries(out).filter(([, value]) => String(value).startsWith('ÉCHEC')).map(([name]) => name);

if (failed.length || errors.length) {
    console.error(`Échec : ${[...failed, ...errors].join(' ; ')}`);
    process.exitCode = 1;
}
