<script setup>
import { ref } from 'vue';
import { RouterLink } from 'vue-router';
import { bookingService } from '../../bookings/services/bookingService';
import { labelWorkflowStatus } from '../../../../shared/utils/format';

const props = defineProps({ bookingId: { type: String, required: true } });
const rating = ref(5); const body = ref(''); const busy = ref(false); const error = ref(''); const review = ref(null);
async function submit() {
  if (busy.value || review.value) return;
  busy.value = true; error.value = '';
  try { review.value = await bookingService.review(props.bookingId, { rating: rating.value, body: body.value.trim() || null }); }
  catch (cause) { error.value = cause.message; }
  finally { busy.value = false; }
}
</script>

<template><section class="auth-wrap"><form class="card auth-card review-form" @submit.prevent="submit"><p class="eyebrow">ĐÁNH GIÁ SAU KHI HOÀN TẤT</p><h1>Chia sẻ trải nghiệm</h1><p class="muted">Booking #{{ bookingId }} · Chỉ Owner của booking hoàn tất mới có thể gửi đánh giá.</p><label>Điểm đánh giá<select v-model.number="rating"><option :value="5">5 — Rất tốt</option><option :value="4">4 — Tốt</option><option :value="3">3 — Bình thường</option><option :value="2">2 — Chưa tốt</option><option :value="1">1 — Không hài lòng</option></select></label><label>Nhận xét (không bắt buộc)<textarea v-model.trim="body" maxlength="1000" rows="5" placeholder="Chia sẻ điều hữu ích cho các chủ nuôi khác" /></label><small>{{ body.length }}/1000 ký tự</small><p v-if="error" class="error-message" role="alert">{{ error }}</p><div v-if="review" class="notice-message" role="status">Đánh giá #{{ review.id }} đã được backend ghi nhận. Trạng thái: {{ labelWorkflowStatus(review.status) }}.</div><button v-else type="submit" class="button button-primary" :disabled="busy">{{ busy ? 'Đang gửi…' : 'Gửi đánh giá' }}</button><p class="small-note">Không gửi thông tin cá nhân, y tế nhạy cảm hoặc chi tiết liên hệ. Nếu booking chưa hoàn tất, backend sẽ từ chối.</p><RouterLink :to="`/listings/${review?.listing_id ?? ''}`" v-if="review?.listing_id" class="link-button">Xem dịch vụ và đánh giá</RouterLink></form></section></template>
