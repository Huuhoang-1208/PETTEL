import { describe, expect, it, vi } from 'vitest';
import { petService } from '../src/features/owner/pets/services/petService';

vi.mock('../src/shared/api/httpClient', () => ({ request: vi.fn(), unwrapData: (payload) => payload?.data ?? payload }));
import { request } from '../src/shared/api/httpClient';

describe('petService', () => {
  it('lists only the backend-provided pet collection', async () => {
    request.mockResolvedValueOnce({ data: [{ id: '8', name: 'Mochi', status: 'ACTIVE' }] });
    await expect(petService.list()).resolves.toEqual([{ id: '8', name: 'Mochi', status: 'ACTIVE' }]);
    expect(request).toHaveBeenCalledWith('/api/pets');
  });
  it('uses the owned-pet archive operation and surfaces forbidden responses', async () => {
    request.mockRejectedValueOnce({ status: 403, code: 'FORBIDDEN', message: 'Owner required' });
    await expect(petService.archive('8')).rejects.toMatchObject({ status: 403 });
    expect(request).toHaveBeenCalledWith('/api/pets/8/archive', { method: 'PATCH' });
  });
  it('reads one owned pet through its protected detail operation', async () => {
    request.mockResolvedValueOnce({ data: { id: '8', name: 'Mochi' } });
    await expect(petService.get('8')).resolves.toMatchObject({ id: '8', name: 'Mochi' });
    expect(request).toHaveBeenCalledWith('/api/pets/8');
  });
  it('creates and updates through the documented pet resource routes', async () => {
    request.mockResolvedValueOnce({ data: { id: '9', name: 'Miu' } }).mockResolvedValueOnce({ data: { id: '9', name: 'Miu' } });
    await petService.create({ name: 'Miu', species: 'CAT' });
    await petService.update('9', { name: 'Miu' });
    expect(request).toHaveBeenNthCalledWith(1, '/api/pets', { method: 'POST', body: { name: 'Miu', species: 'CAT' } });
    expect(request).toHaveBeenNthCalledWith(2, '/api/pets/9', { method: 'PATCH', body: { name: 'Miu' } });
  });
  it('activates and soft-deletes through the owner pet endpoints', async () => {
    request.mockResolvedValueOnce({ data: { id: '9', status: 'ACTIVE' } }).mockResolvedValueOnce(null);
    await petService.activate('9'); await petService.remove('9');
    expect(request).toHaveBeenNthCalledWith(1, '/api/pets/9/activate', { method: 'PATCH' });
    expect(request).toHaveBeenNthCalledWith(2, '/api/pets/9', { method: 'DELETE' });
  });
});
