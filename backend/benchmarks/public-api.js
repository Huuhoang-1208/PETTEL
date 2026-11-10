import http from 'k6/http';
import { check, group, sleep } from 'k6';

const baseUrl = __ENV.BASE_URL || 'http://localhost:18088';
const listingId = __ENV.LISTING_ID || '1';

export const options = {
  vus: Number(__ENV.VUS || 50),
  duration: __ENV.DURATION || '60s',
  thresholds: {
    http_req_failed: ['rate<0.01'],
    'http_req_duration{endpoint:search_default}': ['p(95)<2000'],
    'http_req_duration{endpoint:search_filter}': ['p(95)<2000'],
    'http_req_duration{endpoint:search_deep_page}': ['p(95)<2000'],
    'http_req_duration{endpoint:listing_detail}': ['p(95)<1000'],
    'http_req_duration{endpoint:review_list}': ['p(95)<2000'],
  },
};

const endpoints = [
  ['search_default', '/api/listings?per_page=20&page=1'],
  ['search_filter', '/api/listings?per_page=20&page=1&service_type=DAYCARE&area_code=DN_CENTER'],
  ['search_deep_page', '/api/listings?per_page=20&page=100'],
  ['listing_detail', `/api/listings/${listingId}`],
  ['review_list', `/api/listings/${listingId}/reviews?per_page=20&page=1`],
];

export default function () {
  for (const [endpoint, path] of endpoints) {
    group(endpoint, () => {
      const response = http.get(`${baseUrl}${path}`, {
        tags: { endpoint },
        headers: { Accept: 'application/json' },
      });
      check(response, { [`${endpoint} returns 200`]: (r) => r.status === 200 });
    });
    sleep(0.1);
  }
}

export function handleSummary(data) {
  const runName = __ENV.RUN_NAME || 'baseline';
  return {
    [`/results/${runName}.json`]: JSON.stringify(data, null, 2),
  };
}
