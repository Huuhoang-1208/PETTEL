<script setup>
import { ref, watch } from 'vue';
import { RouterLink } from 'vue-router';
import { bookingService } from '../services/bookingService';
import { formatVnd, labelWorkflowStatus } from '../../../../shared/utils/format';

const props = defineProps({ bookingId: { type: String, required: true } });
const attempt = ref(null); const paymentResult = ref(null); const busy = ref(false); const error = ref(''); const requestKey = ref(''); const simulationLocked = ref(false);
watch(() => props.bookingId, () => { attempt.value = null; paymentResult.value = null; error.value = ''; requestKey.value = ''; simulationLocked.value = false; }, { immediate: true });
async function startPayment() {
  if (busy.value) return;
  busy.value = true; error.value = '';
  if (!requestKey.value) requestKey.value = `owner-payment-${crypto.randomUUID().toLowerCase()}`;
  try { attempt.value = await bookingService.startSandboxPayment(props.bookingId, requestKey.value); paymentResult.value = null; simulationLocked.value = false; }
  catch (cause) { error.value = cause.message; }
  finally { busy.value = false; }
}
async function simulate(result) {
  if (!attempt.value || busy.value) return;
  busy.value = true; error.value = ''; simulationLocked.value = true;
  try {
    paymentResult.value = await bookingService.simulateSandboxPayment(props.bookingId, attempt.value.id, result);
    if (paymentResult.value.attempt_status === 'FAILED') { attempt.value = { ...attempt.value, status: 'FAILED' }; requestKey.value = ''; }
    if (paymentResult.value.attempt_status === 'AUTHORIZED') attempt.value = { ...attempt.value, status: 'AUTHORIZED' };
    if (result === 'TIMEOUT' && paymentResult.value.status === 'PENDING') simulationLocked.value = false;
  } catch (cause) { error.value = cause.message; }
  finally { busy.value = false; }
}
</script>

<template><section class="checkout-wrap"><RouterLink to="/account" class="back-link">← Tài khoản</RouterLink><article class="card checkout-card"><p class="eyebrow">THANH TOÁN MÔ PHỎNG</p><h1>Thanh toán booking #{{ bookingId }}</h1><div class="sandbox-banner">Đây là mô phỏng trong môi trường thử nghiệm. Không nhập thông tin thẻ và không có khoản tiền thật bị trừ.</div>
  <p class="muted">Chỉ booking đang ở trạng thái chờ thanh toán mới có thể bắt đầu. Máy chủ sẽ kiểm tra trạng thái, thời hạn và quyền sở hữu.</p>
  <p v-if="error" class="error-message" role="alert">{{ error }}</p>
  <div v-if="attempt" class="payment-attempt"><div class="summary-line"><span>Số tiền từ máy chủ</span><strong>{{ formatVnd(attempt.amount_vnd) }}</strong></div><div class="summary-line"><span>Lần thử</span><strong>{{ attempt.attempt_no }}</strong></div><div class="summary-line"><span>Hạn thanh toán</span><strong>{{ attempt.payment_due_at ?? 'Không có hạn trả về' }}</strong></div><p class="status-pill" :class="attempt.status === 'AUTHORIZED' ? 'status-active' : ''">{{ labelWorkflowStatus(attempt.status) }}</p></div>
  <div v-if="attempt?.status === 'PENDING' && !simulationLocked" class="checkout-actions"><button class="button button-primary" :disabled="busy" @click="simulate('SUCCESS')">Mô phỏng thành công</button><button class="button button-soft" :disabled="busy" @click="simulate('FAILURE')">Mô phỏng thất bại</button><button class="button button-soft" :disabled="busy" @click="simulate('TIMEOUT')">Mô phỏng hết thời gian chờ</button></div><p v-if="attempt?.status === 'PENDING' && simulationLocked && error" class="small-note">Không rõ máy chủ đã nhận kết quả mô phỏng hay chưa. Đã khóa gửi lại để tránh tạo callback trùng; không có API đọc attempt để đối chiếu.</p>
  <div v-if="paymentResult" class="quote-box" role="status"><strong v-if="paymentResult.booking_status === 'CONFIRMED'" class="available">Máy chủ đã xác nhận booking sau thanh toán mô phỏng.</strong><strong v-else-if="paymentResult.attempt_status === 'FAILED'" class="error-message">Máy chủ ghi nhận lần thanh toán mô phỏng thất bại. Có thể thử lại nếu còn hạn.</strong><strong v-else>Máy chủ đã nhận kết quả mô phỏng: {{ labelWorkflowStatus(paymentResult.result ?? paymentResult.processing_status) }}.</strong></div>
  <button v-if="!attempt || attempt.status === 'FAILED'" class="button button-primary button-wide" :disabled="busy" @click="startPayment">{{ busy ? 'Đang gửi tới máy chủ…' : attempt ? 'Thử thanh toán lại' : 'Bắt đầu thanh toán mô phỏng' }}</button>
  <p class="small-note">Nếu booking vẫn đang chờ người chăm, API sẽ từ chối thao tác này. Danh sách booking để biết khi nào được chấp nhận hiện chưa có API Owner.</p>
  </article></section></template>
