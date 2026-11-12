<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { RouterLink } from 'vue-router';
import { petService } from '../../pets/services/petService';
import { listingService } from '../../discovery/services/listingService';
import BookingRequestForm from '../components/BookingRequestForm.vue';
import { useBookingForm } from '../composables/useBookingForm';
import { useBookingQuote } from '../composables/useBookingQuote';
import { bookingService } from '../services/bookingService';
import { dateTimeWithOffset, formatVnd, labelBookingStatus, labelService } from '../../../../shared/utils/format';

const props = defineProps({ listingId: { type: String, required: true } });
const listing = ref(null); const pets = ref([]); const pageError = ref(''); const loading = ref(true); const submitting = ref(false); const created = ref(null);
const cancelling = ref(false); const cancelResult = ref(null); const cancelIdempotencyKey = ref('');
const model = reactive({ petId: '', startAt: '', endAt: '', instructions: '' });
const idempotencyKey = ref('');
const serviceType = computed(() => listing.value?.service_type ?? 'DAYCARE');
const form = useBookingForm(serviceType);
watch([form.petId, form.startAt, form.endAt, form.instructions], ([petId, startAt, endAt, instructions]) => Object.assign(model, { petId, startAt, endAt, instructions }), { immediate: true });
watch(() => model.petId, (value) => { if (form.petId.value !== value) form.petId.value = value; });
watch(() => model.startAt, (value) => { if (form.startAt.value !== value) form.startAt.value = value; });
watch(() => model.endAt, (value) => { if (form.endAt.value !== value) form.endAt.value = value; });
watch(() => model.instructions, (value) => { if (form.instructions.value !== value) form.instructions.value = value; });
watch([form.petId, form.startAt, form.endAt, form.instructions], () => { idempotencyKey.value = ''; }, { flush: 'sync' });
const quoteModel = {
  petId: form.petId,
  listingId: computed(() => props.listingId),
  startAt: form.startAt,
  endAt: form.endAt,
};
const quoteState = useBookingQuote(quoteModel);
const formValid = computed(() => form.valid.value && Boolean(listing.value));
const canCancel = computed(() => created.value && ['REQUESTED', 'AWAITING_PAYMENT', 'CONFIRMED'].includes(created.value.status) && (!created.value.start_at || new Date(created.value.start_at) > new Date()));

async function loadBookingInputs() {
  loading.value = true; pageError.value = ''; created.value = null; idempotencyKey.value = ''; cancelResult.value = null; cancelIdempotencyKey.value = '';
  try {
    [listing.value, pets.value] = await Promise.all([listingService.get(props.listingId), petService.list()]);
    form.petId.value = pets.value.find((pet) => pet.status === 'ACTIVE')?.id ?? '';
  } catch (cause) { pageError.value = cause.message; }
  finally { loading.value = false; }
}
watch(() => props.listingId, loadBookingInputs, { immediate: true });

async function requestQuote() {
  if (!formValid.value) return;
  await quoteState.check();
}

function updateBookingField({ field, value }) {
  if (['petId', 'startAt', 'endAt', 'instructions'].includes(field)) model[field] = value;
}

async function submitBooking() {
  if (!quoteState.quote.value || !formValid.value || submitting.value) return;
  submitting.value = true; pageError.value = '';
  if (!idempotencyKey.value) idempotencyKey.value = `owner-booking-${crypto.randomUUID().toLowerCase()}`;
  try {
    created.value = await bookingService.create({ pet_id: form.petId.value, listing_id: props.listingId, start_at: dateTimeWithOffset(form.startAt.value), end_at: dateTimeWithOffset(form.endAt.value), instructions: form.instructions.value.trim() || null }, idempotencyKey.value);
  } catch (cause) { pageError.value = cause.message; quoteState.quote.value = null; }
  finally { submitting.value = false; }
}

async function cancelBooking() {
  if (!canCancel.value || cancelling.value || !window.confirm('Bạn muốn gửi yêu cầu hủy booking này? Máy chủ sẽ kiểm tra chính sách và trạng thái hiện tại.')) return;
  cancelling.value = true; pageError.value = '';
  if (!cancelIdempotencyKey.value) cancelIdempotencyKey.value = `owner-cancel-${crypto.randomUUID().toLowerCase()}`;
  try {
    cancelResult.value = await bookingService.cancel(created.value.id, cancelIdempotencyKey.value);
    created.value = cancelResult.value.booking ?? cancelResult.value;
  } catch (cause) { pageError.value = cause.message; }
  finally { cancelling.value = false; }
}
</script>

