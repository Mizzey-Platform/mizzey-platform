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
| 3. Low-fidelity structural wireframes | Not started. The first is drawn only when the owner approves the start of the wireframe pass |
| 4. Brand identity received | Waiting on the client (OD-01) |
| 5. High-fidelity UI and UX | Not started |
| 6. The contracted working HTML and CSS design, approved by the client | Not started. This is PRE-03b |
| 7. Storefront implementation | Frozen until step 6 (D-14) |

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
| `inventory/surfaces.json` | **The spine.** One record for each thing that needs design, and the delivery rows that have no surface |
| `inventory/flows.json` | The journeys as ordered steps, each pointing at surfaces |
| `inventory/components.json` | The structural component inventory, and the curated section library |
| `inventory/placeholders.json` | Every open decision that affects design, with its neutral placeholder rule, and the design questions the owner has decided |
| `coverage.md` | Generated. How the inventory accounts for the signed scope |
| `briefs/` | Empty. One brief per surface, generated when wireframing is approved |
| `wireframes/` | Empty. Low-fidelity wireframes, after the coverage review |
| `tokens/` | Empty. Neutral tokens first, brand values after OD-01 |

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
| `design_freedom` | `free`, `constrained` by a platform block, `annotate_only`, or `none` |
| `open_items` | Ids in `placeholders.json` that this surface waits on |
| `decisions` | Owner design decisions in `placeholders.json` that govern this surface |
| `erp_dependency` | `none`, `partial`, or `per PRE-09` |
| `status` | `inventoried`, `briefed`, `wireframed`, `reviewed`, `branded`, `approved`, or `not_designed` |
| `composes` | Other surfaces this one contains |
| `scope_note` | Why a surface outside contracted scope exists |
| `build_note` | Why the build PBI is not the accepting PBI. Present only where they differ |

## Two PBIs behind one surface

Accepting a row and building its screen are two different facts, and a surface records both.

- **`owner_pbi` accepts.** It is the PBI that owns the surface's register rows in
  `docs/scope/backlog-ownership.json`: one row, one accepting PBI. This folder never changes it.
- **`build_pbi` builds.** It is the PBI that implements or integrates the visible surface.

They are usually the same PBI. Where they differ, `build_note` says why and `coverage.md` lists the surface. The
order confirmation is the plain case: its page row IA-10 is accepted by #242 as the route, and the screen is
built with the checkout, #255. No row moves owner to make this convenient. Design dependency is read on
`build_pbi`. A surface no PBI builds is a provider's interface or a platform screen the owner has not placed.

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

**Second-release rows are contracted and staged S2.** Their surfaces are inventoried and marked S2. They are not
built in the first release and are not wireframed in the Stage 1 pass. Where each attaches is kept free, so the
first-release structure does not prevent the later work (DQ-04).

## Owner design decisions

Seven questions no source answered were decided by the owner in D-14. They are in
`inventory/placeholders.json` under `owner_design_decisions`, and each surface they govern cites them in
`decisions`. **They are owner design decisions: not client confirmations, and not scope additions.**

| Id | Decision |
|---|---|
| DQ-01 | Western digits 0 to 9 in both languages. No Arabic-Indic digits unless the client asks |
| DQ-02 | Numbered pagination. No infinite scroll |
| DQ-03 | One checkout page in clear sections, with the review before Place Order. No wizard |
| DQ-04 | S2 surfaces stay inventoried and are not wireframed in the S1 pass |
| DQ-05 | Cash collected and remitted is a staff state. The customer sees Delivered |
| DQ-06 | A signed-in customer may review. No verified-purchase requirement or badge |
| DQ-07 | Accept, Decline and Preferences, within what the consent tool selected in #288 supports |

## Rules for whoever designs

1. **Draw only what a surface record lists.** A state, a field or a control that no row requires is not added
   because a store usually has one. If it seems missing, raise it. It may be a Change Request.
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
   do. Numerals are Western digits in both languages (DQ-01).
9. **Mobile first**, then tablet and desktop.
10. **A contradiction is escalated, not resolved.** The open points are in `placeholders.json`. A new one is
    reported to the owner, who decides it. A designer does not.

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

The first checks the inventory against the register mirror and PBI ownership and rewrites `coverage.md`. A PR
that touches this folder is `internal:governance`, `internal:documentation` or `requirement`
(`PATH_POLICY` in `tools/scope_trace.py`). Only markdown and JSON are accepted here today: a wireframe file type
is added to the policy when its format is approved.

This folder never changes PBI ownership, a register row, a stage or an acceptance obligation. Where the inventory
and `docs/scope/backlog-ownership.json` disagree, `coverage.md` reports it and the owner decides.
