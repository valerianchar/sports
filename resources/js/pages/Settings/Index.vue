<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { SwitchRoot, SwitchThumb } from 'reka-ui';
import { Head, Link } from '@inertiajs/vue3';
import Stepper from '../../components/Stepper.vue';
import TabBar from '../../components/TabBar.vue';
import { diagnoseAlerts, disableAlerts, enableAlerts, pushSupport, testAlert } from '../../alerts';
import { loadCustomSound, previewCountdown, setAudioMode } from '../../audio';
import { forgetOfflinePages } from '../../offline';
import { routes } from '../../routes';

/*
 * Les réglages : le son du lecteur, les alertes hors de l'appli, le départ
 * des séances, le compte. Une vraie page plutôt qu'une feuille : il y a de
 * quoi faire défiler.
 */

const page = usePage();
const user = computed(() => page.props.auth.user);

const form = useForm({
    sound: user.value.sound,
    prep_seconds: user.value.prep_seconds,
    countdown_seconds: user.value.countdown_seconds ?? 5,
    volume: user.value.volume ?? 80,
    countdown_sound: user.value.countdown_sound ?? 'bip',
    audio_mode: user.value.audio_mode ?? 'melange',
    warmup_sets: user.value.warmup_sets ?? true,
});

// Le mode s'entend tout de suite au « Tester », avant même d'enregistrer.
watch(() => form.audio_mode, (value) => setAudioMode(value));

/*
 * Alertes hors de l'appli : l'état de ce téléphone (abonné ou non), relu à
 * chaque ouverture des réglages.
 */
const alerts = ref({ support: 'unsupported', on: false, busy: false, message: null, diagnosis: null });

async function diagnose() {
    const diagnosis = await diagnoseAlerts();
    alerts.value = { ...alerts.value, support: diagnosis.support, on: diagnosis.local && diagnosis.server !== false, diagnosis };
}

onMounted(async () => {
    if (page.props.push_public_key) {
        await diagnose();
    }
});

// Ce qui a échoué, dit simplement.
const failures = {
    denied: 'Notifications refusées : autorise-les dans les Réglages de l’iPhone, rubrique Notifications, puis Séance.',
    worker: 'L’appli n’a pas pu préparer les notifications. Ferme-la complètement, rouvre-la depuis son icône, puis réessaie.',
    subscribe: 'Le téléphone a refusé l’abonnement. Vérifie que Séance est bien ouverte depuis son icône sur l’écran d’accueil.',
    server: 'Le serveur n’a pas enregistré ce téléphone. Réessaie dans un instant.',
};

async function toggleAlerts() {
    alerts.value.busy = true;
    alerts.value.message = null;

    try {
        if (alerts.value.on) {
            await disableAlerts();
            alerts.value.on = false;
        } else {
            await enableAlerts(page.props.push_public_key);
            alerts.value.on = true;
            await testAlert();
            alerts.value.message = 'Activées : une notification d’essai vient de partir.';
        }
    } catch (error) {
        alerts.value.support = pushSupport();
        alerts.value.message = `${failures[error.step] ?? 'Impossible d’activer les alertes pour le moment. Réessaie.'}${error.detail ? ` (${error.detail})` : ''}`;
    } finally {
        alerts.value.busy = false;
        await diagnose();
    }
}

async function sendTest() {
    alerts.value.message = null;

    try {
        const { sent } = await testAlert();
        alerts.value.message = sent ? 'Notification envoyée : elle arrive dans quelques secondes.' : 'Aucun téléphone abonné : réactive les alertes.';
    } catch {
        alerts.value.message = 'Envoi impossible pour le moment.';
    }
}

/* Entendre le réglage tout de suite : trois secondes de décompte et la reprise. */
async function test() {
    if (form.countdown_sound === 'perso') {
        await loadCustomSound(user.value.custom_sound_url);
    }

    previewCountdown(form.countdown_sound, form.volume / 100);
}

function chooseSound(value) {
    if (value === 'perso' && !user.value.custom_sound_url) {
        fileInput.value?.click();

        return;
    }

    form.countdown_sound = value;
    test();
}

// « Mon son » : le fichier part tout de suite, puis devient le son choisi.
const fileInput = ref(null);
const uploading = ref(false);
const uploadError = computed(() => page.props.errors.sound);

