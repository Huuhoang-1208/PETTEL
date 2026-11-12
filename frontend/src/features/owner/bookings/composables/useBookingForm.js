import { computed, ref, watch } from 'vue';
import { addHoursToVnLocalInput, currentVnLocalInput, parseVnDateTime } from '../../../../shared/utils/format';

export function useBookingForm(serviceType) {
  const current = currentVnLocalInput();
  const [year, month, day] = current.slice(0, 10).split('-').map(Number);
  const [hour, minute] = current.slice(11).split(':').map(Number);
  const start = new Date(Date.UTC(year, month - 1, day, hour, minute));
  start.setUTCDate(start.getUTCDate() + 1);
  start.setUTCMinutes(Math.ceil(start.getUTCMinutes() / 30) * 30, 0, 0);
  const startAt = ref(start.toISOString().slice(0, 16));
  const endAt = ref(addHoursToVnLocalInput(startAt.value, 4));
  const petId = ref('');
  const instructions = ref('');
  watch(startAt, (nextStart, previousStart) => {
    if (!previousStart || !endAt.value) return;
    const duration = (parseVnDateTime(endAt.value) - parseVnDateTime(previousStart)) / 3_600_000;
    if (Number.isFinite(duration) && duration > 0) endAt.value = addHoursToVnLocalInput(nextStart, duration);
  }, { flush: 'sync' });
  const isBoarding = computed(() => serviceType.value === 'BOARDING');
  const durationLabel = computed(() => {
    const durationMs = parseVnDateTime(endAt.value) - parseVnDateTime(startAt.value);
    if (!Number.isFinite(durationMs) || durationMs <= 0) return '';
    const hours = Math.ceil(durationMs / 3_600_000);
    return isBoarding.value ? `${Math.ceil(hours / 24)} đêm` : `${hours} giờ`;
  });
  const validationMessage = computed(() => {
    if (!petId.value) return 'Hãy chọn một thú cưng đang hoạt động.';
    const startInstant = parseVnDateTime(startAt.value);
    const endInstant = parseVnDateTime(endAt.value);
    if (Number.isNaN(startInstant.getTime()) || Number.isNaN(endInstant.getTime())) return 'Hãy chọn ngày và giờ hợp lệ.';
    if (startInstant <= new Date()) return 'Thời điểm bắt đầu phải ở trong tương lai.';
    const durationHours = (endInstant - startInstant) / 3_600_000;
    if (durationHours <= 0) return 'Thời điểm kết thúc phải sau thời điểm bắt đầu.';
    if (isBoarding.value && (durationHours < 12 || durationHours > 720)) return 'Lưu trú cần từ 12 giờ đến 30 ngày.';
    if (!isBoarding.value && (durationHours < 1 || durationHours > 12 || startAt.value.slice(0, 10) !== endAt.value.slice(0, 10))) return 'Chăm ban ngày cần từ 1 đến 12 giờ và kết thúc trong cùng ngày.';
    return '';
  });
  const valid = computed(() => validationMessage.value === '');
  function setDuration(units) {
    endAt.value = addHoursToVnLocalInput(startAt.value, units * (isBoarding.value ? 24 : 1));
  }
  watch(serviceType, () => setDuration(isBoarding.value ? 1 : 4));
  return { startAt, endAt, petId, instructions, isBoarding, durationLabel, validationMessage, valid, setDuration };
}
