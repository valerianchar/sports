<script setup>
import { Link } from '@inertiajs/vue3';
import { routes } from '../routes';

const props = defineProps({
    active: { type: String, required: true },
});

const tabs = [
    { key: 'home', label: "Aujourd'hui", href: routes.home, icon: 'M4 11l8-7 8 7v8a1 1 0 0 1-1 1h-4v-6H9v6H5a1 1 0 0 1-1-1z' },
    { key: 'workouts', label: 'Séances', href: routes.workouts, icon: 'M4 4h16v16H4zM8 9h8M8 13h8M8 17h5' },
    { key: 'library', label: 'Exercices', href: routes.library, icon: 'M3 9v6M6.5 6.5v11M17.5 6.5v11M21 9v6M6.5 12h11' },
    { key: 'progress', label: 'Progrès', href: routes.progress, icon: 'M4 20V10M10 20V4M16 20v-7M22 20H2' },
];
</script>

<template>
    <nav
        class="grid shrink-0 grid-cols-4 border-t border-divider bg-bg px-2 pt-2 pb-[calc(env(safe-area-inset-bottom)+12px)]"
        aria-label="Navigation principale"
    >
        <Link
            v-for="tab in tabs"
            :key="tab.key"
            :href="tab.href"
            class="flex flex-col items-center gap-1 rounded-xl py-1 text-[11px] font-extrabold"
            :class="props.active === tab.key ? 'text-accent' : 'text-text-faint hover:text-text-soft'"
            :aria-current="props.active === tab.key ? 'page' : undefined"
        >
            <span class="flex h-7 w-12 items-center justify-center rounded-full" :class="props.active === tab.key ? 'bg-accent/15' : ''">
                <svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path :d="tab.icon" />
                </svg>
            </span>
            {{ tab.label }}
        </Link>
    </nav>
</template>
