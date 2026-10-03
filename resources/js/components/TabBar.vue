<script setup>
import { Link } from '@inertiajs/vue3';
import { routes } from '../routes';

const props = defineProps({
    active: { type: String, required: true },
});

const tabs = [
    { key: 'home', label: 'Séances', href: routes.home },
    { key: 'library', label: 'Exercices', href: routes.library },
    { key: 'progress', label: 'Progrès', href: routes.progress },
];
</script>

<template>
    <nav
        class="grid shrink-0 grid-cols-3 border-t border-divider bg-bg px-6 pt-2.5 pb-[calc(env(safe-area-inset-bottom)+14px)]"
        aria-label="Navigation principale"
    >
        <Link
            v-for="tab in tabs"
            :key="tab.key"
            :href="tab.href"
            class="flex flex-col items-center gap-1 text-[11px] font-extrabold"
            :class="props.active === tab.key ? 'text-accent' : 'text-text-faint hover:text-text-soft'"
            :aria-current="props.active === tab.key ? 'page' : undefined"
            preserve-state
        >
            <svg v-if="tab.key === 'home'" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true">
                <rect x="4" y="4" width="16" height="16" rx="4" /><path d="M8 9h8M8 13h8M8 17h5" />
            </svg>
            <svg v-else-if="tab.key === 'library'" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true">
                <path d="M3 9v6M6.5 6.5v11M17.5 6.5v11M21 9v6M6.5 12h11" />
            </svg>
            <svg v-else width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M4 20V10M10 20V4M16 20v-7M22 20H2" />
            </svg>
            {{ tab.label }}
        </Link>
    </nav>
</template>
