<script setup>
import { computed, ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import BottomSheet from './BottomSheet.vue';

/**
 * Les autres actions d'une séance : la modifier, la supprimer (avec
 * confirmation : une séance supprimée ne revient pas, son historique reste).
 */
const props = defineProps({
    workout: { type: Object, default: null },
});

const emit = defineEmits(['close']);

const confirming = ref(false);
const open = computed({
    get: () => props.workout !== null,
    set: (value) => {
        if (!value) {
            emit('close');
        }
    },
});

watch(() => props.workout, () => (confirming.value = false));

function destroy() {
    router.delete(props.workout.urls.destroy, { preserveScroll: true, onFinish: () => emit('close') });
}
</script>

<template>
    <BottomSheet
        v-model:open="open"
        :title="confirming ? 'Supprimer ?' : (props.workout?.name ?? '')"
        :description="confirming ? `« ${props.workout?.name} » disparaîtra. Les séances déjà faites restent dans ton historique.` : null"
    >
        <template v-if="props.workout && !confirming">
            <Link :href="props.workout.urls.edit" class="btn-soft h-[54px] text-[15px] text-text!">Modifier la séance</Link>
            <button type="button" class="btn-soft h-[54px] text-[15px] text-danger" @click="confirming = true">Supprimer</button>
        </template>
        <template v-else-if="props.workout">
            <button type="button" class="btn-accent h-14 w-full bg-danger! text-[22px]" @click="destroy">Supprimer la séance</button>
            <button type="button" class="btn-soft h-[54px] text-[15px]" @click="confirming = false">Garder</button>
        </template>
    </BottomSheet>
</template>
