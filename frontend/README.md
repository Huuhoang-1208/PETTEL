# PETTEL Owner Web

Vue 3 + Vite single-page app for Owner-facing flows that have a confirmed Laravel API. It deliberately does not use prototype fixtures or local storage as business data. Capabilities without a read/write contract are shown as unavailable rather than simulated.

## Local development

1. Start the Laravel backend using the repository's normal Docker Compose workflow on `http://localhost:8080`.
2. From this directory, install the locked dependencies with `npm ci`.
3. Run `npm run dev` and open `http://localhost:5173/owner/`.

Vite proxies API and Sanctum requests to Laravel and rewrites the host/origin headers for the local stateful session setup. Use an active Owner account for authenticated screens.

## Quality checks

```sh
npm run lint
npm test
npm run build
npm run test:e2e
```

E2E tests use the real API, not mocked responses. They expect the local Laravel service and development database to be ready. The authenticated read-only smoke test also needs `PETTEL_E2E_OWNER_EMAIL` and `PETTEL_E2E_OWNER_PASSWORD`; without those credentials Playwright reports that test as skipped. No test creates or mutates business records.

## Current integration boundary

The API readiness table and proposed backend gaps are in [`OWNER_API_MAPPING.md`](./OWNER_API_MAPPING.md). The Vite app is mounted at `/owner/`. Docker now has a Node build stage that compiles the locked Vue app and copies it into Nginx's `public/owner` directory. That container build is not verified yet because this environment cannot access the Docker socket; production/container delivery should be smoke-tested after Docker access is restored.
