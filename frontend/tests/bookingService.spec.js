import { describe, expect, it, vi } from 'vitest';
import { bookingService } from '../src/features/owner/bookings/services/bookingService';

vi.mock('../src/shared/api/httpClient', () => ({ request: vi.fn(), unwrapData: (payload) => payload?.data ?? payload }));
import { request } from '../src/shared/api/httpClient';

describe('bookingService', () => {
  it('sends quote input to the server for authoritative pricing/availability', async () => {
    request.mockResolvedValueOnce({ price_total_vnd: 200000, price_unit: 'HOUR', quantity: 4 });
    await expect(bookingService.quote({ pet_id: '1', listing_id: '2', start_at: '2026-12-01T09:00:00+07:00', end_at: '2026-12-01T13:00:00+07:00' })).resolves.toMatchObject({ price_total_vnd: 200000 });
    expect(request).toHaveBeenCalledWith('/api/bookings/quote', expect.objectContaining({ method: 'POST' }));
  });
  it('attaches Idempotency-Key to booking creation and preserves conflict errors', async () => {
    request.mockRejectedValueOnce({ status: 409, code: 'AVAILABILITY_CONFLICT' });
    await expect(bookingService.create({ listing_id: '2' }, 'owner-booking-key')).rejects.toMatchObject({ code: 'AVAILABILITY_CONFLICT' });
    expect(request).toHaveBeenCalledWith('/api/bookings', expect.objectContaining({ headers: { 'Idempotency-Key': 'owner-booking-key' } }));
  });
  it('starts and simulates sandbox payment through the documented routes only', async () => {
    request.mockResolvedValueOnce({ data: { id: '71', amount_vnd: 180000, status: 'PENDING', is_sandbox: true } })
      .mockResolvedValueOnce({ data: { attempt_status: 'AUTHORIZED', booking_status: 'CONFIRMED', is_sandbox: true } });
    await bookingService.startSandboxPayment('31', 'owner-payment-key');
    await bookingService.simulateSandboxPayment('31', '71', 'SUCCESS');
    expect(request).toHaveBeenNthCalledWith(1, '/api/bookings/31/sandbox-payments', { method: 'POST', headers: { 'Idempotency-Key': 'owner-payment-key' } });
    expect(request).toHaveBeenNthCalledWith(2, '/api/bookings/31/sandbox-payments/71/simulate', { method: 'POST', body: { result: 'SUCCESS' } });
  });
  it('confirms completion only through the owner completion endpoint', async () => {
    request.mockResolvedValueOnce({ booking: { id: '31', status: 'AWAITING_CONFIRMATION' }, release: { status: 'PENDING' } });
    await bookingService.confirmCompletion('31');
    expect(request).toHaveBeenCalledWith('/api/bookings/31/confirm-completion', { method: 'POST' });
  });
  it('cancels with a stable idempotency key and submits review only to the booking route', async () => {
    request.mockResolvedValueOnce({ id: '31', status: 'CANCELLED' }).mockResolvedValueOnce({ data: { id: '92', status: 'VISIBLE' } });
    await bookingService.cancel('31', 'owner-cancel-abc');
    await bookingService.review('31', { rating: 5, body: 'Rất tốt' });
    expect(request).toHaveBeenNthCalledWith(1, '/api/bookings/31/cancel', expect.objectContaining({ headers: { 'Idempotency-Key': 'owner-cancel-abc' } }));
    expect(request).toHaveBeenNthCalledWith(2, '/api/bookings/31/reviews', { method: 'POST', body: { rating: 5, body: 'Rất tốt' } });
  });
});
