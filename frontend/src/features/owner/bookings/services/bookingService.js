import { request, unwrapData } from '../../../../shared/api/httpClient';

export const bookingService = {
  async quote(input) { return request('/api/bookings/quote', { method: 'POST', body: input }); },
  async create(input, idempotencyKey) {
    return unwrapData(await request('/api/bookings', { method: 'POST', body: input, headers: { 'Idempotency-Key': idempotencyKey } }));
  },
  async cancel(id, idempotencyKey, reason_note = '') {
    return unwrapData(await request(`/api/bookings/${id}/cancel`, { method: 'POST', body: { reason_note }, headers: { 'Idempotency-Key': idempotencyKey } }));
  },
  async startSandboxPayment(id, idempotencyKey) {
    return unwrapData(await request(`/api/bookings/${id}/sandbox-payments`, { method: 'POST', headers: { 'Idempotency-Key': idempotencyKey } }));
  },
  async simulateSandboxPayment(id, attemptId, result) {
    return unwrapData(await request(`/api/bookings/${id}/sandbox-payments/${attemptId}/simulate`, { method: 'POST', body: { result } }));
  },
  async confirmCompletion(id) { return request(`/api/bookings/${id}/confirm-completion`, { method: 'POST' }); },
  async review(id, input) { return unwrapData(await request(`/api/bookings/${id}/reviews`, { method: 'POST', body: input })); },
};
