# 06 — Screen Specifications

These specifications describe the interactive prototype, not existing Vue pages. Implement with shared shell/components and local mock data. Every business state is distinct from a loading/modal presentation state. Cross-screen IDs and priorities are defined in [05-screen-inventory.md](05-screen-inventory.md); domain paths are in [04-feature-flows.md](04-feature-flows.md).

## 1. Shared shell and common screen rules

### SHELL-01 — Role-aware application shell (P0)

- **Purpose:** provide consistent PETTEL identity, page title, navigation, current demo persona and responsive menu.
- **Structure:** header with brand/home, primary nav, notification/demo indicator, account menu; mobile uses compact header and accessible menu; main content uses one shared max-width grid; footer contains sandbox/demo notice and help/privacy links only if supported by approved copy.
- **Role navigation:** Guest: discover/login/register. Owner: discover, pets, bookings, profile. Caregiver: overview, booking requests, services, availability, profile. Admin reference uses separate Filament-style area and explicit “Mô phỏng giao diện quản trị”.
- **Persona switching:** a prototype-only, clearly labeled selector may change among preseeded Owner/Caregiver/Admin personas. It must never appear to be a real product role grant and must not offer “make me admin” as a user action.
- **States:** signed out, Owner, Caregiver (may also be Owner), Admin reference; menu open/closed; narrow viewport; route not found.
- **Accessibility:** skip link, landmarks, visible focus, keyboard menu close/escape and return focus, correct page title and active nav indication.
- **Evidence:** FE-H01 shared shell requirement; current frontend directory is unscaffolded.

## 2. Public and account screens

### AUTH-01 — Login, registration and password reset (P0)

- **Purpose/hierarchy:** short form first; explain service and sandbox only in secondary copy. Login, register and reset are distinct routes or tabs with clear labels.
- **Fields:** email; password; registration name/password confirmation only as required by Breeze/API contract; reset email; reset token/new password for reset completion. Email verification is not specified for P0.
- **Actions:** submit, reveal password, switch auth mode, request reset, return to login. No self-assigned admin field; new account is Owner.
- **Validation/states:** required/email format, password contract, pending, server field errors, invalid credentials, generic reset success, expired session (401), expired CSRF (419). Preserve non-secret input; clear password after failed submit if needed.
- **Account deletion:** Q-04 remains unresolved. P0 privacy/retention rules constrain all mock data, but this screen does not add a deletion-request UI until the user confirms scope.
- **Permissions/API:** Breeze routes and Sanctum CSRF endpoint; no real auth backend in prototype.
- **Navigation:** successful demo login uses selected persona; mock role switching is labeled demonstration.
- **Components:** AUTH form, input, button, inline field error, status notice.

### PUB-01 — Discovery and search results (P0)

- **Layout:** page title/search row; collapsible filters on mobile; applied-filter chips; result count and stable cards; empty/error state in the same content region.
- **Inputs:** keyword (max 100 chars after trim/NFC); service DAYCARE/BOARDING; area code; date/time pair; species/size; min/max total VND; optional Owner pet for Smart Match. Require both date endpoints together; price bounds require dates; start must be future; min ≤ max.
- **Card content:** listing title, service label, declared area, price unit and total only when dates are selected, acceptance chips, eligible review aggregate, up to a few photos if fixture includes them, and match reason codes translated to plain Vietnamese. Never show exact address or pet notes.
- **Actions:** submit filters; reset; remove one chip; open detail; paginate; choose a pet / switch to manual search.
- **States:** initial public results, loading skeleton, filtered results, no results with general suggestions, invalid form, result changed/unavailable message, guest vs signed-in Smart Match. Empty state must not invent listings or claim which private rule excluded one.
- **Simulation:** search applies the same deterministic rules as the fixture data: public ACTIVE only, acceptance species+size, slot coverage, locked booking overlap, calculated total price. Use one stable sort: total price then listing ID when dates exist; otherwise ID.
- **APIs if later integrated:** GET /api/listings; GET /api/pets for Owner selector.

### PUB-02 — Listing detail (P0)

- **Hierarchy:** title/service/area and public caregiver summary; media gallery; service description; price unit and acceptance rules; date/availability selector; review evidence; sticky booking CTA on mobile.
- **Data:** do not show total until a valid date range is quoted; show “mỗi giờ” for DAYCARE and “mỗi block tối đa 24 giờ” for BOARDING; area is approximate internal area; no home address.
- **Actions:** view photos/reviews; select dates and pet; request quote; start booking; submit report on allowed target.
- **States:** public ACTIVE; no longer public/hidden; no reviews; no photos; selected dates unavailable; quote validation; match context from search preserved.
- **Rules:** details are public; booking eligibility is rechecked on create/accept; seed reviews labeled illustrative and excluded from aggregate.
- **APIs:** GET /api/listings/{id}; /reviews and media content; quote endpoint.

## 3. Owner screens

### OWN-01 — Pet manager and editor (P0)

