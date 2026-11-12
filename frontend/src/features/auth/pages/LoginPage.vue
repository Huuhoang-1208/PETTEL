<script setup>
import { computed, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { authService } from '../services/authService';
import { useAuthSession } from '../../../app/composables/useAuthSession';

const route = useRoute(); const router = useRouter();
const auth = useAuthSession();
const mode = ref(route.query.token ? 'reset' : ['register', 'forgot'].includes(route.query.mode) ? route.query.mode : 'login');
const name = ref(''); const email = ref(String(route.query.email ?? '')); const password = ref(''); const passwordConfirmation = ref(''); const token = ref(String(route.query.token ?? ''));
const busy = ref(false); const error = ref(''); const notice = ref('');
const heading = computed(() => ({ login: 'Đăng nhập PETTEL', register: 'Tạo tài khoản Owner', forgot: 'Khôi phục mật khẩu', reset: 'Đặt mật khẩu mới' })[mode.value]);
async function submit() {
  busy.value = true; error.value = ''; notice.value = '';
  try {
    if (mode.value === 'login') { await auth.login({ email: email.value, password: password.value }); await router.replace(String(route.query.redirect ?? '/')); }
    if (mode.value === 'register') { await auth.register({ name: name.value, email: email.value, password: password.value, password_confirmation: passwordConfirmation.value }); await router.replace('/'); }
    if (mode.value === 'forgot') { await authService.requestPasswordReset(email.value); notice.value = 'Nếu tài khoản phù hợp, hướng dẫn đặt lại mật khẩu sẽ được gửi qua email.'; }
    if (mode.value === 'reset') { await authService.resetPassword({ token: token.value, email: email.value, password: password.value, password_confirmation: passwordConfirmation.value }); notice.value = 'Mật khẩu đã được cập nhật. Bạn có thể đăng nhập.'; mode.value = 'login'; password.value = ''; passwordConfirmation.value = ''; }
  } catch (cause) { error.value = cause.message; }
  finally { busy.value = false; }
}
</script>

<template>
  <section class="auth-wrap"><form class="card auth-card" @submit.prevent="submit"><p class="eyebrow">CHÀO MỪNG BẠN</p><h1>{{ heading }}</h1><p v-if="mode === 'login'" class="muted">Đăng nhập để quản lý thú cưng và gửi yêu cầu đặt dịch vụ.</p><p v-if="mode === 'register'" class="muted">Tài khoản đăng ký mới sẽ có vai trò Owner.</p>
    <label v-if="mode === 'register'">Tên hiển thị<input v-model.trim="name" required maxlength="100" autocomplete="name" /></label>
    <label v-if="mode === 'reset'">Mã đặt lại<input v-model="token" required autocomplete="one-time-code" /></label>
    <label>Email<input v-model.trim="email" required type="email" autocomplete="username" /></label>
    <label v-if="['login','register','reset'].includes(mode)">Mật khẩu<input v-model="password" required :minlength="mode === 'login' ? undefined : 8" type="password" :autocomplete="mode === 'login' ? 'current-password' : 'new-password'" /></label>
    <label v-if="['register','reset'].includes(mode)">Nhập lại mật khẩu<input v-model="passwordConfirmation" required type="password" autocomplete="new-password" /></label>
    <p v-if="error" class="error-message" role="alert">{{ error }}</p><p v-if="notice" class="notice-message" role="status">{{ notice }}</p>
    <button class="button button-primary button-wide" :disabled="busy">{{ busy ? 'Đang xử lý…' : mode === 'login' ? 'Đăng nhập' : mode === 'register' ? 'Tạo tài khoản' : mode === 'forgot' ? 'Gửi hướng dẫn' : 'Cập nhật mật khẩu' }}</button>
    <div v-if="mode === 'login'" class="auth-links"><button type="button" class="link-button" @click="mode = 'forgot'; error = ''; notice = ''">Quên mật khẩu?</button><button type="button" class="link-button" @click="mode = 'register'; error = ''; notice = ''">Tạo tài khoản mới</button></div>
    <button v-else-if="mode !== 'reset'" type="button" class="link-button" @click="mode = 'login'; error = ''; notice = ''">Quay lại đăng nhập</button>
    <button v-else type="button" class="link-button" @click="mode = 'login'; error = ''; notice = ''">Quay lại đăng nhập</button>
  </form></section>
</template>
