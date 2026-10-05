# Structural design plan for the interface design (#250, PRE-03b)

5 October 2026. Owner PBI: #250. This is the plan for the structural work D-10 unblocked. It is a plan, not the
design: no screen exists yet.

## What is contracted, and what this plan is

PRE-03b is the interface design, "approved before storefront implementation begins", delivered as working HTML and
CSS screens, "produced after the brand identity is approved", with the screens as the approved artefact and "no
separate wireframe stage". The canonical design source is English; the Arabic layouts are the right-to-left
localisation of that design, not a separate design.

| | |
|---|---|
| **What proceeds now** | The structure: screens, journeys, components, states, responsive behaviour and both reading directions, on neutral design tokens. An engineering step |
| **What waits for OD-01** | Final visual approval. The brand name, logo and identity are the client's input, and no screen is presented to the client as approved before it arrives |
| **What this is not** | A wireframe deliverable for the client, a Figma file, a logo, or a brand identity. None is contracted |
| **What it must not become** | A generic, unrestricted page builder. The editable surface is the curated library below and nothing wider |

## 1. Screen inventory

Every screen the Stage 1 storefront PBIs build, with its owner. A screen with no row is not designed.

| Group | Screens | Owner PBI |
|---|---|---|
| Frame | Header, mobile navigation, mega menu, footer | #253 |
| Home | Home page and its sections | #302 |
| Catalogue | Product listing, category, collection, brand listing | #303 |
| Search | Results, no results, suggestions | #304 |
| Product | Product details page, simple and variable | #254 |
| Cart | Cart page, mini cart, empty cart | #306 |
| Checkout | Checkout, order received | #255 |
| Identity | Sign in, register, lost password, Google sign-in entry | #305 |
| Account | Dashboard, orders, order detail, addresses, account details, tracking | #308 |
| Lists | Wishlist, recently viewed | #307 |
| Reviews | Review list and form on the product page | #309 |
| Trust and support | About, contact, help, authenticity, shipping, returns, privacy, terms | #310 |
| System | 404, maintenance, empty and error states of each screen above | With each owner |

The routes for all of them exist and are verified by #242 (`specs/003-information-architecture-urls/url-map.md`).

## 2. Journeys

The ten Stage 1 journey rows, owned by #322 as the end-to-end record (its issue names each id). The structural
design draws each as a path through the screens above, in both languages. In outline, with the register's own
wording governing where this summary differs:

1. Arrive and discover, on the home page and from search.
2. Find, by navigation, listing, filter and search.
3. Evaluate, on the product page.
4. Decide, by selecting a variation and adding to the cart.
5. Carry the cart through registration, sign-in and a return visit.
6. Check out, as a guest or signed in.
7. Pay, and reach the order received screen.
8. Track the order.
9. Use the account.
10. After-sales: a refund recorded on the order.

## 3. Neutral wireframes

Built as working HTML and CSS in `mizzey-theme/`, never as images, so that the structural work becomes the
approved screens once the identity is applied rather than being redrawn.

| Rule | Reason |
|---|---|
| Greyscale, one neutral typeface scale, no logo, no brand colour | Nothing that could be mistaken for a visual proposal |
| Real component markup, placeholder content | The structure is what is being settled |
| Every measurement, colour and type size is a token | The identity is applied by changing tokens, not markup |
| No user-facing string hard-coded | `tools/tests/test_storefront_strings.py` already fails on one |

## 4. Viewports

| Viewport | Width | Notes |
|---|---|---|
| Mobile | 360 to 767 | Designed first (NFR mobile first). Touch targets, one column |
| Tablet | 768 to 1023 | Two columns where the content allows |
| Desktop | 1024 and above | Mega menu, multi-column listing |

The browser matrix D-09 approved applies: current Chrome, Safari, Edge and Firefox on desktop, current Chrome on
Android and current Safari on iOS, each in both reading directions.

## 5. English left to right and Arabic right to left

- English is the canonical source. Arabic is the same structure mirrored, never a second layout.
- Logical CSS properties throughout (`margin-inline`, `padding-inline`, `inset-inline`), so mirroring is the
  direction attribute and not a second stylesheet.
