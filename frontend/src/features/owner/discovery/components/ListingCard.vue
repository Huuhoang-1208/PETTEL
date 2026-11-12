<script setup>
import { computed } from 'vue';
import { RouterLink } from 'vue-router';
import catCard from '../../../../shared/assets/cat-card.svg';
import dogCard from '../../../../shared/assets/dog-card.svg';
import AppIcon from '../../../../shared/components/AppIcon.vue';
import { formatVnd, labelArea, labelService } from '../../../../shared/utils/format';

const props = defineProps({ listing: { type: Object, required: true } });
const acceptance = computed(() => {
  try {
    const value = typeof props.listing.acceptance_rules_json === 'string'
      ? JSON.parse(props.listing.acceptance_rules_json)
      : props.listing.acceptance_rules_json;
    return value && typeof value === 'object' ? value : {};
  } catch {
    return {};
  }
});
const acceptedSpecies = computed(() => (acceptance.value.species ?? []).map((species) => ({ DOG: 'Chó', CAT: 'Mèo', OTHER: 'Khác' })[species] ?? species));
const acceptedSizes = computed(() => (acceptance.value.sizes ?? []).map((size) => ({ SMALL: 'nhỏ', MEDIUM: 'vừa', LARGE: 'lớn' })[size] ?? size));
const photo = computed(() => props.listing.media?.find((media) => media.thumbnail_url || media.image_url) ?? null);
const fallbackImage = computed(() => acceptance.value.species?.length === 1 && acceptance.value.species[0] === 'CAT' ? catCard : dogCard);
const displayedPrice = computed(() => props.listing.price_total_vnd ?? props.listing.price_vnd);
</script>

<template>
  <article class="listing-card">
    <RouterLink :to="`/listings/${listing.id}`" class="listing-photo-link" :aria-label="`Xem dịch vụ ${listing.title}`">
      <div class="listing-photo">
        <img :src="photo?.thumbnail_url ?? photo?.image_url ?? fallbackImage" :alt="photo ? `Ảnh dịch vụ ${listing.title}` : 'Minh họa không gian chăm sóc thú cưng'" loading="lazy" />
        <span class="photo-badge">{{ labelService(listing.service_type) }}</span>
      </div>
    </RouterLink>

    <div class="listing-content">
      <h3><RouterLink :to="`/listings/${listing.id}`">{{ listing.title }}</RouterLink></h3>
      <div class="meta-line"><span aria-hidden="true"><AppIcon name="pin" :size="16" /></span>{{ labelArea(listing.area_code) }}</div>

      <div class="chips">
        <span class="chip">{{ acceptedSpecies.length ? acceptedSpecies.join(', ') : 'Thông tin loài chưa được cung cấp' }}<template v-if="acceptedSizes.length"> · {{ acceptedSizes.join(', ') }}</template></span>
        <span class="chip">{{ listing.review_count ? `★ ${listing.rating_average} · ${listing.review_count} đánh giá` : 'Chưa có đánh giá' }}</span>
      </div>

      <p class="listing-description">{{ listing.description || 'Chưa có mô tả cho dịch vụ này.' }}</p>

      <div class="listing-bottom">
        <div class="price">
          {{ formatVnd(displayedPrice) }}
          <small v-if="listing.price_total_vnd != null">tổng dự kiến</small>
          <small v-else>/ {{ listing.price_unit === 'NIGHT' ? 'đêm' : 'giờ' }}</small>
        </div>
        <RouterLink :to="`/listings/${listing.id}`" class="text-link">Xem dịch vụ <AppIcon name="arrow" :size="16" /></RouterLink>
      </div>
    </div>
  </article>
</template>
