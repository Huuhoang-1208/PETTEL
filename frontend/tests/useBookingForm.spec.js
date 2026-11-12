import { describe, expect, it } from 'vitest';
import { ref } from 'vue';
import { useBookingForm } from '../src/features/owner/bookings/composables/useBookingForm';

describe('useBookingForm', () => {
  it('sets hourly and 24-hour duration presets using Vietnam wall time', () => {
    const service = ref('DAYCARE'); const form = useBookingForm(service);
    form.startAt.value = '2026-12-10T09:00'; form.setDuration(4);
    expect(form.endAt.value).toBe('2026-12-10T13:00'); expect(form.durationLabel.value).toBe('4 giờ');
    service.value = 'BOARDING'; form.setDuration(1);
    expect(form.endAt.value).toBe('2026-12-11T09:00'); expect(form.durationLabel.value).toBe('1 đêm');
  });
  it('prevents invalid day care and overnight durations before quote/create', () => {
    const form = useBookingForm(ref('DAYCARE'));
    form.petId.value = '1'; form.startAt.value = '2026-12-10T16:00'; form.endAt.value = '2026-12-11T00:00';
    expect(form.valid.value).toBe(false); expect(form.validationMessage.value).toContain('cùng ngày');
    const boarding = useBookingForm(ref('BOARDING'));
    boarding.petId.value = '1'; boarding.startAt.value = '2026-12-10T09:00'; boarding.endAt.value = '2026-12-10T20:00';
    expect(boarding.valid.value).toBe(false); expect(boarding.validationMessage.value).toContain('12 giờ');
  });

  it('moves the end time with the start while preserving the selected duration', () => {
    const form = useBookingForm(ref('DAYCARE'));
    form.startAt.value = '2026-12-10T09:00';
    form.setDuration(4);

    form.startAt.value = '2026-12-10T10:30';

    expect(form.endAt.value).toBe('2026-12-10T14:30');
    expect(form.durationLabel.value).toBe('4 giờ');
  });
});
