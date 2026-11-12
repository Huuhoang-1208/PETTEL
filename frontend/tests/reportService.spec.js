import { describe, expect, it, vi } from 'vitest';
import { reportService } from '../src/features/owner/reports/services/reportService';

vi.mock('../src/shared/api/httpClient', () => ({ request: vi.fn(), unwrapData: (payload) => payload?.data ?? payload }));
import { request } from '../src/shared/api/httpClient';

describe('reportService', () => {
  it('loads only the current actor report collection', async () => {
    request.mockResolvedValueOnce({ data: [{ id: '7', status: 'OPEN' }] });
    await expect(reportService.mine()).resolves.toEqual([{ id: '7', status: 'OPEN' }]);
    expect(request).toHaveBeenCalledWith('/api/reports/mine');
  });
  it('does not hide authorization errors from report submission', async () => {
    request.mockRejectedValueOnce({ status: 403, code: 'FORBIDDEN' });
    await expect(reportService.create({ listing_id: '4', reason_code: 'CONTENT' })).rejects.toMatchObject({ status: 403 });
  });
});
