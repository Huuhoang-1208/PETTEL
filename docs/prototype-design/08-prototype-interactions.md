# 08 — Prototype Interaction & State Specifications

This document describes deterministic front-end simulation. It does not claim that a mock action is a real backend operation. Business rules and permission checks must be applied by the mock layer so the UI cannot demonstrate invalid transitions.

## 1. Interaction contract

For each action, the prototype model keeps:

- **Business state:** persisted mock domain state, such as booking REQUESTED or payment AUTHORIZED.
- **Presentation state:** loading, dialog open, validation error, toast, selected tab, or disabled control.
- **Scenario state:** selected deterministic response, such as sandbox success/failure/release failure.

An action applies only after permission, current state and input are checked. Failed action leaves business state unchanged, preserves safe form values, shows a useful explanation, and offers only a valid recovery path. Do not auto-advance multiple business states because a user clicked one button.

## 2. Critical action table

| ID / trigger | Conditions and state before | User feedback | State after / navigation | Error, retry and domain constraint |
|---|---|---|---|---|
| INT-01 Submit search | Public discovery; valid query | Loading then stable results; applied filter chips | Filter state updates; remain PUB-01 | Invalid query points to filter; empty result suggests editing criteria. Only ACTIVE public listings; total-price sort only when date range exists. |
| INT-02 Run Smart Match | Signed-in Owner persona; own ACTIVE pet with species+size | “Đang đối chiếu loài, kích cỡ, lịch và giá” with reasons | Results show tested match reasons; remain PUB-01 | Missing/archived/foreign pet uses contract errors; no age/special-needs/AI claims. |
| INT-03 Open listing | Listing still public in mock state | Detail content loads | PUB-02 | Hidden/non-active listing goes to unavailable state; no private data. |
| INT-04 Request quote | Valid listing, pet and date/time pair | Quote recalculating then total/unit/quantity | Quote panel updates, no booking created | Invalid timezone, past start, duration rule, no slot/overlap: keep form and explain field/domain issue. Server owns total. |
| INT-05 Create booking | Valid quote, Owner, pet/listing eligible; idempotency key minted once per logical attempt | Submit pending; then “Yêu cầu đã gửi, chưa giữ chỗ” | Create REQUESTED once; show OWN-03 | Retry same payload/key returns same booking and current state; changed payload/key conflict is not a second silent booking. REQUESTED does not lock calendar. |
| INT-06 Caregiver accepts | Assigned Caregiver; booking REQUESTED, not expired; listing/caregiver active; slot covers; no locked overlap | Confirmation with time/service summary; after success show 15-minute deadline | AWAITING_PAYMENT; update list/detail | Expired → REQUEST_EXPIRED; wrong state → INVALID_STATE; overlap/missing slot → AVAILABILITY_CONFLICT. No partial state/event. |
| INT-07 Caregiver rejects | Assigned Caregiver; REQUESTED | Confirm reject and optional user-facing explanation if contract supports it | CANCELLED with CAREGIVER_REJECTED; history shows actor/event | Same caregiver replay is idempotent, no extra event; do not allow reject after accept through reject route. |
| INT-08 Start payment | Owner owns booking; AWAITING_PAYMENT; current deadline valid | Persistent sandbox banner; pending action | Payment attempt PENDING | No payment credentials. If expired, show blocked/recoverable cancellation state; no confirmation. |
| INT-09 Payment result | PENDING; mock scenario selected | Scenario result and reference are visibly “simulation” | Success → attempt AUTHORIZED, booking CONFIRMED; failure → FAILED; timeout stays pending until operation result | Duplicate/late callback does not create another effect or resurrect booking. |
| INT-10 Retry failed payment | Current attempt FAILED; before deadline; Owner participant | “Thử lại thanh toán mô phỏng” | New attempt PENDING; previous attempt remains in history | Retry does not extend 15-minute hold; disabled after deadline. |
| INT-11 Cancel booking | Owner/Caregiver participant; state-specific regular cancellation allowed; idempotency key | Explain no fee in sandbox, current payment effect and processing possibility; confirm dialog | REQUESTED → CANCELLED directly; AWAITING_PAYMENT/CONFIRMED may enter operation pending until void/refund succeeds | Preserve booking/payment snapshot and history. Gateway failure keeps current status/hold; 202 pending or 502/manual review as contract, not false CANCELLED. No regular cancel after IN_PROGRESS. |
| INT-12 Caregiver starts care | Assigned Caregiver; CONFIRMED; time/state allowed by implementation contract | Confirm start | IN_PROGRESS; enable care-update composer | Invalid actor/state leaves unchanged. |
| INT-13 Send chat message | Participant; chat writable in current state/retention period | Sending indicator; message appears after mock success | Add message to this booking thread only | Failed attachment rolls back whole mock message; no permanent public image URL. Read-only after cancellation or fixed completion window. |
| INT-14 Send care update | Assigned Caregiver; booking IN_PROGRESS; valid body and/or image | Uploading/pending; success appended to timeline | Care update stored under booking | Outside state/wrong actor blocked; upload error leaves no partial update; no caregiver update after request-completion. |
| INT-15 Request completion | Assigned Caregiver; IN_PROGRESS | Confirmation and “Chờ chủ nuôi xác nhận” | AWAITING_CONFIRMATION; disable new care-update composer | Never mark COMPLETED here. |
| INT-16 Owner confirms completion | Owner owns booking; AWAITING_CONFIRMATION; no active report hold | Confirmation dialog; then “Đang xử lý giải ngân mô phỏng” | Release success → payment RELEASED and booking COMPLETED; failure remains AWAITING_CONFIRMATION | Release retry uses same operation key. After bounded attempts show Admin review path; no direct status mutation. |
| INT-17 Completion timeout | AWAITING_CONFIRMATION without Owner response | Reminder once after 24h; admin review indication after 72h | Business booking remains AWAITING_CONFIRMATION | Never auto-release or complete on time alone. |
| INT-18 Create review | Owner of COMPLETED booking; no prior review | Validate rating and optional text | Review visible by default; listing aggregate recomputed from eligible non-demo reviews | Duplicate/hidden review remains one per booking. No trust score or verification badge. |
| INT-19 Submit report | Signed-in actor can access target; one target/reason | Submit, then show report reference/status OPEN | Add report; if booking report enters IN_REVIEW, apply hold | Not-found/private target must not reveal content; report does not automatically hide review. |
| INT-20 Admin report action | Admin persona; policy permits action; reason required | Dialog shows target/action, reason, confirmation; audit preview | Status/history updates; booking operation may be pending | Booking decision through COMPLETE or CANCEL_REFUND operation; dismissal changes report only. Evidence access is separate and logged. |
| INT-21 Listing submit/review | Caregiver owns DRAFT or valid change request; Admin reviews separately | Submit confirmation; pending badge; reject reason shown | DRAFT→PENDING_REVIEW; admin approve→ACTIVE or reject→DRAFT; change request approval applies new version | No caregiver self-approval. Active previous version stays public during change request until approved unless suspended. |
| INT-22 Change availability | Caregiver owns listing; valid non-overlap slot | Save pending/result | Slot list updates; listing version need not change | Block removing coverage required by locked booking; adjacent slots do not merge implicitly. |

