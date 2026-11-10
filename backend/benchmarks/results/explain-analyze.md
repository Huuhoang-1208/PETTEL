# MySQL EXPLAIN ANALYZE notes

Environment: MySQL 8.4, isolated `pettel_perf` tmpfs database; 5,000 active listings, 10,000 completed bookings and 10,000 visible reviews. SQL uses public query predicates and no request bindings.

## Public listing search and rating aggregate

- Plan actual time: about 32.8 ms; returned 20 listings.
- `listings_public_search_idx` served the `status='ACTIVE'` lookup; 5,000 rows passed the listing filters.
- Caregiver/user/role checks used primary and covering indexes, although correlated checks looped over the 5,000 listings.
- Rating summary materialized one aggregate group in about 13.5 ms. The plan scanned 10,000 reviews and did 10,000 booking primary-key lookups.
- This confirms the review aggregation is the main DB work inside this synthetic search request. It does not show a search p95 problem at the tested 50 VU load: search p95 remained below 0.8 seconds.

## Public review page

- Plan actual time: about 9.3 ms to return the first 20 rows.
- MySQL used `reviews_listing_id_status_is_demo_created_at_id_index` for listing/status and read 10,000 matching review rows, then sorted them by creation time and ID.
- It performed booking primary-key checks for the returned rows. Pagination also runs a count query.
- The five minute page cache removes the count and page queries on a warm hit. The public listing visibility check still runs on every request.
