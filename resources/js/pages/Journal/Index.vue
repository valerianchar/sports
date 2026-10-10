<script setup>
import { computed } from 'vue';
import { Head, InfiniteScroll, Link } from '@inertiajs/vue3';
import TabBar from '../../components/TabBar.vue';
import { formatKcal, formatMinutesLong, formatTonnage } from '../../format';
import { routes } from '../../routes';

/**
 * Le journal : chaque séance faite, la plus récente d'abord, regroupées par
 * semaine. La suite se charge au défilement, vingt par vingt.
 */
const props = defineProps({
    sessions: { type: Object, required: true },
});

const entries = computed(() => props.sessions.data);

/* Un en-tête de semaine au-dessus de la première séance de chaque semaine. */
const startsWeek = (index) => index === 0 || entries.value[index - 1].week_start !== entries.value[index].week_start;
</script>

<template>
    <Head title="Journal des séances" />

    <div class="no-scrollbar flex-1 overflow-y-auto px-5 pt-3 pb-8">
        <header class="mb-4 flex items-end gap-3">
            <Link :href="routes.progress" class="iconbtn size-12" aria-label="Retour aux progrès">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 5l-7 7 7 7" /></svg>
            </Link>
            <div class="min-w-0">
                <p class="eyebrow text-accent">Progrès</p>
                <h1 class="display mt-1 text-[46px] leading-[0.9] font-extrabold">Journal</h1>
            </div>
        </header>

        <div v-if="!entries.length" class="flex flex-col items-center gap-4 rounded-3xl bg-surface px-6 py-10 text-center">
            <p class="display text-[30px] font-extrabold">Rien au journal</p>
            <p class="text-[14px] font-medium text-text-muted">Chaque séance terminée y viendra, avec toutes ses séries.</p>
            <Link :href="routes.workouts" class="btn-accent h-12 px-5 text-[20px] text-on-accent!">Choisir une séance</Link>
        </div>

        <InfiniteScroll v-else data="sessions" as="ol" only-next preserve-url :buffer="400" class="flex flex-col">
            <li v-for="(session, index) in entries" :key="session.id">
                <h2 v-if="startsWeek(index)" class="eyebrow pb-2.5 text-text-muted" :class="index === 0 ? '' : 'pt-5'">{{ session.week_label }}</h2>

                <Link
                    :href="routes.journalSession(session.id)"
                    class="mb-2 flex flex-col gap-2 rounded-[22px] bg-surface p-4 text-text! transition-colors hover:bg-surface-hover"
                    :class="session.completed ? '' : 'border-[1.5px] border-dashed border-line-strong bg-transparent!'"
                >
                    <div class="flex items-baseline justify-between gap-3 text-[12.5px] font-bold">
                        <span class="text-text-soft">{{ session.day_label }} · {{ session.time }}</span>
                        <span class="shrink-0 text-text-muted tabular-nums">{{ formatMinutesLong(session.minutes) }}</span>
                    </div>

                    <div class="flex items-start justify-between gap-3">
                        <h3 class="display min-w-0 text-[26px] leading-[0.95] font-extrabold text-balance" :class="session.completed ? '' : 'text-text-soft'">{{ session.name }}</h3>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" class="mt-1 shrink-0 text-text-faint" aria-hidden="true"><path d="M9 5l7 7-7 7" /></svg>
                    </div>

                    <span v-if="!session.completed" class="self-start rounded-md border border-prep/50 px-2 py-1 text-[11px] font-extrabold tracking-[0.06em] text-prep uppercase">Séance interrompue</span>

                    <p class="flex flex-wrap gap-x-3 gap-y-1 text-[13px] font-semibold text-text-muted tabular-nums">
                        <span><b class="font-extrabold text-text">{{ session.sets }}</b> série{{ session.sets > 1 ? 's' : '' }}</span>
                        <span v-if="session.tonnage > 0"><b class="font-extrabold text-text">{{ formatTonnage(session.tonnage) }}</b></span>
                        <span v-if="session.kcal !== null">{{ formatKcal(session.kcal) }}</span>
                        <span v-if="session.rpe">ressenti <b class="font-extrabold text-text">{{ session.rpe }}</b>/10</span>
                    </p>

                    <p v-if="session.exercises.length" class="truncate text-[12.5px] font-medium text-text-faint">{{ session.exercises.join(' · ') }}</p>
                </Link>
            </li>

            <template #loading>
                <p class="py-4 text-center text-[13px] font-semibold text-text-muted">Chargement…</p>
            </template>
        </InfiniteScroll>
    </div>

    <TabBar active="progress" />
</template>
