import { request, unwrapData } from '../../../../shared/api/httpClient';

export const reportService = {
  async mine() { return unwrapData(await request('/api/reports/mine')) ?? []; },
  async create(input) { return unwrapData(await request('/api/reports', { method: 'POST', body: input })); },
};
