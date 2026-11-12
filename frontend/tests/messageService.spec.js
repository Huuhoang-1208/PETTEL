import { describe, expect, it, vi } from 'vitest';
import { messageService } from '../src/features/owner/messaging/services/messageService';

vi.mock('../src/shared/api/httpClient', () => ({ request: vi.fn(), unwrapData: (payload) => payload?.data ?? payload }));
import { request } from '../src/shared/api/httpClient';

describe('messageService', () => {
  it('requests booking-scoped messages and forwards a cursor', async () => {
    request.mockResolvedValueOnce({ data: [] });
    await messageService.list('21', 'old_cursor');
    expect(request).toHaveBeenCalledWith('/api/bookings/21/messages?per_page=20&cursor=old_cursor');
  });
  it('sends message content only through the participant-checked endpoint', async () => {
    request.mockResolvedValueOnce({ data: { id: '8', body: 'Xin chào' } });
    await messageService.send('21', 'Xin chào');
    expect(request).toHaveBeenCalledWith('/api/bookings/21/messages', { method: 'POST', body: { body: 'Xin chào' } });
  });
});
