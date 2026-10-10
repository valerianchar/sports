<script setup>
import { computed, ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import BottomSheet from './BottomSheet.vue';
import { postJson } from '../http';

/**
 * Les autres actions d'une séance : la modifier, la dupliquer, la partager
 * (par mail, par lien ou par une appli du téléphone), la supprimer — avec
 * confirmation : une séance supprimée ne revient pas, son historique reste.
 */
const props = defineProps({
    workout: { type: Object, default: null },
});

const emit = defineEmits(['close']);

// 'menu', 'share' ou 'delete'.
const view = ref('menu');
const email = ref('');
const sending = ref(false);
const message = ref(null);
const error = ref(null);
const link = ref(null);

const open = computed({
    get: () => props.workout !== null,
    set: (value) => {
        if (!value) {
            emit('close');
        }
    },
});

// Une autre séance : on repart du menu, sans les messages de la précédente.
watch(
    () => props.workout,
    () => {
        view.value = 'menu';
        email.value = '';
        message.value = null;
        error.value = null;
        link.value = null;
    },
);

const titles = computed(() => ({
    menu: props.workout?.name ?? '',
    share: 'Partager',
    delete: 'Supprimer ?',
}));

const descriptions = computed(() => ({
    menu: null,
    share: `« ${props.workout?.name} » : la personne l'ajoute d'un toucher à ses propres séances.`,
    delete: `« ${props.workout?.name} » disparaîtra. Les séances déjà faites restent dans ton historique.`,
}));

function duplicate() {
    router.post(props.workout.urls.duplicate, {}, { onFinish: () => emit('close') });
}

function destroy() {
    router.delete(props.workout.urls.destroy, { preserveScroll: true, onFinish: () => emit('close') });
}

async function sendMail() {
    sending.value = true;
    message.value = null;
    error.value = null;

    try {
        await postJson(props.workout.urls.share_mail, { email: email.value.trim() });
        message.value = `Envoyée à ${email.value.trim()}.`;
        email.value = '';
    } catch (failure) {
        error.value = failure.status === 422 ? 'Cette adresse est invalide ou refusée par le serveur de mail.' : failure.status === 429 ? 'Trop d’envois d’un coup : réessaie dans un moment.' : 'Envoi impossible pour le moment.';
    } finally {
        sending.value = false;
    }
}

/* Le lien : par les applis du téléphone (Messages, WhatsApp…) si possible, sinon copié. */
async function shareLink() {
    error.value = null;

    try {
        link.value ??= (await postJson(props.workout.urls.share, {})).url;
    } catch {
        error.value = 'Lien indisponible pour le moment.';

        return;
    }

    if (navigator.share) {
        await navigator.share({ title: props.workout.name, text: `Ma séance « ${props.workout.name} » sur Séance`, url: link.value }).catch(() => null);

        return;
    }

    await navigator.clipboard?.writeText(link.value).catch(() => null);
    message.value = 'Lien copié : colle-le où tu veux.';
}
</script>

<template>
    <BottomSheet v-model:open="open" :title="titles[view]" :description="descriptions[view]">
        <template v-if="props.workout && view === 'menu'">
            <Link :href="props.workout.urls.edit" class="btn-soft h-[54px] text-[15px] text-text!">Modifier la séance</Link>
            <button type="button" class="btn-soft h-[54px] text-[15px]" @click="duplicate">Dupliquer</button>
            <button type="button" class="btn-soft h-[54px] text-[15px] text-accent" @click="view = 'share'">Partager</button>
            <button type="button" class="btn-soft h-[54px] text-[15px] text-danger" @click="view = 'delete'">Supprimer</button>
        </template>

        <template v-else-if="props.workout && view === 'share'">
            <form class="flex flex-col gap-2" @submit.prevent="sendMail">
                <label class="text-[10.5px] font-extrabold tracking-[0.1em] text-text-muted uppercase" for="share-email">Par mail</label>
                <div class="flex gap-2">
                    <input
                        id="share-email"
                        v-model="email"
                        type="email"
                        inputmode="email"
                        autocomplete="email"
                        placeholder="adresse@mail.fr"
                        class="h-12 min-w-0 flex-1 rounded-2xl border-0 bg-surface px-4 text-[16px] font-medium text-text outline-none focus:ring-2 focus:ring-accent"
                    />
                    <button type="submit" class="btn-accent h-12 shrink-0 px-5 text-[18px]" :disabled="sending || !email.trim()">{{ sending ? '…' : 'Envoyer' }}</button>
                </div>
            </form>
            <button type="button" class="btn-soft h-[54px] text-[15px]" @click="shareLink">Partager le lien…</button>
            <p v-if="message" class="text-[13px] font-semibold text-accent" aria-live="polite">{{ message }}</p>
            <p v-if="error" class="text-[13px] font-semibold text-danger" aria-live="polite">{{ error }}</p>
            <button type="button" class="h-11 text-[14px] font-bold text-text-muted" @click="view = 'menu'">Retour</button>
        </template>

        <template v-else-if="props.workout">
            <button type="button" class="btn-accent h-14 w-full bg-danger! text-[22px]" @click="destroy">Supprimer la séance</button>
            <button type="button" class="btn-soft h-[54px] text-[15px]" @click="view = 'menu'">Garder</button>
        </template>
    </BottomSheet>
</template>
