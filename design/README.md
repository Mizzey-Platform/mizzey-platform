# Design: the UX source of truth

This folder is the single place the design of the Mizzey Launch Platform is worked from. It was opened by owner
decision D-14 on 6 October 2026.

**Nothing here is approved UI or UX, and nothing here is a client deliverable.** It is an inventory: what needs
design, in what states, on which signed rows. No screen has been drawn. The contracted interface design is
PRE-03b, owned by #250, delivered as working HTML and CSS after the brand identity is approved. This folder is how
that work is prepared, and it changes no contract term.

## Where the work stands

| Step | State |
|---|---|
| 1. Signed scope and PBIs | Done. Register MS-ANX-2026-006 version 1.5, and `docs/scope/` |
| 2. Complete inventory of surfaces, flows and states | **This folder.** Reported in `coverage.md` |
| 3. Low-fidelity wireframe pass for the first-release (S1) surfaces | Not started. The first is drawn only when the owner approves the start of the wireframe pass |
| 4. Structural component and interaction system, established and stable | Not started |
| 5. Second low-fidelity pass, for the contracted second-release (S2) surfaces | Not started |
| 6. Brand identity received and applied | Waiting on the client (OD-01) |
| 7. High-fidelity design for all contracted storefront surfaces, S1 and S2 | Not started |
| 8. The contracted working HTML and CSS design | Not started |
| 9. PRE-03b approved by the client | Not started |
| 10. Storefront implementation | Frozen until step 9 (D-14) |

**Low-fidelity wireframes are design artefacts, prepared here first.** They are not built in `mizzey-theme/`, and
nothing is implemented in the theme until the work reaches step 6. They are not a client deliverable: PRE-03b
says no separate wireframe stage is produced, and the approved artefact is the working screens.

## Authority

When two sources disagree, the higher one wins. Do not settle a disagreement by editing the lower one: record it
and raise it.

| Rank | Source | Where |
|---|---|---|
| 1 | The signed Services Agreement and the governing engagement documents | Outside this repository. `sources.json` |
| 2 | The signed Feature Register | Outside this repository. Mirrored without its wording in `docs/scope/register-ids.json` |
| 3 | The Functional Specification and the client's requirements document | Outside this repository |
| 4 | The acceptance and UAT requirements | Outside this repository |
| 5 | Option B PBI ownership and the exact register ids | `docs/scope/backlog-ownership.json` |
| 6 | Approved owner decisions and open items | `DECISIONS.md`, `docs/scope/open-items.json` |
| 7 | The verified sitemap, flows and URL map | `docs/pre-development/PRE-03a-sitemap-and-user-flows.md`, `specs/003-information-architecture-urls/url-map.md` |
| 8 | Existing technical evidence, where it constrains design | `specs/`, `docs/staging-environment.md` |
| 9 | Historical Option C material | **Reference only. Never scope** |

**Option C was never signed.** `scripts/stories.json`, everything under `discovery/`, `docs/discovery/`,
`docs/decisions/`, GitHub Project 4 and its issues describe a different engagement. No surface, flow, state or
wireframe may be derived from them. `discovery/tasks/WIREFRAME.md` and `discovery/tasks/JOURNEY.md` are retired
instructions and must not be followed.

## The signed documents are not in this repository

This repository is public (D-08). The signed register, the Functional Specification and the other engagement
documents stay in `final docs/`, beside the repository on the developer's machine. They are not copied here, in
whole or in part.

`sources.json` names each one: its document id, version, role, authority rank, local path and hash. Whoever
designs is given two things: this repository, and the original documents from `final docs/`. The inventory records
ids and structure. **The wording of a row is read from the signed register, never from memory and never from this
folder.**

## What is in this folder

