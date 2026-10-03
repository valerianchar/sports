<script setup>
import { computed, reactive, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { SwitchRoot, SwitchThumb } from 'reka-ui';
import BodyMap from '../../components/BodyMap.vue';
import ExerciseSheet from '../../components/ExerciseSheet.vue';
import MuscleSummary from '../../components/MuscleSummary.vue';
import { unlockAudio } from '../../audio';
import { routes } from '../../routes';
import { bySlug, formatShort, summary } from '../../workout';

const props = defineProps({
    proposal: { type: Object, default: null },
    exercises: { type: Array, required: true },
    input: { type: Object, required: true },
    goals: { type: Array, required: true },
    equipments: { type: Array, required: true },
});

const page = usePage();
const labels = computed(() => page.props.muscles);
const catalog = computed(() => bySlug(props.exercises));

/*
 * Les muscles proposés au choix, par région. Tibias et cou restent hors de
 * l'assistant : on ne bâtit pas une séance autour d'eux.
 */
const regions = [
    { label: 'Haut du corps', muscles: ['chest', 'front-deltoids', 'rear-deltoids', 'upper-back', 'trapezius', 'biceps', 'triceps', 'forearm'] },
    { label: 'Tronc', muscles: ['abs', 'obliques', 'lower-back'] },
    { label: 'Bas du corps', muscles: ['gluteal', 'quadriceps', 'hamstring', 'adductors', 'calves'] },
];

const presets = [
    { label: 'Push', muscles: ['chest', 'front-deltoids', 'triceps'] },
    { label: 'Pull', muscles: ['upper-back', 'rear-deltoids', 'trapezius', 'biceps'] },
    { label: 'Jambes', muscles: ['quadriceps', 'hamstring', 'gluteal', 'adductors', 'calves'] },
    { label: 'Haut du corps', muscles: ['chest', 'upper-back', 'front-deltoids', 'rear-deltoids', 'biceps', 'triceps'] },
    { label: 'Full body', muscles: ['chest', 'upper-back', 'front-deltoids', 'quadriceps', 'hamstring', 'gluteal', 'abs'] },
    { label: 'Abdos', muscles: ['abs', 'obliques', 'lower-back'] },
];

const choosable = regions.flatMap((region) => region.muscles);

const form = reactive({
    muscles: props.input.muscles.filter((muscle) => choosable.includes(muscle)),
    minutes: props.input.minutes,
    goal: props.input.goal,
    equipment: props.input.equipment ?? '',
    warmup: props.input.warmup,
    stretch: props.input.stretch,
});

// Une proposition s'affiche à la place du formulaire ; « Modifier les critères » y revient.
const editing = ref(!props.proposal);
const loading = ref(false);
const detail = ref(null);
const detailExercise = computed(() => (detail.value ? catalog.value[detail.value] : null));

const intensity = computed(() => Object.fromEntries(form.muscles.map((muscle) => [muscle, 1])));

function toggle(muscle) {
    if (!choosable.includes(muscle)) {
        return;
    }

    form.muscles = form.muscles.includes(muscle) ? form.muscles.filter((m) => m !== muscle) : [...form.muscles, muscle];
}

const presetActive = (preset) => preset.muscles.length === form.muscles.length && preset.muscles.every((m) => form.muscles.includes(m));

function applyPreset(preset) {
    form.muscles = presetActive(preset) ? [] : [...preset.muscles];
}

function setMinutes(value) {
    form.minutes = Math.min(150, Math.max(10, value));
}

function suggest(variant = 0) {
    router.get(
        routes.assistantSuggest,
        {
            muscles: form.muscles,
            minutes: form.minutes,
            goal: form.goal,
            equipment: form.equipment || undefined,
            warmup: form.warmup ? 1 : 0,
            stretch: form.stretch ? 1 : 0,
            variant,
        },
        {
            onStart: () => (loading.value = true),
            onFinish: () => (loading.value = false),
            onSuccess: () => {
                editing.value = false;
                document.querySelector('[data-scroll]')?.scrollTo({ top: 0 });
            },
        },
    );
}

const another = () => suggest((props.input.variant ?? 0) + 1);

function save(options = {}) {
    if (options.start) {
        unlockAudio();
    }

    router.post(routes.workouts, { name: props.proposal.name, items: props.proposal.items, ...options }, {
        onStart: () => (loading.value = true),
        onFinish: () => (loading.value = false),
    });
}

const proposalSummary = computed(() => (props.proposal ? summary(props.proposal.items, page.props.seconds_per_rep) : ''));

const prescription = (item) => {
    const effort = item.mode === 'reps' ? `${item.value} reps` : formatShort(item.value);
    const rest = item.sets > 1 && item.rest_sets ? ` · repos ${formatShort(item.rest_sets)}` : '';

    return `${item.sets} × ${effort}${rest}`;
};
</script>

<template>
    <Head title="Assistant" />

    <div class="relative flex min-h-0 flex-1 flex-col">
        <div class="flex items-center gap-3 px-5 pt-1 pb-2">
            <Link :href="routes.home" class="iconbtn size-10" aria-label="Retour aux séances">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M15 5l-7 7 7 7" /></svg>
            </Link>
            <span class="eyebrow text-accent">Assistant</span>
        </div>

        <!-- CRITÈRES -->
        <div v-if="editing" data-scroll class="no-scrollbar flex flex-1 flex-col gap-3 overflow-y-auto px-5 pt-1 pb-[110px]">
            <h1 class="display text-[44px] leading-[0.9] font-extrabold">Compose ta<br />séance</h1>
            <p class="mb-2 text-[14px] font-medium text-text-muted">
                Dis ce que tu veux travailler et le temps que tu as : je propose une séance que tu pourras retoucher.
            </p>

            <section class="flex flex-col gap-4 rounded-[22px] bg-surface p-4">
                <div class="flex items-baseline justify-between">
                    <h2 class="display text-[22px] font-bold">Muscles</h2>
                    <button v-if="form.muscles.length" type="button" class="text-[13px] font-bold text-text-muted" @click="form.muscles = []">
                        Effacer
                    </button>
                </div>

                <div class="no-scrollbar -mx-4 flex gap-2 overflow-x-auto px-4">
                    <button
                        v-for="preset in presets"
                        :key="preset.label"
                        type="button"
                        class="h-[34px] shrink-0 rounded-full px-3.5 text-[13px] font-bold whitespace-nowrap"
                        :class="presetActive(preset) ? 'bg-accent text-on-accent' : 'border-[1.5px] border-line text-text-soft'"
                        :aria-pressed="presetActive(preset)"
                        @click="applyPreset(preset)"
                    >
                        {{ preset.label }}
                    </button>
                </div>

                <BodyMap :intensity="intensity" :height="240" interactive label="" @toggle="toggle" />
                <p class="-mt-2 text-center text-[12px] font-semibold text-text-faint">Touche un muscle pour le choisir</p>

                <div v-for="region in regions" :key="region.label" class="flex flex-col gap-2">
                    <span class="text-[10.5px] font-extrabold tracking-[0.1em] text-text-muted uppercase">{{ region.label }}</span>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="muscle in region.muscles"
                            :key="muscle"
                            type="button"
                            class="h-[34px] rounded-full px-3.5 text-[13px] font-bold"
                            :class="form.muscles.includes(muscle) ? 'bg-accent text-on-accent' : 'bg-surface-2 text-text-soft'"
                            :aria-pressed="form.muscles.includes(muscle)"
                            @click="toggle(muscle)"
                        >
                            {{ labels[muscle] }}
                        </button>
                    </div>
                </div>
                <p v-if="page.props.errors.muscles" class="text-[13px] text-danger">{{ page.props.errors.muscles }}</p>
            </section>

            <section class="flex flex-col gap-3 rounded-[22px] bg-surface p-4">
                <h2 class="display text-[22px] font-bold">Durée</h2>
                <div class="flex items-center justify-between">
                    <button type="button" class="iconbtn size-11 bg-surface-2! text-[22px] font-semibold" aria-label="5 minutes de moins" @click="setMinutes(form.minutes - 5)">−</button>
                    <span class="font-display text-[44px] leading-none font-extrabold tabular-nums" aria-live="polite">{{ form.minutes }} min</span>
                    <button type="button" class="iconbtn size-11 bg-surface-2! text-[22px] font-semibold" aria-label="5 minutes de plus" @click="setMinutes(form.minutes + 5)">+</button>
                </div>
                <div class="grid grid-cols-4 gap-2">
                    <button
                        v-for="minutes in [30, 45, 60, 90]"
                        :key="minutes"
                        type="button"
                        class="h-9 rounded-xl text-[13px] font-bold"
                        :class="form.minutes === minutes ? 'bg-text text-bg' : 'bg-surface-2 text-text-soft'"
                        @click="form.minutes = minutes"
                    >
                        {{ minutes }} min
                    </button>
                </div>
            </section>

            <section class="flex flex-col gap-3 rounded-[22px] bg-surface p-4">
                <h2 class="display text-[22px] font-bold">Objectif</h2>
                <div class="grid grid-cols-3 gap-2" role="radiogroup" aria-label="Objectif">
                    <button
                        v-for="goal in props.goals"
                        :key="goal.value"
                        type="button"
                        role="radio"
                        :aria-checked="form.goal === goal.value"
                        class="flex flex-col items-start gap-1 rounded-2xl p-3 text-left"
                        :class="form.goal === goal.value ? 'bg-accent text-on-accent' : 'bg-surface-2 text-text'"
                        @click="form.goal = goal.value"
                    >
                        <span class="font-display text-[20px] leading-none font-extrabold uppercase">{{ goal.label }}</span>
                        <span class="text-[11.5px] leading-tight font-semibold" :class="form.goal === goal.value ? 'text-on-accent/75' : 'text-text-muted'">
                            {{ goal.description }}
                        </span>
                    </button>
                </div>
            </section>

            <section class="flex flex-col gap-3 rounded-[22px] bg-surface p-4">
                <h2 class="display text-[22px] font-bold">Matériel</h2>
                <div class="grid grid-cols-2 gap-2" role="radiogroup" aria-label="Matériel">
                    <button
                        v-for="choice in [{ value: '', label: 'Tout' }, ...props.equipments]"
                        :key="choice.value"
                        type="button"
                        role="radio"
                        :aria-checked="form.equipment === choice.value"
                        class="h-11 rounded-xl px-3 text-[13px] font-bold"
                        :class="form.equipment === choice.value ? 'bg-text text-bg' : 'bg-surface-2 text-text-soft'"
                        @click="form.equipment = choice.value"
                    >
                        {{ choice.label }}
                    </button>
                </div>
            </section>

            <section class="flex flex-col gap-4 rounded-[22px] bg-surface p-4">
                <label v-for="option in [
                    { key: 'warmup', title: 'Échauffement', text: '5 minutes de cardio pour commencer' },
                    { key: 'stretch', title: 'Étirements', text: 'Deux étirements des muscles travaillés à la fin' },
                ]" :key="option.key" class="flex cursor-pointer items-center justify-between gap-4">
                    <span class="flex flex-col gap-0.5">
                        <span class="text-[15px] font-bold">{{ option.title }}</span>
                        <span class="text-[12.5px] font-medium text-text-muted">{{ option.text }}</span>
                    </span>
                    <SwitchRoot
                        v-model="form[option.key]"
                        class="relative h-7 w-12 shrink-0 rounded-full transition-colors"
                        :class="form[option.key] ? 'bg-accent' : 'bg-surface-3'"
                        :aria-label="option.title"
                    >
                        <SwitchThumb
                            class="absolute top-1 block size-5 rounded-full transition-[left]"
                            :class="form[option.key] ? 'left-6 bg-on-accent' : 'left-1 bg-text-muted'"
                        />
                    </SwitchRoot>
                </label>
            </section>
        </div>

        <!-- PROPOSITION -->
        <div v-else-if="props.proposal" data-scroll class="no-scrollbar flex flex-1 flex-col gap-3 overflow-y-auto px-5 pt-1 pb-[190px]">
            <p class="text-[12px] font-bold tracking-[0.12em] text-text-muted uppercase">Ma proposition</p>
            <h1 class="display text-[38px] leading-[0.92] font-extrabold text-balance">{{ props.proposal.name }}</h1>
            <p class="mb-1 text-[13px] font-semibold text-text-muted">{{ proposalSummary }}</p>

            <MuscleSummary :items="props.proposal.items" :catalog="catalog" :height="200" />

            <ol class="flex flex-col gap-2">
                <li v-for="(item, index) in props.proposal.items" :key="item.exercise" class="flex items-center gap-3 rounded-2xl bg-surface p-2.5">
                    <span class="w-6 shrink-0 text-center font-display text-[20px] font-extrabold text-accent">{{ index + 1 }}</span>
                    <span class="size-12 shrink-0 overflow-hidden rounded-[10px]">
                        <img :src="catalog[item.exercise].images[0]" alt="" class="size-full object-cover" />
                    </span>
                    <span class="flex min-w-0 flex-1 flex-col gap-0.5">
                        <span class="text-[14.5px] leading-tight font-bold">{{ catalog[item.exercise].name }}</span>
                        <span class="text-[12.5px] font-medium text-text-muted">{{ prescription(item) }}</span>
                    </span>
                    <button
                        type="button"
                        class="iconbtn size-9 bg-surface-2! font-serif text-[15px] font-extrabold text-text-soft italic"
                        :aria-label="`Comment faire : ${catalog[item.exercise].name}`"
                        @click="detail = item.exercise"
                    >
                        i
                    </button>
                </li>
            </ol>

            <div class="mt-1 grid grid-cols-2 gap-2">
                <button type="button" class="btn-soft h-12 text-[14px]" :disabled="loading" @click="another">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 11a8 8 0 1 0-2.3 5.7M20 4v7h-7" /></svg>
                    Autre proposition
                </button>
                <button type="button" class="btn-soft h-12 text-[14px]" @click="editing = true">Modifier les critères</button>
            </div>
        </div>

        <div class="bottom-bar flex flex-col gap-2">
            <template v-if="editing">
                <button type="button" class="btn-accent h-14 w-full text-[22px]" :disabled="!form.muscles.length || loading" @click="suggest()">
                    {{ form.muscles.length ? 'Proposer une séance' : 'Choisis des muscles' }}
                </button>
            </template>
            <template v-else>
                <button type="button" class="btn-accent h-14 w-full text-[22px]" :disabled="loading" @click="save({ start: true })">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M7 4.5v15a1 1 0 0 0 1.5.86l12.5-7.5a1 1 0 0 0 0-1.72L8.5 3.64A1 1 0 0 0 7 4.5z" /></svg>
                    Lancer maintenant
                </button>
                <div class="grid grid-cols-2 gap-2">
                    <button type="button" class="btn-soft h-12 text-[14px]" :disabled="loading" @click="save({ edit: true })">Retoucher</button>
                    <button type="button" class="btn-soft h-12 text-[14px] text-accent" :disabled="loading" @click="save()">Enregistrer</button>
                </div>
            </template>
        </div>
    </div>

    <ExerciseSheet v-if="detailExercise" :exercise="detailExercise" @close="detail = null" />
</template>
