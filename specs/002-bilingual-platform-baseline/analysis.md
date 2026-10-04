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

## The one scope reading, now decided

This analysis surfaced AC-5 as the only criterion that was a reading rather than a restatement, and it was put to
Mustafa rather than kept on judgement.

**Decided 4 October 2026.** AC-5 is kept, and **classified as technical acceptance needed to deliver FIX-04
safely, not as a restatement of FIX-04.** The spec now says so in both the criteria table and a note beneath it.
The distinction is the whole point of the decision: FIX-04 does not mention translation identity, so AC-5 may not
be presented as its wording, and it may not be presented to the client as an additional deliverable either. It
adds no behaviour beyond what FIX-04 already obliges.

The need is measured rather than theoretical. A WPML duplicate shares its original's SKU, so one SKU addresses two
products; and A11 showed that a translation group can be corrupted so an Arabic record detaches from its English
source. Later bilingual slices need a deterministic English-to-Arabic record relationship, and that is what AC-5
accepts.

## Second pass, 4 October 2026, after review

External review found a cross-artifact inconsistency this analysis had missed: **`spec.md`'s Native coverage was
stale**. It still said the Arabic storefront was not reachable over HTTP, that `lang="ar"` was unresolved, and
that the missing `.htaccess` was an open runtime gap, while `research.md`, `plan.md` and `tasks.md` all recorded
the final served measurement. The first pass checked criteria coverage, requirement citations, register ids and
path classes, and **did not compare factual claims across artifacts**, which is exactly the class of error it
existed to catch.

The analysis now does, mechanically. Five claims are compared across all five artifacts and the run must prove
each is stated the same way everywhere:

| Claim | Required state |
|---|---|
| Is `/ar/` reachable | Served, 200 |
| Is `lang="ar"` verified | Verified on a served request; the in-process result is an artifact |
| Are AC-3, AC-4 and AC-6 native | Yes, tests rather than implementation |
| What is rewrite configuration responsible for | A deterministic baseline prerequisite, not a platform defect |
| AC-5's classification | Technical acceptance, explicitly not a restatement of FIX-04 |

## Result

**Pass.** Three inconsistencies found and fixed in total: a criterion with no task, a dangling file reference, and
a stale Native coverage section that contradicted three other artifacts. Three decisions recorded (AC-5's
classification, the NFR-14 matrix, S-1 and S-2). No task is scheduled against a path this work type may not touch.
One item cannot be closed by this feature: AC-9, which needs staging and is owned by #244.
