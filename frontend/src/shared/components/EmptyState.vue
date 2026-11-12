<script setup>
import AppIcon from './AppIcon.vue';

defineProps({ title: { type: String, required: true }, description: { type: String, default: '' }, icon: { type: String, default: 'search' } });
</script>
<template><section class="empty-card card"><span class="empty-icon"><AppIcon :name="icon" :size="28" /></span><h2>{{ title }}</h2><p v-if="description" class="muted">{{ description }}</p><slot /></section></template>
