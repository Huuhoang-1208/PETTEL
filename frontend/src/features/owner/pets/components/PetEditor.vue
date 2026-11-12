<script setup>
import { reactive, watch } from 'vue';

const props = defineProps({ pet: { type: Object, default: null }, busy: { type: Boolean, default: false }, error: { type: String, default: '' } });
const emit = defineEmits(['save', 'close']);
const blank = () => ({ name: '', species: 'DOG', birth_date: '', age_note: '', sex: 'UNKNOWN', size: '', diet_notes: '', vaccination_note: '', temperament: '', special_needs: '' });
const form = reactive(blank());
watch(() => props.pet, (pet) => { Object.assign(form, blank(), pet ?? {}); }, { immediate: true });
function submit() { emit('save', { ...form, birth_date: form.birth_date || null, age_note: form.age_note || null, size: form.size || null, sex: form.sex || null, diet_notes: form.diet_notes || null, vaccination_note: form.vaccination_note || null, temperament: form.temperament || null, special_needs: form.special_needs || null }); }
</script>

<template><div class="modal-backdrop" @click.self="emit('close')"><form class="card pet-form" @submit.prevent="submit"><div class="modal-heading"><div><p class="eyebrow">HỒ SƠ THÚ CƯNG</p><h2>{{ pet ? 'Chỉnh sửa hồ sơ' : 'Thêm thú cưng' }}</h2></div><button type="button" class="icon-button" aria-label="Đóng" @click="emit('close')">×</button></div>
  <label>Tên thú cưng<input v-model.trim="form.name" required maxlength="100" /></label><div class="form-row"><label>Loài<select v-model="form.species"><option value="DOG">Chó</option><option value="CAT">Mèo</option><option value="OTHER">Khác</option></select></label><label>Kích cỡ<select v-model="form.size"><option value="">Chưa chọn</option><option value="SMALL">Nhỏ</option><option value="MEDIUM">Vừa</option><option value="LARGE">Lớn</option></select></label></div><div class="form-row"><label>Ngày sinh<input v-model="form.birth_date" type="date" /></label><label>Ghi chú tuổi<input v-model.trim="form.age_note" maxlength="100" placeholder="Ví dụ: khoảng 2 tuổi" /></label></div><label>Chế độ ăn<textarea v-model.trim="form.diet_notes" rows="2" maxlength="2000" /></label><label>Tiêm phòng (tự khai)<textarea v-model.trim="form.vaccination_note" rows="2" maxlength="2000" /></label><label>Tính cách<textarea v-model.trim="form.temperament" rows="2" maxlength="2000" /></label><label>Nhu cầu đặc biệt<textarea v-model.trim="form.special_needs" rows="2" maxlength="2000" /></label><p class="small-note">Ảnh thú cưng chưa thể cập nhật tại đây vì API hiện chưa có luồng tải ảnh cho Owner.</p><p v-if="error" class="error-message" role="alert">{{ error }}</p><div class="form-actions"><button type="button" class="button button-soft" @click="emit('close')">Hủy</button><button class="button button-primary" :disabled="busy">{{ busy ? 'Đang lưu…' : 'Lưu hồ sơ' }}</button></div>
</form></div></template>
