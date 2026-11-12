# 11 — Open Questions and Decisions

This register separates decisions already established by P0 contracts from implementation gaps and UX choices that remain unconfirmed. No UNKNOWN item below has been silently converted into a product rule.

## 1. Confirmed decisions used by the package

| Topic | Current decision | Evidence |
|---|---|---|
| Demo scope | P0 is the closed pet-care journey; P1 only after P0; real payment, real verification, AI, video/camera and financial dispute resolution are out of P0 | Product Scope; P0 Contract |
| Guest access | Guests may search and view ACTIVE public listings, but cannot see pet data, message, book or review | Actors & Roles; API M04 |
| Roles | New public account is Owner; user may enable Caregiver; Admin is granted outside self-registration | P0 §5 |
| Booking | One pet/booking; one locked booking per listing/range; quote/server owns amount; idempotency and immutable snapshots | P0 §§1,8–12 |
| Payment | Sandbox only; 15-minute hold; no fee; no card data; final booking state follows successful void/refund/release operation | P0 §§3–4,13 |
| Smart Match | Rule-based match species/size/availability/total price; no AI or age/special-needs promise | P0 §§7–8; M04 |
| Review | One eligible Owner review per COMPLETED booking; demo reviews excluded from aggregate; no real verification badge | P0 §8; BE-H05 contract |
| Admin access | Filament panel is the product admin boundary; actions need authorization, reason and audit | M09; README; current Filament resource |
| Accessibility | Vietnamese UI, responsive/mobile-first and WCAG 2.2 AA are design targets, not completed user research | NFR Usability & Accessibility |

## 2. Decisions and questions requiring tracking

| ID | Question | Why it matters | Safe interim treatment in this package | Priority |
|---|---|---|---|---|
| Q-01 Admin prototype scope — **RESOLVED 11/11/2026** | User chose limited Filament-reference screens for moderation/report; no separate Admin SPA. | P0 includes moderation/report/admin flows; current workspace has a Filament Reports resource but no Filament listing resource; booking operations are exposed by admin API. | Include a minimal Filament-style listing moderation reference (ADM-01) and reports queue/detail/evidence/history reference (ADM-02). Show booking-operation state inside the relevant report case, not as a separate admin dashboard. Label the UI as prototype reference; do not claim unseen Filament pages already exist. | P0 demo presentation |
| Q-02 Legacy visual source | Can the team provide access to the legacy repository or share its screenshots/assets? | GitHub/raw could not be fetched and shell DNS failed, so no verified palette/layout/component can be inherited. | Keep 07 design tokens explicitly PROPOSED; do not describe them as legacy PETTEL styling. | Design direction |
| Q-03 Booking read API | Who owns the contract for listing/detail/history reads, and what response fields/pagination are intended? | No general GET booking collection/detail route was found; FE-T01 expects list/detail screens and FE-T02 needs status refresh. | Use mock booking records only in the prototype; create a backend/API gap, not an invented endpoint. | P0 Vue integration |
| Q-04 Account deletion UI | Should the P0 prototype include request-account-deletion/DELETION_PENDING flow? | P0 §6 specifies deletion and retention behavior, but Week 4 FE-H01 scope lists auth and pet UI and no deletion route was found in the API route inventory. | Do not add a deletion flow to the prototype until product scope/API contract is confirmed; retain privacy rules on existing data surfaces. | P1 / privacy gap |
| Q-05 Email verification | Is email verification enabled for the demo, and is a delivery path available? | M01/README describe verification conditionally; no confirmed email flow is in FE-H01 scope. | Exclude verification from P0 prototype; include only if the team confirms it and supplies a simulation rule. | P1 |

## 3. Implementation and evidence gaps (not product decisions)

| ID | Gap | Evidence | Consequence |
|---|---|---|---|
| G-01 | Vue application is not scaffolded | README and frontend/.gitkeep | No current screens/components to reuse; prototype is a separate HTML/CSS/JS artifact |
| G-02 | Generic booking read routes are absent from current API route list | backend/routes/api.php | Mock list/detail UI must be labeled as prototype-only; FE implementation requires endpoint contract |
| G-03 | Filament listing moderation resource is absent from current Filament resource tree | Only Reports/ReportResource found; admin listing API exists | Admin listing moderation UI is unknown; cannot claim it is already available in Filament |
| G-04 | Legacy website source not accessible | GitHub/raw fetch blocked, git ls-remote DNS failure | Legacy reuse decisions are unknown; no visual claims |
| G-05 | Current branch/workspace cannot establish latest remote/CI status | Local snapshot is branch fe / 39bde51; remote was not fetchable | Do not present workspace HEAD or older handoff as current Gate BE/CI proof |
| G-06 | User research results not present | Product Research file describes planned interviews/tests | Treat hypotheses/metrics as proposed evaluation criteria, not validated outcomes |

## 4. Source discrepancies reconciled without changing rules

| Discrepancy | Resolution used |
|---|---|
| Product and module docs are labeled drafts/proposals, while P0 Contract contains approved demo decisions | Treat P0 Contract and dated contract addenda as product rule baseline; separately verify actual implementation in code/tests. |
| Older workflow/M09 prose describes booking report outcomes broadly, while newer contract requires operation/outbox and payment success before final booking state | Use newer P0/API contract: dismissal affects report only; COMPLETE/CANCEL_REFUND uses operation; booking final state waits for successful sandbox action. |
| Implementation handoffs mention different branches/SHAs and some historical test/CI evidence | Record current workspace snapshot only; do not reassign older test/CI metrics to current branch/commit. |
| Prompt says Admin workflows matter and also says not to rebuild Filament as an SPA | User confirmed limited Filament-style references for listing moderation and reports, with no separate Admin SPA. Booking operation state is embedded in report case detail. |

## 5. Decisions not blocking prototype documentation

- The legacy audit limitation is already allowed by the original execution prompt: record it and continue from available sources.
- Booking list/detail can be mocked for the prototype because this does not alter business rules; the missing live API remains a blocker for real Vue integration.
- NFR palette/tokens and the fixed demo clock are design/simulation proposals, not product policy.

No business rule in the Builder Brief is changed based on the unanswered questions above. Q-01 is resolved. Q-02 remains a source-access limitation; the original execution prompt allows continuing with proposed design tokens while the legacy visual comparison stays UNKNOWN.

