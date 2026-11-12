<script setup>
import { computed } from 'vue';
import { RouterLink } from 'vue-router';
import { formatVnd } from '../../../../shared/utils/format';

const props = defineProps({ listing: { type: Object, required: true }, pets: { type: Array, default: () => [] }, model: { type: Object, required: true }, quote: { type: Object, default: null }, state: { type: String, required: true }, quoteError: { type: String, default: '' }, submitting: { type: Boolean, default: false }, pageError: { type: String, default: '' }, valid: { type: Boolean, default: false }, validationMessage: { type: String, default: '' }, durationLabel: { type: String, default: '' } });
const emit = defineEmits(['check', 'submit', 'set-duration', 'update:field']);
const activePets = computed(() => props.pets.filter((pet) => pet.status === 'ACTIVE'));
const presets = computed(() => props.listing.service_type === 'BOARDING' ? [1, 2, 3] : [1, 2, 3, 4, 6, 8]);
const field = (key) => computed({ get: () => props.model[key], set: (value) => emit('update:field', { field: key, value }) });
const petId = field('petId'); const startAt = field('startAt'); const endAt = field('endAt'); const instructions = field('instructions');
</script>

<template><form class="card booking-form" @submit.prevent="emit('check')"><h2>Thông tin đặt dịch vụ</h2>
  <label>Thú cưng<select v-model="petId" required><option value="" disabled>Chọn thú cưng</option><option v-for="pet in activePets" :key="pet.id" :value="pet.id">{{ pet.name }}</option></select></label><p v-if="!activePets.length" class="small-note">Bạn cần có hồ sơ thú cưng đang hoạt động. <RouterLink to="/pets">Thêm hồ sơ</RouterLink></p>
  <div class="form-row"><label>Ngày và giờ bắt đầu<input v-model="startAt" type="datetime-local" step="1800" required /></label><label>Ngày và giờ kết thúc<input v-model="endAt" type="datetime-local" step="1800" required /></label></div><p class="small-note">Thời gian được hiểu theo giờ Việt Nam (GMT+7), chọn theo bước 30 phút. {{ listing.service_type === 'BOARDING' ? 'Lưu trú: 12 giờ đến 30 ngày; giá tính theo block 24 giờ.' : 'Chăm ban ngày: 1–12 giờ và phải trong cùng ngày.' }} Lịch chỉ được xác nhận qua báo giá máy chủ.</p><p v-if="validationMessage" class="error-message" role="status">{{ validationMessage }}</p>
  <fieldset><legend>{{ listing.service_type === 'BOARDING' ? 'Chọn nhanh số đêm' : 'Chọn nhanh thời lượng' }}</legend><div class="preset-row"><button v-for="duration in presets" :key="duration" type="button" class="preset" @click="emit('set-duration', duration)">{{ duration }} {{ listing.service_type === 'BOARDING' ? 'đêm' : 'giờ' }}</button></div></fieldset>
  <label>Ghi chú cho người chăm (không bắt buộc)<textarea v-model.trim="instructions" rows="3" maxlength="2000" placeholder="Chia sẻ lưu ý cần thiết cho lần chăm sóc này" /></label>
  <div class="quote-box"><div v-if="state === 'idle'">Chưa kiểm tra khoảng thời gian. Giá sẽ do máy chủ tính.</div><div v-else-if="state === 'checking'" role="status">Đang kiểm tra lịch và giá…</div><div v-else-if="state === 'unavailable'" class="error-message" role="alert">{{ quoteError }}</div><div v-else><strong class="available">✓ Máy chủ đã báo giá cho khoảng thời gian này</strong><div class="summary-line"><span>Thời lượng</span><strong>{{ durationLabel }}</strong></div><div class="summary-line"><span>Tạm tính ({{ quote.quantity }} {{ quote.price_unit === 'NIGHT' ? 'đêm' : 'giờ' }})</span><strong>{{ formatVnd(quote.price_total_vnd) }}</strong></div><p class="small-note">Giá được tính lại khi gửi yêu cầu. Đây chưa phải xác nhận giữ chỗ.</p></div></div>
  <p v-if="pageError" class="error-message" role="alert">{{ pageError }}</p><div class="booking-actions"><button type="submit" class="button button-soft" :disabled="state === 'checking' || !valid">{{ state === 'checking' ? 'Đang kiểm tra…' : 'Kiểm tra lịch và giá' }}</button><button type="button" class="button button-primary" :disabled="!quote || submitting || !valid" @click="emit('submit')">{{ submitting ? 'Đang gửi…' : 'Gửi yêu cầu đặt dịch vụ' }}</button></div>
</form></template>
