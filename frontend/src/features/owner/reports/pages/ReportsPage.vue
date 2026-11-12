<script setup>
import { onMounted, reactive, ref } from 'vue';
import { useRoute } from 'vue-router';
import StatusBadge from '../../../../shared/components/StatusBadge.vue';
import { reportService } from '../services/reportService';

const route = useRoute(); const reports = ref([]); const busy = ref(true); const submitting = ref(false); const error = ref(''); const notice = ref('');
const targets = ['listing_id', 'review_id', 'message_id', 'booking_id'];
const initialTarget = String(route.query.target ?? 'listing_id');
const form = reactive({ target: targets.includes(initialTarget) ? initialTarget : 'listing_id', id: String(route.query.id ?? ''), reason_code: '', description: '' });
onMounted(load);
async function load() { busy.value = true; error.value = ''; try { reports.value = await reportService.mine(); } catch (cause) { error.value = cause.message; } finally { busy.value = false; } }
async function submit() {
  submitting.value = true; error.value = ''; notice.value = '';
  try { const report = await reportService.create({ [form.target]: form.id, reason_code: form.reason_code.trim(), description: form.description.trim() || null }); notice.value = `Báo cáo #${report.id} đã được gửi và đang mở.`; form.id = ''; form.reason_code = ''; form.description = ''; await load(); }
  catch (cause) { error.value = cause.message; }
  finally { submitting.value = false; }
}
</script>

<template>
  <section class="page-heading"><div><p class="eyebrow">HỖ TRỢ AN TOÀN</p><h1>Báo cáo của tôi</h1><p class="muted">Theo dõi báo cáo do tài khoản của bạn gửi.</p></div></section>
  <div class="report-layout"><form class="card report-form" @submit.prevent="submit"><h2>Gửi báo cáo</h2><label>Loại nội dung<select v-model="form.target"><option v-for="target in targets" :key="target" :value="target">{{ ({ listing_id: 'Dịch vụ', review_id: 'Đánh giá', message_id: 'Tin nhắn', booking_id: 'Booking' })[target] }}</option></select></label><label>Mã nội dung<input v-model.trim="form.id" inputmode="numeric" required placeholder="Nhập mã từ đường dẫn nội dung" /></label><label>Mã lý do<input v-model.trim="form.reason_code" maxlength="64" required placeholder="Ví dụ theo lý do bạn muốn báo cáo" /></label><label>Mô tả thêm<textarea v-model.trim="form.description" maxlength="2000" rows="4" placeholder="Thông tin giúp đội ngũ hiểu tình huống" /></label><p v-if="error" class="error-message" role="alert">{{ error }}</p><p v-if="notice" class="notice-message" role="status">{{ notice }}</p><button class="button button-primary" :disabled="submitting">{{ submitting ? 'Đang gửi…' : 'Gửi báo cáo' }}</button><p class="small-note">Báo cáo chỉ được gửi khi máy chủ xác nhận quyền truy cập nội dung. Trạng thái OPEN xác nhận đã nhận, không có nghĩa vụ việc đã được kết luận.</p></form>
    <section class="card report-list"><h2>Lịch sử gửi</h2><div v-if="busy" class="state-card" role="status">Đang tải báo cáo…</div><div v-else-if="!reports.length" class="empty-report">Bạn chưa gửi báo cáo nào.</div><article v-for="report in reports" :key="report.id" class="report-row"><div><strong>Báo cáo #{{ report.id }}</strong><p>{{ report.target?.type ?? 'Nội dung' }} · {{ report.reason_code }}</p><small>{{ report.created_at }}</small></div><StatusBadge :status="report.status" /></article></section></div>
</template>
