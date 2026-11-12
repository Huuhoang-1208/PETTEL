# 09 — Mock Data and Simulation Plan

The prototype is local-only. Fixtures are synthetic and must not reuse private production data, passwords, payment credentials or permanent private-media URLs. Mock schemas mirror current domain/API names only where verified; they are not a replacement API contract.

## 1. Demo clock and personas

Use a fixed, configurable demo clock so date validation, hold deadlines and expiry scenarios are repeatable. Default clock: **2026-11-18 08:00 Asia/Ho_Chi_Minh**. Add a visible “Demo time” indicator in prototype controls; never silently depend on the workstation clock for seeded scenarios.

| Persona ID | Roles | Purpose | Restrictions |
|---|---|---|---|
| guest-01 | Guest | Public search/detail and auth gate | No pet, booking, chat or private report evidence |
| owner-01 | OWNER | Complete Owner journey | Owns pet-01 and bookings only |
| caregiver-01 | CAREGIVER | Listing and care journey | Owns caregiver-profile-01 and listings only |
| admin-01 | ADMIN | Limited Filament-reference workflow | Mock queue only; reason/audit required; persona switch is demo-only |

No real email, phone, address or password is needed. Labels use synthetic display names; credentials are not entered in the prototype.

## 2. Mock entities and stable fields

| Entity | Minimum fields | Rules for consistent fixtures |
|---|---|---|
| User/persona | id, display_name, roles[], status | Only named persona owns records; Admin is separate. Optional dual-role user may be added only if role switching is shown. |
| Pet | id, owner_id, name, species, size, status, care notes | Owner-only details; size may be null for ineligible Smart Match scenario; status ACTIVE/ARCHIVED. |
| CaregiverProfile | id, user_id, display_name, area_code, bio | No verification status/badge unless explicitly “demo” and not represented as genuine. |
| Listing | id, caregiver_profile_id, title, description, service_type, area_code, price_vnd, price_unit, acceptance_rules_json, status, version, media_ids | Public results include only ACTIVE and eligible caregiver. price_unit is HOUR for DAYCARE, NIGHT for BOARDING. |
| AvailabilitySlot | id, listing_id, start_at, end_at, deleted_at? | UTC/offset-aware; continuous slot needed; do not merge adjacent slots implicitly. |
| Booking | id, owner_id, caregiver_user_id, pet_id, listing_id, status, start_at, end_at, price_snapshot_vnd, price_unit, price_quantity, pricing_version, cancellation_policy_version, pet_snapshot, listing_snapshot, policy_snapshot, instructions_snapshot, payment_due_at, events[] | Snapshot immutable except permitted privacy redaction; one pet and one listing. |
| PaymentAttempt | id, booking_id, amount_vnd, status, provider_ref, created_at, event_ids[] | Sandbox only; preserve attempts; only current attempt may change booking; no card fields. |
| Operation | id, booking_id, kind, status, stable_operation_key, attempt_count, next_retry_at, last_error_code | Void/refund/release pending/failure; same key on retries. |
| Message | id, booking_id, sender_id, body, attachment_ids[], created_at | Only booking participants; thread never shared across bookings. |
| CareUpdate | id, booking_id, sender_id, body, image_id?, created_at, redacted_at? | Caregiver only while IN_PROGRESS; participant read; private media. |
| Review | id, booking_id, listing_id, owner_id, rating, body, status, is_demo, created_at | One per booking; only visible non-demo eligible records count in aggregate. |
| Report | id, reporter_id, one_target, reason_code, description, status, events[], moderation_actions[] | Exactly one target. Reporter cannot read other user's private evidence. |

## 3. Canonical seed fixtures

| Fixture | Entity values | Scenario purpose |
|---|---|---|
| pet-01 “Mochi” | owner-01; DOG; SMALL; ACTIVE; synthetic short care notes | Eligible for listing-01 Smart Match and booking |
| pet-02 “Miu” | owner-01; CAT; size unknown; ACTIVE | Smart Match missing-size validation; notes remain private |
| pet-03 “Bắp” | owner-01; DOG; LARGE; ARCHIVED | Cannot start a new booking |
| listing-01 | caregiver-01; DAYCARE; DN_CENTER; 50,000 VND/HOUR; accepts DOG + SMALL/MEDIUM; ACTIVE, version 1 | Search, quote and primary successful booking |
| listing-02 | caregiver-01; BOARDING; DN_WEST; 180,000 VND/NIGHT; accepts CAT/DOG + SMALL/MEDIUM; ACTIVE | Different price unit and service duration |
| listing-03 | caregiver-01; DAYCARE; DN_EAST; 40,000 VND/HOUR; SUSPENDED | Must never appear in public discovery |
| slot-01 | listing-01; 2026-11-18 08:00–13:00 +07:00 | Covers booking-01 09:00–11:10; quote rounds to 3 hours = 150,000 VND |
| slot-02 | listing-02; 2026-11-19 08:00 through 2026-11-20 09:00 +07:00 | 25 hours rounds to 2 blocks = 360,000 VND |
| review-demo-01 | listing-01; is_demo=true; VISIBLE | Display as “Dữ liệu minh họa”; excluded from average/count |

