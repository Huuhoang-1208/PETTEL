# 07 — Mini Design System

**Provenance:** All visual tokens below are **PROPOSED for the new prototype**, not measured from the legacy PETTEL site. The legacy repository could not be accessed in this run; see [02-legacy-ui-audit.md](02-legacy-ui-audit.md). Replace or adapt these tokens only after a verified visual audit or explicit brand decision.

## 1. Design direction

Create a calm, practical Vietnamese pet-care service interface. Use generous whitespace, clear price and state hierarchy, friendly but restrained imagery, and readable booking timelines. Avoid styling that implies verified caregivers, medical certification, live tracking, or guaranteed availability.

The experience should feel warm through color, photography and copy, while keeping operational states precise. Use “người chăm”, “chủ nuôi”, “dịch vụ”, “lượt đặt”, and “thanh toán mô phỏng” in user-facing copy. Technical enum names belong in implementation documentation or a secondary detail.

## 2. Foundations

### Color tokens — PROPOSED

| Token | Value | Use |
|---|---|---|
| Brand 900 | #183D30 | Deep brand text/pressed state |
| Brand 700 | #245B47 | Primary button, links, active navigation |
| Brand 600 | #32745D | Hover/secondary action |
| Brand 100 | #EAF3EE | Selected filter, soft brand surface |
| Accent 700 | #A34E2E | Secondary highlight; do not use as status alone |
| Accent 100 | #FFF1E8 | Warm highlight background with dark text |
| Text 950 | #1D2924 | Main text |
| Text 700 | #3D4A43 | Secondary text |
| Text 600 | #53615A | Supporting copy and labels |
| Canvas | #F6F8F6 | App background |
| Surface | #FFFFFF | Cards, fields, dialogs |
| Border | #D8E2DB | Dividers and control outlines |
| Success | #217A4E | Success icon/text; pair with pale green background |
| Warning | #8A5700 | Warning text/icon; pair with pale amber background |
| Danger | #B42318 | Error text/icon; pair with pale red background |
| Info | #175CD3 | Informational text/icon; pair with pale blue background |
| Focus | #175CD3 | Visible 2–3 px keyboard focus ring |

On white, the proposed brand, accent, neutral supporting text, success, warning, danger and info foregrounds have calculated contrast ratios ≥4.5:1. Pale surfaces are not text colors. Maintain at least 4.5:1 for normal text and 3:1 for large text/meaningful controls; do not encode state with color alone.

### Typography — PROPOSED

| Role | Size / weight | Usage |
|---|---|---|
| Display | 32/40, 700 | Landing headline only; one per page |
| H1 | 28/36, 700 | Page title |
| H2 | 22/30, 650–700 | Main section |
| H3 | 18/26, 600 | Card/section heading |
| Body | 16/24, 400 | Main copy and form values |
| Body small | 14/20, 400 | Helper copy, secondary metadata |
| Label | 14/20, 600 | Form labels, table headings |
| Caption | 12/18, 500 | Time, count, secondary status detail; never for essential policy |

Use a system stack with Vietnamese glyph coverage: system-ui, Segoe UI, Arial, sans-serif. Do not depend on a remote font download for the prototype. Use sentence case and avoid long all-caps labels.

### Spacing, shape and elevation — PROPOSED

- Base spacing unit: 4 px. Scale: 4, 8, 12, 16, 20, 24, 32, 40, 48, 64.
- Control height: 44–48 px touch target; icon-only touch control at least 44 × 44 px.
- Radius: 8 px for inputs/buttons; 12 px cards; 16 px large surfaces; pill only for compact status/category chips.
- Borders: 1 px neutral border; use elevation sparingly.
- Shadow: low-elevation card shadow only when the card is interactive; modal uses stronger shadow plus scrim.
- Content density: avoid placing more than one primary decision in a card.

### Layout and breakpoints — PROPOSED

| Range | Layout |
|---|---|
| 320–599 px | One-column; collapsible filters; full-width forms; sticky bottom primary action only when it does not cover content |
| 600–899 px | Two-column cards where readable; filters in drawer or side sheet |
| 900–1199 px | Main content plus optional filter sidebar; two-column detail/booking layout |
| ≥1200 px | Centered content max-width 1200 px; content width 720–840 px for forms/detail text |

Use responsive grid rather than fixed viewport assumptions. At 320 px, no horizontal scrolling for primary flows; tables become stacked rows/cards or scroll within a labeled region.

### Iconography and imagery — PROPOSED

- Use one consistent outline icon family or simple inline SVG; icons always have visible text labels or accessible names. Do not use emoji as a substitute for controls.
- Use natural, respectful pet/service imagery with consistent crop and subdued backgrounds. Never use an image as evidence of certification or identity verification.
- Listing images may use 4:3 or square crops; care update attachments retain a clear preview and alt text where informative. Avatar fallback is initials or a neutral silhouette.
- Public listing media and private booking media must look distinct in component/API boundaries. A private image must not have a permanent public URL.

### Motion and feedback — PROPOSED