| Path | What it holds |
|---|---|
| `sources.json` | The authoritative documents, by reference |
| `direction.md` | The experience target: the quality the design aims at. Not the brand identity, and not scope |
| `inventory/surfaces.json` | **The spine.** One record for each thing that needs design, and the delivery rows that have no surface |
| `inventory/flows.json` | The journeys as ordered steps, each pointing at surfaces |
| `inventory/components.json` | The structural component inventory, and the curated section library |
| `inventory/placeholders.json` | Every open decision that affects design, with its neutral placeholder rule, and the boundaries the owner has locked |
| `inventory/interaction-options.json` | UX choices with more than one legitimate pattern: open, provisional or locked |
| `coverage.md` | Generated. How the inventory accounts for the signed scope |
| `briefs/` | One generated brief per surface, for the surfaces a review batch has opened. Never edited by hand |
| `wireframes/` | The wireframe format, the production workflow and the manifest. **No wireframe exists yet** |
| `tokens/` | Empty. Neutral tokens first, brand values after OD-01 |

## Five kinds of statement, kept apart

**A wireframe must not turn a choice the contract leaves open into a permanent product decision.** Everything in
this folder is one of five things, and each lives in its own place.

| Kind | What it is | Where it lives | Who can change it |
|---|---|---|---|
| Contractual requirement | What the signed scope obliges | `register_ids` on a surface, read in the signed register | Change control only |
| Technical or platform constraint | What WordPress, WooCommerce, an integration or a verified build fixes | `native_baseline`, `design_freedom`, and the `constraints` of an interaction option | Engineering evidence |
| Locked owner decision | A boundary the owner decided | `owner_design_decisions` in `placeholders.json` | The owner |
| Provisional UX pattern | A working baseline for the first wireframes | `interaction-options.json`, status `provisional` | UX review, with no scope change |
| Open design choice | A pattern not yet preferred | `interaction-options.json`, status `open` | UX review |

**A provisional pattern is not a requirement and not an owner-confirmed final product decision.** It is where the
first wireframes start. It may change during UX review, provided the pattern chosen still satisfies the signed
requirement and the constraints. The checker refuses a provisional or open option that claims a selected pattern.

A requirement fixes an outcome. It rarely fixes the pattern. A row that says a customer can continue through a
product list does not say numbered pagination, Load More or continuous loading: those are compared during design,
unless the wording of the row or a real technical constraint settles it. The same holds for the checkout
structure, where filters sit, how the mini-cart opens, whether the buy actions stay in reach, how the gallery and
the menu behave, and how search responds.

## A surface

A surface is anything that needs a design decision. A full page is one kind. A drawer, an empty state, an email,
a validation message, a report or an addition to an admin screen is each its own surface.

| Field | Meaning |
|---|---|
| `id`, `name` | Stable id and plain name |
| `kind` | `page`, `shell`, `overlay`, `component`, `email`, `admin_extension`, `report`, `system_state`, `message` |
| `scope_type` | See below |
| `audience` | `visitor`, `customer`, `staff`, `developer`, `system` |
| `register_ids` | The exact delivery rows this surface is drawn for. Every one is owned by `owner_pbi`. Never a range |
| `context_ids` | Delivery rows another PBI owns that shape this surface |
| `excluded_ids` | Neighbouring rows that are deferred, later-phase or out. **What must not be drawn here** |
| `owner_pbi` | The PBI that **accepts** the surface's rows: an issue number, or a slice key for a second-release slice |
| `build_pbi` | The PBI that **builds** the visible surface. Usually the same PBI. See below |
| `stage` | The contractual stage of its rows. Computed, never chosen |
| `route` | The English and Arabic address, from the URL map, where the surface has one |
| `flow_steps` | The steps of `flows.json` that pass through it. Derived |
| `acceptance` | Stories that trace its rows, acceptance scenarios (AC), and store operations checks (OPS) |
| `states` | Every state to design, with the row that requires it where one does |
| `responsive` | Mobile, tablet and desktop |
| `rtl` | Whether Arabic right to left applies, what mirrors and what does not |
| `native_baseline` | What WordPress, WooCommerce or a selected integration already renders |
| `design_freedom` | `free`, `constrained`, `annotate_only` or `none`. See below |
| `open_items` | Ids in `placeholders.json` that this surface waits on |
| `decisions` | Locked owner decisions in `placeholders.json` that govern this surface |
| `interaction_options` | Open or provisional UX choices in `interaction-options.json` that bear on this surface. Derived |
| `erp_dependency` | `none`, `partial`, or `per PRE-09` |
| `status` | `inventoried`, `briefed`, `wireframed`, `reviewed`, `branded`, `approved`, or `not_designed`. The finer production status is in `wireframes/manifest.json` |
| `purpose` | The outcome the surface exists to support. Written when the surface is briefed |
| `composes` | Other surfaces this one contains |
| `scope_note` | Why a surface outside contracted scope exists |
| `build_note` | Why the build PBI is not the accepting PBI. Present only where they differ |
| `implementation_origin` | Where a surface that no PBI builds comes from: `mizzey`, `corex`, `wordpress`, `woocommerce`, `provider` or `unknown` |

