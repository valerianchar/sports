<script setup>
import { computed } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { SwitchRoot, SwitchThumb } from 'reka-ui';
import BottomSheet from './BottomSheet.vue';
import Stepper from './Stepper.vue';
import { countdownBeep, unlockAudio } from '../audio';
import { routes } from '../routes';

const props = defineProps({
    open: { type: Boolean, required: true },
});

const emit = defineEmits(['update:open']);

const page = usePage();
const user = computed(() => page.props.auth.user);

const form = useForm({
    sound: user.value.sound,
    prep_seconds: user.value.prep_seconds,
    countdown_seconds: user.value.countdown_seconds ?? 5,
    volume: user.value.volume ?? 80,
});

/* Entendre le réglage tout de suite : trois bips, le dernier comme une reprise. */
function test() {
    unlockAudio();
    [3, 2, 1].forEach((second, index) => setTimeout(() => countdownBeep(second, form.volume / 100), index * 1000));
}

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
                <div class="flex items-baseline justify-between">
                    <span class="text-[10.5px] font-extrabold tracking-[0.1em] text-text-muted uppercase">Volume des bips</span>
                    <span class="font-display text-[20px] font-bold tabular-nums">{{ form.volume }} %</span>
                </div>
                <div class="flex items-center gap-3">
                    <input
                        v-model.number="form.volume"
                        type="range"
                        min="10"
                        max="100"
                        step="10"
                        aria-label="Volume des bips"
                        class="h-6 flex-1 cursor-pointer accent-[var(--color-accent)]"
                    />
                    <button type="button" class="h-10 shrink-0 rounded-full bg-surface-2 px-4 text-[13px] font-extrabold text-accent" @click="test">
                        Tester
                    </button>
                </div>
                <p class="text-[12px] font-medium text-text-faint">Le volume du téléphone s'applique aussi : monte-le pendant la séance.</p>
            </div>

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
