import { computed, readonly, ref } from 'vue';
import { authService } from '../../features/auth/services/authService';

const user = ref(null);
const initialized = ref(false);
const loading = ref(false);

export function useAuthSession() {
  async function refresh() {
    loading.value = true;
    try { user.value = await authService.me(); return user.value; }
    catch (error) { if (error.status === 401) user.value = null; throw error; }
    finally { initialized.value = true; loading.value = false; }
  }
  async function login(credentials) { await authService.login(credentials); return refresh(); }
  async function register(input) { await authService.register(input); return refresh(); }
  async function logout() { await authService.logout(); user.value = null; }
  return { user: readonly(user), initialized: readonly(initialized), loading: readonly(loading), isAuthenticated: computed(() => Boolean(user.value)), refresh, login, register, logout };
}
