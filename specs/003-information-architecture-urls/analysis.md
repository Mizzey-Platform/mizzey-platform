# Implementation analysis: cross-artifact consistency

Feature `003-information-architecture-urls`, PBI #242. Run 4 October 2026, after tasks and **before** any
implementation task.

Run mechanically, because the failure this step exists to catch is a criterion nobody noticed had no work behind
it, or a reference that means two different things.

## What was checked

| Check | Result |
|---|---|
| Every acceptance criterion is covered by at least one task | Pass. 16 of 16 |
| Every functional requirement cites a criterion that exists | Pass. FR-001 to FR-012 |
| The Register trace table is exactly the thirty authoritative ids | Pass |
| Every traced id is S1 in the register, and the scope set gives `mixed` | Pass. 28 P1, two P1-L, all S1 |
| No excluded row appears in the criteria section | Pass. IA-12, IA-13, IA-22, IA-34, IA-36, IA-23, IA-33 all absent |
| Every file a task names is in a path class this work type may touch | Pass. 5 paths: 2 site-code, 2 site-tests, 1 feature-spec |
| No browser matrix is invented | Pass, and stated explicitly in tasks |
| No new governance checker is planned | Pass, and the reason is stated in the plan |
| The design boundary is stated | Pass |
| **Criterion labels do not collide with register ids** | **1 problem found, fixed.** See below |

## The problem, and what it was

### Criterion labels collided with real register ids

The criteria were labelled `AC-1` to `AC-16`. The register's Section N holds contracted acceptance scenarios
**AC-01 to AC-25**, owned by E-ACC-1. From `AC-10` upward the labels are **textually identical to register ids
this PBI does not own**:

| Label | Collided with | Register scope |
|---|---|---|
| AC-10 to AC-13, AC-15, AC-16 | register rows AC-10 to AC-13, AC-15, AC-16 | P1 |
| AC-14 | register row AC-14 | P1-L |

Anyone grepping `AC-12` would find this document's breadcrumb criterion **and** register row AC-12, "All core
pages render correctly in both languages without layout break", a row in the same subject area, which makes the
confusion more likely rather than less.

**Why nothing else caught it.** `tools/scope_trace.py` checks the *criterion text* column for register ids absent
from the trace, and the labels live in the `#` column. The product-cost pilot used `AC-1` to `AC-7` and #241 used
`AC-1` to `AC-9`, both below the collision, so #242 is the first spec to cross it.

**Fixed** by relabelling to `AC-242-01` to `G-1`, 95 references across five artifacts, with the reason
recorded in the spec so the next feature does not reintroduce it. The merged specs for 001 and 002 are **not
touched**: their labels do not collide, and rewriting merged records to satisfy a convention they never breached
would be churn.

## What the analysis deliberately did not flag

- **Sixteen criteria for thirty rows.** Twenty-nine of the rows share one obligation, existence and reachability
  in both languages, so AC-242-01 to AC-242-03 carry them collectively rather than being repeated twenty-nine
  times. Nine criteria decompose NFR-03's eight words. That is a reasonable shape, and a spec with thirty
  near-identical criteria would be worse.
- **No row is blocked.** The collection model follows ADM-57 and its neighbours, and the tracking route follows ORD-05, SHIP-03 and ADR-0002, both verified against the register, so IA-04, IA-35 and IA-21 are implemented now. Four rows carry an open **content or presentation input**, named rather than invented.
- **Three code items for thirty rows** is small, and correct: the audit found five of NFR-03's eight obligations
  native and eight rows deliverable as endpoint configuration. A plan producing more code would be the thing
  worth flagging.
- **One code item's kind is undecided.** T-09 is configuration or code depending on T-01's measurement. Deferring
  that is the native-first rule working, not an incomplete plan.

## Two readings a reviewer should see

Both are in the checklist too, because they are judgements rather than measurements.

**The "information architecture" reading.** SRS §5, which all twenty-nine page rows cite, is a page inventory
with no URL content, so the page rows oblige existence and reachability, and #242 owns the runtime IA, URL and SEO **output** assigned to its registered rows while separately registered SEO and admin-control rows keep their own ownership. This is sourced from the SRS rather than inferred from the PBI's title, and it is the reading most
likely to be challenged: someone could argue the title "and URL structure" implies a broader URL deliverable. The
spec's position is that a title is not a register row.

**G-1, the duplicate-URL criterion.** NFR-03 says "Clean URLs" and does not say "no duplicate URLs". The
criterion is kept because a URL that is clean and duplicated fails the obligation in substance, and because the
endpoint finding showed exactly how duplicates would arise here. Surfaced so it can be disagreed with on the
record.

## Result

**Pass.** One inconsistency found and fixed, two readings surfaced for review, four decisions recorded rather than
invented, and no task scheduled against a path this work type may not touch. **Implementation is unblocked for
twenty-eight of the thirty rows**, and stops at the checkpoint by instruction rather than by obstacle.

## Repair, 5 October 2026

| # | Finding | Severity | Resolution |
|---|---|---|---|
| R-1 | t22 took "the archive address answers 200" as the term's archive, and t23 never fetched a term archive, so no scenario read the links of an Arabic archive | Evidence, and a defect behind it | t28 fetches each archive as a visitor in both languages and follows every address it points at. t22 requires the term on the page |
| R-2 | The brand taxonomy's address word was a translatable default | Defect, AC-242-09 and NFR-03's language links | Pinned to the documented word by `Brands`. `research.md` section 11 |
| R-3 | The collection archive answered 200 with nothing on it | Defect, AC-242-05 | Served with the platform's product archive template. `research.md` section 12. Found by the storefront guard, which is why it is in this repair and was not in the staging finding |
| R-4 | A note in the spec, the verification record and t23 said the structured-data generators do not fire | Evidence | False: it described the "coming soon" page. Corrected with a dated note; the criteria assert the mechanism and stand |
| R-5 | Serving the collection archive with the product archive template could be read as building a page | Scope, checked | It reuses the template the category and brand archives already use, through a documented filter, and steps aside for any template the theme supplies. No design, no content, no new template. SSC-06 and the design rows are untouched |
| R-6 | `Brands` overrides a WooCommerce default | Behaviour, checked | Only the default. A word saved in the permalink settings is used as saved |

Guards on the repair diff. Each skill was invoked in the session and its rules walked against the diff. None of
them exposes a command that runs.

| Guard | What it changed or confirmed |
|---|---|
| wp-guard | Confirmed: both hooks exist in the installed source, no output, no request data, no query. The filter values are checked before use because any plugin can pass anything through a filter |
| woo-guard | Confirmed: a hook and a template slug are used, and no template is copied into the plugin or the theme |
| clean-code-guard | Changed: the address word constant is private, since nothing outside the class reads it |
| test-guard | Confirmed: no mock; t28 cites the staging incident; each language and taxonomy is a loop over one body. A link is asserted by fetching what it leads to, not by its text |
| docs-guard | Each named function, hook, option and figure in the repair's records was checked against the installed source or the committed evidence |