<template>
  <div v-if="loading" class="state-card" role="status">Đang tải dịch vụ và hồ sơ thú cưng…</div>
  <section v-else-if="pageError && !listing" class="state-card error-message" role="alert">{{ pageError }} <RouterLink to="/">Quay lại</RouterLink></section>
  <section v-else-if="created" class="success-card card"><span class="success-icon">✓</span><p class="eyebrow">API ĐÃ XÁC NHẬN</p><h1>Yêu cầu đặt dịch vụ đã được gửi</h1><p>Trạng thái: <strong>{{ labelBookingStatus(created.status) }}</strong>. {{ created.status === 'REQUESTED' ? 'Yêu cầu chưa giữ chỗ; người chăm cần chấp nhận trước.' : 'Trạng thái được đọc từ phản hồi booking của máy chủ.' }}</p><div class="summary-line"><span>Mã yêu cầu</span><strong>#{{ created.id }}</strong></div><div class="summary-line"><span>Tổng giá được lưu khi gửi</span><strong>{{ formatVnd(created.price_snapshot_vnd) }}</strong></div><p v-if="cancelResult?.cancellation" class="notice-message" role="status">Yêu cầu hủy đã được tiếp nhận, đang chờ hệ thống hoàn tất mô phỏng. Booking chưa được đánh dấu đã hủy.</p><p v-else-if="created.status === 'CANCELLED'" class="notice-message" role="status">Máy chủ xác nhận booking đã hủy.</p><p v-if="pageError" class="error-message" role="alert">{{ pageError }}</p><div class="success-actions"><RouterLink :to="`/bookings/${created.id}/messages`" class="button button-primary">Mở tin nhắn booking</RouterLink><RouterLink :to="`/bookings/${created.id}/checkout`" class="button button-soft">Thanh toán mô phỏng (nếu đã được chấp nhận)</RouterLink><RouterLink :to="`/bookings/${created.id}/confirm-completion`" class="button button-soft">Xác nhận hoàn tất (nếu đang chờ)</RouterLink><RouterLink :to="`/bookings/${created.id}/review`" class="button button-soft">Đánh giá (nếu đã hoàn tất)</RouterLink><RouterLink :to="`/listings/${listing.id}`" class="button button-soft">Quay lại dịch vụ</RouterLink></div><button v-if="canCancel" class="text-action text-danger" :disabled="cancelling" @click="cancelBooking">{{ cancelling ? 'Đang gửi yêu cầu hủy…' : 'Hủy yêu cầu đặt dịch vụ' }}</button><p class="small-note">Các thao tác chỉ gửi lên máy chủ khi bạn bấm; API sẽ kiểm tra lại trạng thái và quyền, có thể từ chối nếu booking chưa đủ điều kiện. Chưa có API đọc danh sách booking nên không thể xem lại trạng thái sau khi rời trang.</p></section>
  <div v-else-if="listing" class="booking-page"><div><RouterLink :to="`/listings/${listing.id}`" class="back-link">← Quay lại dịch vụ</RouterLink><p class="eyebrow">ĐẶT DỊCH VỤ</p><h1>{{ listing.title }}</h1><p class="muted">{{ labelService(listing.service_type) }}</p>
    <BookingRequestForm :listing="listing" :pets="pets" :model="model" :quote="quoteState.quote.value" :state="quoteState.state.value" :quote-error="quoteState.error.value" :submitting="submitting" :page-error="pageError" :valid="formValid" :validation-message="form.validationMessage.value" :duration-label="form.durationLabel.value" @check="requestQuote" @submit="submitBooking" @set-duration="form.setDuration" @update:field="updateBookingField" />
    </div><aside class="card booking-aside"><p class="eyebrow">THÔNG TIN DỊCH VỤ</p><h2>{{ formatVnd(listing.price_vnd) }} <small>/ {{ listing.price_unit === 'NIGHT' ? 'đêm' : 'giờ' }}</small></h2><p>{{ listing.description }}</p><p class="small-note">Báo giá máy chủ là căn cứ cho tạm tính. Gửi yêu cầu chưa giữ chỗ; người chăm cần chấp nhận trước khi thanh toán sandbox.</p></aside></div>
</template>
