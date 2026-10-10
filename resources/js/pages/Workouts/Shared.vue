<script setup>
import { computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import MuscleSummary from '../../components/MuscleSummary.vue';
import { routes } from '../../routes';
import { bySlug, formatWeight, summary, targetLabel } from '../../workout';

/**
 * Une séance partagée par un autre membre : ce qu'elle contient, et un
 * toucher pour l'ajouter à ses propres séances.
 */
const props = defineProps({
    workout: { type: Object, required: true },
    author: { type: String, required: true },
    mine: { type: Boolean, required: true },
    exercises: { type: Array, required: true },
    token: { type: String, required: true },
});

const page = usePage();
const catalog = computed(() => bySlug(props.exercises));
const meta = computed(() => summary(props.workout.items, page.props.seconds_per_rep));

const line = (item) => [`${item.sets} × ${targetLabel(item)}`, formatWeight(item.weight)].filter(Boolean).join(' · ');

function addToMine() {
    router.post(`/partage/${props.token}`);
}
</script>

<template>
    <Head :title="props.workout.name" />

    <div class="no-scrollbar flex flex-1 flex-col gap-4 overflow-y-auto px-5 pt-3 pb-[110px]">
        <Link :href="routes.home" class="iconbtn size-11" aria-label="Retour à l'accueil">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M15 5l-7 7 7 7" /></svg>
        </Link>
        <div>
            <p class="eyebrow text-accent">{{ props.mine ? 'Ta séance, telle qu’on la reçoit' : `Partagée par ${props.author}` }}</p>
            <h1 class="display mt-1 text-[42px] leading-[0.92] font-extrabold text-balance">{{ props.workout.name }}</h1>
            <p class="mt-1 text-[13px] font-semibold text-text-muted">{{ meta }}</p>
        </div>

        <ol class="flex flex-col gap-2" aria-label="Exercices">
            <li v-for="(item, index) in props.workout.items" :key="index" class="flex items-center gap-3 rounded-2xl bg-surface p-2.5">
                <span class="w-6 shrink-0 text-center font-display text-[20px] font-extrabold text-accent">{{ index + 1 }}</span>
                <span class="size-14 shrink-0 overflow-hidden rounded-xl bg-surface-2">
                    <img :src="catalog[item.exercise]?.images[0]" alt="" class="size-full object-cover" />
                </span>
                <span class="flex min-w-0 flex-col gap-0.5">
                    <span class="text-[15px] leading-tight font-bold">{{ catalog[item.exercise]?.name ?? item.exercise }}</span>
                    <span class="text-[12.5px] font-medium text-text-muted">{{ line(item) }}</span>
                </span>
            </li>
        </ol>

        <MuscleSummary :items="props.workout.items" :catalog="catalog" :height="200" />
    </div>

    <div class="bottom-bar">
        <Link v-if="props.mine" :href="props.workout.urls.edit" class="btn-accent h-14 w-full text-[22px] text-on-accent!">Modifier ma séance</Link>
        <button v-else type="button" class="btn-accent h-14 w-full text-[22px]" @click="addToMine">Ajouter à mes séances</button>
    </div>
</template>
