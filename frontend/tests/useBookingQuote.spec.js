import { describe, expect, it, vi } from 'vitest';
import { nextTick, ref } from 'vue';
import { useBookingQuote } from '../src/features/owner/bookings/composables/useBookingQuote';

vi.mock('../src/features/owner/bookings/services/bookingService', () => ({ bookingService: { quote: vi.fn() } }));
import { bookingService } from '../src/features/owner/bookings/services/bookingService';

describe('useBookingQuote', () => {
  it('clears the previous server quote when time inputs change', async () => {
    const petId = ref('1'); const listingId = ref('2'); const startAt = ref('2026-12-01T09:00'); const endAt = ref('2026-12-01T13:00');
    bookingService.quote.mockResolvedValueOnce({ price_total_vnd: 200000 });
    const quote = useBookingQuote({ petId, listingId, startAt, endAt });
    await quote.check();
    expect(quote.state.value).toBe('available');
    startAt.value = '2026-12-01T10:00'; await nextTick();
    expect(quote.quote.value).toBeNull(); expect(quote.state.value).toBe('idle');
  });
  it('shows availability conflicts as unavailable instead of preserving an old quote', async () => {
    const quote = useBookingQuote({ petId: ref('1'), listingId: ref('2'), startAt: ref('2026-12-01T09:00'), endAt: ref('2026-12-01T13:00') });
    bookingService.quote.mockRejectedValueOnce(new Error('Khung giờ đã có booking.'));
    await quote.check();
    expect(quote.state.value).toBe('unavailable'); expect(quote.quote.value).toBeNull(); expect(quote.error.value).toContain('booking');
  });
  it('ignores a quote response that arrives after the requested time changed', async () => {
    const startAt = ref('2026-12-01T09:00');
    let resolveQuote;
    bookingService.quote.mockImplementationOnce(() => new Promise((resolve) => { resolveQuote = resolve; }));
    const quote = useBookingQuote({ petId: ref('1'), listingId: ref('2'), startAt, endAt: ref('2026-12-01T13:00') });
    const pending = quote.check();
    startAt.value = '2026-12-01T10:00';
    resolveQuote({ price_total_vnd: 200000 });
    await pending;
    expect(quote.quote.value).toBeNull(); expect(quote.state.value).toBe('idle');
  });
});
