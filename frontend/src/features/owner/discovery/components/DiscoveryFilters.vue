<script setup>
import { computed } from 'vue';
import { RouterLink } from 'vue-router';
import AppIcon from '../../../../shared/components/AppIcon.vue';

const props = defineProps({
  model: { type: Object, required: true },
  pets: { type: Array, default: () => [] },
  authenticated: { type: Boolean, default: false },
  validationMessage: { type: String, default: '' },
});
const emit = defineEmits(['search', 'update:filter']);
const field = (key) => computed({ get: () => props.model[key], set: (value) => emit('update:filter', { field: key, value }) });
const query = field('q');
const service = field('service_type');
const petId = field('pet_id');
const area = field('area_code');
const startAt = field('start_at');
const endAt = field('end_at');
const minPrice = field('min_price_vnd');
const maxPrice = field('max_price_vnd');
const smartMatch = field('smart_match');
const activePets = computed(() => props.pets.filter((pet) => pet.status === 'ACTIVE'));
</script>

<template>
  <form id="search-form" class="search-panel" aria-label="Tìm và lọc dịch vụ" @submit.prevent="emit('search')">
    <div class="field">
      <label for="q">Bạn muốn tìm dịch vụ nào?</label>
      <input id="q" v-model.trim="query" name="query" maxlength="100" placeholder="Tìm theo dịch vụ hoặc khu vực" />
    </div>

    <div class="field">
      <label for="service">Loại dịch vụ</label>
      <select id="service" v-model="service" name="service">
        <option value="">Tất cả dịch vụ</option>
        <option value="DAYCARE">Chăm ban ngày</option>
        <option value="BOARDING">Lưu trú</option>
      </select>
    </div>

    <div class="field">
      <label for="pet">Smart Match · thú cưng</label>
      <select id="pet" v-model="petId" name="petId" :disabled="!activePets.length">
        <option value="">{{ activePets.length ? 'Chọn hồ sơ (tùy chọn)' : 'Chưa có hồ sơ đang hoạt động' }}</option>
        <option v-for="pet in activePets" :key="pet.id" :value="pet.id">
          {{ pet.name }} · {{ pet.size ? ({ SMALL: 'nhỏ', MEDIUM: 'vừa', LARGE: 'lớn' })[pet.size] : 'chưa rõ kích cỡ' }}
        </option>
      </select>
      <small v-if="!activePets.length" class="field-hint">
        <RouterLink v-if="authenticated" to="/pets">Tạo hồ sơ thú cưng</RouterLink>
        <RouterLink v-else to="/login">Đăng nhập</RouterLink>
        để dùng Smart Match.
      </small>
    </div>

    <button class="button" type="submit">
      <AppIcon name="search" :size="18" />
      <span>Tìm dịch vụ</span>
    </button>

    <div class="field">
      <label for="area">Khu vực</label>
      <select id="area" v-model="area" name="area">
        <option value="">Mọi khu vực</option>
        <option value="DN_CENTER">Hải Châu</option>
        <option value="DN_WEST">Thanh Khê</option>
        <option value="DN_EAST">Sơn Trà</option>
      </select>
    </div>

    <div class="field">
      <label for="start-search">Bắt đầu · tùy chọn</label>
      <input id="start-search" v-model="startAt" type="datetime-local" name="start" step="1800" />
    </div>
    <div class="field">
      <label for="end-search">Kết thúc · tùy chọn</label>
      <input id="end-search" v-model="endAt" type="datetime-local" name="end" step="1800" />
    </div>
    <div class="field">
      <label for="min-price">Tổng giá từ · VND</label>
      <input id="min-price" v-model="minPrice" type="number" min="0" step="1000" name="min" placeholder="0" />
    </div>
    <div class="field">
      <label for="max-price">Tổng giá đến · VND</label>
      <input id="max-price" v-model="maxPrice" type="number" min="0" step="1000" name="max" placeholder="Không giới hạn" />
    </div>
    <label class="checkbox-row smart-checkbox">
      <input v-model="smartMatch" type="checkbox" name="smart" />
      <span>Đối chiếu Smart Match</span>
    </label>

    <p v-if="validationMessage" class="field-error search-validation" role="alert">{{ validationMessage }}</p>
  </form>
</template>
