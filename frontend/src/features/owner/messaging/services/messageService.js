import { request, unwrapData } from '../../../../shared/api/httpClient';

export const messageService = {
  async list(bookingId, cursor = '') {
    const query = new URLSearchParams({ per_page: '20', ...(cursor ? { cursor } : {}) });
    return request(`/api/bookings/${bookingId}/messages?${query}`);
  },
  async send(bookingId, body) { return unwrapData(await request(`/api/bookings/${bookingId}/messages`, { method: 'POST', body: { body } })); },
  async careUpdates(bookingId) { return unwrapData(await request(`/api/bookings/${bookingId}/care-updates?per_page=50`)) ?? []; },
};
