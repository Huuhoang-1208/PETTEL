<script setup>
import { ref, watch } from 'vue';
import { RouterLink } from 'vue-router';
import { messageService } from '../services/messageService';
import { authService } from '../../../auth/services/authService';

const props = defineProps({ bookingId: { type: String, required: true } });
const messages = ref([]); const updates = ref([]); const nextCursor = ref(''); const draft = ref(''); const busy = ref(true); const sending = ref(false); const error = ref(''); const currentUserId = ref('');
let loadSequence = 0;
async function load(cursor = '') {
  const currentSequence = ++loadSequence;
  const bookingId = props.bookingId;
  busy.value = !cursor; error.value = '';
  try {
    const [result, care] = await Promise.all([messageService.list(bookingId, cursor), cursor ? Promise.resolve(updates.value) : messageService.careUpdates(bookingId)]);
    if (currentSequence !== loadSequence) return;
    messages.value = cursor ? [...messages.value, ...(result.data ?? [])] : result.data ?? [];
    updates.value = care;
    nextCursor.value = result.links?.next ? new URL(result.links.next, location.origin).searchParams.get('cursor') ?? '' : '';
  } catch (cause) { if (currentSequence === loadSequence) error.value = cause.message; }
  finally { if (currentSequence === loadSequence) busy.value = false; }
}
async function send() {
  if (!draft.value.trim() || sending.value) return;
  sending.value = true; error.value = '';
  try { await messageService.send(props.bookingId, draft.value.trim()); draft.value = ''; await load(); }
  catch (cause) { error.value = cause.message; }
  finally { sending.value = false; }
}
watch(() => props.bookingId, async () => { messages.value = []; updates.value = []; nextCursor.value = ''; try { currentUserId.value = String((await authService.me()).id); } catch { currentUserId.value = ''; } await load(); }, { immediate: true });
</script>

<template><section class="page-heading"><div><p class="eyebrow">TRAO ĐỔI TRONG LƯỢT ĐẶT</p><h1>Tin nhắn và cập nhật chăm sóc</h1><p class="muted">Booking #{{ bookingId }} · Máy chủ kiểm tra quyền tham gia trước khi trả dữ liệu.</p></div></section>
  <div class="thread-layout"><section class="card thread-card"><h2>Tin nhắn</h2><div v-if="busy" class="state-card" role="status">Đang tải tin nhắn…</div><div v-else-if="error" class="state-card error-message" role="alert">{{ error }}</div><div v-else-if="!messages.length" class="empty-report">Chưa có tin nhắn trong booking này.</div><div v-else class="thread-messages"><article v-for="message in messages" :key="message.id" class="message-item"><small class="message-author">{{ message.sender_id === currentUserId ? 'Bạn' : 'Người chăm' }}</small><p>{{ message.body }}</p><small>{{ message.created_at }}</small></article></div><button v-if="nextCursor" class="button button-soft" :disabled="busy" @click="load(nextCursor)">Tải tin nhắn cũ hơn</button><form class="message-composer" @submit.prevent="send"><label class="sr-only" for="message-body">Tin nhắn</label><textarea id="message-body" v-model.trim="draft" rows="3" maxlength="2000" placeholder="Viết tin nhắn…" /><p v-if="error && !busy" class="error-message" role="alert">{{ error }}</p><button class="button button-primary" :disabled="!draft.trim() || sending">{{ sending ? 'Đang gửi…' : 'Gửi tin nhắn' }}</button><small class="small-note">Tệp đính kèm chưa được bật trong giao diện Owner.</small></form></section>
    <aside class="card thread-card"><h2>Cập nhật chăm sóc</h2><p class="small-note">Chỉ hiển thị cập nhật được gửi bởi người chăm trong booking này.</p><div v-if="!updates.length" class="empty-report">Chưa có cập nhật chăm sóc.</div><article v-for="update in updates" :key="update.id" class="care-update"><strong>{{ update.sender?.name ?? 'Người chăm' }}</strong><p v-if="!update.redacted">{{ update.body }}</p><p v-else class="muted">Nội dung này đã được ẩn.</p><small>{{ update.created_at }}</small></article><RouterLink to="/account" class="back-link">Quay lại tài khoản</RouterLink></aside></div>
</template>
