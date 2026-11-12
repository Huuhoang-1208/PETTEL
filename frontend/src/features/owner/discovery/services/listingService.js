import { request, unwrapData } from '../../../../shared/api/httpClient';

export const listingService = {
  async search(filters = {}) {
    const query = new URLSearchParams(Object.entries(filters).filter(([, value]) => value !== '' && value != null));
    const result = await request(`/api/listings${query.size ? `?${query}` : ''}`);
    return { ...result, items: unwrapData(result) ?? [] };
  },
  async get(id) { return unwrapData(await request(`/api/listings/${id}`)); },
  async reviews(id) { return request(`/api/listings/${id}/reviews`); },
};
