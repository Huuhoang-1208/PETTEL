<script setup>
import { ref } from 'vue';
import { RouterLink } from 'vue-router';
import { bookingService } from '../services/bookingService';

const props = defineProps({ bookingId: { type: String, required: true } });
const busy = ref(false); const error = ref(''); const result = ref(null);
async function confirm() {
  if (busy.value || result.value) return;
  busy.value = true; error.value = '';
  try { result.value = await bookingService.confirmCompletion(props.bookingId); }
  catch (cause) { error.value = cause.message; }
  finally { busy.value = false; }
}
</script>

<template><section class="checkout-wrap"><article class="card checkout-card"><p class="eyebrow">HOÀN TẤT CHĂM SÓC</p><h1>Xác nhận booking #{{ bookingId }}</h1><p>Chỉ xác nhận sau khi bạn đã kiểm tra dịch vụ và cập nhật chăm sóc. API sẽ kiểm tra booking thuộc bạn và đang ở trạng thái chờ xác nhận.</p><p class="sandbox-banner">Xác nhận sẽ yêu cầu hệ thống xử lý thanh toán mô phỏng. Booking chỉ chuyển sang hoàn tất sau khi thao tác release thành công.</p><p v-if="error" class="error-message" role="alert">{{ error }}</p>
  <div v-if="result" class="quote-box" role="status"><strong v-if="result.status === 'COMPLETED' || result.booking?.status === 'COMPLETED'" class="available">Máy chủ xác nhận booking đã hoàn tất.</strong><strong v-else>Máy chủ đã nhận yêu cầu xác nhận. Trạng thái hiện tại: {{ result.booking?.status ?? result.status ?? 'đang chờ xử lý' }}.</strong><p v-if="result.release">Trạng thái release mô phỏng: {{ result.release.status }}.</p><RouterLink v-if="result.status === 'COMPLETED' || result.booking?.status === 'COMPLETED'" :to="`/bookings/${bookingId}/review`" class="button button-primary">Viết đánh giá</RouterLink></div>
  <button v-else class="button button-primary" :disabled="busy" @click="confirm">{{ busy ? 'Đang gửi yêu cầu…' : 'Xác nhận hoàn tất' }}</button><p class="small-note">Không có booking read API để kiểm tra trạng thái trước. Nếu booking chưa đủ điều kiện, thao tác sẽ bị backend từ chối và trạng thái không được giả định.</p><RouterLink :to="`/bookings/${bookingId}/messages`" class="text-action">Xem tin nhắn và cập nhật chăm sóc</RouterLink></article></section></template>
