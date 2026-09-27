<script setup>
import { computed, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import ExerciseLibrary from '../../components/ExerciseLibrary.vue';
import ExerciseSheet from '../../components/ExerciseSheet.vue';
import TabBar from '../../components/TabBar.vue';

const props = defineProps({
    exercises: { type: Array, required: true },
    groups: { type: Array, required: true },
});

const detail = ref(null);
const detailExercise = computed(() => props.exercises.find((e) => e.slug === detail.value));
</script>

<template>
    <Head title="Exercices" />

    <ExerciseLibrary :exercises="props.exercises" :groups="props.groups" @info="detail = $event" />
    <TabBar active="library" />

    <ExerciseSheet v-if="detailExercise" :exercise="detailExercise" @close="detail = null" />
</template>
