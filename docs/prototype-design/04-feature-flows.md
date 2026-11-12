# 04 — Feature Flows

State names below are business states; UI copy must be understandable Vietnamese. Mock responses must be visually distinguished from real backend behavior. **UNKNOWN** indicates the exact UI/API behavior is not established by reviewed sources.

## F01 — Identity, role activation and pet management

| Field | Specification |
|---|---|
| Actor / entry | Guest at login/register/reset; Owner at pet management; signed-in Owner enables Caregiver role. |
| Preconditions | Public discovery needs no login. Pet CRUD requires active authenticated Owner. Caregiver role is opt-in; ADMIN is not self-assigned. |
| Main path | Register/login → Owner session → create/update/archive own pet → select pet for search/booking. Signed-in user may separately enable CAREGIVER and create a profile. |
| Alternative/error paths | Invalid fields remain editable; 401 returns to auth; 419 refreshes CSRF/session; wrong owner cannot open pet (404/403 per contract); archived pet cannot book (409). Password reset does not disclose whether the email exists. |
| Permissions | Owner reads/writes own pet. Caregiver sees only the scoped booking snapshot for an associated booking. Admin reads private data only for an audited case. |
| State/business rules | Pet ACTIVE/ARCHIVED; only ACTIVE can be used for a new booking. Soft delete/history does not erase a booking snapshot. Vaccination note is owner-entered, not certification. |
| Exit / next | Select pet → F02 Smart Match or F04 booking. Enable Caregiver → F03. |
| Sources | M01, M02; P0 §§5–8; auth routes, /api/pets*, /api/user/roles/caregiver; PetProfileTest and auth tests. |

## F02 — Discovery, search and Smart Match

| Field | Specification |
|---|---|
| Actor / entry | Guest or Owner opens GET /api/listings; Owner may supply own pet as pet_id. |
| Preconditions | Public result listing is ACTIVE, not soft-deleted, and caregiver profile/user/role eligible. Smart Match requires active authenticated Owner and their ACTIVE pet with species and size. |
| Main path | Set service, area, dates, species/size and total budget → optional pet Smart Match → combine filters with AND → see explainable match reasons → open listing detail/reviews/media. |
| Alternative/error paths | Empty result returns 200/data empty and general suggestions; invalid query 422; non-public detail 404; wrong pet hidden as 404, archived pet 409, missing species/size 422. Never claim this is AI or infer age/special-needs fit. |
| Permissions | Guest reads public allowlist only. Owner can match own pet; response contains no pet details. Owner-managed listing is excluded from own booking match. |
| State/business rules | Only ACTIVE public; dates require timezone and future start; one slot must cover the full range; interval is [start,end); only four booking states lock calendar. Budget compares total calculated price, not hourly and nightly unit prices. |
| Exit / next | Listing detail → F04 quote/create. Empty state → edit filters. |
| Sources | Workflow 01; M04; P0 §§1,7–8; API Contract M04; listings/detail/reviews/media routes; PublicDiscoveryEndToEndTest and SmartMatchTest. |

## F03 — Caregiver profile, listing, availability and moderation

| Field | Specification |
|---|---|
| Actor / entry | Caregiver opens profile/listing workspace; Admin reviews a submitted listing/change request. |
| Preconditions | Active signed-in user with CAREGIVER role and profile for listing management; Admin account for review. |
| Main path | Enable role → create profile → create DRAFT listing/media/open slots → submit → Admin approves to ACTIVE or rejects with reason to DRAFT. Critical ACTIVE/SUSPENDED edits use a change request; Caregiver may withdraw while pending. |
| Alternative/error paths | Invalid price/rules/media/slot gives field errors; overlapping slot rejected; pending change request blocks another; archive blocked while a booking locks listing; caregiver cannot restore admin suspension; wrong owner/record returns 404. |
| Permissions | Caregiver manages own profile/listings/slots. Admin moderation is reasoned/audited. Previously approved version remains public until change approval unless caregiver suspends it. |
| State/business rules | Listing DRAFT → PENDING_REVIEW → ACTIVE; reject returns DRAFT; ACTIVE/SUSPENDED may be archived, terminal. Change request PENDING_REVIEW → APPROVED/REJECTED/WITHDRAWN. One continuous slot must cover a booking. Rules v1 require nonempty species/sizes arrays. |
| Exit / next | ACTIVE listing → F02 discovery; dashboard → F04 booking inbox. |
| Sources | Workflow 02; M03; P0 §§2,8,12; BE-T03 contract/report; caregiver and admin listing/slot routes; lifecycle/media/availability tests. |

