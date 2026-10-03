import { postJson } from './http';

/**
 * Journaux de séance pas encore arrivés au serveur. Une salle de sport au
 * sous-sol n'a souvent pas de réseau : la séance terminée est gardée sur le
 * téléphone et renvoyée au prochain passage. Chaque journal porte son
 * identifiant — le serveur ne le compte qu'une fois.
 */
const KEY = 'seance.pending-logs.v1';

function read() {
    try {
        return JSON.parse(localStorage.getItem(KEY)) ?? [];
    } catch {
        return [];
    }
}

function write(logs) {
    try {
        localStorage.setItem(KEY, JSON.stringify(logs));
    } catch {
        // Navigation privée pleine : le journal se perdra si l'envoi échoue.
    }
}

/**
 * Envoie le journal d'une séance et rend la réponse du serveur — les records
 * battus — ou null s'il n'a pas pu partir : il est alors gardé et renvoyé plus
 * tard.
 */
export async function sendLog(url, payload) {
    write([...read().filter((log) => log.payload.client_id !== payload.client_id), { url, payload }]);

    try {
        const response = await postJson(url, payload);
        write(read().filter((log) => log.payload.client_id !== payload.client_id));
        flushPendingLogs();

        return response;
    } catch {
        return null;
    }
}

/** Renvoie ce qui attend. Rend true si plus rien n'attend. */
export async function flushPendingLogs() {
    const remaining = [];

    for (const log of read()) {
        try {
            await postJson(log.url, log.payload);
        } catch (error) {
            // Refusé pour de bon — séance supprimée (404), pas la sienne (403), données
            // invalides (422) : inutile d'insister. Le reste (réseau, session
            // expirée, serveur en redémarrage) se retentera.
            if (![403, 404, 422].includes(error.status)) {
                remaining.push(log);
            }
        }
    }

    write(remaining);

    return remaining.length === 0;
}
