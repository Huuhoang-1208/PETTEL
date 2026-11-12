<script setup>
import { computed, ref, watch } from 'vue';
import { RouterLink } from 'vue-router';
import { listingService } from '../services/listingService';
import { formatVnd, labelArea, labelService } from '../../../../shared/utils/format';

const props = defineProps({ id: { type: String, required: true } });
const listing = ref(null); const reviews = ref(null); const error = ref(''); const reviewError = ref(''); const busy = ref(true);
const acceptance = computed(() => {
  try { return typeof listing.value?.acceptance_rules_json === 'string' ? JSON.parse(listing.value.acceptance_rules_json) : listing.value?.acceptance_rules_json; }
  catch { return null; }
});
let loadSequence = 0;
watch(() => props.id, async (id) => {
  const currentSequence = ++loadSequence; busy.value = true; error.value = ''; reviewError.value = ''; listing.value = null; reviews.value = null;
  try {
    listing.value = await listingService.get(id);
    try { reviews.value = await listingService.reviews(id); } catch (cause) { if (currentSequence === loadSequence) reviewError.value = cause.message; }
  } catch (cause) { if (currentSequence === loadSequence) error.value = cause.message; }
  finally { if (currentSequence === loadSequence) busy.value = false; }
}, { immediate: true });
</script>

<template>
  <section v-if="busy" class="state-card">Đang tải thông tin dịch vụ…</section><section v-else-if="error" class="state-card error-message" role="alert">{{ error }} <RouterLink to="/">Quay lại tìm dịch vụ</RouterLink></section>
  <div v-else-if="listing" class="detail-layout"><article class="detail-main"><RouterLink to="/" class="back-link">← Tìm dịch vụ</RouterLink><div class="detail-banner"><div><p class="eyebrow">{{ labelService(listing.service_type) }}</p><h1>{{ listing.title }}</h1><p>{{ labelArea(listing.area_code) }} · Đà Nẵng</p></div><div class="detail-emoji" aria-hidden="true">{{ listing.service_type === 'BOARDING' ? '🌙' : '🐾' }}</div></div><section v-if="listing.media?.length" class="detail-gallery"><img v-for="photo in listing.media" :key="photo.media_object_id" :src="photo.image_url" :alt="`Ảnh dịch vụ ${listing.title}`" loading="lazy" /></section><section class="card detail-section"><p class="eyebrow">GIỚI THIỆU</p><h2>Về dịch vụ này</h2><p class="body-copy">{{ listing.description || 'Chưa có nội dung giới thiệu.' }}</p></section><section class="card detail-section"><p class="eyebrow">ĐIỀU KIỆN NHẬN THÚ CƯNG</p><h2>Loài và kích cỡ phù hợp</h2><div v-if="acceptance" class="condition-chips"><span v-for="species in acceptance.species" :key="species">{{ ({ DOG: 'Chó', CAT: 'Mèo', OTHER: 'Khác' })[species] }}</span><span v-for="size in acceptance.sizes" :key="size">{{ ({ SMALL: 'Nhỏ', MEDIUM: 'Vừa', LARGE: 'Lớn' })[size] }}</span></div><p v-else class="muted">Điều kiện nhận thú cưng chưa được API trả về.</p></section><section class="card detail-section"><p class="eyebrow">ĐÁNH GIÁ</p><h2>Chia sẻ từ chủ nuôi</h2><p v-if="reviews?.review_count" class="rating">★ {{ reviews.rating_average }} · {{ reviews.review_count }} đánh giá</p><p v-else-if="reviewError" class="muted">Chưa tải được đánh giá. {{ reviewError }}</p><p v-else class="muted">Chưa có đánh giá công khai cho dịch vụ này.</p><article v-for="review in reviews?.data ?? []" :key="review.id" class="review-item"><strong>{{ '★'.repeat(review.rating) }}</strong><span v-if="review.is_demo" class="demo-tag">Đánh giá minh họa</span><p>{{ review.body || 'Không có nhận xét.' }}</p></article></section><p class="small-note">Thông tin listing và đánh giá được tải từ API. Lịch và giá cuối cùng cần được máy chủ kiểm tra khi báo giá. Backend chưa cung cấp hồ sơ caregiver công khai cho màn này.</p></article>
    <aside class="card booking-aside"><p class="eyebrow">GIÁ DỊCH VỤ</p><h2>{{ formatVnd(listing.price_vnd) }} <small>/ {{ listing.price_unit === 'NIGHT' ? 'đêm' : 'giờ' }}</small></h2><p class="muted">Tổng tiền được báo lại theo ngày, giờ và thú cưng bạn chọn.</p><RouterLink :to="`/book/${listing.id}`" class="button button-primary button-wide">Chọn thời gian đặt</RouterLink><p class="small-note">Gửi yêu cầu chưa đồng nghĩa với giữ chỗ. Người chăm cần chấp nhận yêu cầu.</p><RouterLink :to="{ name: 'reports', query: { target: 'listing_id', id: listing.id } }" class="text-action">Báo cáo dịch vụ này</RouterLink></aside></div>
</template>