## Design freedom

`design_freedom` says how far a surface may be explored. **It controls how an existing requirement is expressed,
never what functionality exists.** It is not a route to more scope.

| Value | Meaning |
|---|---|
| `free` | The designer may explore composition, hierarchy, visual treatment, layout and interaction patterns, as long as the signed capability and its states remain satisfied |
| `constrained` | A platform, a native block, an integration or a contractual condition limits the solution. Design decisions still exist within that boundary |
| `annotate_only` | The interface belongs mainly to a provider or to the standard administration. The handoff, the state or the extension is documented. The external or native product is not redesigned |
| `none` | There is no design choice to make |

## Two PBIs behind one surface

Accepting a row and building its screen are two different facts, and a surface records both.

- **`owner_pbi` accepts.** It is the PBI that owns the surface's register rows in
  `docs/scope/backlog-ownership.json`: one row, one accepting PBI. This folder never changes it.
- **`build_pbi` builds.** It is the PBI that implements or integrates the visible surface.

They are usually the same PBI. Where they differ, `build_note` says why and `coverage.md` lists the surface. The
order confirmation is the plain case: its page row IA-10 is accepted by #242 as the route, and the screen is
built with the checkout, #255. No row moves owner to make this convenient. Design dependency is read on
`build_pbi`.

**No PBI is invented to fill the field.** A surface outside contracted scope, such as the 404 page, the
maintenance message, Coming Soon, the store notices or the sign-in-required state, has no `build_pbi` until there
is a real implementation owner. `implementation_origin` says where it comes from today. A provider's interface
has the origin `provider` and is built by nobody here.

## Scope type

Every surface carries exactly one.

| Value | Meaning | Register rows |
|---|---|---|
| `contracted` | Backed by one or more exact delivery rows of the register | At least one, all owned by its PBI |
| `native_required` | A standard platform or system experience the site cannot be without, which no row contracts as a custom feature | None |
| `internal_operational` | An engineering or launch-support experience. Useful, not a contracted deliverable | None |
| `provider_hosted` | A third party's interface, which is not ours and is not redesigned | None |

A surface that is not `contracted` cites no register row, says in `scope_note` why it exists, and is never
presented to anyone as contracted scope. The 404 page is `native_required`. Coming Soon is
`internal_operational`. The payment provider's hosted step is `provider_hosted`: what is designed is our handoff,
loading, failure, return and success around it.

**Second-release rows are contracted and staged S2, and their surfaces are designed, not left out.** They are
inventoried and marked S2. They are excluded from the initial S1 wireframe pass, and where each attaches is kept
free during it. They then get their own low-fidelity pass before PRE-03b is approved, and are part of the
high-fidelity design and of the final contracted interface-design package, because the signed documents measure
that design by every contracted storefront screen. **This is design sequencing only.** They are still built in
the second release, and no row changes stage (DQ-04).

## Locked owner decisions

Four boundaries were decided by the owner in D-14, for questions no source answered. They are in
`inventory/placeholders.json` under `owner_design_decisions`, and each surface they govern cites them in
`decisions`. **They are owner design decisions: not client confirmations, and not scope additions.**

