<script setup>
import { onMounted, onUnmounted, ref, watch } from 'vue';

/**
 * Les deux images d'un exercice — départ, arrivée — qui alternent comme une
 * mini-démo. Les deux sont chargées d'emblée et superposées : le passage de
 * l'une à l'autre ne clignote jamais.
 */
const props = defineProps({
    images: { type: Array, required: true },
    alt: { type: String, required: true },
    animate: { type: Boolean, default: true },
    interval: { type: Number, default: 1100 },
});

const frame = ref(0);
let timer = null;

function start() {
    stop();

    if (props.animate && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        timer = setInterval(() => (frame.value = 1 - frame.value), props.interval);
    }
}

function stop() {
    clearInterval(timer);
    timer = null;
}

watch(() => [props.animate, props.images[0]], start);
onMounted(start);
onUnmounted(stop);
</script>

<template>
    <div class="relative size-full overflow-hidden bg-surface-2">
        <img
            v-for="(src, index) in props.images"
            :key="src"
            :src="src"
            :alt="index === 0 ? props.alt : ''"
            :aria-hidden="index === 0 ? undefined : 'true'"
            class="absolute inset-0 size-full object-cover transition-opacity duration-300"
            :class="frame === index ? 'opacity-100' : 'opacity-0'"
            decoding="async"
            draggable="false"
        />
    </div>
</template>
