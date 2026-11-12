<script setup>
import { onMounted, ref } from 'vue';
import PetEditor from '../components/PetEditor.vue';
import PetProfileCard from '../components/PetProfileCard.vue';
import PetDetails from '../components/PetDetails.vue';
import EmptyState from '../../../../shared/components/EmptyState.vue';
import { petService } from '../services/petService';

const pets = ref([]); const loading = ref(true); const saving = ref(false); const error = ref(''); const editingPet = ref(null); const editorOpen = ref(false); const viewingPet = ref(null); const detailLoading = ref(false); const detailError = ref('');
async function loadPets() {
  loading.value = true; error.value = '';
  try { pets.value = await petService.list(); } catch (cause) { error.value = cause.message; }
  finally { loading.value = false; }
}
function openEditor(pet = null) { editingPet.value = pet; error.value = ''; editorOpen.value = true; }
async function viewPet(pet) { viewingPet.value = null; detailError.value = ''; detailLoading.value = true; try { viewingPet.value = await petService.get(pet.id); } catch (cause) { detailError.value = cause.message; } finally { detailLoading.value = false; } }
async function savePet(input) {
  saving.value = true; error.value = '';
  try { if (editingPet.value) await petService.update(editingPet.value.id, input); else await petService.create(input); editorOpen.value = false; await loadPets(); }
  catch (cause) { error.value = cause.message; }
  finally { saving.value = false; }
}
async function changeStatus(pet) {
  error.value = '';
  try { if (pet.status === 'ACTIVE') await petService.archive(pet.id); else await petService.activate(pet.id); await loadPets(); }
  catch (cause) { error.value = cause.message; }
}
async function removePet(pet) {
  if (!window.confirm(`Xóa hồ sơ ${pet.name}?`)) return;
  error.value = '';
  try { await petService.remove(pet.id); await loadPets(); } catch (cause) { error.value = cause.message; }
}
onMounted(loadPets);
</script>

<template>
  <section class="page-heading"><div><p class="eyebrow">HỒ SƠ CỦA BẠN</p><h1>Thú cưng của tôi</h1><p class="muted">Thông tin này giúp người chăm hiểu nhu cầu của bé khi bạn gửi yêu cầu đặt dịch vụ.</p></div><button class="button button-primary" @click="openEditor()">＋ Thêm thú cưng</button></section>
  <p v-if="error && !editorOpen" class="error-message state-card" role="alert">{{ error }} <button class="link-button" @click="loadPets">Thử lại</button></p>
  <div v-if="loading" class="state-card" role="status">Đang tải hồ sơ…</div>
  <EmptyState v-else-if="!pets.length" title="Bắt đầu bằng hồ sơ đầu tiên" description="Thêm thông tin cơ bản để dễ chọn bé khi đặt dịch vụ."><button class="button button-primary" @click="openEditor()">Thêm hồ sơ thú cưng</button></EmptyState>
  <div v-else class="pet-grid"><PetProfileCard v-for="pet in pets" :key="pet.id" :pet="pet" @view="viewPet" @edit="openEditor" @toggle-status="changeStatus" @remove="removePet" /></div>
  <PetEditor v-if="editorOpen" :pet="editingPet" :busy="saving" :error="error" @save="savePet" @close="editorOpen = false" />
  <PetDetails v-if="viewingPet || detailLoading || detailError" :pet="viewingPet" :loading="detailLoading" :error="detailError" @close="viewingPet = null; detailError = ''" />
</template>
