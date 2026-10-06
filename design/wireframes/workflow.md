# Wireframe production workflow

How the low-fidelity wireframes are produced, reviewed and tracked. The artefact format is in
[README.md](README.md). Nothing in this document has started: **no wireframe exists**, and the calibration set
begins only on the owner's instruction.

## 1. What Claude Design receives and reads

The design is produced by Claude Design (section 2). When design work begins it is given three things:

1. **This repository.**
2. **The working briefs, with the signed row wording merged in.** They are generated outside the repository and
   never committed.
3. **The original signed source documents from `final docs/`**, as read-only reference where a brief is not
   enough. They are never committed to Git, in whole or in part.

For each surface, in this order:

1. **Its brief**, `../briefs/<surface id>.md`. Generated from the inventory by `tools/design_briefs.py`. It
   carries the surface's rows by id, its states, its components, its open inputs, the interaction options that
   bear on it and the direction. It is never edited by hand.
2. **The wording of each row it lists**, in the signed register. The repository holds ids, not wording
   (`../sources.json`). On the developer's machine, Claude Code produces working copies with the wording merged
   in, outside the repository:

   ```bash
   python tools/design_briefs.py --register "<path to the signed register>" --out "<a folder outside the repository>"
   ```

   The tool refuses to write those inside the repository.
3. **`../direction.md`**, the experience target.
4. **`../README.md`**, for the authority order and the rule that separates presentation from capability.

A brief exists for a surface when the manifest lists one. Briefs are generated for a batch when that batch opens,
not for all 154 surfaces at once.

## 2. Two roles: Claude Code and Claude Design

Two agents work on the design, and they do different jobs. **Neither does the other's.**

| | Claude Code | Claude Design |
|---|---|---|
| Is | The keeper of the repository workflow | The UI and UX design executor |
| Does | Maintains the canonical inventories, the generators and the checkers. Generates the briefs. Validates wireframe artefacts. Updates the manifest and the status records after approved design work. Reports scope conflicts | Reads the repository, the working briefs, the signed source wording supplied outside Git, and `../direction.md`. Creates the calibration wireframes. Proposes directions A, B and C. Recommends UX alternatives. Produces the structural design artefacts |
| Does not | Choose the visual or UX direction. Create the calibration layouts. Silently resolve a design alternative | Edit the Feature Register, the SRS or any contract document. Edit canonical scope ownership. Change `surfaces.json` to make its design easier. Change a contractual constraint. Invent a customer or business capability. Resolve a scope conflict itself |

### What Claude Design changes

**Only the approved design artefacts under `design/wireframes/`**: the HTML and CSS files of the wireframes.

It does not change `design/inventory/`, `design/briefs/`, `docs/scope/`, `DECISIONS.md`, `PROGRESS.md`, a PBI
or application code, unless the owner later explicitly authorises another path.

`manifest.json` already declares the path of every calibration file, as
`s1/calibration/<a|b|c>/<frame id>.<en|ar>.html`. Claude Design creates the files at those paths and leaves the
manifest alone. **The manifest and the canonical status records are updated by Claude Code, after the design
review.**

### What Claude Design reports instead of changing

If Claude Design finds:

- a missing requirement;
- an apparent contradiction;
- a design need that looks like new capability;
- a constraint that makes a professional solution impossible;

it reports it back to the owner. It does not change scope, an inventory record or a constraint to get past it,
and it does not draw around it as though it were settled.

### How Claude Design works

The designer, here and below, is Claude Design. The designer works as a professional product, UI and UX
designer, not as a converter of rows into rectangles. Within the `design_freedom` of each surface, the designer:

- makes professional ecommerce recommendations and uses contemporary patterns;
- recognises when more than one solution is valid, recommends one and says why;
- shows alternatives where the choice materially changes the experience;
- holds to accessibility, performance and right to left;
- never invents uncontracted business functionality.

**For an open interaction option, the designer may show alternatives. For a pattern the contract fixes, the
designer respects it.** Each brief marks which is which: a constraint of type `contract_pattern` is not a design
alternative.

