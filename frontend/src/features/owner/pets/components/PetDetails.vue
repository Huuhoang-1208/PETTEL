<script setup>
import StatusBadge from '../../../../shared/components/StatusBadge.vue';

defineProps({ pet: { type: Object, default: null }, loading: { type: Boolean, default: false }, error: { type: String, default: '' } });
const emit = defineEmits(['close']);
const labels = { species: 'Loài', size: 'Kích cỡ', sex: 'Giới tính', birth_date: 'Ngày sinh', age_note: 'Ghi chú tuổi', diet_notes: 'Chế độ ăn', vaccination_note: 'Tiêm phòng (tự khai)', temperament: 'Tính cách', special_needs: 'Nhu cầu đặc biệt' };
const options = { DOG: 'Chó', CAT: 'Mèo', OTHER: 'Khác', SMALL: 'Nhỏ', MEDIUM: 'Vừa', LARGE: 'Lớn', MALE: 'Đực', FEMALE: 'Cái', UNKNOWN: 'Chưa rõ' };
</script>

<template><div class="modal-backdrop" @click.self="emit('close')" @keydown.esc="emit('close')"><section class="card pet-details" role="dialog" aria-modal="true" :aria-label="`Thông tin ${pet?.name ?? 'thú cưng'}`"><div class="modal-heading"><div><p class="eyebrow">CHI TIẾT HỒ SƠ</p><h2>{{ pet?.name ?? 'Đang tải…' }}</h2></div><button type="button" class="icon-button" aria-label="Đóng chi tiết" @click="emit('close')">×</button></div><div v-if="loading" class="state-card" role="status">Đang tải chi tiết…</div><p v-else-if="error" class="error-message" role="alert">{{ error }}</p><template v-else-if="pet"><StatusBadge :status="pet.status" /><dl class="pet-detail-list"><template v-for="(label, field) in labels" :key="field"><dt>{{ label }}</dt><dd>{{ options[pet[field]] ?? pet[field] ?? 'Chưa cung cấp' }}</dd></template></dl><p class="small-note">Thông tin được lấy từ hồ sơ riêng của bạn. Ghi chú cá nhân chỉ được gửi trong snapshot booking khi bạn đặt dịch vụ.</p></template></section></div></template>
