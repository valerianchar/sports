<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import Stepper from './Stepper.vue';
import { machineFields, stepSetting } from '../workout';

/**
 * Les réglages d'une machine de cardio dans l'éditeur : vitesse et
 * inclinaison du tapis, niveau du vélo ou du rameur.
 */
const props = defineProps({
    item: { type: Object, required: true },
    exercise: { type: Object, required: true },
});

const emit = defineEmits(['update']);

const machine = computed(() => usePage().props.machine_settings);
const fields = computed(() => machineFields(props.exercise, machine.value));
</script>

<template>
    <div v-if="fields.length" class="grid grid-cols-2 gap-x-3 gap-y-3.5 rounded-2xl bg-bg p-3">
        <Stepper
            v-for="field in fields"
            :key="field"
            :label="machine.fields[field].unit ? `${machine.fields[field].label} (${machine.fields[field].unit})` : machine.fields[field].label"
            :display="props.item[field] === null || props.item[field] === undefined ? '—' : Number(props.item[field]).toLocaleString('fr-FR', { maximumFractionDigits: 1 })"
            :can-decrease="props.item[field] !== null && props.item[field] > machine.fields[field].min"
            :can-increase="props.item[field] === null || props.item[field] < machine.fields[field].max"
            @decrease="emit('update', { [field]: stepSetting(field, props.item[field], -1, machine) })"
            @increase="emit('update', { [field]: stepSetting(field, props.item[field], 1, machine) })"
        />
    </div>
</template>