Low fidelity is grayscale, and it still shows professional ecommerce judgement. A composition of bare boxes does
not meet `../direction.md`.

## 3. The calibration set

The first exercise is a calibration set. Its purpose is to establish the structural ecommerce direction before
the design is carried across the inventory. **It is drawn in three directions, and it commits the project to
none of them until the owner selects.**

### The eight frames

Each frame reuses existing surface ids. One frame may compose several surfaces. No frame introduces scope.

| Frame | What it covers | Viewport | Languages |
|---|---|---|---|
| `cal-01` | Global desktop frame: header, navigation, search and footer | Desktop | English, Arabic |
| `cal-02` | Mobile header and navigation | Mobile | English, Arabic |
| `cal-03` | Home page | Desktop | English |
| `cal-04` | Category listing | Desktop | English |
| `cal-05` | Product detail page | Desktop | English, Arabic |
| `cal-06` | Mini-cart and cart | Desktop | English |
| `cal-07` | Checkout | Desktop | English, Arabic |
| `cal-08` | Mobile shopping composition: listing, filters, product and add to cart | Mobile | English, Arabic |

The surfaces of each frame, the states it must show and the interaction options it exercises are in
`manifest.json`, under `calibration.frames`. The 32 surfaces they cover are briefed. Arabic is drawn for five of
the eight frames, chosen because they carry the most direction-sensitive structure: the two headers, the product
page, the checkout form and the mobile composition. Every surface is drawn in both languages in its batch.

### The three directions

Three structural approaches to hierarchy, density, composition and interaction. **They are not three brand
identities.** The labels are descriptive only.

| | Label | Approach |
|---|---|---|
| A | Clean Premium Commerce | Calm and restrained. Lower density, generous whitespace, a quiet header, large product imagery, and merchandising content used sparingly so the product carries each view |
| B | Editorial Modern Retail | Composed like a publication. Larger editorial blocks set among the products, strong typographic hierarchy, imagery that dominates, and grids that vary rather than repeat |
| C | Dynamic Contemporary Ecommerce | Energetic and efficient. Higher density, more products in view, prominent merchandising rails and badges, a utility-forward header, and the purchase actions kept close at hand |

| The three may differ in | The three never differ in |
|---|---|
| Density and whitespace | Contractual functionality |
| Product-card emphasis | The states drawn |
| Home-page composition | Any pattern the contract fixes |
| Header architecture and navigation treatment | The open inputs and their placeholder rules |
| How far imagery dominates | Neutrality: greys, a system typeface, no logo, no final imagery |
| Where merchandising content sits | |
| The product page: gallery and buy area | |
| Cart presentation and information hierarchy | |
| Interaction patterns that are genuinely open | |

**No direction adds a feature to look richer.** If one direction seems to need something the scope does not
contain, that is reported, not drawn.

Each frame says, in an option note, which candidate of each open or provisional interaction option it shows.
Directions may show different candidates, which is how the owner compares them.

## 4. The review model

**The owner is not asked for low-level design decisions**: not an exact radius, not pixel spacing, not animation
curves, not the internals of a component.

The owner reviews:

- the overall direction, its hierarchy and its density;
- whether it feels like modern ecommerce or dated, polished or visually poor;
- the meaningful UX alternatives the designer shows;
- whether the shopping experience feels appropriate.

For the calibration set the result is one of:

- choose A;
- choose B;
- choose C;
- choose one direction, with named elements from another.

It is recorded in `manifest.json` under `selection`, with who decided and where. **Selecting a structural
direction is not approval of the brand identity, and not approval of the interface design.** The brand identity
is the client's (OD-01), and the contracted approval is PRE-03b.

An interaction option is decided at the stage it names. When the owner selects a pattern, the option becomes
`locked` in `../inventory/interaction-options.json`, with the pattern and the source of the decision. Until then a
baseline drawn in a wireframe is still provisional.

## 5. After calibration

Planned, not started.

