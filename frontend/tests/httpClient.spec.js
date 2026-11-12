import { beforeEach, describe, expect, it, vi } from 'vitest';
import { request, unwrapData } from '../src/shared/api/httpClient';

describe('shared HTTP client', () => {
  beforeEach(() => { vi.stubGlobal('fetch', vi.fn()); });
  it('loads a Sanctum CSRF cookie before a mutation and sends it with cookies', async () => {
    fetch.mockImplementationOnce(async () => { document.cookie = 'XSRF-TOKEN=csrf-value; path=/'; return new Response(null, { status: 204 }); })
      .mockResolvedValueOnce(new Response(null, { status: 204 }));
    await request('/api/pets', { method: 'POST', body: { name: 'Mochi', species: 'DOG' } });
    expect(fetch).toHaveBeenCalledTimes(2);
    expect(fetch.mock.calls[1][1].credentials).toBe('include');
    expect(fetch.mock.calls[1][1].headers['X-XSRF-TOKEN']).toBe('csrf-value');
  });

  it('refreshes CSRF and retries once after an expired token response', async () => {
    document.cookie = 'XSRF-TOKEN=expired; path=/';
    fetch.mockResolvedValueOnce(new Response(JSON.stringify({ error: { code: 'CSRF_EXPIRED', message: 'Expired' } }), { status: 419 }))
      .mockImplementationOnce(async () => { document.cookie = 'XSRF-TOKEN=fresh; path=/'; return new Response(null, { status: 204 }); })
      .mockResolvedValueOnce(new Response(null, { status: 204 }));
    await request('/api/pets/1', { method: 'PATCH', body: { name: 'Mochi' } });
    expect(fetch).toHaveBeenCalledTimes(3);
    expect(fetch.mock.calls[2][1].headers['X-XSRF-TOKEN']).toBe('fresh');
  });

  it('normalizes authorization and validation errors into ApiError', async () => {
    fetch.mockResolvedValueOnce(new Response(JSON.stringify({ error: { code: 'FORBIDDEN', message: 'Owner role required.' } }), { status: 403 }));
    await expect(request('/api/pets')).rejects.toMatchObject({ name: 'ApiError', status: 403, code: 'FORBIDDEN' });
  });
  it('unwraps Laravel resource data', () => { expect(unwrapData({ data: [{ id: '1' }] })).toEqual([{ id: '1' }]); });
});
