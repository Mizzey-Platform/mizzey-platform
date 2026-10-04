# Implementation analysis: cross-artifact consistency

Feature `002-bilingual-platform-baseline`, PBI #241. Run 4 October 2026, after tasks and **before** any
implementation task.

Run mechanically rather than by eye, because the failure this step exists to catch is a criterion that nobody
noticed had no work behind it.

## What was checked

| Check | Result |
|---|---|
| Every acceptance criterion is covered by at least one task | **2 problems found, both fixed.** See below |
| Every functional requirement cites a criterion that exists | Pass. FR-001 to FR-009, all nine citing real criteria |
| No register id outside the Register trace appears in the criteria section | Pass. Exactly the five: FIX-04, FIX-04a, NFR-04, NFR-04a, NFR-14 |
| Every traced id is P1 and S1 in the register | Pass, read from `docs/scope/register-ids.json` |
| Every file a task names falls in a path class this work type may touch | Pass, 12 paths: 7 site-code, 2 site-tests, 2 test-infra, 1 feature-spec |
| No artifact references a file that does not exist and no task creates | **1 problem found, fixed** |
| All five guard skills are named in the Guard Gate | Pass, including `woo-guard` as explicitly having nothing to review |

## The two problems, and what they were

### 1. AC-7 had no task covering it

Nine criteria in the spec, eight named in tasks. **AC-7 was the gap**: the criterion that the storefront contains
no hard-coded user-facing text.

The work existed. T-03, T-04 and T-05 build the text domain mechanism and T-06 builds the check. None of them
cited AC-7, because the plan had treated the check as FR-007's evidence and never joined it back to the criterion
it serves. A reader auditing coverage would have found a criterion with no owner.

**Fixed** by naming the criterion where the work is: T-03 now cites AC-7 and AC-8 as the substance, since a string
cannot resolve to Arabic without a loaded domain, and T-06 cites AC-7 as its guard rather than its substance. The
distinction is kept because it is real: the mechanism satisfies the criterion and the check keeps it satisfied.

### 2. A dangling reference to a quickstart that does not exist

T-05 said to record the `.pot` regeneration command "in the spec's quickstart". There is no quickstart in this
feature, the plan's Structure Decision does not list one, and no task created one. The command would have been
written nowhere.

**Fixed** by recording the command inline in T-05. A quickstart was not invented to hold one line.

## What the analysis deliberately did not flag

- **The production code is four tasks out of eleven, and two of those are a `.pot` file and a two-line
  bootstrap.** That is small for a feature carrying five P1 rows, and it is correct: measurement showed three of
  the nine criteria are already met natively, with no code. A plan that produced more code would be the thing
  worth flagging.
- **AC-9 is covered by a task that cannot complete it.** T-08 writes the browser matrix with every row
  unexercised. That is the honest state, not a coverage gap, and the task says so.
- **T-02 creates page records but is not production code.** It is platform configuration under clarification
  C-2, and it lands in the baseline fixture. A reviewer may disagree with that classification; the checklist
  records it so the disagreement is on the record.

## One scope reading a reviewer should see

The checklist flags it and so does this analysis, because it is the only criterion that is a reading rather than
a restatement.

**AC-5** requires a valid translation relationship with the English record as source. FIX-04 reads "Multilingual
storefront: English and Arabic with full LTR/RTL support" and says nothing about translation identity.

The argument for keeping it: a multilingual storefront that cannot reliably pair a record with its counterpart is
not a multilingual storefront, and the product-cost pilot measured two distinct ways that pairing fails in this
exact stack. The argument against: the row does not say it, and M-1 says a valid id is not a licence.

It is kept, and flagged, so that it can be disagreed with on the record rather than discovered later.

## Result

**Pass.** Two inconsistencies found and fixed, one scope reading surfaced for review, and no task scheduled
against a path this work type may not touch. Implementation is unblocked, and stops at the checkpoint by
instruction rather than by obstacle.
