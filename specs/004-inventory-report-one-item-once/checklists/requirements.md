# Checklist: specification quality, before planning

Feature `004-inventory-report-one-item-once`, PBI #246. Run against `spec.md` on 5 October 2026, at the end of
the specify step. Research, plan and tasks do not exist yet, so the items that need them are left open rather
than ticked.

The point is not to agree with the spec. It is to try to break it.

## Scope discipline (M-1)

- [x] **The one cited id is an obligation row.** RPT-10, P1-L, S1, verified against
      `docs/scope/register-ids.json`.
- [x] **Scope and stage match the register exactly.** Verified by `tools/scope_trace.py`, 0 problems.
- [x] **One accepting owner.** FIX-04 and NFR-04 are context rows, owned by #241 (D-09), and no criterion traces
      them.
- [x] **No criterion names an id outside the trace.** The P1-E inventory rows are named only in the context
      table and the clarifications.
- [x] **Does any criterion quietly expand the row?** The two to challenge are **AC-246-09**, the dashboard
      counts, and **AC-246-10**, the older stock report screen. Both are `provisional` and put to Mustafa in C-1,
      with a default that builds neither.
- [x] **Is the P1-L limit respected?** Dead stock, adjustments and stock cover are listed as not built. Nothing in
      the criteria adds a report, a column or a filter: every criterion makes an existing surface correct.
- [x] **Is the wording of the row the Option B wording?** Yes: "standard low-stock and out-of-stock reporting, on
      the figures your ERP supplies", from register v1.5. The Option C wording, which listed dead stock and
      adjustments, is not used.

## Clarity and testability

- [x] **Every criterion is checkable by someone else.** AC-246-01 to AC-246-08 by the report's own endpoint from
      scripted fixtures, in three language contexts.
- [x] **Statuses use only the permitted vocabulary.** Eight `final`, two `provisional`, one `pending PRE-09`.
- [x] **The success criteria are measurable.** SC-001 names the expected line counts and totals.
- [ ] **Each functional requirement names its criterion.** To be closed in the plan.
- [ ] **AC-246-04 in a real admin request.** The audit switched the language in process. Whether a browser admin
      request sets the same context is a research task, and it decides how the criterion is tested.

## Honesty about evidence (M-7)

- [x] **The measured gaps are dated and versioned.** 5 October 2026, WooCommerce 11.1.0, WPML 4.9.7, WooCommerce
      Multilingual 5.5.7.
- [x] **The disagreement with P-020 is stated, not smoothed over.** C-4 keeps both measurements and says the
      cause is not established.
- [x] **One gap is by source reading only.** The older stock report screen was not exercised in an admin request,
      and the Native coverage table says so.
- [x] **Nothing is claimed for staging.** DOD-04 is named as outstanding in the delivery stage section.

## ERP boundary (M-3, D-10)

- [x] **No ERP mechanism is chosen.** FR-011, C-3.
- [x] **The ERP-dependent criterion is not final.** AC-246-11 is `pending PRE-09`.
- [x] **The feature changes no stock and no synchronisation.** FR-010.
