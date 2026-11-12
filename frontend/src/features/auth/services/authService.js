import { request } from '../../../shared/api/httpClient';

export const authService = {
  async me() { return request('/api/user'); },
  async login(credentials) { return request('/login', { method: 'POST', body: credentials }); },
  async register(input) { return request('/register', { method: 'POST', body: input }); },
  async requestPasswordReset(email) { return request('/forgot-password', { method: 'POST', body: { email } }); },
  async resetPassword(input) { return request('/reset-password', { method: 'POST', body: input }); },
  async logout() { return request('/logout', { method: 'POST' }); },
};