## 3. Domain state tables

### Listing

| Current | Actor/action | Next state | Notes |
|---|---|---|---|
| DRAFT | Caregiver submits | PENDING_REVIEW | Content/media validation required |
| PENDING_REVIEW | Admin approves | ACTIVE | Publicly discoverable |
| PENDING_REVIEW | Admin rejects | DRAFT | Reason shown |
| ACTIVE | Caregiver/admin suspends | SUSPENDED | Record suspension source/reason |
| SUSPENDED | Source-authorized actor restores | ACTIVE | Eligibility checked |
| ACTIVE or SUSPENDED | Caregiver/admin archives under rules | ARCHIVED | Terminal; no restore |
| ACTIVE/SUSPENDED | Critical content edit submitted | Listing remains as-is; change request PENDING_REVIEW | Separate change-request lifecycle |

### Booking and payment

| Booking state | Payment/status condition | UI next step |
|---|---|---|
| REQUESTED | No hold, no payment | Wait for Caregiver; no payment CTA |
| AWAITING_PAYMENT | Hold active; payment not authorized | Owner starts/retries sandbox payment |
| CONFIRMED | Payment AUTHORIZED | Caregiver starts service; both may communicate |
| IN_PROGRESS | Payment authorized | Caregiver updates and requests completion |
| AWAITING_CONFIRMATION | Release not complete | Owner confirms; report/admin may hold |
| COMPLETED | Release succeeded or authorized Admin resolution | Review eligible; messages read-only after fixed window |
| CANCELLED | State-specific void/refund/expiry/reject completed | Read-only history; no regular actions |

Payment is an independent aggregate. A pending/failed cancellation or release operation is not equivalent to a final booking state.

### Report

| Current | Action | Next | Effect |
|---|---|---|---|
| OPEN | Admin starts review | IN_REVIEW | Booking report holds completion when applicable |
| OPEN/IN_REVIEW | Admin dismisses with reason | DISMISSED | Report only; booking/payment unchanged |
| IN_REVIEW non-booking | Admin resolves/moderates | RESOLVED | Target action and history logged |
| IN_REVIEW booking | Admin chooses COMPLETE/CANCEL_REFUND | Operation pending, report decision recorded per contract | Booking moves only after sandbox operation succeeds |

## 4. Prototype state persistence and undo

- Keep the initial immutable fixture set available through “Reset demo”.
- Persist mock changes in localStorage only if reload continuity is useful; label data as local prototype simulation.
- Reset requires confirmation if it will discard the current demo state.
- For high-impact transitions (cancel, archive, admin decision, completion), show a confirmation dialog and record a visible mock event.
- A scenario selector must not mutate existing unrelated records; use a fresh fixture or reset scenario.

