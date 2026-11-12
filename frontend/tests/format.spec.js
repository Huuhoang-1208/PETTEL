import { describe, expect, it } from 'vitest';
import { addHoursToVnLocalInput, dateTimeWithOffset, formatVnd, labelArea, labelService } from '../src/shared/utils/format';

describe('format utilities', () => {
  it('formats VND with no fractional digits', () => { expect(formatVnd(180000)).toContain('180.000'); });
  it('maps public service and area codes to Vietnamese labels', () => {
    expect(labelService('BOARDING')).toBe('Lưu trú qua đêm');
    expect(labelArea('DN_CENTER')).toBe('Hải Châu, Đà Nẵng');
    expect(labelArea('DN_WEST')).toBe('Thanh Khê, Đà Nẵng');
    expect(labelArea('DN_EAST')).toBe('Sơn Trà, Đà Nẵng');
  });
  it('serializes local date-time input with explicit timezone offset', () => {
    expect(dateTimeWithOffset('2026-11-12T09:00')).toBe('2026-11-12T09:00:00+07:00');
    expect(addHoursToVnLocalInput('2026-11-12T09:00', 4)).toBe('2026-11-12T13:00');
  });
});