- Use brief transitions for menu/dialog/state feedback (120–200 ms); no animated countdown pressure.
- Respect prefers-reduced-motion. Never communicate booking/payment state only through animation.
- Toast confirms transient action; persistent payment/report errors remain inline near the relevant content.

## 3. Semantic state design

| State | Suggested presentation | Required text meaning |
|---|---|---|
| DRAFT | Neutral gray badge | Chưa gửi duyệt |
| PENDING_REVIEW | Amber badge + clock icon | Đang chờ quản trị xem |
| ACTIVE / CONFIRMED / COMPLETED | Green badge + label | State-specific Vietnamese text |
| SUSPENDED / CANCELLED / FAILED | Red or neutral badge + icon | Explain what is blocked and next valid action |
| AWAITING_PAYMENT / AWAITING_CONFIRMATION | Blue/amber badge + next actor | State who must act and any deadline |
| PROCESSING / operation pending | Blue indicator + text | “Đang xử lý”; do not imply final completion/cancellation |

Exact color pairing is a design proposal. State labels and transition rules are domain-constrained. Color is always accompanied by text and/or icon.

## 4. Component catalogue

| Component | Purpose / variants | Interaction and states | Accessibility | Reuse |
|---|---|---|---|---|
| AppHeader / RoleNav | Shared shell; desktop full nav, mobile menu | Active route; guest/owner/caregiver persona; menu open/closed | Skip link, landmarks, keyboard menu and focus return | All Vue-like prototype areas |
| Role/persona selector | Demo-only switching between fixtures | Selection changes mock actor and permitted actions; label “Prototype persona” | Native select or radio group; no implied account privilege grant | Prototype shell only |
| Button | Primary, secondary, outline, danger, text, icon+label | Hover/focus/pressed/disabled/loading; one primary per decision panel | Native button, 44 px target, text label, announced loading state | All screens |
| Text input / textarea | Text, email, password, search, notes | Default/focus/error/disabled; helper and character count | Visible label, described-by helper/error, autocomplete where appropriate | Auth, pet, listing, report, messages |
| Select / combobox | Service, area, species, size, pet, reason | Placeholder, selected, disabled, invalid | Native select when practical; clear selected value | Search and forms |
| Date/time range | Select service range | Paired start/end, timezone display, invalid range, unavailable | Separate labels, keyboard entry, error text; no color-only blocked slot | Search, quote, calendar |
| ListingCard | Compare public services | Hover/focus/open; save not in P0 unless approved | Entire card not nested interactive; title link is clear | Discovery |
| PetCard | Select/manage pet | Active/archive/selected | Status text; selection announced; no private details in public contexts | Owner screens |
| BookingCard | Show status and next action | Pending/active/history/error | State label, next actor/action in text | Owner/Caregiver dashboards |
| PriceSummary | Explain unit, quantity, total, policy | Recalculating/quoted/stale | Numeric total and unit in text; currency localized to VND | Listing, quote, checkout, booking |
| StatusBadge | Compact state summary | Domain state variants | Text + optional icon; not color alone | Listing, booking, payment, report |
| Timeline | Explain state and event history | Loading, collapsed/expanded | Ordered list with actor/time/action labels | Booking, moderation, report |
| Tabs | Separate booking messages/updates or review views | Selected/disabled | Tab semantics or links; keyboard arrows if tab pattern | Booking detail |
| Dialog | Confirm irreversible/important action | Open/close/confirm/pending | Focus trap, labelled title, escape policy, return focus | Archive, cancel, report decision, completion |
| Toast / InlineNotice | Success/transient notice vs persistent issue | Success/info/warning/error | Live region; errors remain visible until addressed | All |
| DataTable | Admin queue and history | Sort/filter/page/action | Row headers, responsive alternative, clear action label | Admin reference |
| EmptyState | Explain no pets/results/bookings/reports | CTA only when valid | Heading and actionable text, not decorative only | All |
| ErrorState | Explain failure and retry path | retry/refresh/login | Specific recovery text; role=status/alert as appropriate | All |
| Skeleton | Loading placeholder | Loading only; no interaction | Avoid announcing repeated skeleton content | Lists/cards |
| MediaPreview | Public gallery or private attachment | load/error/zoom/download if permitted | Alt text, visible privacy context, keyboard dismiss | Listing, messages, updates |

## 5. Design provenance and approval boundary

| Token/component source | Status |
|---|---|
| Legacy brand, fonts, palette, components, assets | UNKNOWN until source is accessible |
| P0 status names, copy meaning and permission behavior | CONFIRMED by domain contracts; translate into Vietnamese UI |
| Palette, type scale, spacing, breakpoint and component styling in this document | PROPOSED prototype foundation |
| WCAG 2.2 AA/mobile/320 px targets | PROPOSED NFR target in project docs; not user-test evidence |
| Admin page styling | Limited Filament-style reference screens for listing moderation and reports are confirmed by the user; exact styling remains PROPOSED because no rendered legacy/Admin visual audit was available |

