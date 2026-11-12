# 05 — Screen Inventory

**Priority:** P0 = required for a complete demo; P1 = only after P0; P2 = deferred. A row is a screen group; tabs, modal and loading/error states do not count as additional pages unless required. Endpoints listed as available are found in route source, not proof of browser UI. Prototype uses local mock state.

## 1. Public and authentication

| ID / screen | Actor · feature · purpose | Entry and actions | Required information / fields | States, validation, permission | API / navigation / priority | Reusable UI and evidence |
|---|---|---|---|---|---|---|
| PUB-01 Discovery & results | Guest/Owner · F02 · find service | Public nav; search, filter, Smart Match, reset, paginate | q, service, area, start/end, total budget, species/size, optional Owner pet | Loading/results/empty/sort; invalid query; Smart Match requires signed-in Owner and owned ACTIVE pet; only public ACTIVE listing | GET /api/listings; GET /api/pets for pet selector; → PUB-02/OWN-02; P0 | Search filters, listing card, match reason, price summary, pagination; M04/API M04 |
| PUB-02 Listing detail | Guest/Owner · F02 · evaluate service | Card; inspect details/media/reviews; continue to quote | Title/description, service/area, unit price, acceptance rules, media, eligible review summary | Hidden/unavailable=404; detail has no date context so price total is not a quote; no private caregiver/pet data | GET /api/listings/{id}, /reviews, media; POST /api/bookings/quote; → OWN-02; P0 | Gallery, rule chips, review summary, action bar; M04/M08 |
| AUTH-01 Account access | Guest · F01 · login/register/reset | Header; login, register, request/reset password | Email/password; registration fields per auth contract | Loading, validation, invalid credentials, generic reset feedback, 401/419; new user OWNER only; ADMIN not self-assignable. Email verification behavior UNKNOWN | Breeze auth routes + /sanctum/csrf-cookie; → role home; P0 | Auth form, inline errors, password reveal, status notice; M01 |

## 2. Owner and shared booking screens

| ID / screen | Actor · feature · purpose | Entry and actions | Required information / fields | States, validation, permission | API / navigation / priority | Reusable UI and evidence |
|---|---|---|---|---|---|---|
| OWN-01 Pet manager/editor | Owner · F01 · manage pets | Owner nav; create/edit/archive/select | Name, species, size, age/date if collected, sex, diet/vaccination notes, temperament, special needs | Empty/list/form/errors; ACTIVE vs ARCHIVED; notes private; archived cannot book; hold values on validation error | GET/POST/PATCH/DELETE /api/pets, archive/activate; → PUB-01/OWN-02; P0 | Pet card, form sections, archive confirmation, privacy note; M02/P0 §§6–9 |
| OWN-02 Quote and booking request | Owner · F04 · review before sending | PUB-02; select pet/time; quote; submit | Pet, listing, start/end with timezone, optional instructions ≤2,000, total/unit/quantity, cancellation policy, sandbox notice | Quote/loading/error; availability or eligibility conflict; validation; idempotent submit; no client-calculated amount accepted | POST /api/bookings/quote, POST /api/bookings + Idempotency-Key; → OWN-03; P0 | Date/time input, pet picker, price/policy summary, confirmation; M05/P0 §§1,8–11 |
| OWN-03 Booking list and detail | Owner · F04/F06 · track state/action | Owner nav; select request/active/history item | Booking reference, listing/pet snapshot, time, total/policy, current state, next actor/action, events | Empty/loading/pending/cancelled/completed; participant-only data; stale state/conflict and error paths | **GAP:** no generic GET /api/bookings collection/detail route seen; mock-only in prototype; → OWN-04/BOOK-01; P0 | Booking cards, state badge, snapshot, timeline, next-step panel; FE-T01 |
| OWN-04 Sandbox checkout | Owner · F05 · simulate payment | AWAITING_PAYMENT booking; choose demo result | Due time, snapshot amount, success/failure/late/expiry scenario; no card credentials | Pending, authorized, failed/retry, expired, late callback ignored, cancellation pending; no CONFIRMED before authorization | POST /api/bookings/{id}/sandbox-payments and attempt simulate; server callback route; → OWN-03; P0 | Sandbox banner, deadline, status, retry; M06/BE-HA05 |
| BOOK-01 Conversation and care updates | Owner/assigned Caregiver · F07 | Booking detail; read/paginate/send message, attach image, view updates | Message body/attachments; update body/image; sender/time | Empty/loading/upload errors; read-only after cancellation or time cutoff; care update composer only for assigned caregiver in IN_PROGRESS; private image auth | GET/POST /api/bookings/{id}/messages*, care-updates*; → OWN-05/CG-05; P0 | Thread, composer, attachment preview, timeline, time labels; M07/P0 §§5–6 |
| OWN-05 Completion confirmation | Owner · F06 · confirm service completion | Booking AWAITING_CONFIRMATION | Booking/care summary; confirm action and effect | Confirmation; release pending/failure; stays awaiting on failure; COMPLETED only after release succeeds | POST /api/bookings/{id}/confirm-completion; → OWN-06; P0 | Confirmation dialog, pending/error notice, state timeline; P0 §4 |
| OWN-06 Review | Owner · F08 · review completed service | Completed booking or listing | Integer rating 1–5; optional body ≤1,000; existing review | Disabled until eligible; duplicate conflict; demo label; hidden review remains existing | POST /api/bookings/{id}/reviews; GET /api/listings/{id}/reviews; P0 | Rating control, character counter, review card/demo badge; M08/BE-H05 |
| OWN-07 Report composer and own reports | Owner/Caregiver · F09 | Content action menu; report list | Exactly one target, reason, optional description ≤2,000 | OPEN after submit; field errors; target access returns 404 as specified; caller sees own reports only | POST /api/reports; GET /api/reports/mine; P0 | Reason select, counter, status history; M09 |

