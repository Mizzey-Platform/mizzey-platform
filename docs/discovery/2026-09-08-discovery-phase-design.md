# Discovery and Validation Phase: Design

**Status:** approved 8 September 2026
**Owner:** Mustafa Shaaban
**Internal.** Not a client document. Not contractual. Nothing here changes scope.

Read `CLAUDE.md` at the project root first. This document assumes it.

---

## 1. Why this phase exists

The engagement pack was issued on 8 September 2026 and the Agreement and Statement of Work are
signed. The build has not started.

205 user stories across 27 epics already exist in `platform/scripts/stories.json`, generated from
the Functional Specification, each carrying acceptance criteria, traceability to the Feature
Register and the SRS, points, and a sprint. The Technical Design section 7 already names 20 places
where WooCommerce stops and something has to be written.

**None of it has been tested against a running WooCommerce.** Every claim in TDD section 7 is
analysis. The `leverage` field in `stories.json` is assigned **per epic**, not per story, so it is
an estimating device rather than a feasibility judgement: every story in E05 is `mixed` because
E05 is `mixed`.

This phase converts that analysis into evidence, produces the two artefacts that do not exist at
all (journeys and wireframes), and does it before the Functional Specification, the Delivery
Backlog and the Event Tracking Plan are handed over at the end of Stage 1.

**That hold-back is the opportunity.** Those three documents are deliberately not yet delivered.
Correcting them now is free. Correcting them after delivery is Change Control.

---

## 2. Decisions taken

| Decision | Choice |
|---|---|
| What "validate" means | Hybrid. Desk-check every story; prove the risky ones on a running install |
| Output status | Internal first. It feeds the Stage 1 client walkthrough, it is not itself a client deliverable |
| Coverage | Risk-weighted, not uniform |
| Artefact form | Data-first. Documents are generated, never hand-written |
| Test environment | A dedicated Mizzey site via `wp corex make:site` |
| Shape | Validation-led. Evidence before narrative |
| Where the data lives | The `platform` repo, under version control |

**Risk-weighted means:** full depth on the five custom epics (E13, E15, E20, E22, E23: 44 stories,
211 points, 31 percent of the 670 point total), the 43 stories flagged `key`, and all 20 TDD
section 7 rows. Every other story gets a light pass: a verdict row at
`confidence: reasoned` whose only job is to confirm that `native` is actually true, or to escalate
the story into the risk-weighted set if it is not.

---

## 3. The dataset

`stories.json` is generated from the specification and **must never be hand-edited**: it is
overwritten on every regeneration. All new data lives in sidecar files keyed by story id.

Location: `platform/discovery/data/`.

### 3.1 `probes.json`

One row per empirical test.

| Field | Meaning |
|---|---|
| `id` | `P-NNN` |
| `question` | The single question the probe answers, stated so it can only come back yes or no |
| `stories` | Story ids the answer bears on |
| `gap_rows` | TDD section 7 row numbers, where applicable |
| `script` | Path to the executable probe, relative to `discovery/probes/` |
| `method` | `wp-cli`, `php`, or `playwright` |
| `expected` | What the Technical Design assumes today |
| `observed` | What actually happened. Written by the runner |
| `verdict` | `confirmed`, `refuted`, or `partial` |
| `env` | WordPress, WooCommerce and CoreX versions at run time |
| `run_at` | Date |
| `notes` | Free text |

`expected` is recorded **before** the probe runs. A probe that refutes the Technical Design is the
most valuable output this phase can produce, and that is only legible if the prior claim was
written down first.

### 3.2 `verdicts.json`

One row per story. This is the heart of the dataset.

