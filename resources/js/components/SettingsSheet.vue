<script setup>
import { computed } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { SwitchRoot, SwitchThumb } from 'reka-ui';
import BottomSheet from './BottomSheet.vue';
import Stepper from './Stepper.vue';
import { routes } from '../routes';

const props = defineProps({
    open: { type: Boolean, required: true },
});

const emit = defineEmits(['update:open']);

const page = usePage();
const user = computed(() => page.props.auth.user);

const form = useForm({ sound: user.value.sound, prep_seconds: user.value.prep_seconds });

function save() {
    form.put(routes.preferences, { preserveScroll: true, onSuccess: () => emit('update:open', false) });
}

function logout() {
    router.post(routes.logout);
}
</script>

<template>
    <BottomSheet :open="props.open" title="Réglages" :description="`${user.name} · ${user.email}`" @update:open="emit('update:open', $event)">
        <div class="flex flex-col gap-5 rounded-[22px] bg-surface p-4">
            <label class="flex cursor-pointer items-center justify-between gap-4">
                <span class="flex flex-col gap-0.5">
                    <span class="text-[15px] font-bold">Bips et vibrations</span>
                    <span class="text-[12.5px] font-medium text-text-muted">Début d'effort, repos, 3 dernières secondes</span>
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

            <Stepper
                label="Compte à rebours avant la séance"
                :display="form.prep_seconds === 0 ? 'Aucun' : `${form.prep_seconds} s`"
                :can-decrease="form.prep_seconds > 0"
                :can-increase="form.prep_seconds < 15"
                @decrease="form.prep_seconds--"
                @increase="form.prep_seconds++"
            />
        </div>

        <button type="button" class="btn-accent h-14 w-full text-[22px]" :disabled="form.processing" @click="save">Enregistrer</button>
        <button type="button" class="h-11 text-[14px] font-bold text-danger" @click="logout">Se déconnecter</button>
    </BottomSheet>
</template>
