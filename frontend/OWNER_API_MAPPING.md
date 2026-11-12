# Owner Website — API readiness and gaps

Source checked: Laravel route definitions, controllers/resources/requests, API contract, domain rules, workflow docs and the accepted prototype screen matrix. This document describes the checked working tree; it is not a backend acceptance report.

## READY

| Owner capability | Backend operation | Integration status in this frontend |
|---|---|---|
| Register/login/logout/password reset | Breeze `/register`, `/login`, `/logout`, `/forgot-password`, `/reset-password`; `GET /sanctum/csrf-cookie`; `GET /api/user` | Implemented through a shared Sanctum session client. Registration only grants `OWNER`, per backend behavior. |
| Pet list/create/read/update/archive/activate/delete | `GET/POST /api/pets`, `GET/PATCH/DELETE /api/pets/{pet}`, `PATCH /archive`, `PATCH /activate` | List/create/update/archive/activate/delete UI implemented. Server authorization and validation remain authoritative. Photo upload is absent. |
| Public search/detail/media/reviews | `GET /api/listings`, `/api/listings/{id}`, `/api/listings/{id}/reviews`, public media content URL | Search, pagination, detail, allowlisted pet conditions, public media and review cards implemented. Smart Match uses only pet species/size supplied by the server. |
| Booking quote/create | `POST /api/bookings/quote`, `POST /api/bookings` with `Idempotency-Key` | Implemented for DAYCARE/HOUR and BOARDING/NIGHT. Quote/availability/price come from server. `+07:00` is sent explicitly. The same key is retained for a retry of the same payload. |
| Own reports list/create | `GET /api/reports/mine`, `POST /api/reports` | Implemented. The report target must be supplied and authorization is checked server-side. The reason code contract has no documented enum; UI takes the backend accepted string. |
| Booking conversation and care update reads | `GET/POST /api/bookings/{id}/messages`; `GET /api/bookings/{id}/care-updates` | Message thread and care update reading implemented for a known booking ID. The booking ID returned from creation links to the thread. Uploads are not included. |

## PARTIAL

| Capability | Confirmed backend operations | Remaining constraint |
|---|---|---|
| Booking management/cancel | `POST /api/bookings/{id}/cancel` with `Idempotency-Key` and optional `reason_note` | Cancellation is available only immediately after the create response, while the UI has that booking's server state. If cancellation is accepted asynchronously, UI reports pending; no booking list/detail API lets the Owner rediscover or track it later. |
| Sandbox checkout | Start attempt and simulate result: `POST /api/bookings/{id}/sandbox-payments`, `POST /api/bookings/{id}/sandbox-payments/{attemptId}/simulate` | Checkout actions are integrated on an explicitly labeled sandbox screen for a known booking ID; backend rechecks eligibility and simulation configuration. No booking read endpoint lets the Owner discover when a booking becomes `AWAITING_PAYMENT` or retrieve the latest attempt after leaving the screen. |
| Care lifecycle / Owner completion | `POST /api/bookings/{id}/confirm-completion`; `GET /api/bookings/{id}/care-updates` | Confirmation and care update reading work for a known booking ID and defer eligibility to Laravel. Owner cannot discover which booking entered `AWAITING_CONFIRMATION` or view a full status timeline without booking reads. |
| Reviews | `POST /api/bookings/{id}/reviews`; public `GET /api/listings/{id}/reviews` | Submission works for a known booking ID and displays success only after a backend response. There is no Owner review history or booking state read API. |
| Messages | Booking-scoped list/send, private attachments | List/send works for a known booking ID and API participant checks remain authoritative. No booking list/detail; attachments are not implemented in UI. Lifecycle-specific send restrictions are enforced by API and surfaced as errors. |
| Account overview/password recovery | `GET /api/user`, password reset routes | Read-only account page and reset entry point are present. No profile update, session management, or account-deletion route for Owner was found. |
| Smart Match | `GET /api/listings?pet_id=…` | Supported backend rules are species/size only. Age and special-needs suitability are not inferred or claimed. |

## BLOCKED — no confirmed API contract in checked routes

| Capability | Missing operation(s) needed to complete UX | Suggested contract for backend discussion only (not implemented) |
|---|---|---|
| Booking list/detail and lifecycle state | Owner-scoped list and detail/read model; ideally include status events and current payment attempt | `GET /api/bookings?status=&page=&per_page=` → `{data:[{id,status,pet_snapshot,listing_snapshot,start_at,end_at,price_snapshot_vnd,payment_due_at,latest_payment_attempt,available_actions}],links,meta}`; `GET /api/bookings/{id}` → same detail plus allowed timeline/events. Require authenticated active OWNER and participant scoping; non-owned booking should be indistinguishable from missing (404). |
| Owner profile update | No `PATCH /api/user` or equivalent found | `PATCH /api/user` accepting only supported profile fields, returning public account fields. Require session, active account and owner self-scope; fields and validation need contract approval. |
| Owner review history | No `/api/reviews/mine` found | `GET /api/reviews/mine?page=&per_page=` returning only review metadata owned by caller, with pagination. Require active OWNER; exclude private booking snapshots. |
| Public caregiver detail | No public caregiver profile endpoint; public listing resource has no caregiver identity/profile fields | `GET /api/caregivers/{public_id}` returning only approved public display name, public bio/area, and current public listing IDs. Must not expose private contact/address/verification claims. Need approved public identifier and privacy allowlist. |
| Care lifecycle read/timeline | No booking read/status event endpoint for the Owner | Include `status`, allowed Owner actions, status events, `payment_due_at`, and release state in the scoped booking detail contract; do not infer state client-side. |
| Report reason choices | API validates `reason_code` as a required string up to 64 characters; no canonical Owner-facing enum is documented | Backend currently accepts a free-form string rather than a documented reason catalog. Form labels this as a reason code and sends it unchanged. Agree a canonical enum or catalog for a polished production choice list. |

## Business constraints applied in UI

- P0 services are only `DAYCARE` priced per `HOUR` and `BOARDING` priced per `NIGHT` (a maximum 24-hour pricing block). No extra service type is introduced.
- DAYCARE quote rules (1–12 hours, same Vietnam day), BOARDING quote rules (12 hours–30 days), exact quantity rounding, slot coverage, conflicts, and final price are decided by Laravel. The client submits explicit `+07:00` times and never sends a price.
- `REQUESTED` does not hold a slot. The UI explains this after creation.
- Booking creation, payment, cancellation and review operations require stable idempotency/eligibility per server contract; frontend controls do not replace backend authorization.
- Public review demo flags are shown as illustrative and are not represented as verified trust signals.
- No fixtures, localStorage business records, fake caregiver profiles, fake booking lists, or fake payment results are used.
