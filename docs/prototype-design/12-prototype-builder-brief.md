# 12 — Prototype Builder Brief

**Primary input for Codex Agent 2.** Build the PETTEL interactive prototype from this brief and the supporting specifications in this folder. Do not redefine business rules from scratch. The source package distinguishes confirmed P0 rules, implementation evidence, proposals and unresolved questions.

## 1. Goal

Create a responsive, interactive PETTEL website prototype using plain HTML, CSS and JavaScript. It should demonstrate the P0 user experience for pet-care discovery, booking, sandbox payment, care communication/completion, review, caregiver service management and the report/admin workflow boundary.

This is a design-validation artifact, not a second product frontend. Do not connect it to Laravel, process real payment, use real personal data or build a replacement Vue/Filament application.

## 2. Source and interpretation rules

1. Treat [00-source-inventory.md](00-source-inventory.md) as the evidence guide.
2. Use P0 Decision Contract and dated contract addenda as the product rule baseline. Verify implementation statements against source/routes/tests; a written contract is not proof that code or UI exists.
3. Use [03-master-flow.md](03-master-flow.md), [04-feature-flows.md](04-feature-flows.md), [05-screen-inventory.md](05-screen-inventory.md), [06-screen-specifications.md](06-screen-specifications.md), [07-design-system.md](07-design-system.md), [08-prototype-interactions.md](08-prototype-interactions.md), [09-mock-data-plan.md](09-mock-data-plan.md) and [10-fe-coverage-matrix.md](10-fe-coverage-matrix.md) for specific behavior.
4. Do not infer legacy brand styling: repository access failed. Visual tokens in 07 are proposed. If legacy assets/screenshots are supplied later, audit before replacing them.
5. Admin visual scope was confirmed by the user on 11/11/2026: build limited Filament-style references for listing moderation and reports, with no separate Admin SPA. Show booking-operation state inside the relevant report case; do not invent a separate operations dashboard or claim unseen Filament pages already exist.
6. Keep missing booking read API visible as a mock-only boundary. Do not invent a live GET endpoint or imply backend integration.
7. Account deletion UI is unresolved in Q-04. Apply privacy/retention constraints to mock data, but do not add a deletion-request screen until confirmed.

## 3. Scope and screen set

### P0 — build

- **SHELL-01:** responsive shell and role-aware navigation.
- **AUTH-01:** login/register/reset simulation; user-created account defaults to Owner; no self-assigned Admin.
- **PUB-01 / PUB-02:** public search/results and listing detail; ACTIVE listings only.
- **OWN-01:** pet manager/editor.
- **OWN-02 / OWN-03:** quote/create booking and booking list/detail from mock state.
- **OWN-04:** sandbox checkout.
- **BOOK-01:** booking-specific messaging and care updates.
- **OWN-05:** Owner completion confirmation and release state.
- **OWN-06:** eligible review and public review display.
- **OWN-07:** report composer and own report list.
- **CG-01–CG-05:** caregiver profile, listing workspace/editor, availability, review/change request status and booking inbox/detail.
- **ADM-01 / ADM-02:** include limited Filament-style reference screens for listing moderation and reports. Show related booking-operation status inside the relevant report case. Do not build a separate Admin app.

### P1 — omit unless P0 is complete and the team asks

- PUB-03 listing comparison.
- CG-06 caregiver statistics.
- Email notifications, advanced search, multiple pets per booking.

### Out of scope

Real money/payment credentials, escrow, identity/background verification, genuine Verified/Pro badges, AI/ML match claims, live video/camera/streaming, native app, insurance, full financial dispute resolution and unsupported product features.

## 4. Navigation and role behavior

- Guest: discover, listing detail, login/register.
- Owner: discover, pets, bookings, profile; booking detail leads to checkout, conversation/updates, completion confirmation, review or report as eligible.
- Caregiver: overview, booking requests, services, availability, profile; listing status and booking lifecycle determine actions.
- Admin: existing Filament is the product boundary. Build the agreed limited reference views for listing moderation and reports, clearly label them as prototype simulations, and avoid a functional Admin SPA.
- A demo persona selector may switch among fixtures for presentation. Label it “Prototype persona”; do not present it as real role authorization or user self-grant of Admin.
- Public search remains available without login. Private pet/booking/message/media data is not exposed by route changes or hidden navigation.

## 5. Business rules that must not be violated

