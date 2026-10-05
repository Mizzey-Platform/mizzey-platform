# Checklist: requirements quality, before tasks are executed

Feature `004-inventory-report-one-item-once`, PBI #246. First run against `spec.md` at the end of the specify
step, then again against `spec.md`, `research.md`, `plan.md` and `tasks.md` on 5 October 2026, after decision
D-246-1.

The point is not to agree with the spec. It is to try to break it.

## Scope discipline (M-1)

- [x] **The one cited id is an obligation row.** RPT-10, P1-L, S1, verified against
      `docs/scope/register-ids.json`.
- [x] **Scope and stage match the register exactly.** Verified by `tools/scope_trace.py`, 0 problems.
- [x] **One accepting owner.** FIX-04 and NFR-04 are context rows, owned by #241 (D-09), and no criterion traces
      them.
- [x] **No criterion names an id outside the trace.** The P1-E inventory rows and the two dashboard rows are
      named only in the context table, the clarifications and the future items.
- [x] **Does any criterion quietly expand the row?** The two that did, the dashboard counts and the older stock
      report screen, are **removed by D-246-1**. The register owns the dashboard figures separately, under ADM-10
      and ADM-11, and an obsolete screen being reachable does not create scope.
- [x] **Does the code quietly expand the row?** The correction reaches the report's unfiltered and in-stock
      views as well as the two the criteria name. Challenged in `analysis.md` A-2 and kept: one query, one screen,
      no added capability.
- [x] **Is the P1-L limit respected?** Dead stock, adjustments and stock cover are listed as not built. No
      report, column or filter is added.
- [x] **Is the wording of the row the Option B wording?** Yes: "standard low-stock and out-of-stock reporting, on
      the figures your ERP supplies", from register v1.5.

## Clarity and testability

- [x] **Every criterion is checkable by someone else.** AC-246-01 to AC-246-08 by the report's own endpoint and
      WooCommerce's own exporter, from scripted fixtures, in three language contexts.
- [x] **Statuses use only the permitted vocabulary.** Eight `final`, one `pending PRE-09`.
- [x] **The success criteria are measurable.** SC-001 to SC-004 each name what is asserted.
- [x] **Each functional requirement names its criterion.** The table in `analysis.md`.
- [x] **AC-246-04 in a real admin request.** Answered in `research.md` R-4: a browser request differs only in how
      the session language is first chosen, and the correction does not depend on it. The rendered screen is owed
      under DOD-04 and is not claimed.

## Honesty about evidence (M-7)

- [x] **The measured gaps are dated and versioned.** 5 October 2026, WooCommerce 11.1.0, WPML 4.9.7, WooCommerce
      Multilingual 5.5.7.
- [x] **The disagreement with P-020 is stated, not smoothed over.** `research.md` R-3 separates what is
      established from the hypothesis.
- [x] **One finding is by source reading only.** The older stock report screen was not exercised in an admin
      request, and `research.md` R-9 says so.
- [x] **The measured time is not presented as a production figure.** `research.md` R-8.
- [x] **Nothing is claimed for staging.** DOD-04 is outstanding and is a task left open, T014.

## ERP boundary (M-3, D-10)

- [x] **No ERP mechanism is chosen.** FR-011, C-3.
- [x] **The ERP-dependent criterion is not final.** AC-246-11 is `pending PRE-09`.
- [x] **The feature changes no stock and no synchronisation.** FR-010, asserted by t24.

## Findings kept for other owners

- [x] **The dashboard double count is not lost.** `research.md` R-9, addressed to the owner of ADM-10 and ADM-11.
- [x] **The older screen's behaviour is not lost.** `research.md` R-9, addressed to the Stage 1 ownership pass.