## F04 — Booking request, accept, reject and cancellation

| Field | Specification |
|---|---|
| Actor / entry | Owner starts from listing detail; Caregiver enters from booking inbox. |
| Preconditions | Owner/account/role valid; own ACTIVE pet; ACTIVE listing; eligible caregiver profile/account/role; no self-booking; pet matches species and size; one open slot contains range; quote and policy shown. |
| Main path | Quote → confirm pet/date/instructions/total → idempotent create → REQUESTED; Caregiver accepts → transaction rechecks listing/eligibility/slot/overlap → AWAITING_PAYMENT with 15-minute hold. |
| Alternative/error paths | Caregiver rejects → CANCELLED with CAREGIVER_REJECTED event; Owner/Caregiver may cancel before care under payment-state rules; expiry follows cancellation operation. Same key/payload replays saved booking; changed payload 409. Conflict 409; invalid input 422; private/missing resource 404. |
| Permissions | Only booking owner or assigned caregiver uses normal actions. Admin exceptions use F09, not normal cancel endpoint. |
| State/business rules | REQUESTED does not lock slot; accept must be before min(created_at + 24h, start_at). AWAITING_PAYMENT, CONFIRMED, IN_PROGRESS, AWAITING_CONFIRMATION lock. REQUESTED expires at 24h or start, whichever is earlier. Do not treat overdue hold as free until cancellation succeeds and state releases it. |
| Exit / next | AWAITING_PAYMENT → F05; CONFIRMED → F06/F07; cancellation → read-only history as allowed. |
| Sources | Workflow 03; M05; P0 §§1–4,8–13; API Contract M05; booking action routes; creation/acceptance/cancellation/expiry/concurrency tests. |

## F05 — Sandbox checkout and payment operations

| Field | Specification |
|---|---|
| Actor / entry | Owner opens checkout for accepted booking; sandbox is simulated external actor; Admin sees manual operations only when policy routes it there. |
| Preconditions | Booking is AWAITING_PAYMENT and current attempt/deadline valid. No real card/bank credentials. |
| Main path | Start payment attempt → choose deterministic demo success → authorized callback → booking CONFIRMED; always show “Thanh toán mô phỏng — không trừ tiền thật.” |
| Alternative/error paths | FAILED may retry before deadline; duplicate/late callback is idempotent and cannot revive expired booking. Expiry/cancel void/refund must succeed before final cancellation/releasing schedule. Release failure keeps AWAITING_CONFIRMATION; bounded retry then admin. Pending cancellation stays processing, not false CANCELLED. |
| Permissions | Owner starts/simulates payment for own booking. Admin handles exceptional operation queue with reason. Never ask for card, CVV, OTP or bank account. |
| State/business rules | Payment NOT_STARTED → PENDING → AUTHORIZED → RELEASED, or FAILED; cancellation uses VOIDED/REFUNDED mock. One release maximum; retry uses same operation key; booking final state follows successful payment operation. |
| Exit / next | AUTHORIZED → F06/F07; failure → retry/cancel; timeout → expired booking state/history. |
| Sources | M06; P0 §§3–4,13; BE-HA05; payment routes; sandbox/cancellation/release tests. |

## F06 — Care lifecycle and completion

| Field | Specification |
|---|---|
| Actor / entry | Caregiver enters confirmed booking detail; Owner receives completion request. |
| Preconditions | Payment authorized; booking CONFIRMED; actor is a participant. |
| Main path | Caregiver starts → IN_PROGRESS → posts updates → requests completion → AWAITING_CONFIRMATION → Owner confirms → release succeeds → COMPLETED. |
| Alternative/error paths | Wrong actor/state rejected; no ordinary cancellation after care begins. Remind Owner once after 24h; after 72h send to Admin queue, never auto-complete. Release failure leaves waiting state; report IN_REVIEW blocks ordinary completion/release. |
| Permissions | Assigned Caregiver starts/requests completion; only Owner confirms; Admin may resolve exception with reason/audit and sandbox operation. |
| State/business rules | No COMPLETED before successful release or authorized Admin exception path. Release and retries are idempotent. |
| Exit / next | COMPLETED → F08 review; conversation becomes read-only after fixed send window; report remains available per policy. |
| Sources | P0 §4; M05/M06/M09; BE-HA06; start/request-completion/confirm-completion routes; lifecycle/follow-up/report-decision tests. |