| Id | Decision |
|---|---|
| DQ-04 | S2 surfaces are excluded from the initial S1 wireframe pass, keep their attachment points, get their own low-fidelity pass before PRE-03b is approved, and are in the final interface-design package. Sequencing only: they are built in the second release |
| DQ-05 | Cash collected and remitted is a staff and accounting state. The customer sees Delivered |
| DQ-06 | A signed-in customer may review. No verified-purchase requirement or badge |
| DQ-07 | Accept, Decline and Preferences, within what the consent tool selected in #288 supports |

## Interaction options

`inventory/interaction-options.json` lists the UX choices that have more than one legitimate pattern. An entry
exists only where the requirement defines an outcome, more than one normal pattern can meet it, neither the
contract nor the platform fixes the pattern, and choosing early would constrain the design for no reason. It is
not a list of every component.

| Status | Meaning |
|---|---|
| `open` | No pattern is preferred yet. The designer proposes, recommends one and shows the alternatives |
| `provisional` | A working baseline for the first wireframes. **Not a requirement and not an owner-confirmed final product decision.** It may change in review with no scope change |
| `locked` | A pattern was selected at its decision stage. `selected_pattern` and `decision_source` say which, and by whom |

Three are provisional today, and none is locked:

| Id | Choice | Working baseline | Also candidates |
|---|---|---|---|
| DQ-01 | Numerals in Arabic | Western digits 0 to 9 | Arabic-Indic digits, or a mixed treatment, if justified in the design and localisation review |
| DQ-02 | Product-list continuation | Numbered pagination | Load More, or controlled continuous loading |
| DQ-03 | Checkout structure | One page with clear sections, review before Place Order | Progressive or accordion sections, or a stepped checkout if it stays compatible with the contracted behaviour and the commerce implementation |

Each entry carries its `selection_criteria`, which are what the review weighs, and its `constraints`, which are
real limits. An option never adds a capability: it is about how a contracted one is presented.

**Not every choice is equally open.** Each constraint is typed, and `constrained_by` sums them up:

| Constraint type | Meaning | What the designer does |
|---|---|---|
| `contract_pattern` | The signed wording fixes part of the pattern itself | **Keeps it. It is not a design alternative.** Every candidate pattern already keeps it. Changing it needs a requirement decision, not a design review |
| `contract_condition` | A contracted requirement that every candidate must meet | Makes sure the pattern chosen meets it |
| `platform` | What the platform, a native block or a technical fact limits | Works within it, or says what leaving it would cost |

An option with no `contract_pattern` and no `platform` constraint is genuinely open. The product information
layout (IX-08) shows the difference. PDP-12 words the full description as a tab, so the tab is a
`contract_pattern`: the description stays a tab on every viewport. The designer explores how the other
information sits around that tab, and how the tab navigation responds on a small screen, and does not propose a
layout in which the description is no longer a tab. The home hero (IX-09) is genuinely open, because
HOME-01 names a banner or a slider and fixes neither. **A contractual constraint is never an invitation to
redesign the requirement.**

## The designer proposes

Whoever designs is expected to work as a professional product, UI and UX designer, not to convert rows into
boxes. Within the freedom each surface allows, the designer:

- proposes professional ecommerce patterns and uses contemporary conventions;
- notices when more than one UX solution is valid;
- recommends one and says why;
- shows the alternatives when the choice materially changes the experience;
- holds to accessibility and performance;
- adds no uncontracted functionality.

**The owner is not expected to specify pixels, radii, animation curves or component internals.** The owner
reviews the direction and chooses between meaningful alternatives. `direction.md` says what quality the work
aims at.

## Presentation is design. Capability is scope.

The rule against scope expansion stands, and it is about capability, not presentation.

**A designer does not add a product capability because another store has it.** A control that gives the customer
something new to do, or gives the business a new behaviour, needs scope authority: a delivery row of the register,
or a Change Request. Back-in-stock signup, product comparison, a returns area, a wishlist share button and a
"verified purchase" badge are capabilities, and none of them is contracted.

**Normal presentation and interaction design needs no register row of its own.** When a treatment only presents
an action that is already authorised, it is a design decision:

