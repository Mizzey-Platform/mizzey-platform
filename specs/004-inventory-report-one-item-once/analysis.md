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
| A-3 | The scenario switches the session language in process and does not log in through a browser | Evidence | Stated in `research.md` R-4. The correction does not depend on how the session language is chosen. The rendered report in a browser is owed under DOD-04 and is not claimed. **This resolution was wrong, as staging showed on 5 October 2026: see A-9** |
| A-4 | P-020's September observation contradicts the current measurement | Evidence | `research.md` R-3. The cause is a hypothesis and is labelled as one. B11 is closed as measured on the scripted baseline |
| A-5 | t25 writes product rows with SQL, which the WooCommerce guard forbids for product data | Guard | Accepted for a test fixture only, with the reason in the file: the scenario measures the report's query, and the rows are removed on finish. No production code writes product data |
| A-6 | `PhysicalItems::representativeOnly()` interpolates table names into SQL | Guard | Identifiers cannot be placeholder values. The table argument is refused unless it is a plain identifier, and every value is a placeholder passed through `$wpdb->prepare()` |
| A-7 | The time measured at 5,000 items is for a developer machine | Evidence | Reported as a fact with no threshold. Correctness at that size is what t25 passes or fails on |
| A-8 | If the two records of one item disagree on stock, the line shows the representative's figure | Behaviour | As the spec's edge case states. The disagreement is a data-integrity matter (A12) and is not repaired here. Safeguard S-1 would report it and is not approved |

### Findings of the repair, 5 October 2026

| # | Finding | Severity | Resolution |
|---|---|---|---|
| A-9 | A-3 accepted an in-process scenario as covering the browser, on the argument that only the choice of session language differed. The kind of request differed too, and the multilingual plugin honours "all languages" only inside wp-admin and WP-CLI | Evidence, and a production defect behind it | t26 reads the report through the signed-in HTTP request, and compares the in-process dispatch with it in every context. The correction no longer uses the session language (`research.md` R-11) |
| A-10 | The summary under the table was not in the first specification and counted language records | Scope, checked | Inside RPT-10 by C-6: the same standard report on the same screen, and AC-246-06 is read there. It is not the dashboard, whose figures stay with ADM-10 and ADM-11. FR-013 |
| A-11 | The summary is now read live, where WooCommerce keeps its own figures for thirty days | Performance | Deliberate: a summary that lags behind its list is the fault being corrected. Measured in t25 at the sizing baseline, beside the cost of a request that reads no product |
| A-12 | The summary asks the report's controller for each list's total, which calls `get_items()` without that route's own permission check | Security, checked | The filter runs only inside the summary endpoint, whose own permission check has already passed and asks for the same capability. Only counts are returned |
| A-13 | t26's front-end probe reads the exporter's rows through a bound closure, as t24 does | Test | The exporter has no reading method short of writing a file. The probe is a test fixture in the disposable runtime, behind a per-run secret, removed on finish |
| A-14 | t26 creates a temporary administrator and sends that account's session cookies over HTTP | Test | The existing pattern of `_workflows.php`. The account is removed on finish, and no credential is written to a file or an evidence record |

## Guards

| Guard | Result |
|---|---|
| wp-guard | No output is rendered and no request data is read. Every value in SQL is a placeholder. Hooks are removed from nothing they did not add |
| woo-guard | No order is touched. Products are read through `wc_get_product()` by the native controller. WooCommerce's presence is checked before its class is used. No template override |
| test-guard | Each assertion names its criterion. Fixtures are created through the product API except where A-5 says otherwise, and are removed on finish |
| clean-code-guard | Two classes with one responsibility each: the resolution, and the report hook. No abstraction beyond the one FR-009 requires |
| docs-guard | The class comments state the measured fault, the versions, and what the code does not do |

Guards on the repair diff, 5 October 2026. Each skill was invoked and its rules walked against the diff. None of
them exposes a command that runs, so "executed" means the skill's own self-check was applied.

| Guard | What it changed or confirmed |
|---|---|
| wp-guard | Confirmed: no output, no request data, no new SQL, both hooks verified in the installed source. Changed: the summary now tolerates the error answer the controller's contract allows, instead of calling a method on it |
| woo-guard | Confirmed: no order, no write, no template, no cart or session access. Changed: the low-stock list is named by WooCommerce's own constant, not a copied string |
| clean-code-guard | Changed: a class check that could never be false inside WooCommerce's own filter was removed, with a redundant cast. The stored language and its restore hook are gone, so the class holds no state |
| test-guard | Confirmed: no mock; the regression cites the staging incident; contexts are a loop, not copies. Changed: the screen helpers moved into the shared workflows so t25 and t26 use one implementation |
| docs-guard | Each named function, hook, flag and figure in the repair's records was checked against the installed source or the committed evidence. Changed: four statements that a scheduled export always runs outside wp-admin, which is not so, now say it can |

## Constitution

No violation found. M-1: one row, and each criterion checked against its wording. M-3: nothing about the ERP is
chosen. M-4: the native report is kept and the gap is measured. M-7: the three states are reported separately in
`verification.md`.
