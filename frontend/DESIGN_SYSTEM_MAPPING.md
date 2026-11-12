# Prototype to Vue Discovery Design Mapping

The approved prototype is the visual source of truth for Owner Discovery. Vue keeps Laravel API data and interactions; only the presentation is migrated here. Prototype fixtures are not used as production data.

## Element mapping

| Prototype element | Prototype source | Asset / behavior | Vue component | Production style mapping |
| --- | --- | --- | --- | --- |
| Global palette and type | `prototype/css/tokens.css` | Color, type, spacing, radius, shadow tokens | `src/shared/styles/tokens.css` | Same token values exposed as CSS custom properties |
| Page canvas and background | `prototype/css/styles.css`, `prototype/css/petty-theme.css` | Soft pink canvas with lavender/mauve radial wash | `src/app/App.vue` | `src/shared/styles/discovery.css`: body canvas and site shell |
| Brand mark and wordmark | Header markup / `iconSvg('paw')` in `prototype/js/app.js`; `.brand*` in Petty theme | Paw SVG; script-like wordmark | `src/shared/components/AppHeader.vue`, `AppIcon.vue` | `.brand`, `.brand-mark`, `.site-header` |
| Main navigation | Header markup in `prototype/js/app.js`; `.main-nav`, `.nav-link` in Petty theme | Home, pet, report and account icons; responsive menu | `src/shared/components/AppHeader.vue` | `.header-inner`, `.main-nav`, `.nav-link`, `.mobile-nav` |
| Hero banner | `prototype/js/app.js` discovery markup; `.hero*` in Petty theme | `prototype/assets/heroImage.png` (1677×938 RGBA) | `features/owner/discovery/components/ServiceHero.vue` | `.hero`, `.hero-copy`, `.hero-art`; original image imported by Vite |
| Search/filter form | `prototype/js/app.js` discovery form; `.search-panel`, `.field`, `.checkbox-row` styles | Search icon, service, pet, area, date, price and Smart Match controls | `features/owner/discovery/components/DiscoveryFilters.vue` | `.search-panel`, `.field`, `.checkbox-row`; emits filters to page |
| Result heading and location tag | `prototype/js/app.js` discovery results | Location pin icon | `features/owner/discovery/pages/SearchPage.vue` + `AppIcon.vue` | `.section-heading`, `.soft-tag` |
| Listing card | `prototype/js/app.js` `listingCard()`; card rules in Petty theme | Listing API media when present; otherwise original species illustrations | `features/owner/discovery/components/ListingCard.vue` | `.listing-card`, `.listing-photo`, `.photo-badge`, `.chips`, `.listing-bottom` |
| Dog / cat fallback illustration | `prototype/assets/dog-card.svg`, `cat-card.svg` | Exact SVG assets copied into Vue assets (hash-checked) | `ListingCard.vue` | Vite-managed imports; shown only when API media is absent |
| Empty state | Discovery empty markup in `prototype/js/app.js`; `.empty-illustration` Petty theme | Search icon and reset action | `src/shared/components/EmptyState.vue` | `.empty-card`, `.empty-icon` |
| Footer | Prototype footer markup / `.footer` styles | PETTEL service description | `src/shared/components/AppFooter.vue` | `.footer` |

## Tokens

| Token group | Values migrated from prototype |
| --- | --- |
| Brand | `#353454`, `#474672`, `#5a5a83`, `#efedf7` |
| Accent | Mauve `#c48590`, pale pink `#FBEDEC` |
| Text / surfaces | `#010911`, `#263948`, `#697681`, white surface, `#eadde3` border |
| Feedback | Success `#287767`, warning `#8a5700`, danger `#d42323`, focus `#918bdb` |
| Petty palette | Sky `#abdcfc`, mint `#b1e0d8`, lavender `#bbb7ea`, peach `#fdd2a4`, coral `#fbae9e` |
| Typography | `-apple-system`, BlinkMacSystemFont, SF Pro Text, Segoe UI, system sans-serif |
| Spacing | 4, 8, 12, 16, 20, 24, 32, 40, 48, 64 px |
| Radius | 12, 16, 24, 30 px; pill 999 px |
| Shadows | `0 5px 18px rgb(71 70 114 / 8%)`, `0 18px 44px rgb(71 70 114 / 15%)`, inset CTA shadow |
| Content width | 1200 px; header 1260 px |
| Breakpoints | 980, 760, 520, 360 px (retained from prototype responsive rules) |

## Data and UI separation

- Production discovery results and card content come from Laravel `GET /api/listings`; pet choices come from the authenticated Owner pet API.
- Production listing media URLs are preferred. Dog/cat SVGs are decorative fallback illustrations, not listing photos or evidence that a listing has media.
- Search fixture data remains in the prototype only. No prototype listing records were copied into production code.
- Smart Match is a client-side filter choice that sends the selected `pet_id` to Laravel. The UI does not claim that it is AI. Availability and total-price filters are sent to the API when a complete time range is present.
- Visual test fixtures, if added, belong only in isolated tests or mocked browser requests and must not be used by the running production UI.

## Responsive rules

- Desktop: 3 listing columns; search controls use the prototype 4-column lead row and wrap into the remaining fields.
- Tablet (`≤980px`): 2 listing columns and 2-column search controls.
- Mobile (`≤760px`): single-column hero content, collapsible header navigation, compact page gutters.
- Narrow mobile (`≤520px`): single-column filter form and listing cards.
- Small screens (`≤360px`): reduced outer gutters and hero padding.