- Only public ACTIVE listings appear in search/detail. Search availability is indicative and must be rechecked at create/accept.
- Smart Match is explainable rule-based filtering for species, size, availability and calculated total price. Do not claim AI or infer age/special-needs support. Missing pet size cannot match a size-restricted listing.
- One pet and one listing per booking; use immutable snapshots and server-calculated price. DAYCARE is hourly; BOARDING is a block up to 24 hours. Show total, quantity/unit and cancellation policy before submit.
- REQUESTED does not lock calendar; caregiver acceptance checks current listing/caregiver status, a continuous slot covering the full range and locked booking overlap. A valid accept holds the slot for 15 minutes; retry does not extend it.
- REQUESTED expires after 24 hours from creation or at start time, whichever comes first. AWAITING_PAYMENT remains a lock until cancellation/void/refund operation changes state successfully.
- Booking state path: REQUESTED → AWAITING_PAYMENT → CONFIRMED → IN_PROGRESS → AWAITING_CONFIRMATION → COMPLETED, with state-valid CANCELLED branches. Do not add ACCEPTED or REJECTED booking states.
- Payment is simulation only. Never request card/bank credentials. Failed payment retry, callback replay/late result, expiry, cancellation, refund and release must match 08 interaction rules. Payment outcome is independent from UI loading.
- Booking only becomes COMPLETED after sandbox release succeeds or an authorized Admin operation completes. Owner silence at 24/72 hours does not auto-complete/release.
- Care update creation is assigned-Caregiver-only during IN_PROGRESS. Chat is booking-specific; private media requires participant access. Respect fixed 90-day completion send window and read-only canceled state.
- Review is allowed only once for the Owner of a COMPLETED booking; demo reviews are labeled and excluded from rating aggregate. Do not show a genuine verification badge.
- Reports use OPEN/IN_REVIEW/RESOLVED/DISMISSED and require a reason. Booking reports in review hold ordinary completion. Dismissal does not change booking/payment; COMPLETE/CANCEL_REFUND waits for sandbox operation success.
- Errors never silently discard safe form input. Explain 401/419, permission/not-found, state conflict and validation in the appropriate UI state.

## 6. Visual and accessibility direction

- Use proposed tokens in 07-design-system.md, keeping provenance comments: they are not verified legacy PETTEL values.
- Write UI copy in Vietnamese; use VND formatting; convert stored UTC/offset time to Asia/Ho_Chi_Minh display.
- Mobile-first responsive layout for 320 px through desktop; touch targets ≥44 px; forms, cards, tables and dialogs adapt without horizontal page scrolling.
- Use visible focus, keyboard access, semantic labels/headings, appropriate live regions, text plus icon for status and sufficient contrast. Respect reduced motion.
- Use neutral/local placeholder imagery only. Do not fetch unreviewed assets or imply any photo proves identity, health or service quality.
- Admin screens must not be styled as a new product shell; if added after confirmation, represent them as Filament references.

## 7. Technical implementation proposal for Agent 2

Create a separate directory at the repository root named prototype/; do not write into frontend/ or backend/.

Suggested structure:

| Path | Responsibility |
|---|---|
| prototype/index.html | Static entry point and app shell |
| prototype/css/tokens.css | Proposed visual tokens |
| prototype/css/styles.css | Responsive layout and component styling |
| prototype/js/app.js | Routing/render orchestration and event binding |
| prototype/js/data/fixtures.js | Synthetic personas/entities/scenarios from 09 |
| prototype/js/domain/rules.js | Pure mock validation and allowed state transitions |
| prototype/js/services/mock-store.js | Single source of mock state, scenario reset, optional localStorage |
| prototype/assets/ | Local neutral placeholders only |
| prototype/README.md | Local run instructions and demo scenario guide |

Use vanilla HTML/CSS/JavaScript, preferably ES modules. Avoid frameworks, build tooling, external CDNs and Laravel API calls. Because browser ES modules may not run from file://, document a static local server command or VS Code Live Server. Use browser History/hash navigation consistently.

Keep the mock store/domain rules separate from rendering. A page must call a mock action and render the returned state; it must not mutate booking/payment objects ad hoc. If using localStorage, include a confirmed Reset demo action and initial immutable fixtures.

## 8. Required interactions and scenarios

All high-impact controls in P0 must work. Implement the scenarios from 09-mock-data-plan.md:

