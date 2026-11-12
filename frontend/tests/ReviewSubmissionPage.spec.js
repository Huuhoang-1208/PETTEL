import { describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import ReviewSubmissionPage from '../src/features/owner/reviews/pages/ReviewSubmissionPage.vue';

vi.mock('../src/features/owner/bookings/services/bookingService', () => ({ bookingService: { review: vi.fn() } }));
import { bookingService } from '../src/features/owner/bookings/services/bookingService';

describe('ReviewSubmissionPage', () => {
  it('shows an API validation failure and keeps the form editable', async () => {
    bookingService.review.mockRejectedValueOnce(new Error('Booking chưa hoàn tất.'));
    const wrapper = mount(ReviewSubmissionPage, { props: { bookingId: '14' }, global: { stubs: { RouterLink: true } } });
    await wrapper.get('form').trigger('submit'); await flushPromises();
    expect(wrapper.get('[role="alert"]').text()).toContain('chưa hoàn tất');
    expect(wrapper.get('button[type="submit"]').element.disabled).toBe(false);
  });
  it('confirms review submission only after a backend response', async () => {
    bookingService.review.mockResolvedValueOnce({ id: '50', status: 'VISIBLE' });
    const wrapper = mount(ReviewSubmissionPage, { props: { bookingId: '14' }, global: { stubs: { RouterLink: true } } });
    await wrapper.get('form').trigger('submit'); await flushPromises();
    expect(wrapper.get('[role="status"]').text()).toContain('Đánh giá #50');
  });
});