Caregiver fixture data should not contain exact home address, phone or claims of identity verification. Area codes are internal declared zones, not verified geolocation.

## 4. Booking and history fixtures

| ID | State at scenario entry | Snapshot/payment/events | Used for |
|---|---|---|---|
| booking-requested-01 | REQUESTED | pet-01/listing-01; 09:00–11:10; total 150,000; no payment; create event | Owner waiting and Caregiver inbox |
| booking-awaiting-payment-01 | AWAITING_PAYMENT | accepted 08:10; payment_due_at 08:25; 15-minute hold; current attempt PENDING | Checkout success/fail/retry/expiry at demo clock 08:15 |
| booking-confirmed-01 | CONFIRMED | payment AUTHORIZED before deadline; snapshot unchanged | Start care and active conversation |
| booking-in-progress-01 | IN_PROGRESS | authorized payment; prior message and update | Caregiver can create care update |
| booking-awaiting-confirmation-01 | AWAITING_CONFIRMATION | caregiver completion event; release operation may be pending/failed | Owner confirm/release retry/admin follow-up |
| booking-overlap-01 | CONFIRMED | Same listing/time overlaps incoming request | Accept conflict |
| booking-cancelled-01 | CANCELLED | Separate reject and successful-refund examples, distinct reasons | Read-only state and event history |
| booking-completed-01 | COMPLETED | Release succeeded; review absent | Eligible review form |
| booking-completed-reviewed-01 | COMPLETED | One VISIBLE review | Duplicate review prevention |

Use 2026-11-18 09:00–11:10 for listing-01 to exercise DAYCARE 2h10 → ceil(duration/1h) = 3 hours, total 150,000 VND. Use a 25-hour BOARDING range for 2 blocks. Do not use client-provided total prices.

## 5. Scenario fixtures and deterministic outcomes

| Scenario ID | Entry fixture | Action/result | Expected UI/domain outcome |
|---|---|---|---|
| SC-01 Public search | guest-01 | Search listing-01 and listing-02 | Public ACTIVE listings only; no pet/private fields |
| SC-02 Smart Match success | owner-01 + pet-01 | Select future dates and area | listing-01 explains species/size/availability/total; no AI wording |
| SC-03 Missing size | owner-01 + pet-02 (size unknown) | Smart Match | 422-style field state; ask Owner to add size; do not assume fit |
| SC-04 Request create/replay | owner-01 + listing-01/slot-01 | Create booking; replay same logical request | One booking/event; same payload reuses idempotency; changed payload shows conflict |
| SC-05 Caregiver decision | booking-requested-01 | Accept or reject | Accept only before expiry and without overlap; reject becomes CANCELLED with event |
| SC-06 Availability conflict | booking-overlap-01 | Accept another overlapping request | Existing locked booking remains; other request stays REQUESTED with conflict |
| SC-07 Payment outcomes | booking-awaiting-payment-01 | Select success/failure/retry/expiry/late callback | Deterministic attempts and states; retry does not extend hold |
| SC-08 Care and completion | booking-confirmed-01 | Start, update, request completion, Owner confirm | Follow explicit states; release success required before COMPLETED |
| SC-09 Release failure | booking-awaiting-confirmation-01 | Simulate bounded failure then retry | Keep AWAITING_CONFIRMATION; same operation key; show Admin path after limit |
| SC-10 Report hold | booking-awaiting-confirmation-01 | Create booking report, mark IN_REVIEW | Block ordinary completion; dismissal changes report only; resolution uses operation |
| SC-11 Listing moderation | Draft/pending listing | Caregiver submit; Admin approve/reject | DRAFT/PENDING_REVIEW/ACTIVE or DRAFT with reason; public visibility follows state |
| SC-12 Private access | Any booking with messages/images | Switch to guest/unrelated persona | No private thread/image/pet snapshot; show not-found/permission state without content leakage |
| SC-13 Valid search with no match | owner-01 + pet-01 | Search a date/area with no eligible fixture | 200-style empty results with general filter suggestions; no listing is invented |

## 6. Mock behavior boundary

- Mirror input/output behavior only for confirmed API contracts. Do not claim unconfirmed endpoints exist; booking list/detail reads are mock-only because no generic GET routes were found.
- Simulate concurrency with preconfigured conflict fixtures and clear “conflict detected” outcome; do not claim the browser prototype proves database locking.
- Use local static placeholder assets or neutral placeholders. Do not fetch real people/pet-service imagery from unreviewed sources.
- Represent upload as local preview/mock attachment; enforce documented MIME/count/size constraints in the simulated interaction.
- Use a single mock store/service layer and stable IDs; page components do not own independent copies of the same booking.
- Include a “Reset demo” control behind a clearly labeled prototype menu; reset is confirmed before discarding state.
- Every payment, report decision and Admin operation carries a “Mô phỏng”/“Demo” marker.