1. Public search, listing detail and hidden listing exclusion.
2. Smart Match success, missing-size validation and valid no-match empty state.
3. Booking quote, create and same-key replay without duplicates.
4. Caregiver accept/reject; availability overlap conflict; expiry.
5. Sandbox success, failure/retry, late callback ignored and expiry.
6. Care start, booking conversation/care update, completion request and Owner confirmation.
7. Release pending/failure/retry with booking remaining awaiting until success.
8. Report submission, Admin reference/hold state and private evidence boundary.
9. Listing draft/submit/approve/reject/change request/suspend/restore/archive.
10. Wrong persona/private record access denial.

Use the fixed demo clock so the 15-minute hold, 24-hour reminder and 72-hour Admin queue can be demonstrated without waiting in real time. Expose scenario time control only in clearly labeled prototype controls; do not make it look like a user-facing production feature.

## 9. Mock data and simulation requirements

- Use stable IDs, synthetic names and coherent references across pages. No real PII, credentials, bank details or permanent private media URL.
- Keep demo seed records for one eligible Owner pet, one missing-size pet, one archived pet, two ACTIVE listings across DAYCARE/BOARDING, one SUSPENDED listing, slots, booking states, payment attempts, messages, care updates, a demo review and reports.
- Respect price calculations from P0; example fixture: 2h10 DAYCARE at 50,000 VND/hour is 3 units and 150,000 VND.
- Represent network/API outcomes as mock simulation labels. Conflict fixtures do not prove transaction locking; prototype is not API or browser E2E evidence.
- Booking list/detail read is mock-only until the backend read model/endpoint is specified.

## 10. Acceptance checklist

- [ ] A user can navigate the P0 Owner and Caregiver flows end-to-end with all required state transitions.
- [ ] Every P0 primary action has a valid result or is disabled with a clear reason; no decorative dead buttons.
- [ ] Role/ownership/state restrictions are reflected in the mock domain layer, not only by hiding buttons.
- [ ] Booking snapshots and price remain stable after pet/listing fixture edits.
- [ ] Payment, reports, seed reviews and Admin reference clearly indicate simulation/demo.
- [ ] Expiry, cancellation, conflict, error, empty, pending/retry and completion states are accessible from the UI.
- [ ] Admin is not rebuilt as a separate SPA; limited Filament-style listing moderation and report references are shown, with booking-operation status inside the report case.
- [ ] Responsive layout works at 320 px, tablet and desktop; keyboard and visible focus work across P0 screens.
- [ ] Vietnamese labels are consistent; no unsupported verified/AI/live-payment claims.
- [ ] Local run and Reset demo instructions are present in prototype/README.md.
- [ ] Missing booking read API and inaccessible legacy visual source remain documented limitations.

## 11. Browser walkthrough for review

1. Open prototype via the documented static server; start as Guest and search/open a public listing.
2. Switch to Owner demo persona; create/select pet, run Smart Match and create a booking.
3. Switch to Caregiver; accept/reject request; demonstrate conflict and hold deadline.
4. Switch to Owner; simulate payment failure/retry and success.
5. Switch to Caregiver; start care, send update, request completion.
6. Switch to Owner; simulate release failure then success; verify review becomes available only after COMPLETED.
7. Submit a report, switch to Admin reference, inspect status/hold flow only within approved Admin scope.
8. Repeat selected screens at mobile width, keyboard-only and with an empty/error fixture.

Do not claim browser/API tests have passed merely because this walkthrough is documented or the prototype renders. Record observed review outcomes separately from specification.

## 12. Mapping to Vue after prototype approval

Treat prototype pages as behavioral references, not source code to copy wholesale. Likely mapping:

- Layout/shell → Vue layout + role-aware router guards.
- Screen groups → Vue route pages.
- ListingCard, PetCard, BookingCard, PriceSummary, StatusBadge, Timeline, forms/dialogs → shared Vue components.
- Search/booking/pet/message flows → composables or store/actions with API boundary.
- Mock domain rules → backend contracts and thin presentation handling; do not port authoritative pricing/authorization rules into production JS.
- Auth → Sanctum cookie/CSRF client, with 401/419 handling.
- Admin → existing Filament panel and shared backend actions/policies, not Vue Admin SPA.

Open API read-model gaps and Admin presentation questions must be resolved before live integration. The prototype must never be treated as evidence those contracts exist.

