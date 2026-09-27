<script setup>
import { DialogContent, DialogDescription, DialogOverlay, DialogPortal, DialogRoot, DialogTitle } from 'reka-ui';

/**
 * Feuille qui monte du bas de l'écran : réglages, confirmation de suppression.
 */
const props = defineProps({
    open: { type: Boolean, required: true },
    title: { type: String, required: true },
    description: { type: String, default: null },
});

const emit = defineEmits(['update:open']);
</script>

<template>
    <DialogRoot :open="props.open" @update:open="emit('update:open', $event)">
        <DialogPortal>
            <DialogOverlay class="fixed inset-0 z-40 bg-[rgb(10_10_8/0.75)] backdrop-blur-[6px]" />
            <DialogContent
                class="animate-sheet fixed inset-x-0 bottom-0 z-50 mx-auto flex max-h-[88dvh] w-full max-w-[480px] flex-col gap-4 overflow-y-auto rounded-t-[28px] border border-b-0 border-line bg-bg px-5 pt-3 pb-[calc(env(safe-area-inset-bottom)+24px)] outline-none"
            >
                <div class="mx-auto h-1 w-9 rounded-full bg-line-strong" aria-hidden="true" />
                <div>
                    <DialogTitle class="display text-[32px] font-extrabold">{{ props.title }}</DialogTitle>
                    <DialogDescription v-if="props.description" class="mt-1.5 text-[14px] font-medium text-text-muted">
                        {{ props.description }}
                    </DialogDescription>
                </div>
                <slot />
            </DialogContent>
        </DialogPortal>
    </DialogRoot>
</template>