- Icons that imply direction are mirrored; icons that do not, and all numerals and prices, are not.
- Every screen is checked at both directions before it is called structurally complete. Probe P-010, the header
  and navigation mirroring, runs in #253.

## 6. Component inventory

| Layer | Components |
|---|---|
| Foundations | Type scale, spacing scale, grid, colour roles, radius, elevation, focus ring |
| Controls | Button, link, text field, select, checkbox, radio, quantity stepper, toggle, search field |
| Feedback | Notice, inline error, toast, skeleton, empty state, badge, stock state label |
| Navigation | Header bar, menu, mega menu panel, breadcrumb, pagination, tabs, language switcher |
| Commerce | Product card, price, gallery, variation selector, add to cart, cart line, order summary, address card, order status timeline, rating stars, review item |
| Content | Hero, banner, product rail, category tiles, rich text, image with text, accordion, trust strip |

## 7. States

Each component and screen is designed in every state it can be in: default, hover, focus, active, disabled,
loading, empty, error, success, out of stock, low stock where the row requires it, signed out and signed in, and
long content in both languages. A state that is not designed is a state the build would invent.

## 8. The curated section and component library

The self-service rows (SSC, owned by #311 and #302) are met with blocks, as the Gutenberg decision records, and
with a curated library, not a free canvas.

| Section | Allowed variants | Editable fields | Locked |
|---|---|---|---|
| Hero | Image left, image right, full bleed | Heading, text, button label and link, image, alt text | Height, type scale, spacing, overlay |
| Promotional banner | Strip, card | Text, link, image, start date and time, optional end date and time, page placement, enabled or disabled (SSC-09, CX-06) | Position rules, size |
| Product rail | Manual pick, by collection, by category | Heading, source, item count within a fixed range | Card layout, columns per viewport |
| Category tiles | Four or six tiles | Category, image, label | Grid, aspect ratio |
| Rich text | One or two columns | Text, headings to a fixed depth, links | Fonts, colours |
| Image with text | Image left or right | Image, alt text, heading, text, link | Proportions |
| Trust strip | Three or four items | Icon from a fixed set, label, link | Layout |
| Accordion | Single or multiple open | Question, answer | Styling |

**Locked layout rules.** An editor chooses a section, a variant and its content. An editor cannot set arbitrary
colours, fonts, spacing, widths or custom CSS, cannot nest sections freely, and cannot break the mirrored layout.
Banner scheduling follows the owner resolution of CX-06 (D-12, not client-confirmed): a promotional banner carries
its own start, optional end, page placement and enabled state. No other section is scheduled: HOME-12 and ADM-62
are P2.

## 9. Neutral design tokens

Tokens live in `mizzey-theme/theme.json` and as CSS custom properties with the `--mizzey-` prefix.

| Group | Tokens |
|---|---|
| Colour roles | Surface, surface raised, text, text muted, border, primary, on primary, accent, success, warning, danger, focus |
| Type | Family for headings and body in each script, a modular size scale, weights, line heights |
| Space | One spacing scale used for every gap, padding and margin |
| Shape | Radius scale, border widths, elevation levels |
| Motion | Duration and easing, reduced when the visitor asks for reduced motion |

Components reference roles, never raw values. Neutral values are greys and one system typeface per script.

## 10. Replacing the neutral tokens with the brand

1. The client supplies the identity: OD-01 and CR-02, the written approval of brand direction.
2. The colour roles, type families and logo assets are set in the token file and the asset folder. No component
   markup changes.
3. Contrast is rechecked for every colour role in both directions.
4. The screens are regenerated and presented to the client as the interface design, through the contracted
   revision rounds.
5. The client's written approval is recorded. Only then is PRE-03b accepted, and only then is a storefront PBI's
   interface criterion (DOD-01, "interface implemented as approved") acceptable.

## What this plan does not settle

- The brand. OD-01 is the client's.
- The page copy and imagery. Content inputs, held as placeholders.
- Final visual approval, which is the contractual gate and is not weakened by anything here.