- **Hierarchy:** pet list with status; primary Add Pet; editor grouped into identity, care and optional notes; privacy note near sensitive fields.
- **Fields:** name (≤100 chars), species DOG/CAT/OTHER, size SMALL/MEDIUM/LARGE or unknown, age/birth date if captured, sex, diet note, owner-entered vaccination note, temperament and special needs; notes max 2,000 characters. Avoid collecting fields not present in contract.
- **Actions:** create, edit, archive/activate, select for booking. Destructive/archival action requires confirmation and explains that booking history remains.
- **States:** no pets; active and archived; save pending/success; field errors; no access; archive/activate conflict.
- **Privacy:** pet details never public; Smart Match response must not echo them. Caregiver sees only booking snapshot under participant policy.
- **APIs:** GET/POST/PATCH/DELETE /api/pets and archive/activate endpoints.

### OWN-02 — Quote and booking request (P0)

- **Hierarchy:** listing and selected pet; time range; private care instructions; price calculation; cancellation terms; explicit sandbox notice; final review/submit.
- **Inputs:** one owned ACTIVE pet; start/end with explicit offset; instructions optional ≤2,000 after trim/NFC. DAYCARE 1–12 hours, same Ho Chi Minh date; BOARDING 12 hours–30 days. Show server quote as total, unit and quantity; never accept a client-entered amount.
- **Actions:** change pet/date, refresh quote, confirm and submit with one idempotency key. If retried, reuse the key and same normalized payload.
- **States:** quote loading, validation, no open slot, eligibility mismatch, availability conflict, request sent, replay returned existing booking, key/payload conflict, stale listing.
- **Copy:** “Yêu cầu chưa được chấp nhận”; “Chưa giữ chỗ” while REQUESTED. Explain caregiver acceptance starts a 15-minute payment hold.
- **APIs:** POST /api/bookings/quote, POST /api/bookings with Idempotency-Key. Prototype action writes a mock booking only.

### OWN-03 — Booking list and detail (P0)

- **List hierarchy:** active/pending first; history; status filters; each card shows service, pet label, time, total snapshot, status and next actor.
- **Detail hierarchy:** current status + next step; immutable booking snapshot; event timeline; payment/operation status; related thread/update tabs; allowed actions at bottom.
- **States:** REQUESTED waiting caregiver; AWAITING_PAYMENT deadline; CONFIRMED; IN_PROGRESS; AWAITING_CONFIRMATION; cancellation pending; CANCELLED/COMPLETED; empty/history; record not found; stale state.
- **Permissions:** only Owner sees own records and scoped pet snapshot; no direct status editing.
- **Critical gap:** route inventory has no general booking list/detail GET. This page is mock-only for the prototype and requires an API contract before real Vue integration.
- **Actions:** open checkout; cancel when allowed; chat; confirm completion when allowed; review only after COMPLETED; report.

### OWN-04 — Sandbox checkout (P0)

- **Layout:** booking/payment summary; high-visibility “Simulation only — no real payment”; scenario choices labeled demo; action and status/result panel.
- **Scenarios:** success, failure/retry, expired, late callback ignored, cancel while pending, refund/cancellation pending, release pending/failure shown later on completion flow.
- **States:** payment PENDING, AUTHORIZED, FAILED, VOIDED/REFUNDED/RELEASED; booking changes only after corresponding successful event.
- **Actions:** start payment; choose a deterministic scenario; retry only while valid; return to booking. Never ask for payment details.
- **APIs:** start sandbox payment and simulate attempt routes. Callback itself is system-side and not a user form.

### BOOK-01 — Conversation and care updates (P0, shared with Caregiver)

- **Layout:** booking header/status, two tabs (Messages / Care updates), chronological thread, composer; mobile composer remains keyboard-safe.
- **Message fields:** body up to 2,000 chars; up to 5 JPEG/PNG/WebP attachments, combined max 10,000,000 bytes, where relevant. Update body/image max 2,000 chars; do not promise realtime.
- **Actions:** paginate/read, send; Caregiver may create care update only in IN_PROGRESS; image preview/removal before submit. Atomic upload error leaves no partially sent message.
- **States:** empty, sending, sent, upload failed, forbidden/not found, read-only, redacted/expired; use Asia/Ho_Chi_Minh display from UTC.
- **Permissions:** thread belongs to one booking; nonparticipants do not access; care updates are participant-readable, caregiver-written in allowed state; Admin access appears only in audited evidence context.
- **Retention UI:** completed thread can accept messages only before fixed 90-day cutoff; canceled thread read-only. No “extend retention by messaging” behavior.
- **APIs:** messages and care-updates routes and private attachment/image routes.

### OWN-05 — Confirm completion (P0)

- **Content:** completed-by-caregiver notice, care summary, any available updates, explicit action effect.
- **Action:** confirm completion opens a confirmation dialog. The UI then shows release processing and waits for actual/mock success.
- **States:** AWAITING_CONFIRMATION; release pending; release failure (stay awaiting); success (then COMPLETED); report hold/needs Admin; stale action.
- **Rule:** no timer or client action can move directly to COMPLETED. 24-hour reminder and 72-hour admin queue do not auto-release.
- **API:** POST /api/bookings/{id}/confirm-completion.

