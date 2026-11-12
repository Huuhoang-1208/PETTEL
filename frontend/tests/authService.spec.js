import { describe, expect, it, vi } from 'vitest';
import { authService } from '../src/features/auth/services/authService';

vi.mock('../src/shared/api/httpClient', () => ({ request: vi.fn() }));
import { request } from '../src/shared/api/httpClient';

describe('authService', () => {
  it('registers through the backend route that grants OWNER role by default', async () => {
    request.mockResolvedValueOnce(null);
    await authService.register({ name: 'Minh Anh', email: 'owner@example.test', password: 'secret', password_confirmation: 'secret' });
    expect(request).toHaveBeenCalledWith('/register', expect.objectContaining({ method: 'POST' }));
  });
  it('uses Laravel password reset endpoints without disclosing account existence in the UI response', async () => {
    request.mockResolvedValueOnce({ status: 'password-reset-link-requested' }).mockResolvedValueOnce({ status: 'Password reset' });
    await authService.requestPasswordReset('owner@example.test');
    await authService.resetPassword({ email: 'owner@example.test', token: 'token', password: 'secret', password_confirmation: 'secret' });
    expect(request).toHaveBeenNthCalledWith(1, '/forgot-password', { method: 'POST', body: { email: 'owner@example.test' } });
    expect(request).toHaveBeenNthCalledWith(2, '/reset-password', expect.objectContaining({ method: 'POST' }));
  });
});