1. Lock the selected structural direction.
2. Resolve the interaction options that review decided.
3. Generate the remaining first-release briefs.
4. Wireframe the first-release surfaces in review batches.
5. Review and close each batch.
6. Stabilise the structural component and interaction system.
7. Generate the briefs of the second-release surfaces and wireframe them.
8. Review the complete structural UX.
9. Receive and apply the brand identity.
10. Produce the high-fidelity UI and UX.
11. Specify motion and detailed interaction.
12. Produce the contracted working HTML and CSS design.
13. Obtain PRE-03b approval.
14. Only then resume storefront implementation.

## 6. Review batches

Batches follow the shopping experience, not one PBI at a time, so that what is reviewed together is used
together. Every surface stays traceable by its canonical id, and `manifest.json` says which batch each is in.

| Batch | What it covers | Surfaces |
|---|---|---|
| `batch-01` | Global frame, discovery and home: both headers, the menus, the language switcher, the footer, the consent banner, the promotional banner, the home page, and the 404, maintenance and Coming Soon screens | 12 |
| `batch-02` | Listing, filters and search: the four listing pages, the product card and its badges, filters, the filter drawer, the toolbar, list continuation, breadcrumbs, the empty list, and search suggestions, results and no results | 15 |
| `batch-03` | Product page, gallery and purchase actions: both product pages, the gallery, the stock state, related and recently viewed, and the reviews summary | 7 |
| `batch-04` | Cart and authentication: the cart and its states, the mini-cart, the free-shipping prompt, and sign in, registration, password, passwordless, Google and the account offers | 15 |
| `batch-05` | Checkout, payment and order received: the checkout and its sections, validation, the stock and ERP failure states, the payment handoff and failure, and the order confirmation | 12 |
| `batch-06` | Account, orders and tracking: the account screens, order history and detail, tracking, the invoice, and the status timeline and labels | 11 |
| `batch-07` | Wishlist, reviews, content and trust: the wishlist, recently viewed, the review list and form, and the content, policy, contact and campaign pages | 15 |
| `batch-08` | Transactional emails and customer messages: the email layout, the eight customer emails, the platform's staff emails, and the store notices | 11 |
| `batch-09` | Additions to the standard administration, and reports: annotated, not redesigned | 40 |
| `batch-10` | Contracted second-release surfaces, in their own pass after the first-release system is stable (DQ-04) | 8 |

Eight surfaces are in no batch: the seven provider-hosted interfaces, which are not ours to draw, and the premium
visual treatment, which exists only at high fidelity.

`batch-09` is different in kind. The register contracts the standard administration and no bespoke administration
interface is designed, so those surfaces are annotated against the standard screen each extends, in English only.
They do not follow a storefront direction.

A batch opens when the one before it is reviewed and closed. Opening a batch means: set its surfaces to `briefed`
in the manifest, write a purpose for each in the inventory, and generate their briefs.

## 7. Tracking

`manifest.json` tracks design production and nothing else. It does not restate a requirement: the rows, the
states and the options of a surface are in the inventory, and an entry points at them by the surface id.

| Field | What it tracks |
|---|---|
| `surface` | The canonical surface id |
| `batch` | Its review batch |
| `brief` | The path of its generated brief, once it has one |
| `artifacts` | The wireframe files that exist for it |
| `en_ltr`, `ar_rtl` | English and Arabic: `not_started`, `drawn`, `reviewed`, or `not_applicable` |
| `mobile`, `desktop` | The same, per viewport |
| `states_covered` | Which of the surface's states the wireframes show, by name |
| `interaction_pending` | The interaction options bearing on it that are not yet locked. Derived from the inventory |
| `review_status` | `not_started`, `briefed`, `exploration`, `candidate_selected`, `wireframed`, `reviewed`, or `not_applicable` |

The calibration set is tracked beside the entries, per frame and per direction, because one frame composes
several surfaces.

**Claude Code updates the manifest, after the design review.** Claude Design does not edit it (section 2). A
calibration file at a path the manifest declares may therefore exist while its direction still reads
`not_started`: the status records the review, and is set once the review has happened.

`tools/design_wireframes.py` holds the manifest to the inventory and to the files on disk: one entry per surface,
a brief wherever a surface is past `not_started`, no artefact the manifest does not list, no listed artefact that
does not exist, and no surface `reviewed` while a required state is uncovered.
