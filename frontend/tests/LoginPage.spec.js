import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import { createMemoryHistory, createRouter } from 'vue-router';
import LoginPage from '../src/features/auth/pages/LoginPage.vue';

const { auth } = vi.hoisted(() => ({ auth: { login: vi.fn(), register: vi.fn() } }));
vi.mock('../src/app/composables/useAuthSession', () => ({ useAuthSession: () => auth }));

function renderLogin(path = '/login') {
  const router = createRouter({ history: createMemoryHistory(), routes: [{ path: '/login', component: LoginPage }] });
  return router.push(path).then(() => router.isReady()).then(() => ({ wrapper: mount(LoginPage, { global: { plugins: [router] } }), router }));
}

describe('LoginPage', () => {
  beforeEach(() => { auth.login.mockReset(); auth.register.mockReset(); });
  it('shows an authentication failure without claiming login succeeded', async () => {
    auth.login.mockRejectedValueOnce(new Error('Thông tin đăng nhập không hợp lệ.'));
    const { wrapper } = await renderLogin();
    await wrapper.get('input[type="email"]').setValue('owner@example.test');
    await wrapper.get('input[type="password"]').setValue('wrong-password');
    await wrapper.get('form').trigger('submit'); await flushPromises();
    expect(wrapper.get('[role="alert"]').text()).toContain('không hợp lệ');
  });
  it('opens the reset form with the token and email supplied by the backend email link', async () => {
    const { wrapper } = await renderLogin('/login?token=abc&email=owner%40example.test');
    expect(wrapper.get('h1').text()).toContain('Đặt mật khẩu mới');
    expect(wrapper.get('input[type="email"]').element.value).toBe('owner@example.test');
  });
});
