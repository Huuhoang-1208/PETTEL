import { computed, ref, watch } from 'vue';
import { bookingService } from '../services/bookingService';
import { dateTimeWithOffset } from '../../../../shared/utils/format';

export function useBookingQuote({ petId, listingId, startAt, endAt }) {
  const quote = ref(null);
  const state = ref('idle');
  const error = ref('');
  let sequence = 0;
  const input = computed(() => ({ pet_id: petId.value, listing_id: listingId.value, start_at: dateTimeWithOffset(startAt.value), end_at: dateTimeWithOffset(endAt.value) }));

  watch([petId, listingId, startAt, endAt], () => { sequence += 1; quote.value = null; state.value = 'idle'; error.value = ''; }, { flush: 'sync' });

  async function check() {
    const requestSequence = ++sequence;
    quote.value = null;
    state.value = 'checking';
    error.value = '';
    try {
      const result = await bookingService.quote(input.value);
      if (requestSequence !== sequence) return null;
      quote.value = result;
      state.value = 'available';
      return result;
    } catch (cause) {
      if (requestSequence !== sequence) return null;
      state.value = 'unavailable';
      error.value = cause.message;
      return null;
    }
  }
  return { quote, state, error, check };
}
