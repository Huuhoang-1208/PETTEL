<script setup>
import StatusBadge from '../../../../shared/components/StatusBadge.vue';

defineProps({ pet: { type: Object, required: true } });
const emit = defineEmits(['view', 'edit', 'toggle-status', 'remove']);
</script>

<template><article class="card pet-card"><div class="pet-avatar" aria-hidden="true">{{ pet.species === 'CAT' ? '🐈' : pet.species === 'DOG' ? '🐕' : '🐾' }}</div><div class="pet-info"><div class="pet-title"><h2>{{ pet.name }}</h2><StatusBadge :status="pet.status" /></div><p>{{ pet.species === 'DOG' ? 'Chó' : pet.species === 'CAT' ? 'Mèo' : 'Khác' }}<span v-if="pet.size"> · {{ ({ SMALL: 'Nhỏ', MEDIUM: 'Vừa', LARGE: 'Lớn' })[pet.size] }}</span></p><p v-if="pet.special_needs" class="muted">Lưu ý: {{ pet.special_needs }}</p><div class="pet-actions"><button class="text-action" @click="emit('view', pet)">Chi tiết</button><button class="text-action" @click="emit('edit', pet)">Chỉnh sửa</button><button class="text-action" @click="emit('toggle-status', pet)">{{ pet.status === 'ACTIVE' ? 'Lưu trữ' : 'Kích hoạt lại' }}</button><button class="text-action text-danger" @click="emit('remove', pet)">Xóa</button></div></div></article></template>
