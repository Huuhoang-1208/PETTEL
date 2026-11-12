<script setup>
import { onMounted, ref } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import { authService } from '../../../auth/services/authService';
import { useAuthSession } from '../../../../app/composables/useAuthSession';

const account = ref(null); const busy = ref(true); const error = ref('');
const auth = useAuthSession(); const router = useRouter();
onMounted(async () => { try { account.value = await authService.me(); } catch (cause) { error.value = cause.message; } finally { busy.value = false; } });
async function startPasswordReset() { try { await auth.logout(); await router.push({ name: 'login', query: { mode: 'forgot' } }); } catch (cause) { error.value = cause.message; } }
</script>

<template>
  <section class="page-heading"><div><p class="eyebrow">TÀI KHOẢN OWNER</p><h1>Tài khoản của tôi</h1><p class="muted">Thông tin tài khoản được tải trực tiếp từ phiên đăng nhập.</p></div></section>
  <div v-if="busy" class="state-card" role="status">Đang tải tài khoản…</div><div v-else-if="error" class="state-card error-message" role="alert">{{ error }}</div>
  <div v-else class="account-grid"><section class="card account-card"><span class="profile-avatar">{{ (account?.name ?? 'O').slice(0, 1).toUpperCase() }}</span><p class="eyebrow">THÔNG TIN CÁ NHÂN</p><h2>{{ account?.name }}</h2><p>{{ account?.email }}</p><div class="status-pill status-active">Tài khoản đang hoạt động</div><p class="small-note">API hiện hỗ trợ đọc hồ sơ phiên đăng nhập. Chưa có endpoint cập nhật tên/email.</p><button class="text-action" @click="startPasswordReset">Đăng xuất và đặt lại mật khẩu</button></section>
    <section class="account-links"><RouterLink to="/pets" class="card account-link"><span>🐾</span><div><h2>Thú cưng của tôi</h2><p>Quản lý hồ sơ và thông tin chăm sóc</p></div><b>→</b></RouterLink><section class="card account-link unavailable"><span>📅</span><div><h2>Booking của tôi</h2><p>Chưa khả dụng: backend chưa có API đọc danh sách/chi tiết booking Owner.</p></div></section><section class="card account-link unavailable"><span>⭐</span><div><h2>Đánh giá của tôi</h2><p>Chưa khả dụng: chưa có API lịch sử review của Owner.</p></div></section><section class="card account-link unavailable"><span>🔐</span><div><h2>Bảo mật</h2><p>Đặt lại mật khẩu dùng luồng email. Quản lý phiên đăng nhập và yêu cầu xóa tài khoản chưa có API Owner.</p></div></section></section></div>
</template>