## 3. Caregiver screens

| ID / screen | Actor · feature · purpose | Entry and actions | Required information / fields | States, validation, permission | API / navigation / priority | Reusable UI and evidence |
|---|---|---|---|---|---|---|
| CG-01 Caregiver profile | Signed-in user with CAREGIVER · F01/F03 | Enable role; create/update profile | Display name/business, bio, area code and contract-approved fields | Role not enabled/profile empty/saved; never display unverified badge; owner of profile only | POST/GET/PATCH /api/caregiver/profile; P0 | Profile form/header and save feedback; M01/M03 |
| CG-02 Listing workspace/editor | Caregiver · F03 · create/manage services | Caregiver nav; list/new/edit/submit | Title/description, service type, area, price/unit, species/sizes rules, photos | DRAFT edit; pending/rejected/active/suspended/archive; invalid rules/price/media; only own listing | /api/caregiver/listings*, /api/media; submit/change-request routes; P0 | Listing card, sections, upload preview, status badge; M03 |
| CG-03 Availability calendar | Caregiver · F03 · manage open slots | Listing → calendar | Start/end with offset; open slots and booking holds | Empty/open/overlap; no slot overlap; locked booking cannot lose required coverage; adjacent slots remain distinct | /api/caregiver/listings/{id}/availability-slots; P0 | Range editor/calendar, conflict notice; M03/BE-T03 |
| CG-04 Listing review/change requests | Caregiver · F03 · track moderation | Listing status/details | Current listing version, submission status, rejection reason, change request status | Pending/rejected-to-draft/approved/withdrawn; approved version stays public during pending change unless suspended | Submit/change-request/withdraw endpoints; P0 | Status timeline, reason panel, change comparison; M03/lifecycle |
| CG-05 Booking inbox/detail | Caregiver · F04/F06 · decide and care | Caregiver nav; open request/active/history | Booking and pet snapshot, instructions, time, price snapshot, lifecycle/events | REQUESTED accept/reject; expired/conflict; AWAITING_PAYMENT through terminal states; actions by state | **GAP:** no generic GET booking inbox/detail route seen; action routes accept/reject/start/request-completion/cancel exist; P0 | Booking card, snapshot, decision confirmation, next step; FE-T01 |

## 4. Admin and deferred screens

| ID / screen | Actor · feature · purpose | Entry and actions | Required information / fields | States, validation, permission | API / navigation / priority | Reusable UI and evidence |
|---|---|---|---|---|---|---|
| ADM-01 Listing moderation reference | Admin · F03 · approve/reject/suspend listing | Existing admin workflow | Listing submission/version/media, reason, decision history | Admin-only; reason/audit; current workspace has admin API routes but no Filament listing resource | /api/admin/listings*, /api/admin/listing-change-requests*; **limited reference confirmed by user**; P0 workflow | Minimal Filament-style reference simulation; no Admin SPA; do not imply this Filament page already exists |
| ADM-02 Reports queue/detail/evidence/history | Admin · F09 · include booking operation state in related report case | Existing Filament Reports navigation | Queue/filter, target summary, report reason/status, permitted evidence, history, decision reason; related operation state | Admin-only; evidence separately authorized/audited; booking decisions use operation path | Filament ReportResource and /api/admin/reports*; **limited reference confirmed by user**; P0 workflow | Table, case detail/action dialog, history/evidence panel and operation state; M09 |
| PUB-03 Listing comparison | Owner · P1 compare services | Discovery select comparison | Side-by-side price/conditions/reviews | After P0 acceptance | No dedicated API; optional mock-only; P1 |
| CG-06 Caregiver statistics | Caregiver · P1 service metrics | Caregiver dashboard | Measures not yet contractually defined | P1; no confirmed metric/API | No confirmed screen/API; P1 |

## 5. Coverage by frontend issue

| FE issue | Screen groups | Main interactions | Dependencies / gaps |
|---|---|---|---|
| FE-H01 | AUTH-01, OWN-01, shared shell/navigation | Auth, 401/419, role-aware nav, pet CRUD | Vue not scaffolded; Sanctum cookie/CSRF contract |
| FE-T01 | OWN-02/03, CG-05 | Quote/create/replay, inbox, accept/reject/cancel, status/history | Generic booking read/list API missing from current route inventory |
| FE-HA01 | CG-01/02/03/04, ADM-01 | Profile, listing/availability, review/moderation | API routes exist; Filament listing resource not found |
| FE-H02 | PUB-01/02, OWN-06 | Search/filter/Smart Match, detail/reviews, empty state | Search contract and review/media routes |
| FE-T02 | OWN-04/05 and CG-05 transitions | Sandbox outcomes, start/request completion/confirm | Payment routes/operations exist; no real payment |
| FE-HA02 | BOOK-01, OWN-07, ADM-02 | Messaging, private media, updates, reports/admin | Private attachment policy; booking operation status appears within report case; no separate Admin SPA |

**Count:** 18 P0 screen groups, 2 P1 groups. Admin booking operation status is embedded in ADM-02, not a separate screen. Tabs, dialogs, loading/empty/error states are included within the parent screen groups. Inventory describes intended prototype coverage; no current Vue screens were found.

## 6. Scope item pending user confirmation

| Item | Source tension | Current handling |
|---|---|---|
| Account deletion request / DELETION_PENDING | P0 Contract §6 defines deletion/retention behavior, while FE-H01 scope and current API route inventory do not include a deletion screen/endpoint. The user was asked whether to include a minimal simulation; no selection was received. | Not counted in the 18 P0 screen groups and not included in Builder Brief implementation scope until confirmed. Privacy/retention constraints still apply to all mock records. |