### OWN-06 — Review form and public review display (P0)

- **Form:** integer stars 1–5; optional body ≤1,000 chars; submit only for Owner’s COMPLETED booking.
- **States:** not eligible; eligible; submitted; already reviewed; hidden; demo/seed. A hidden existing review does not enable another review.
- **Public card:** rating average to 2 decimals and count from visible non-demo completed-booking reviews; null/0 empty aggregate. Never label seed as verified.
- **API:** POST booking review; GET public listing reviews.

### OWN-07 — Report composer and report list (P0)

- **Composer:** identify target context (one listing/review/message/booking), required reason, optional description ≤2,000. Warn not to include irrelevant health, contact, credential or financial details.
- **List:** caller’s own report status, submission time, target summary and next action only; never show other party’s private evidence.
- **States:** OPEN after submit; loading/validation; target not accessible; IN_REVIEW/RESOLVED/DISMISSED.
- **APIs:** POST /api/reports; GET /api/reports/mine.

## 4. Caregiver screens

### CG-01 — Caregiver role/profile (P0)

- Explain that enabling caregiver role is distinct from listing approval and is not identity verification.
- Profile form only uses API-contract fields; include declared area/bio and no unsupported Verified/Pro badge.
- States: role not enabled, setup, saved, validation, profile unavailable. APIs: role activation and caregiver profile.

### CG-02 — Listing workspace/editor (P0)

- Dashboard groups DRAFT, PENDING_REVIEW, ACTIVE, SUSPENDED, ARCHIVED and pending change request.
- DRAFT editor fields: title ≤120, description ≤2,000, DAYCARE/BOARDING, price integer >0, required price unit, area code, acceptance_rules_json v1 species/sizes arrays, media. No dynamic fees/tax/discounts.
- Media: JPEG/PNG/WebP, ≤10 MiB, ≤20 megapixels, up to 10 per listing; private upload and approved public rendering. Slot management is separate.
- Actions: save draft, submit, create change request, withdraw pending change, suspend/restore/archive when allowed.
- States/errors: draft, pending, rejected with reason, active, suspended by caregiver/admin, archived terminal; upload invalid, overlapping slot, pending change already exists, archive blocked by locked booking.
- No representation of approval as an instant caregiver action.

### CG-03 — Availability calendar (P0)

- Show open intervals and occupied/locked booking ranges separately. Avoid exposing Owner identity or pet notes on calendar.
- Form accepts offset timestamps, stores UTC, keeps microseconds where contract requires. Slots cannot overlap; adjacent slots may remain separate. One slot must fully cover a booking; do not imply split-slot coverage.
- Deleting/changing slot that would break an active locked booking is blocked; REQUESTED does not itself lock the slot, but acceptance rechecks.
- API: caregiver listing availability slot GET/POST/PATCH/DELETE.

### CG-04 — Listing moderation status/change requests (P0)

- Show current public version separately from pending changes. Explain that active content stays public until approval unless listing is suspended.
- Rejection reason and state history are prominent. Caregiver may withdraw a pending change request; archive is terminal and blocked while a booking locks listing.
- API: submit/list/show/withdraw change request; admin decision is a separate action.

### CG-05 — Booking inbox/detail (P0)

- Inbox separates REQUESTED from accepted/active/history; each detail exposes only booking snapshot fields needed for care.
- REQUESTED actions: accept or reject. Accept shows hold deadline only after successful state change; conflict/expired returns to clear “không được nhận” state. Reject records reason code.
- Active actions: start care at CONFIRMED, request completion at IN_PROGRESS; cancellation only before IN_PROGRESS under payment rules. No action after terminal state except permitted read/report.
- **Critical gap:** no generic booking inbox/detail read API was found; prototype uses mock fixtures. Record in FE integration matrix.

## 5. Admin reference screens

### ADM-01 — Listing moderation reference (P0 workflow, limited Filament-style scope CONFIRMED)

- Show a minimal Filament-like table/detail to communicate queue, listing/version/media review, approve/reject reason, suspend/restore/archive with confirmation and audit history.
- Label as “Mô phỏng tham chiếu Filament”. Do not implement a separate role-based Admin SPA or suggest public access.
- User confirmed a limited reference view; backend source has admin listing routes but no Filament listing resource in workspace. This prototype screen is a proposed visual reference, not a representation of an existing Filament page.

### ADM-02 — Reports and booking operation reference (P0 workflow, limited Filament-style scope CONFIRMED)

- Reports table filters by status; detail summarizes reporter/target/reason without leaking evidence by default; evidence requires explicit permitted view; history shows actor/time/reason; action dialog requires reason.
- Booking report resolution choices are COMPLETE or CANCEL_REFUND through operation/outbox. Display pending, retry/manual review and final success distinctly inside the relevant report case.
- Do not create a separate booking-operations dashboard. No direct state/payment editing.
- Use existing Filament ReportResource as the visual/product boundary; the reference view is not a claim of pixel-perfect reproduction or of unobserved Filament pages.