## F07 — Messaging, care updates and private media

| Field | Specification |
|---|---|
| Actor / entry | Owner or assigned Caregiver from booking detail. Admin only from audited report/booking case. |
| Preconditions | Authenticated participant and existing booking. Each booking has its own thread. |
| Main path | Read/paginate messages; send text and permitted attachments; Caregiver creates text/image update only in IN_PROGRESS; Owner/Caregiver view timeline and private media. |
| Alternative/error paths | Unrelated user receives same 404 for message/attachment as nonexistent booking; care-update unrelated user receives 403 per contract. Invalid media is rejected atomically; care-update image URL is short-lived; expiry/redaction follows policy. |
| Permissions | Both participants read chat within retention; send during active states and fixed 90 days after completion; cancelled chat is read-only. Only assigned Caregiver creates update during IN_PROGRESS; both participants read. Admin access needs case, reason and audit. |
| State/business rules | Never merge threads; never expose permanent public URL/object key; display local time from UTC; new messages do not extend the fixed post-completion window. |
| Exit / next | Booking detail; completion; report content if needed. |
| Sources | M07; P0 §§5–6; message/care-update routes; message/access/retention tests. |

## F08 — Review and trust signals

| Field | Specification |
|---|---|
| Actor / entry | Owner after own booking becomes COMPLETED; guest/Owner reads public listing reviews. |
| Preconditions | Active Owner owns completed booking; no prior review for that booking. |
| Main path | Submit integer rating 1–5 and optional text up to 1,000 characters → visible by default → aggregate updates from eligible visible, non-demo reviews of completed bookings. |
| Alternative/error paths | Before completion or duplicate gets state/conflict error; invalid rating/body gets 422; hidden review remains historical and cannot be recreated. Report does not auto-hide. |
| Permissions | Owner only creates for own booking; Admin hides/restores with history. Caregiver cannot review own listing. |
| State/business rules | No composite trust score or verified badge. Seed/demo review is labeled and excluded from public average/count. No eligible review means null average / 0 count. |
| Exit / next | Return to listing detail/history; report review through F09. |
| Sources | M08; P0/BE-H05 contract; create booking review and public listing reviews routes; review create/moderation/public review tests. |

## F09 — Reports, moderation and Admin decisions

| Field | Specification |
|---|---|
| Actor / entry | Owner/Caregiver reports a listing/review/message/booking they can see; Admin opens Filament Reports queue. |
| Preconditions | Authenticated reporter; target access allowed; exactly one target, reason required and description optional. Admin account for queue/evidence/action. |
| Main path | Submit → OPEN → Admin IN_REVIEW → policy-scoped evidence → DISMISSED or RESOLVED with reason/action/history. Content moderation hides/restores; listing moderation suspends/restores. |
| Alternative/error paths | Invalid/nonexistent target does not leak private content; non-admin cannot open panel/evidence; booking report IN_REVIEW holds completion; release/refund pending/failure stays pending or manual review. |
| Permissions | Reporter sees own report state only. Admin reads minimum evidence needed. Admin actions record actor/time/reason/request ID. |
| State/business rules | OPEN → IN_REVIEW → RESOLVED/DISMISSED per contract. Booking decision is COMPLETE or CANCEL_REFUND through service/outbox. Dismissal changes report only. UI never directly mutates booking/payment. |
| Exit / next | Listing restored/hidden; booking settles only after operation succeeds; reporter sees status, not other party's private data. |
| Sources | Workflow 04; M09; P0 §§4–6,13; report/admin routes and Filament ReportResource; report workflow/evidence/moderation/booking-decision tests. |

**Source drift:** some workflow/module prose describes moderation outcomes at a high level. Newer P0/API contracts require booking report outcomes to use operation/outbox and state that booking changes only after sandbox success; follow the newer contract.