| Field | Values |
|---|---|
| `story` | `US-NN-NN` |
| `claimed` | The epic-level leverage inherited from `stories.json` |
| `actual` | `native`, `extend`, `build`, `plugin` (the Technical Design's own classes) |
| `confidence` | `proved`, `reasoned`, `assumed` |
| `evidence` | Probe ids. **Required when `confidence` is `proved`** |
| `plugins` | Plugin ids from `plugins.json` |
| `gap_rows` | TDD section 7 rows |
| `points_flag` | `ok`, `under`, `over` |
| `risk` | One sentence, or empty |
| `open` | Questions this story raised that an agent could not answer |

`confidence` makes assumption visible. Today every story is effectively `assumed` and nothing
records that. `check.py` refuses `assumed` on any story in the risk-weighted set.

`points_flag` catches estimate drift early. Stage 4 is already at 68 points a week and Stage 5 at
92, against a 55 average, so a cluster of `under` flags in those stages is a scheduling problem
that has to surface now rather than in week nine.

### 3.3 `plugins.json`

| Field | Meaning |
|---|---|
| `id` | `PL-NN` |
| `need` | The capability required, and the story ids requiring it |
| `candidates` | Each with name, licence, annual cost, last update, active installs, maintenance risk |
| `decision` | The chosen candidate, or `build instead` |
| `evidence` | Probe ids supporting the decision |
| `cost_annual` | Figure, in the currency quoted |
| `owner` | Whose account the licence sits in |

The Technical Design section 11 already quotes the client an annual figure of about 107 USD for the
translation plugin, and names Google sign-in, a redirect manager, two-factor authentication and
product feeds as free-tier or free. **Those figures are already in the client's hands.** A decision
that raises them is a commercial conversation, not a technical one, and must be flagged, not
absorbed.

### 3.4 `journeys.json`

| Field | Meaning |
|---|---|
| `id` | `J-NN` |
| `name` | |
| `actor` | shopper, guest, admin, fulfilment, support |
| `language` | `ar`, `en`, or `both` |
| `steps` | Ordered. Each step names the story ids it exercises and the screen it needs |
| `gaps` | Cross-story failures the journey exposes that no single story shows |

`gaps` is the reason journeys are in this phase at all. A guest adding to a cart, signing in, losing
the cart to a Woo replace, checking out on cash on delivery and then requesting a return is five
epics and invisible in 205 separate rows.

---

## 4. Generators and the gate

Generators in `platform/discovery/bin/`, Python, matching the conventions already in
`platform/scripts/`.

| Script | Produces |
|---|---|
| `gen_epic_dossiers.py` | 27 markdown dossiers, one per epic |
| `gen_validation_report.py` | The consolidated validation report |
| `gen_plugin_register.py` | The plugin register with total annual cost |
| `gen_journey_docs.py` | One document per journey |
| `run_probes.py` | Executes probes, writes `observed`, `verdict` and `env` back |
| `check.py` | The gate |

Output goes to `platform/discovery/generated/`. **Nothing in `generated/` is ever hand-edited.**
This is the same rule as the rendered PDFs in `final docs/Client/Branded/`, and for the same
reason: a hand edit to generated output is a change that silently disappears on the next run.

`check.py` fails on:

- A story in the risk-weighted set with `confidence: assumed`
- `confidence: proved` with no probe in `evidence`
- `actual: build` that traces to no gap row and carries no explanation
- A plugin decision with no `cost_annual` or no `licence`
- A plugin decision that raises the total above the figure already quoted to the client, unless
  explicitly acknowledged
- A journey step naming a story id that does not exist
- Any reference to a story id absent from `stories.json`
- A story in `verdicts.json` with no Annex A trace in `stories.json`
- Any output file containing an emoji or an em dash

The last one is the house rule, and it applies here because these documents feed client-facing
work even though they are not themselves client-facing.

---

## 5. The probe protocol

A probe is an executable file, not a note. It runs headless, prints JSON on stdout, and is
re-runnable at any time.

```
discovery/probes/P-004-cart-merge-on-login.php
```

`run_probes.py` executes one or all, captures stdout, and writes `observed`, `verdict` and `env`
into `probes.json`. Recording the WordPress, WooCommerce and CoreX versions on every run means the
suite doubles as a regression check: when Woo updates, re-run it and find out what changed, rather
than discovering it during the build.

Method is `wp-cli` or `php` for behaviour, `playwright` for anything right-to-left or visual.
Playwright is already available as a skill in this environment.

### 5.1 The first probes

Drawn from the 20 Technical Design rows and the riskiest assumptions in the five custom epics.
Roughly 15 to 20 in total. These are the ones to write first, in this order.

| # | Probe | Why it is first |
|---|---|---|
| 1 | Cart merge on login | TDD row 1. The Technical Design calls it "the most delicate logic in the storefront". Does Woo replace, and does `woocommerce_cart_loaded_from_session` fire where the design assumes |
| 2 | Price snapshot, BR-005 | Recorded as native, and as the highest risk rule in the register. A claim carrying that much weight should not be untested |
| 3 | Coupon stacking and priority, BR-004 | The entire promotion resolver is built on Woo stacking freely with no priority |
| 4 | Idempotency and duplicate provider callback | PAY-05, AC-06, AC-07. The Technical Design calls it non-negotiable |
| 5 | `corex-kit-woo` seam | 204 lines, roadmap milestone M9, "Active now: nothing". Establish exactly what it gates before anything is planned around it |
| 6 | Translation plugin against Woo product data | This is the 107 USD per year already quoted to the client. Prove it before the build depends on it |
| 7 | Arabic search on the WordPress `LIKE` query | TDD row 9. Sets the floor the synonym list has to lift |
| 8 | CSV import with Arabic encoding | TDD row 6, the largest single item in the project |
| 9 | Stock decrement under concurrency | Claimed native. Expensive to be wrong about |
| 10 | Right-to-left through the CoreX M3 header, nav and footer | US-01-01, 13 points, and it touches every screen in the build |
| 11 | Free shipping by item count | BR-002. Woo has an amount condition and no count condition |
| 12 | Order status registration and carrier status map | TDD row 10. Prevents a returned parcel reading as delivered |
| 13 | Roles beyond Shop Manager | TDD row 15, and the negative tests matter more than the positive ones |
| 14 | Refund ceiling against the paid amount | Partial refunds against the gateway |
| 15 | Guest wishlist transfer on sign-in | TDD row 13, and it shares the cart-merge failure mode |

---

## 6. The agent contract

The requirement this section exists to meet: **an agent given a task must never need to come back
and ask a question.**

Five task types. Each has a template in `platform/discovery/tasks/` naming the exact files to read,
the exact file and rows to write, the schema, and the done-condition.

| Type | Reads | Writes | Must not |
|---|---|---|---|
| PROBE | one `probes.json` row | its probe script, and that row | touch `verdicts.json` |
| VERDICT | one epic's stories, and probe evidence | that epic's verdict rows | run probes, or edit `stories.json` |
| PLUGIN | one need, and probe evidence | one `plugins.json` row | decide without evidence |
| JOURNEY | validated stories | one `journeys.json` row | invent a step with no story id |
| WIREFRAME | one journey | HTML and CSS screens | apply visual identity |

Three rules make parallelism safe:

**One writer per file per task.** No two agents ever write the same row. Conflict is structurally
impossible rather than merely unlikely.

**A blocked agent writes an `open` row and continues.** It does not stop and ask. Questions
accumulate in the data and are reviewed in one batch at the next gate. This is the difference
between fifteen interruptions and one list.

**Every template names the files to read.** No agent goes exploring. This matters specifically
because `_archive/` contains superseded pricing drafts that will produce wrong figures if an
exploring agent finds them.

Every task template also states the two-lanes rule inline, because it is the rule most likely to be
broken by an agent trying to be helpful.

---

## 7. Sequence and gates

```
Step 0  Schema, generators, check.py, task templates, probe list   [blocking]
Step 1  wp corex make:site Mizzey                       [blocking, Mustafa]
Step 2  Run the probes                          [15 to 20 parallel agents]
        Gate A: check.py passes. Open batch reviewed. Refuted TDD rows listed
Step 3  Epic verdicts                                [27 parallel agents]
        Gate B: no risk-set story left at confidence "assumed"
Step 4  Plugin register                                        [1 agent]
        Gate B2: total annual cost against the figure already quoted
Step 5  Journeys                                      [parallel agents]
        Gate C: journey gaps become new probes. Loop back to step 2 for those
Step 6  Wireframes, low fidelity, no visual identity   [parallel agents]
        Gate D: feeds the Stage 1 client walkthrough
```

Step 1 is blocking and is Mustafa's, not an agent's: it needs `wp corex make:site`, a database, and
decisions about local hosts.

Gate C loops back deliberately. Journeys find what story lists cannot, and anything they find has
to be proved the same way everything else was.

Steps 2, 3, 5 and 6 fan out to parallel agents. Steps 0, 1 and 4 do not.

---

## 8. When validation says no

A defined disposition, so that nobody improvises under time pressure.

**The specification's acceptance criteria are wrong, but the requirement is fine.**
Correct the Functional Specification and regenerate. The specification has not been delivered, so
this costs nothing. This is the reason it was held back.

**A Feature Register requirement cannot be met as written.**
Change Control, MS-CHG-2026-014. Priced there. Never silently dropped and never silently absorbed.
Annex A is the sole definition of scope and this phase has no authority to change it.

**It is possible, but costs materially more than it was pointed.**
Re-point, and re-plan the sprints internally. Escalate only if it moves a contracted stage
boundary. Nine stories are already pulled forward into Stage 2 for exactly this reason and the two
contingency weeks attach to sprints 12 and 13.

**A probe reveals something desirable that is not in Annex A.**
It is lane two. It does not become a story. Record it and move on. The Gutenberg editor and the
external search engine are already sitting there by deliberate decision, recorded in
`final docs/Client/_internal/Post-Signature-Build-Notes.md`.

---

## 9. What this phase does not do

- It does not write production code.
- It does not apply visual identity. Wireframes are low fidelity and structural. The client's brand
  identity has not arrived, and the contracted design deliverable is working HTML and CSS with
  design tokens, not a design-tool source file.
- It does not change the Feature Register.
- It does not open board tickets. The board is generated from the specification, and a ticket for
  unvalidated work is the drift the setup exists to prevent.
- It does not produce client documents. Its output feeds the Stage 1 walkthrough; it is not sent.

---

## 10. Done

The phase is done when:

- `check.py` passes with no failures.
- Every story in the risk-weighted set has a verdict at `proved` or `reasoned`, none at `assumed`.
- Every one of the 20 Technical Design section 7 rows is `confirmed`, `refuted` or `partial`, with
  a probe behind it.
- The plugin register names a specific product for every need, with a licence and an annual figure,
  and the total is reconciled against what the client has already been quoted.
- Every journey has been walked and its gaps either probed or dispositioned under section 8.
- Every wireframe traces to a journey, and every journey step traces to a story id.
- The `open` list is empty, or every remaining item is assigned to Mustafa or the client with a
  named decision date.
- The corrections that section 8 sends back to the Functional Specification have been made, and the
  specification regenerated.
