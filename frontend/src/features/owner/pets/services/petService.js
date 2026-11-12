import { request, unwrapData } from '../../../../shared/api/httpClient';

export const petService = {
  async list() { return unwrapData(await request('/api/pets')) ?? []; },
  async get(id) { return unwrapData(await request(`/api/pets/${id}`)); },
  async create(pet) { return unwrapData(await request('/api/pets', { method: 'POST', body: pet })); },
  async update(id, pet) { return unwrapData(await request(`/api/pets/${id}`, { method: 'PATCH', body: pet })); },
  async archive(id) { return unwrapData(await request(`/api/pets/${id}/archive`, { method: 'PATCH' })); },
  async activate(id) { return unwrapData(await request(`/api/pets/${id}/activate`, { method: 'PATCH' })); },
  async remove(id) { return request(`/api/pets/${id}`, { method: 'DELETE' }); },
};