- hover, press and focus feedback;
- the animation of a contracted drawer opening;
- a skeleton for a loading operation that already exists;
- a transition between states that already exist;
- layout composition, spacing and hierarchy;
- visual grouping and card treatment;
- subtle motion;
- responsive behaviour.

The test is one question. **Does it create a new capability or business behaviour, or does it change how an
authorised one is presented?** The first needs scope authority. The second is design.

## Rules for whoever designs

1. **Design the contracted capabilities, and no others.** The capabilities, states and fields a surface lists are
   its scope. Do not add a capability because a store usually has one: if it seems missing, raise it, and it may
   be a Change Request. How those capabilities are composed, presented and animated is yours to propose.
2. **Read `excluded_ids` before drawing.** It names what the client did not buy on that screen: the returns area,
   the compare page, back-in-stock signup, section scheduling, and the rest.
3. **Read the wording of each row in the signed register.** An id is necessary and not sufficient.
4. **Never derive scope from Option C material.**
5. **Neutral only, until OD-01.** No logo, no client colour, no final typeface, no imagery style. Greys, one
   system typeface per script, labelled boxes. `placeholders.json` says what may stand in for each open input and
   what may not be assumed.
6. **Do not invent ERP behaviour.** Structural stock states may exist. Timing, mechanism, reservation and stock
   wording wait for PRE-09.
7. **Do not design a provider's interface.**
8. **English is the canonical design. Arabic is the same design mirrored**, never a second layout. Every
   customer-facing surface is drawn in both. Numerals, prices, imagery and logos do not mirror. Directional icons
   do. Western digits in both languages is the working baseline, and it is provisional (DQ-01).
9. **Mobile first**, then tablet and desktop.
10. **A contradiction is escalated, not resolved.** The open points are in `placeholders.json`. A new one is
    reported to the owner, who decides it. A designer does not.
11. **Do not lock a pattern by drawing it.** Where a surface lists an interaction option, start from the working
    baseline if there is one, and say which pattern you recommend and what the alternatives are. A baseline drawn
    in a wireframe is still provisional until the owner selects it at its decision stage.

## The administration is not redesigned

The register contracts the standard commerce administration, and no bespoke administration interface is designed
(register sections G10 and G11). The inventory therefore lists only the additions this engagement makes to a
standard screen, names the screen each extends, and marks most of them `annotate_only` or `constrained`. Rows met
by a standard screen are marked `no_surface` with the reason `native_admin`.

The administration is English only (ADM-159). An Arabic administration is ADM-159a, which is not contracted.
Content edited in the administration is bilingual, and its storefront preview is drawn in both directions.

## What the existing code is

Nothing committed in `mizzey-site/` or `mizzey-theme/` is approved UI or UX. The plugin's classes are technical
foundation: language loading, data integrity between language records, addresses, search engine output and report
corrections. None renders a storefront or admin screen. The theme is a scaffold. Its two templates and two parts
are stubs to be replaced, not a design to follow. Four information-architecture choices are already verified in
tests and may still be challenged by the wireframes: they are open points OP-14, OP-18, OP-19 and OP-20, and
changing one is a requirement PR to spec 003.

## Changing the inventory

Edit the JSON, then run:

```bash
python tools/design_inventory.py --write
python -m unittest tools.tests.test_design_inventory
```

The first checks the inventory against the register mirror and PBI ownership and rewrites `coverage.md`. After a
change to the inventory, regenerate the briefs and check the manifest too:

```bash
python tools/design_briefs.py --write
python tools/design_wireframes.py
```

A PR that touches this folder is `internal:governance`, `internal:documentation` or `requirement`
(`PATH_POLICY` in `tools/scope_trace.py`). Markdown and JSON are accepted anywhere here. Static HTML and CSS are
accepted under `wireframes/` only, as design prototypes. Script and every other file type are refused.

This folder never changes PBI ownership, a register row, a stage or an acceptance obligation. Where the inventory
and `docs/scope/backlog-ownership.json` disagree, `coverage.md` reports it and the owner decides.
