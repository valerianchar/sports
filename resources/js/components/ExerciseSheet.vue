<script setup>
import { computed, onMounted, onUnmounted } from 'vue';
import { usePage } from '@inertiajs/vue3';
import BodyMap from './BodyMap.vue';
import ExerciseImage from './ExerciseImage.vue';
import { exerciseIntensity } from '../workout';

/**
 * La fiche « Comment faire » : elle recouvre l'écran courant sans le quitter,
 * si bien qu'une séance en pause ou une sélection en cours restent intactes.
 */
const props = defineProps({
    exercise: { type: Object, required: true },
    closeLabel: { type: String, default: 'Fermer' },
});

const emit = defineEmits(['close']);

const page = usePage();
const names = (muscles) => muscles.map((muscle) => page.props.muscles[muscle]).join(', ');
const intensity = computed(() => exerciseIntensity(props.exercise));

const credit = computed(() => {
    const source = props.exercise.credit;

    if (source === 'free-exercise-db') {
        return 'Photos : free-exercise-db (domaine public)';
    }

    return source === 'illustration' ? 'Illustration Séance' : `Photo : ${source}`;
});

const videoUrl = () =>
    'https://www.youtube.com/results?search_query=' +
    encodeURIComponent(`${props.exercise.name} ${props.exercise.equipment_label} exécution`);

const onKey = (event) => event.key === 'Escape' && emit('close');
onMounted(() => window.addEventListener('keydown', onKey));
onUnmounted(() => window.removeEventListener('keydown', onKey));
</script>

<template>
    <div class="absolute inset-0 z-20 flex flex-col bg-bg" role="dialog" aria-modal="true" :aria-label="props.exercise.name">
        <div class="no-scrollbar flex-1 overflow-y-auto pb-[110px]">
            <div class="relative aspect-[3/2] max-h-[320px] w-full bg-surface">
                <ExerciseImage :images="props.exercise.images" :alt="`${props.exercise.name} — ${props.exercise.equipment_label}`" />
                <button
                    type="button"
                    class="iconbtn absolute top-4 left-5 size-10 bg-[rgb(14_15_12/0.8)]!"
                    aria-label="Fermer"
                    @click="emit('close')"
                >
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18" /></svg>
                </button>
            </div>

            <div class="flex flex-col gap-1.5 px-5 pt-[22px]">
                <div class="flex flex-wrap gap-1.5">
                    <span class="tag border-accent! bg-accent text-on-accent!">{{ props.exercise.group_label.toUpperCase() }}</span>
                    <span class="tag">{{ props.exercise.mode === 'time' ? 'DURÉE' : 'RÉPÉTITIONS' }}</span>
                </div>
                <h2 class="display mt-1.5 text-[44px] leading-[0.92] font-extrabold text-balance">{{ props.exercise.name }}</h2>
                <p class="text-[14px] font-semibold text-text-soft">{{ props.exercise.equipment_label }}</p>
            </div>

            <section class="mx-5 mt-5 flex items-center gap-4 rounded-[22px] bg-surface p-4">
                <BodyMap :intensity="intensity" :height="170" :label="`Muscles : ${names(props.exercise.primary)}`" />
                <dl class="flex min-w-0 flex-1 flex-col gap-3 text-[13px]">
                    <div>
                        <dt class="flex items-center gap-1.5 text-[10.5px] font-extrabold tracking-[0.1em] text-text-muted uppercase">
                            <span class="size-2.5 rounded-full bg-accent" aria-hidden="true" /> Principaux
                        </dt>
                        <dd class="mt-1 font-semibold">{{ names(props.exercise.primary) }}</dd>
                    </div>
                    <div v-if="props.exercise.secondary.length">
                        <dt class="flex items-center gap-1.5 text-[10.5px] font-extrabold tracking-[0.1em] text-text-muted uppercase">
                            <span class="size-2.5 rounded-full bg-[color-mix(in_srgb,var(--color-accent)_62%,var(--color-surface-3))]" aria-hidden="true" /> Secondaires
                        </dt>
                        <dd class="mt-1 font-medium text-text-soft">{{ names(props.exercise.secondary) }}</dd>
                    </div>
                </dl>
            </section>

            <div class="flex flex-col gap-2.5 px-5 pt-[26px]">
                <h3 class="display text-[22px] font-bold">Comment faire</h3>
                <ol class="flex flex-col gap-2.5">
                    <li
                        v-for="(step, index) in props.exercise.steps"
                        :key="index"
                        class="flex items-start gap-3.5 rounded-2xl bg-surface px-4 py-3.5"
                    >
                        <span class="w-[18px] shrink-0 font-display text-[24px] leading-none font-extrabold text-accent">{{ index + 1 }}</span>
                        <span class="text-[14.5px] leading-[1.45] font-medium text-pretty">{{ step }}</span>
                    </li>
                </ol>

                <div v-if="props.exercise.tip" class="flex flex-col gap-1 rounded-2xl border-[1.5px] border-line px-4 py-3.5">
                    <span class="text-[10.5px] font-extrabold tracking-[0.12em] text-prep">CONSEIL</span>
                    <p class="text-[14px] leading-[1.45] font-medium text-pretty text-text-soft">{{ props.exercise.tip }}</p>
                </div>

                <a :href="videoUrl()" target="_blank" rel="noopener" class="btn-soft mt-1 h-[50px] text-[14px]">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M7 4.5v15a1 1 0 0 0 1.5.86l12.5-7.5a1 1 0 0 0 0-1.72L8.5 3.64A1 1 0 0 0 7 4.5z" /></svg>
                    Voir une démo vidéo
                </a>

                <p class="pt-2 text-center text-[11px] text-text-faint">
                    {{ credit }}
                </p>
            </div>
        </div>

        <div class="bottom-bar">
            <button type="button" class="btn-accent h-14 w-full text-[22px]" @click="emit('close')">{{ props.closeLabel }}</button>
        </div>
    </div>
</template>
