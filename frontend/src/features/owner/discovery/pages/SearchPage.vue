<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { listingService } from '../services/listingService';
import { petService } from '../../pets/services/petService';
import { useAuthSession } from '../../../../app/composables/useAuthSession';
import EmptyState from '../../../../shared/components/EmptyState.vue';
import AppIcon from '../../../../shared/components/AppIcon.vue';
import { dateTimeWithOffset, parseVnDateTime } from '../../../../shared/utils/format';
import DiscoveryFilters from '../components/DiscoveryFilters.vue';
import ListingCard from '../components/ListingCard.vue';
import ServiceHero from '../components/ServiceHero.vue';

const filters = reactive({
  q: '', service_type: '', area_code: '', pet_id: '', smart_match: false,
  start_at: '', end_at: '', min_price_vnd: '', max_price_vnd: '', page: 1,
});
const listings = ref([]);
const pets = ref([]);
const meta = ref(null);
const busy = ref(false);
const error = ref('');
const validationMessage = ref('');
const requestSequence = ref(0);
const auth = useAuthSession();
const authenticated = computed(() => auth.isAuthenticated.value);
const matchSummary = computed(() => {
  if (filters.smart_match && filters.pet_id) return filters.start_at && filters.end_at
    ? 'Đối chiếu loài và kích cỡ theo hồ sơ thú cưng; lịch và giá được máy chủ kiểm tra theo khoảng thời gian đã chọn.'
    : 'Đối chiếu loài và kích cỡ theo hồ sơ thú cưng. Chọn khoảng thời gian để máy chủ kiểm tra lịch và giá.';
  if (filters.start_at && filters.end_at) return 'Kết quả đã được lọc theo khoảng thời gian và tổng giá do máy chủ tính.';
  return '';
});

function updateFilter({ field, value }) {
  if (Object.hasOwn(filters, field)) filters[field] = value;
}

function validateFilters() {
  const hasStart = Boolean(filters.start_at);
  const hasEnd = Boolean(filters.end_at);
  const hasBudget = filters.min_price_vnd !== '' || filters.max_price_vnd !== '';
  if (hasStart !== hasEnd) return 'Hãy nhập cả thời gian bắt đầu và kết thúc.';
  if (hasBudget && (!hasStart || !hasEnd)) return 'Cần chọn đủ khoảng thời gian để lọc theo tổng giá.';
  if (filters.smart_match && !filters.pet_id) return 'Hãy chọn hồ sơ thú cưng để dùng Smart Match.';
  if (hasStart && hasEnd) {
    try {
      const start = parseVnDateTime(filters.start_at);
      const end = parseVnDateTime(filters.end_at);
      if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime())) return 'Ngày và giờ chưa hợp lệ.';
      if (end <= start) return 'Thời gian kết thúc phải sau thời gian bắt đầu.';
    } catch {
      return 'Ngày và giờ chưa hợp lệ.';
    }
  }
  if (filters.min_price_vnd !== '' && filters.max_price_vnd !== '' && Number(filters.min_price_vnd) > Number(filters.max_price_vnd)) {
    return 'Mức giá tối đa cần lớn hơn hoặc bằng mức giá tối thiểu.';
  }
  return '';
}

function apiFilters(page) {
  const params = {
    q: filters.q.trim(),
    service_type: filters.service_type,
    area_code: filters.area_code,
    page,
    per_page: 9,
  };
  if (filters.smart_match) params.pet_id = filters.pet_id;
  if (filters.start_at && filters.end_at) {
    params.start_at = dateTimeWithOffset(filters.start_at);
    params.end_at = dateTimeWithOffset(filters.end_at);
  }
  if (filters.min_price_vnd !== '') params.min_price_vnd = filters.min_price_vnd;
  if (filters.max_price_vnd !== '') params.max_price_vnd = filters.max_price_vnd;
  return params;
}

async function search(page = 1) {
  validationMessage.value = validateFilters();
  if (validationMessage.value) return;
  const currentRequest = ++requestSequence.value;
  filters.page = page;
  busy.value = true;
  error.value = '';
  try {
    const result = await listingService.search(apiFilters(page));
    if (currentRequest !== requestSequence.value) return;
    listings.value = result.items;
    meta.value = result.meta ?? null;
  } catch (cause) {
    if (currentRequest !== requestSequence.value) return;
    error.value = cause.message;
    listings.value = [];
    meta.value = null;
  } finally {
    if (currentRequest === requestSequence.value) busy.value = false;
  }
}

function clearSearch() {
  Object.assign(filters, {
    q: '', service_type: '', area_code: '', pet_id: '', smart_match: false,
    start_at: '', end_at: '', min_price_vnd: '', max_price_vnd: '', page: 1,
  });
  void search(1);
}

onMounted(async () => {
  void search();
  try {
    pets.value = await petService.list();
  } catch {
    // Guests can discover public listings without loading private pet profiles.
  }
});
</script>

<template>
  <ServiceHero />

  <DiscoveryFilters
    :model="filters"
    :pets="pets"
    :authenticated="authenticated"
    :validation-message="validationMessage"
    @search="search(1)"
    @update:filter="updateFilter"
  />

  <section id="results" class="section discovery-results" aria-labelledby="results-title">
    <div class="section-heading">
      <div>
        <h2 id="results-title">Dịch vụ đang nhận yêu cầu</h2>
        <p>{{ meta?.total ?? listings.length }} dịch vụ · chỉ hiển thị dịch vụ đang hoạt động</p>
      </div>
      <span class="soft-tag"><AppIcon name="pin" :size="15" />Đà Nẵng</span>
    </div>

    <div v-if="matchSummary" class="notice info match-notice" role="status">
      <span class="notice-icon" aria-hidden="true">✓</span>
      <div><strong>Đối chiếu theo quy tắc.</strong> {{ matchSummary }}</div>
    </div>
    <p v-if="error" class="state-card error-message" role="alert">{{ error }} <button class="link-button" type="button" @click="search(filters.page)">Thử lại</button></p>
    <div v-else-if="busy" class="state-card" role="status">Đang tìm dịch vụ…</div>
    <EmptyState
      v-else-if="!listings.length"
      title="Chưa có dịch vụ phù hợp"
      description="Thử đổi khu vực, loại dịch vụ hoặc khung giờ. Kết quả trống không có nghĩa là một dịch vụ cụ thể bị loại vì lý do riêng tư."
    >
      <button class="button secondary" type="button" @click="clearSearch">Xóa bộ lọc</button>
    </EmptyState>
    <div v-else class="listing-grid">
      <ListingCard v-for="listing in listings" :key="listing.id" :listing="listing" />
    </div>

    <nav v-if="meta?.last_page > 1" class="pagination" aria-label="Phân trang dịch vụ">
      <button class="button secondary small" :disabled="meta.current_page <= 1 || busy" @click="search(meta.current_page - 1)">← Trước</button>
      <span>Trang {{ meta.current_page }} / {{ meta.last_page }}</span>
      <button class="button secondary small" :disabled="meta.current_page >= meta.last_page || busy" @click="search(meta.current_page + 1)">Tiếp →</button>
    </nav>
  </section>

  <aside class="notice info smart-match-note">
    <span class="notice-icon" aria-hidden="true">✓</span>
    <div><strong>Smart Match là đối chiếu theo quy tắc.</strong> Chỉ dùng loài và kích cỡ do chủ nuôi khai báo; không phải AI và không suy luận tuổi hay nhu cầu đặc biệt.</div>
  </aside>
</template>
