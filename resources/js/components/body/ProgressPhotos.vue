<script setup>
import { computed, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import BottomSheet from '../BottomSheet.vue';
import { formatKg, formatNumber } from '../../format';
import { routes } from '../../routes';

/**
 * Les photos de progression de l'onglet « Corps » : envoi depuis le
 * téléphone, galerie par date et « Avant / après » d'une même pose côte à
 * côte. Les photos ne quittent jamais le disque privé : chaque image est
 * servie au seul propriétaire par /progres/photos/{id}.
 */
const props = defineProps({
    photos: { type: Array, required: true },
    poses: { type: Array, required: true },
});

const page = usePage();
const poseLabel = computed(() => Object.fromEntries(props.poses.map((p) => [p.value, p.label])));

// Envoi : on choisit la pose, puis l'appareil photo ou la galerie.
const MAX_BYTES = 8 * 1024 * 1024;
const MAX_SIDE = 2000;
const pose = ref('face');
const note = ref('');
const uploading = ref(false);
const progress = ref(null);
const localError = ref(null);
const cameraInput = ref(null);
const galleryInput = ref(null);
const error = computed(() => localError.value ?? page.props.errors?.photo ?? page.props.errors?.pose ?? page.props.errors?.note ?? null);

/**
 * Réduit la photo à 2000 px de côté en JPEG avant l'envoi : plus rapide sur
 * le réseau mobile, plus légère dans la galerie, et sans les métadonnées
 * (position GPS comprise). Si le navigateur ne sait pas la décoder, on
 * envoie l'original.
 */
async function shrink(file) {
    const url = URL.createObjectURL(file);

    try {
        const image = new Image();
        image.src = url;
        await image.decode();

        const scale = Math.min(1, MAX_SIDE / Math.max(image.naturalWidth, image.naturalHeight));
        const canvas = document.createElement('canvas');
        canvas.width = Math.round(image.naturalWidth * scale);
        canvas.height = Math.round(image.naturalHeight * scale);
        canvas.getContext('2d').drawImage(image, 0, 0, canvas.width, canvas.height);

        const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', 0.85));

        return blob ? new File([blob], 'photo.jpg', { type: 'image/jpeg' }) : file;
    } catch {
        return file;
    } finally {
        URL.revokeObjectURL(url);
    }
}

async function upload(event) {
    const original = event.target.files?.[0];
    event.target.value = '';

    if (!original) {
        return;
    }

    localError.value = null;
    uploading.value = true;
    const file = await shrink(original);

    if (file.size > MAX_BYTES) {
        localError.value = 'La photo dépasse 8 Mo.';
        uploading.value = false;

        return;
    }

    router.post(
        routes.bodyPhotos,
        { photo: file, pose: pose.value, note: note.value.trim() || null },
        {
            forceFormData: true,
            preserveScroll: true,
            preserveState: true,
            only: ['body'],
            onProgress: (e) => (progress.value = e?.percentage ?? null),
            onSuccess: () => {
                note.value = '';
                comparePose.value = pose.value;
            },
            onFinish: () => {
                uploading.value = false;
                progress.value = null;
            },
        },
    );
}

// Galerie : les photos regroupées par jour, les plus récentes d'abord.
const days = computed(() => {
    const groups = [];

    for (const photo of props.photos) {
        const last = groups.at(-1);

        if (last?.date === photo.date) {
            last.photos.push(photo);
        } else {
            groups.push({ date: photo.date, label: photo.label, kg: photo.kg, photos: [photo] });
        }
    }

    return groups;
});

// Visionneuse : une photo en grand, avec sa note et de quoi la supprimer.
const viewed = ref(null);
const viewerOpen = computed({
    get: () => viewed.value !== null,
    set: (open) => {
        if (!open) {
            viewed.value = null;
        }
    },
});
const deleting = ref(false);

function remove() {
    if (!viewed.value || !confirm('Supprimer cette photo ? Elle disparaîtra pour de bon.')) {
        return;
    }

    deleting.value = true;
    router.delete(`${routes.bodyPhotos}/${viewed.value.id}`, {
        preserveScroll: true,
        preserveState: true,
        only: ['body'],
        onSuccess: () => (viewed.value = null),
        onFinish: () => (deleting.value = false),
    });
}

// Avant / après : deux photos d'une même pose, la plus ancienne et la plus
// récente par défaut.
const comparable = computed(() =>
    props.poses
        .map((p) => ({ ...p, photos: props.photos.filter((photo) => photo.pose === p.value).slice().reverse() }))
        .filter((p) => p.photos.length > 1),
);
const comparePose = ref(null);
const compared = computed(() => comparable.value.find((p) => p.value === comparePose.value) ?? comparable.value[0] ?? null);
const beforeId = ref(null);
const afterId = ref(null);

watch(
    compared,
    (group) => {
        if (!group) {
            return;
        }

        const ids = group.photos.map((p) => p.id);

        if (!ids.includes(beforeId.value)) {
            beforeId.value = ids[0];
        }

        if (!ids.includes(afterId.value) || afterId.value === beforeId.value) {
            afterId.value = ids.at(-1);
        }
    },
    { immediate: true },
);

const before = computed(() => compared.value?.photos.find((p) => p.id === beforeId.value) ?? null);
const after = computed(() => compared.value?.photos.find((p) => p.id === afterId.value) ?? null);

const gap = computed(() => {
    if (!before.value || !after.value) {
        return null;
    }

    const days = Math.round(Math.abs(Date.parse(after.value.date) - Date.parse(before.value.date)) / 86_400_000);
    const parts = [days === 0 ? 'le même jour' : `${formatNumber(days)} jour${days > 1 ? 's' : ''} d'écart`];

    if (before.value.kg !== null && after.value.kg !== null) {
        const delta = after.value.kg - before.value.kg;
        parts.push(`${delta > 0 ? '+' : delta < 0 ? '−' : ''}${formatNumber(Math.abs(delta), 1)} kg`);
    }

    return parts.join(' · ');
});
</script>

<template>
    <section class="flex flex-col gap-3 rounded-[22px] bg-surface p-4">
        <div>
            <h2 class="text-[15px] font-bold">Photos de progression</h2>
            <p class="text-[12px] font-medium text-text-muted">
                Même lumière, même tenue, même cadrage, une fois par mois : c'est là que les progrès se voient. Visibles par toi seul.
            </p>
        </div>
        <div class="grid grid-cols-3 gap-[3px] rounded-xl bg-bg p-[3px]" role="radiogroup" aria-label="Pose">
            <button
                v-for="p in poses"
                :key="p.value"
                type="button"
                role="radio"
                :aria-checked="pose === p.value"
                class="h-11 rounded-[9px] text-[13.5px] font-bold"
                :class="pose === p.value ? 'bg-surface-3 text-text' : 'text-text-faint'"
                @click="pose = p.value"
            >
                {{ p.label }}
            </button>
        </div>
        <input v-model="note" type="text" maxlength="140" placeholder="Note (facultatif)" aria-label="Note de la photo" class="field" />
        <div class="grid grid-cols-2 gap-2">
            <button type="button" class="btn-accent h-14 text-[16px]" :disabled="uploading" @click="cameraInput?.click()">
                {{ uploading ? (progress ? `${progress} %` : 'Envoi…') : 'Prendre la photo' }}
            </button>
            <button type="button" class="btn-soft h-14 bg-surface-2 text-[15px]" :disabled="uploading" @click="galleryInput?.click()">Depuis la galerie</button>
        </div>
        <input ref="cameraInput" type="file" accept="image/*" capture class="hidden" @change="upload" />
        <input ref="galleryInput" type="file" accept="image/*" class="hidden" @change="upload" />
        <p v-if="error" class="text-[13px] text-danger">{{ error }}</p>
    </section>

    <section v-if="compared" class="flex flex-col gap-3 rounded-[22px] bg-surface p-4">
        <h2 class="text-[15px] font-bold">Avant / après</h2>
        <div v-if="comparable.length > 1" class="grid gap-[3px] rounded-xl bg-bg p-[3px]" :style="{ gridTemplateColumns: `repeat(${comparable.length}, minmax(0, 1fr))` }" role="radiogroup" aria-label="Pose comparée">
            <button
                v-for="p in comparable"
                :key="p.value"
                type="button"
                role="radio"
                :aria-checked="compared.value === p.value"
                class="h-11 rounded-[9px] text-[13.5px] font-bold"
                :class="compared.value === p.value ? 'bg-surface-3 text-text' : 'text-text-faint'"
                @click="comparePose = p.value"
            >
                {{ p.label }}
            </button>
        </div>
        <div class="grid grid-cols-2 gap-2">
            <label v-for="side in [{ key: 'before', title: 'Avant' }, { key: 'after', title: 'Après' }]" :key="side.key" class="flex min-w-0 flex-col gap-1.5">
                <span class="eyebrow text-text-muted">{{ side.title }}</span>
                <select
                    v-if="side.key === 'before'"
                    v-model="beforeId"
                    class="h-12 w-full min-w-0 rounded-2xl border-[1.5px] border-transparent bg-bg px-3 text-[13px] font-semibold text-text outline-none focus:border-accent"
                    :aria-label="`Photo « avant », ${compared.label.toLowerCase()}`"
                >
                    <option v-for="photo in compared.photos" :key="photo.id" :value="photo.id">{{ photo.label }}</option>
                </select>
                <select
                    v-else
                    v-model="afterId"
                    class="h-12 w-full min-w-0 rounded-2xl border-[1.5px] border-transparent bg-bg px-3 text-[13px] font-semibold text-text outline-none focus:border-accent"
                    :aria-label="`Photo « après », ${compared.label.toLowerCase()}`">
                    <option v-for="photo in compared.photos" :key="photo.id" :value="photo.id">{{ photo.label }}</option>
                </select>
            </label>
            <img
                v-for="(photo, i) in [before, after].filter(Boolean)"
                :key="i"
                :src="photo.url"
                :alt="`${poseLabel[photo.pose]}, ${photo.label}`"
                class="aspect-[3/4] w-full rounded-2xl bg-bg object-cover"
            />
        </div>
        <p v-if="gap" class="text-center text-[13px] font-bold text-text-soft">{{ gap }}</p>
    </section>

    <section v-if="photos.length" class="flex flex-col gap-3 rounded-[22px] bg-surface p-4">
        <h2 class="text-[15px] font-bold">Galerie</h2>
        <div v-for="day in days" :key="day.date" class="flex flex-col gap-1.5">
            <h3 class="text-[12.5px] font-bold text-text-soft">
                {{ day.label }}<span v-if="day.kg !== null" class="font-semibold text-text-faint"> · {{ formatKg(day.kg) }}</span>
            </h3>
            <div class="grid grid-cols-3 gap-1.5">
                <button
                    v-for="photo in day.photos"
                    :key="photo.id"
                    type="button"
                    class="relative overflow-hidden rounded-xl bg-bg"
                    :aria-label="`Voir la photo ${poseLabel[photo.pose].toLowerCase()} du ${photo.label}`"
                    @click="viewed = photo"
                >
                    <img :src="photo.url" alt="" loading="lazy" class="aspect-[3/4] w-full object-cover" />
                    <span class="absolute bottom-1.5 left-1.5 rounded-md bg-[rgb(10_10_8/0.7)] px-1.5 py-0.5 text-[10.5px] font-bold text-text">{{ poseLabel[photo.pose] }}</span>
                </button>
            </div>
        </div>
    </section>

    <BottomSheet v-model:open="viewerOpen" :title="viewed ? poseLabel[viewed.pose] : ''" :description="viewed ? `${viewed.label}${viewed.kg !== null ? ` · ${formatKg(viewed.kg)}` : ''}` : null">
        <template v-if="viewed">
            <img :src="viewed.url" :alt="`${poseLabel[viewed.pose]}, ${viewed.label}`" class="max-h-[56dvh] w-full rounded-2xl bg-bg object-contain" />
            <p v-if="viewed.note" class="text-[14px] font-medium text-text-soft">{{ viewed.note }}</p>
            <button type="button" class="btn-soft h-[54px] text-[15px] text-danger" :disabled="deleting" @click="remove">Supprimer cette photo</button>
            <button type="button" class="btn-soft h-[54px] text-[15px]" @click="viewed = null">Fermer</button>
        </template>
    </BottomSheet>
</template>
