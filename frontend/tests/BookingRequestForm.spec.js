import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import BookingRequestForm from '../src/features/owner/bookings/components/BookingRequestForm.vue';

const listing = { id: '5', title: 'Chăm sóc', service_type: 'DAYCARE', price_vnd: 50000, price_unit: 'HOUR' };
const model = { petId: '1', startAt: '2026-12-01T09:00', endAt: '2026-12-01T13:00', instructions: '' };

describe('BookingRequestForm', () => {
  it('requires a server quote before the submit action becomes available', async () => {
    const wrapper = mount(BookingRequestForm, { props: { listing, pets: [{ id: '1', name: 'Mochi', status: 'ACTIVE' }], model, state: 'idle', valid: true }, global: { stubs: { RouterLink: true } } });
    expect(wrapper.findAll('button').at(-1).element.disabled).toBe(true);
    await wrapper.get('form').trigger('submit');
    expect(wrapper.emitted('check')).toHaveLength(1);
  });
  it('communicates a server availability conflict', () => {
    const wrapper = mount(BookingRequestForm, { props: { listing, pets: [{ id: '1', name: 'Mochi', status: 'ACTIVE' }], model, state: 'unavailable', quoteError: 'Khung giờ đã có booking.', valid: true }, global: { stubs: { RouterLink: true } } });
    expect(wrapper.get('[role="alert"]').text()).toContain('Khung giờ đã có booking');
  });
});
