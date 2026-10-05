# Analysis: consistency of the spec, the plan, the tasks and the code

Feature `004-inventory-report-one-item-once`, PBI #246, RPT-10. Run on 5 October 2026, after implementation and
before the pull request. The point is to find what disagrees, not to confirm.

## Coverage

| Criterion | Requirement | Task | Test | State |
|---|---|---|---|---|
| AC-246-01 | FR-001 | T006 | t24, low list per context | Covered |
| AC-246-02 | FR-001 | T006 | t24, out-of-stock list per context | Covered |
| AC-246-03 | FR-002 | T006 | t24, the Arabic-only, English-only and draft-original fixtures | Covered |
| AC-246-04 | FR-003 | T007 | t24, three contexts identical | Covered |
| AC-246-05 | FR-004 | T006 | t24, the pair shows 2 | Covered |
| AC-246-06 | FR-005 | T007, T009 | t24 paging walk; t25 at 5,000 items | Covered |
| AC-246-07 | FR-001 | T006 | t24, the translated variation | Covered |
| AC-246-08 | FR-006 | T007 | t24, WooCommerce's own exporter | Covered |
| AC-246-11 | FR-011 | T016 | None possible before PRE-09 | **Open, `pending PRE-09`** |
| (none) | FR-009 | T006 | The resolution is one class, used by the report | Covered by structure |
| (none) | FR-010 | T010 | t24, stored stock and an ordinary query; the rest of the suite | Covered |

No criterion lacks a task. No task serves a criterion that is not in the spec. AC-246-09, AC-246-10, FR-007 and
FR-008 are removed by D-246-1 and have no task and no code.

## Findings

| # | Finding | Severity | Resolution |
|---|---|---|---|
| A-1 | The spec was first drafted with two `provisional` criteria for the dashboard counts and the older screen | Scope | Removed by decision D-246-1. The register owns the dashboard figures under ADM-10 and ADM-11. Both measurements are kept in `research.md` R-9 |
| A-2 | The correction applies to the whole stock report, including its unfiltered and in-stock views, while the criteria name low and out of stock | Scope, checked | Kept. It is the same query on the same screen; correcting two of its filters and leaving the others listing language records would make one report disagree with itself. It adds no capability, and t24 asserts the unfiltered view |
| A-3 | The scenario switches the session language in process and does not log in through a browser | Evidence | Stated in `research.md` R-4. The correction does not depend on how the session language is chosen. The rendered report in a browser is owed under DOD-04 and is not claimed |
| A-4 | P-020's September observation contradicts the current measurement | Evidence | `research.md` R-3. The cause is a hypothesis and is labelled as one. B11 is closed as measured on the scripted baseline |
| A-5 | t25 writes product rows with SQL, which the WooCommerce guard forbids for product data | Guard | Accepted for a test fixture only, with the reason in the file: the scenario measures the report's query, and the rows are removed on finish. No production code writes product data |
| A-6 | `PhysicalItems::representativeOnly()` interpolates table names into SQL | Guard | Identifiers cannot be placeholder values. The table argument is refused unless it is a plain identifier, and every value is a placeholder passed through `$wpdb->prepare()` |
| A-7 | The time measured at 5,000 items is for a developer machine | Evidence | Reported as a fact with no threshold. Correctness at that size is what t25 passes or fails on |
| A-8 | If the two records of one item disagree on stock, the line shows the representative's figure | Behaviour | As the spec's edge case states. The disagreement is a data-integrity matter (A12) and is not repaired here. Safeguard S-1 would report it and is not approved |

## Guards

| Guard | Result |
|---|---|
| wp-guard | No output is rendered and no request data is read. Every value in SQL is a placeholder. Hooks are removed from nothing they did not add |
| woo-guard | No order is touched. Products are read through `wc_get_product()` by the native controller. WooCommerce's presence is checked before its class is used. No template override |
| test-guard | Each assertion names its criterion. Fixtures are created through the product API except where A-5 says otherwise, and are removed on finish |
| clean-code-guard | Two classes with one responsibility each: the resolution, and the report hook. No abstraction beyond the one FR-009 requires |
| docs-guard | The class comments state the measured fault, the versions, and what the code does not do |

## Constitution

No violation found. M-1: one row, and each criterion checked against its wording. M-3: nothing about the ERP is
chosen. M-4: the native report is kept and the gap is measured. M-7: the three states are reported separately in
`verification.md`.