function upload(event) {
    const file = event.target.files?.[0];
    event.target.value = '';

    if (!file) {
        return;
    }

    router.post(routes.preferences + '/son', { sound: file }, {
        forceFormData: true,
        preserveScroll: true,
        preserveState: true,
        onStart: () => (uploading.value = true),
        onFinish: () => (uploading.value = false),
        onSuccess: () => {
            form.countdown_sound = 'perso';
            test();
        },
    });
}

function removeSound() {
    router.delete(routes.preferences + '/son', {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => (form.countdown_sound = 'bip'),
    });
}

function save() {
    form.put(routes.preferences, { preserveScroll: true });
}

function logout() {
    forgetOfflinePages();
    router.post(routes.logout);
}
</script>

<template>
    <Head title="Réglages" />

    <div class="no-scrollbar flex flex-1 flex-col gap-4 overflow-y-auto px-5 pt-3 pb-[120px]">
        <header class="flex items-center gap-3">
            <Link :href="routes.home" class="iconbtn size-11" aria-label="Retour">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M15 5l-7 7 7 7" /></svg>
            </Link>
            <h1 class="display text-[40px] leading-none font-extrabold">Réglages</h1>
        </header>

        <h2 class="mt-2 px-1 text-[11px] font-extrabold tracking-[0.12em] text-text-muted uppercase">Lecteur</h2>
        <div class="flex flex-col gap-5 rounded-[22px] bg-surface p-4">
            <label class="flex cursor-pointer items-center justify-between gap-4">
                <span class="flex flex-col gap-0.5">
                    <span class="text-[15px] font-bold">Bips et vibrations</span>
                    <span class="text-[12.5px] font-medium text-text-muted">Début d'effort, repos, fin de repos</span>
                </span>
                <SwitchRoot
                    v-model="form.sound"
                    class="relative h-7 w-12 shrink-0 rounded-full transition-colors"
                    :class="form.sound ? 'bg-accent' : 'bg-surface-3'"
                    aria-label="Bips et vibrations"
                >
                    <SwitchThumb
                        class="absolute top-1 block size-5 rounded-full transition-[left]"
                        :class="form.sound ? 'left-6 bg-on-accent' : 'left-1 bg-text-muted'"
                    />
                </SwitchRoot>
            </label>

            <div v-if="form.sound" class="flex flex-col gap-1.5">
                <span class="text-[10.5px] font-extrabold tracking-[0.1em] text-text-muted uppercase">Bips avant la fin d'un repos</span>
                <div class="grid grid-cols-4 gap-1.5" role="radiogroup" aria-label="Bips avant la fin d'un repos">
                    <button
                        v-for="choice in [0, 3, 5, 10]"
                        :key="choice"
                        type="button"
                        role="radio"
                        :aria-checked="form.countdown_seconds === choice"
                        class="h-10 rounded-xl text-[13px] font-bold"
                        :class="form.countdown_seconds === choice ? 'bg-text text-bg' : 'bg-surface-2 text-text-soft'"
                        @click="form.countdown_seconds = choice"
                    >
                        {{ choice ? `${choice} s` : 'Aucun' }}
                    </button>
                </div>
            </div>

            <div v-if="form.sound" class="flex flex-col gap-2">
                <span class="text-[10.5px] font-extrabold tracking-[0.1em] text-text-muted uppercase">Son du décompte</span>
                <div class="grid grid-cols-3 gap-1.5" role="radiogroup" aria-label="Son du décompte">
                    <button
                        v-for="choice in page.props.countdown_sounds"
                        :key="choice.value"
                        type="button"
                        role="radio"
                        :aria-checked="form.countdown_sound === choice.value"
                        class="h-11 truncate rounded-xl px-2 text-[13px] font-bold"
                        :class="form.countdown_sound === choice.value ? 'bg-accent text-on-accent' : 'bg-surface-2 text-text-soft'"
                        @click="chooseSound(choice.value)"
                    >
                        {{ choice.value === 'perso' && !user.custom_sound_url ? '+ Mon son' : choice.label }}
                    </button>
                </div>
                <div class="flex items-center justify-between gap-3 rounded-xl bg-bg px-3 py-2.5">
                    <span class="min-w-0 truncate text-[12.5px] font-medium text-text-muted">
                        <template v-if="uploading">Envoi du fichier…</template>
                        <template v-else-if="user.custom_sound_url">Mon son : {{ user.custom_sound_name }}</template>
                        <template v-else>Ton propre son : mp3, m4a, wav ou ogg, 2 Mo max.</template>
                    </span>
                    <span class="flex shrink-0 gap-3">
                        <button type="button" class="text-[13px] font-extrabold text-accent" :disabled="uploading" @click="fileInput?.click()">
                            {{ user.custom_sound_url ? 'Remplacer' : 'Choisir un fichier' }}
                        </button>
                        <button v-if="user.custom_sound_url" type="button" class="text-[13px] font-bold text-danger" @click="removeSound">Supprimer</button>
                    </span>
                    <input ref="fileInput" type="file" accept="audio/*,.mp3,.m4a,.wav,.ogg,.aac" class="hidden" @change="upload" />
                </div>
                <p v-if="uploadError" class="text-[13px] text-danger">{{ uploadError }}</p>
                <p v-else class="text-[12px] font-medium text-text-faint">
                    Un son court sonne à chaque seconde ; un son long (une phrase enregistrée) sonne une fois au début du décompte.
                </p>
            </div>

            <div v-if="form.sound" class="flex flex-col gap-2">
                <div class="flex items-baseline justify-between">
                    <span class="text-[10.5px] font-extrabold tracking-[0.1em] text-text-muted uppercase">Volume des sons</span>
                    <span class="font-display text-[20px] font-bold tabular-nums">{{ form.volume }} %</span>
                </div>
                <div class="flex items-center gap-3">
                    <input
                        v-model.number="form.volume"
                        type="range"
                        min="10"
                        max="100"
                        step="10"
                        aria-label="Volume des sons"
                        class="h-6 flex-1 cursor-pointer accent-[var(--color-accent)]"
                    />
                    <button type="button" class="h-10 shrink-0 rounded-full bg-surface-2 px-4 text-[13px] font-extrabold text-accent" @click="test">
                        Tester
                    </button>
                </div>
                <p class="text-[12px] font-medium text-text-faint">Le volume du téléphone s'applique aussi : monte-le pendant la séance.</p>
            </div>

            <div v-if="form.sound" class="flex flex-col gap-2">
                <span class="text-[10.5px] font-extrabold tracking-[0.1em] text-text-muted uppercase">Bips et musique</span>
                <div class="grid grid-cols-2 gap-1.5" role="radiogroup" aria-label="Bips et musique">
                    <button
                        v-for="choice in page.props.audio_modes"
                        :key="choice.value"
                        type="button"
                        role="radio"
                        :aria-checked="form.audio_mode === choice.value"
                        class="h-11 rounded-xl px-2 text-[13px] font-bold"
                        :class="form.audio_mode === choice.value ? 'bg-accent text-on-accent' : 'bg-surface-2 text-text-soft'"
                        @click="form.audio_mode = choice.value"
                    >
                        {{ choice.label }}
                    </button>
                </div>
                <p class="text-[12px] font-medium text-text-faint">
                    {{ page.props.audio_modes.find((m) => m.value === form.audio_mode)?.description }}
                    <template v-if="form.countdown_sound === 'voix'"> La voix de l'iPhone coupe la musique dans tous les cas : préfère un bip.</template>
                </p>
            </div>

            <label class="flex cursor-pointer items-center justify-between gap-4">
                <span class="flex flex-col gap-0.5">
                    <span class="text-[15px] font-bold">Séries d'échauffement</span>
                    <span class="text-[12.5px] font-medium text-text-muted">Avant la première série lourde (30 kg et plus) : 10 reps à 50 %, puis 5 à 70 %. Elles ne comptent pas dans les statistiques.</span>
                </span>
                <SwitchRoot
                    v-model="form.warmup_sets"
                    class="relative h-7 w-12 shrink-0 rounded-full transition-colors"
                    :class="form.warmup_sets ? 'bg-accent' : 'bg-surface-3'"
                    aria-label="Séries d'échauffement"
                >
                    <SwitchThumb class="absolute top-1 block size-5 rounded-full transition-[left]" :class="form.warmup_sets ? 'left-6 bg-on-accent' : 'left-1 bg-text-muted'" />
                </SwitchRoot>
            </label>

            <Stepper
                label="Compte à rebours avant la séance"
                :display="form.prep_seconds === 0 ? 'Aucun' : `${form.prep_seconds} s`"
                :can-decrease="form.prep_seconds > 0"
                :can-increase="form.prep_seconds < 15"
                @decrease="form.prep_seconds--"
                @increase="form.prep_seconds++"
            />
        </div>

        <h2 v-if="page.props.push_public_key" class="mt-2 px-1 text-[11px] font-extrabold tracking-[0.12em] text-text-muted uppercase">Hors de l'appli</h2>
        <div v-if="page.props.push_public_key" class="flex flex-col gap-2.5 rounded-[22px] bg-surface p-4">
            <span class="flex flex-col gap-0.5">
                <span class="text-[15px] font-bold">Alertes hors de l'appli</span>
                <span class="text-[12.5px] font-medium text-text-muted">
                    Écran verrouillé ou autre appli ouverte : une notification sonne à la fin de chaque repos, par-dessus ta musique.
                </span>
            </span>
            <p v-if="alerts.support === 'install'" class="rounded-xl bg-bg px-3 py-2.5 text-[12.5px] font-semibold text-text-soft">
                Sur iPhone, ajoute d'abord Séance à l'écran d'accueil (bouton Partager, puis « Sur l'écran d'accueil »), puis ouvre-la depuis son icône.
            </p>
            <p v-else-if="alerts.support === 'denied'" class="rounded-xl bg-bg px-3 py-2.5 text-[12.5px] font-semibold text-prep">
                Notifications refusées : autorise-les dans les Réglages du téléphone, rubrique Notifications, puis Séance.
            </p>
            <p v-else-if="alerts.support === 'unsupported'" class="text-[12.5px] font-semibold text-text-muted">Ce navigateur ne reçoit pas de notifications.</p>
            <div v-else class="flex gap-2">
                <button
                    type="button"
                    class="h-11 flex-1 rounded-full text-[14px] font-extrabold"
                    :class="alerts.on ? 'bg-surface-2 text-text-soft' : 'bg-accent text-on-accent'"
                    :disabled="alerts.busy"
                    @click="toggleAlerts"
                >
                    {{ alerts.busy ? '…' : alerts.on ? 'Désactiver' : 'Activer les alertes' }}
                </button>
                <button v-if="alerts.on" type="button" class="h-11 rounded-full bg-surface-2 px-4 text-[14px] font-extrabold text-accent" @click="sendTest">Tester</button>
            </div>
            <p v-if="alerts.message" class="text-[12.5px] font-semibold text-text-soft" aria-live="polite">{{ alerts.message }}</p>
            <!-- L'état réel, pour comprendre ce qui coince sans deviner. -->
            <ul v-if="alerts.diagnosis" class="flex flex-col gap-1 rounded-xl bg-bg px-3 py-2.5 text-[12px] font-semibold text-text-muted" aria-label="État des alertes">
                <li :class="alerts.diagnosis.standalone ? 'text-accent' : 'text-prep'">{{ alerts.diagnosis.standalone ? '✓' : '✗' }} Ouverte depuis l'icône de l'écran d'accueil</li>
                <li :class="alerts.diagnosis.permission === 'granted' ? 'text-accent' : 'text-prep'">
                    {{ alerts.diagnosis.permission === 'granted' ? '✓ Notifications autorisées' : alerts.diagnosis.permission === 'denied' ? '✗ Notifications refusées' : '… Notifications pas encore autorisées' }}
                </li>
                <li :class="alerts.diagnosis.local ? 'text-accent' : 'text-prep'">{{ alerts.diagnosis.local ? '✓' : '✗' }} Téléphone abonné</li>
                <li v-if="alerts.diagnosis.local" :class="alerts.diagnosis.server ? 'text-accent' : 'text-prep'">{{ alerts.diagnosis.server ? '✓' : '✗' }} Abonnement reçu par le serveur</li>
            </ul>
        </div>

        <h2 class="mt-2 px-1 text-[11px] font-extrabold tracking-[0.12em] text-text-muted uppercase">Compte</h2>
        <div class="flex items-center justify-between gap-3 rounded-[22px] bg-surface p-4">
            <span class="flex min-w-0 flex-col">
                <span class="truncate text-[15px] font-bold">{{ user.name }}</span>
                <span class="truncate text-[12.5px] font-medium text-text-muted">{{ user.email }}</span>
            </span>
            <button type="button" class="h-10 shrink-0 rounded-full bg-surface-2 px-4 text-[13px] font-extrabold text-danger" @click="logout">Se déconnecter</button>
        </div>
    </div>

    <!-- Enregistrer reste à portée, au-dessus de la barre d'onglets. -->
    <div v-if="form.isDirty" class="pointer-events-none absolute inset-x-0 bottom-[calc(env(safe-area-inset-bottom)+74px)] px-5">
        <button type="button" class="btn-accent pointer-events-auto h-14 w-full text-[22px] shadow-[0_8px_24px_rgb(0_0_0/0.5)]" :disabled="form.processing" @click="save">
            Enregistrer
        </button>
    </div>

    <TabBar active="home" />
</template>
