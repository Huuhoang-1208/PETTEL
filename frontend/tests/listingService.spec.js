import { beforeEach, describe, expect, it, vi } from 'vitest';

const { request } = vi.hoisted(() => ({ request: vi.fn() }));
vi.mock('../src/shared/api/httpClient', () => ({ request, unwrapData: (response) => response?.data }));

import { listingService } from '../src/features/owner/discovery/services/listingService';

describe('listingService', () => {
  beforeEach(() => request.mockReset());

  it('maps only selected, supported filters into the public discovery request', async () => {
    request.mockResolvedValue({ data: [{ id: '12' }], meta: { total: 1 } });

    const result = await listingService.search({ q: 'chăm ban ngày', service_type: 'DAYCARE', species: 'DOG', size: 'SMALL', area_code: '', page: 1 });

    expect(request).toHaveBeenCalledWith('/api/listings?q=ch%C4%83m+ban+ng%C3%A0y&service_type=DAYCARE&species=DOG&size=SMALL&page=1');
    expect(result.items).toEqual([{ id: '12' }]);
    expect(result.meta.total).toBe(1);
  });

  it('propagates API failures without presenting mock listings', async () => {
    const failure = new Error('Forbidden');
    request.mockRejectedValue(failure);

    await expect(listingService.search({})).rejects.toBe(failure);
  });
});
