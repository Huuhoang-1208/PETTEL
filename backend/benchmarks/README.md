# BE-H06 public API benchmark

This benchmark uses the isolated `compose.performance.yaml` stack. Its MySQL data directory is a tmpfs; it does not connect to the local/demo database. Recreate the database and seed it with:

```sh
docker compose -p pettel-performance -f compose.performance.yaml run --build --rm app-perf sh -ec 'touch .env; php artisan migrate:fresh --force; php artisan db:seed --class="Database\Seeders\PerformanceSeeder" --force'
docker compose -p pettel-performance -f compose.performance.yaml up -d app-perf frontend-perf
```

The seeder refuses to run unless `APP_ENV=performance` and the database name is `pettel_perf`. It creates 5,000 active listings, 5,000 availability slots, 5,000 listing-media links, 10,000 completed bookings, and 10,000 visible reviews. Reviews are concentrated on the first listing to stress a popular listing. The 5,000 links share one synthetic media object; image storage and real media bandwidth are not measured.

`public-api.js` sends the same five requests per iteration: default search, filtered search, deep page, detail, and review list. The app uses Nginx + PHP-FPM from the project Docker images, MySQL 8.4 and Redis 7. k6 uses 50 VUs for 60 seconds per measured run, after a 10 VU / 10 second warm-up. The machine reported 20 CPU threads and 15 GiB total memory; containers had no explicit CPU or memory limits, so these figures are host-specific and not a production capacity claim.

Run a measured sample and save the complete k6 JSON summary:

```sh
docker run --rm --user 1000:1000 --network pettel-performance_default \
  -v "$PWD/backend/benchmarks:/scripts" \
  -v "$PWD/backend/benchmarks/results:/results" \
  -e BASE_URL=http://frontend-perf -e VUS=50 -e DURATION=60s \
  -e RUN_NAME=cache-1 grafana/k6:latest run /scripts/public-api.js
```

The query recorder is active only when `APP_ENV=performance` and `APP_PERFORMANCE_METRICS=true`. It logs route, status, SQL query count, summed SQL duration, cache hit/miss, and per-SQL-template count/total/max duration. It records SQL with placeholders and never records bindings.

## Results

Raw k6 summaries are in `results/` (`baseline-1..3`, `baseline-review`, `cache-cold`, `cache-1..3`). All measured requests returned 200: zero failed checks and zero HTTP errors.

| Mode | Requests/s | Overall p95 | Search default p95 | Search filter p95 | Deep page p95 | Detail p95 | Review p95 |
|---|---:|---:|---:|---:|---:|---:|---:|
| No cache, baseline 1 | 71.78 | 758.5 ms | 778.1 ms | 758.9 ms | 777.8 ms | 693.8 ms | — |
| No cache, baseline 2 | 71.97 | 753.7 ms | 783.8 ms | 752.5 ms | 753.9 ms | 679.0 ms | — |
| No cache, baseline 3 | 70.94 | 766.0 ms | 791.8 ms | 770.6 ms | 767.2 ms | 694.6 ms | — |
| No cache, tagged review run | 77.51 | 685.8 ms | 716.0 ms | 684.0 ms | 695.3 ms | 617.5 ms | 617.7 ms |
| Cache cold, one run | 89.07 | 659.5 ms | 666.5 ms | 666.7 ms | 678.7 ms | 612.4 ms | 422.8 ms |
| Cache warm, run 1 | 89.73 | 659.8 ms | 655.1 ms | 649.7 ms | 689.2 ms | 620.8 ms | 317.6 ms |
| Cache warm, run 2 | 89.90 | 652.3 ms | 657.3 ms | 661.4 ms | 674.6 ms | 605.6 ms | 411.1 ms |
| Cache warm, run 3 | 89.14 | 686.2 ms | 682.3 ms | 709.2 ms | 743.3 ms | 626.0 ms | 319.6 ms |

Baseline 1–3 average 71.56 requests/s and 759.4 ms overall p95. Cache-warm runs average 89.59 requests/s and 666.1 ms overall p95. The tagged no-cache review run p95 was 617.7 ms; cache-warm review p95 averaged 349.4 ms (43% lower). The cache-cold load run review p95 was 422.8 ms.

For a single review-list request, a cold miss used 4 SQL queries / 40.85 ms SQL time; a warm hit used 2 / 13.03 ms. During the cache-cold 50 VU run, 1,098 of 1,100 review requests hit cache and 2 missed; average query count was 2.004. The cache-warm run averaged 2 queries and 21.17 ms SQL time per review request, with 1,100 hits and no misses in the captured third run.

`EXPLAIN ANALYZE` on the listing search query completed in about 32.8 ms. MySQL used `listings_public_search_idx` to read the 5,000 active listings. The rating subquery materialized one group in about 13.5 ms after scanning 10,000 reviews and doing 10,000 booking primary-key lookups. The public review page query used `reviews_listing_id_status_is_demo_created_at_id_index`, read 10,000 matching reviews and sorted them to return 20 rows in about 9.3 ms. The cache therefore targets repeat review-page reads; it does not cache search availability, booking decisions, or listing state.

On this synthetic workload, search p95 stayed below 2 seconds, detail p95 below 1 second, and HTTP errors below 1%. These values only describe this host and data distribution. The 5 minute review-page cache is retained because it lowered repeat review-list query count and latency; version-based invalidation runs after review creation and moderation. Do not treat this benchmark as a production capacity guarantee.
