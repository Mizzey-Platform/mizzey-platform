# Research: the inventory report counts one physical item once

Feature `004-inventory-report-one-item-once`, PBI #246, RPT-10. Measured on the disposable runtime on 5 October
2026: WordPress 7.1.2, WooCommerce 11.1.0, WPML 4.9.7, WooCommerce Multilingual 5.5.7. A result here is evidence
for those versions only (M-8).

## R-1. What the standard stock report is, and how it queries

The current standard report is Analytics, Stock: `Automattic\WooCommerce\Admin\API\Reports\Stock\Controller`,
served at `wc-analytics/reports/stock`.

- `get_items()` registers four filters (`posts_where`, `posts_join`, `posts_groupby`, `posts_clauses`), runs one
  `WP_Query` over the post types `product` and `product_variation`, and removes the filters again.
- The stock filter and the ordering are added by those filters, against `wc_product_meta_lookup`. The total is the
  query's `found_posts`, and the page is the query's own `LIMIT`.
- **The export is the same method.** `ReportCSVExporter::prepare_data_to_export()` builds a request and calls
  `$this->controller->get_items( $request )` directly. It does not go through a REST dispatch, so a correction
  hooked to the REST layer would miss it. A correction inside the query covers both.

**Decision.** Correct the query, not the response. The filter, the ordering, the total and the page then all
operate on physical items, in SQL. Nothing is merged in PHP after the page is cut: P-019 measured that approach
as wrong for the products report, where the merge ran after ordering and gave wrong ranking and paging.

## R-2. The two faults, and their one root

Fixtures: one low item in both languages, one low item in Arabic only, one low item in English only, one
out-of-stock item in both languages. Three low and one out-of-stock physical items are owed.

| Session language | Low-stock list | Out-of-stock list |
|---|---|---|
| English | 2 lines: the Arabic-only item is missing | 1 line |
| Arabic | 2 lines: the English-only item is missing | 1 line |
| All languages | 4 lines, total 4: the bilingual item twice | 2 lines, total 2 |

**The root** is unchanged from P-020: each language record holds its own `wc_product_meta_lookup` row with the
full quantity, because `WCML\Synchronization\Component\Stock` copies stock onto every translation. A report that
lists product posts lists language records.

**The omission** comes from WPML's own query filter, which narrows a query on a translatable post type to the
session language. **The duplication** is what remains when that filter does not narrow. They are the same defect
seen in two language contexts, which is why one correction answers both.

## R-3. Why P-020 saw identical lists in September

P-020 (9 September 2026) recorded four lines in English and four in Arabic, "so no language scoping ran on this
query", and left the scoped fault as not ruled out (B11). The same method on 5 October gives scoped lists.

| | September | October |
|---|---|---|
| Method | `switch_lang()` then an internal dispatch of the endpoint | The same |
| Plugin versions | WooCommerce 11.1.0, WPML 4.9.7, WooCommerce Multilingual 5.5.7 | The same |
| Runtime configuration | Configured by hand during discovery, not recorded as a script | The scripted baseline, `baseline/setup.php` |

**What is established.** The only thing that differs is the runtime's configuration. The current baseline
completes WPML's setup and marks `product` and `product_variation` translatable, and on it
`is_translated_post_type( 'product' )` is true, which is the condition under which WPML narrows a query.

**What is not established.** The September database no longer exists, so its configuration cannot be read back.
The likeliest explanation is that the product post type was not yet marked translatable in September, in which
case WPML filters nothing. That is a hypothesis and is recorded as one.

**Why it does not hold the feature back.** The current baseline is scripted and reproducible, and the regression
scenario asserts the correct list in all three language contexts. Whichever configuration a runtime has, the
report is right: the correction widens the language scope itself rather than relying on what the session set.
B11 is therefore closed as **measured: the scoped view does omit an item**, on this baseline.

## R-4. Does a browser admin request differ from the in-process measurement?

> **Refuted on staging, 5 October 2026.** The answer below is wrong, and is kept as the record of what was
> believed when the feature was first verified. A browser request differs in a second respect, which decided the
> result: the kind of request it is. See R-11.

A browser request differs from the scenario in one respect only: how the session language is first chosen (an
admin cookie or a request parameter, instead of `switch_lang()`). Everything after that is the same code: the
same controller, the same `WP_Query`, the same filters.

The correction does not depend on that choice. It widens the language scope inside the report's query whatever
the session language was, and restores it once the SQL is built. So the three contexts the scenario covers,
English, Arabic and all languages, are the whole input, and no credential is needed to cover them. The rendered
report in a real browser is still owed as manual testing on staging (DOD-04).

## R-5. Identifying the report's query, and nothing else

The correction must touch one query and no other product query on the site.

| Candidate signal | Verdict |
|---|---|
| The REST route | Rejected: the export does not use a REST dispatch (R-1) |
| The `low_in_stock` and `stock_status` query variables | Rejected: the unfiltered report sets neither, and another query could set `stock_status` |
| The controller's own clause filter being registered | **Chosen.** `has_filter( 'posts_where', [ Controller::class, 'add_wp_query_filter' ] )` is true only while `get_items()` is querying, for the screen and the export alike, together with the two post types that method asks for |

The scenario proves the confinement from the other side: an ordinary product query in an English session is
still narrowed to English.

## R-6. Which record represents the item

One record per translation group is kept: the source-language record, the one the translations were made from
(`source_language_code IS NULL` in `icl_translations`). The admin is English only, so this is the record an
operator expects to open.

Two cases need a rule rather than an assumption.

| Case | Rule | Why |
|---|---|---|
| The source-language record is not listable (a draft, or in the bin) and a translation is published | The listable record with the lowest id represents the item | Otherwise a sellable item disappears from a restocking list (workstream item D8) |
| A record belongs to no translation group | It represents itself | A product created while the multilingual plugin was inactive must not vanish |

"Listable" means published or private, the statuses a staff report can show. The stock figure on the line is the
representative's own, so nothing is summed.

## R-7. Language scope: switch it, do not rewrite it

> **Replaced on 5 October 2026.** Switching the session language to all languages works only inside wp-admin and
> WP-CLI. The text below is kept as the record of the first design. The mechanism in use is in R-11.

WPML adds its language condition through its own query filters. Removing that condition from the built SQL by
pattern would bind this code to the plugin's internal wording. The documented interface is the language itself:
`wpml_current_language` to read it, `wpml_switch_language` to set it. The correction sets it to all languages
when the report's query is parsed, which is before WPML's filters run, and restores it at `posts_request`, once
the SQL exists and the scope has done its work. The scenario asserts the session language is unchanged after
every report.

## R-8. Cost at the sizing baseline

D-10 records 5,000 products as the starting size (OD-15, an owner working decision). Scenario t25 seeds 5,000
physical items as 10,000 product records and reads the low-stock report. The figures are those of the committed
run, `evidence/final-suite.txt`.

| Session language | Total reported | First page | Time, this machine |
|---|---|---|---|
| English | 5,000 | 25 distinct source-language records | 655 ms, the first query after seeding |
| Arabic | 5,000 | 25 distinct source-language records | 405 ms |
| All languages | 5,000 | 25 distinct source-language records | 20 ms |

The last page, 200, holds 25 lines: the boundary is counted in physical items.

The plan: the representative condition is materialised once per query, with `icl_translations` read through its
`trid` index for the sibling lookup. The full read of `wc_product_meta_lookup` in the plan is WooCommerce's own
low-stock filter, which no index serves, and is the same with or without this feature. The times are evidence
for a developer machine and are not a production figure; the growth path, if a catalogue many times this size
needs one, is a stored representative flag, which is a later decision and is not built.

## R-9. Findings outside this feature, kept for their owners

Neither is fixed here (decision D-246-1). Both are recorded so the measurement is not repeated.

**The dashboard low-stock and out-of-stock counts double count. Owner: the future PBI for ADM-10 and ADM-11.**
For the R-2 fixtures the counts are 4 low and 2 out of stock, where 3 and 1 are owed, in every language context.
WooCommerce Multilingual scopes those counts through the filters
`woocommerce_status_widget_low_in_stock_count_query` and `woocommerce_status_widget_out_of_stock_count_query`.
WooCommerce 11.1.0 applies neither: the dashboard widget applies only the `_count_pre_query` filters and then
runs its own count over `wc_product_meta_lookup`. So the plugin's scoping is dead on this version and every
language record is counted. `PhysicalItems::representativeOnly()` is the shared resolution that PBI can reuse
through the `_count_pre_query` filters.

**The older stock report screen (Reports, Stock) is language-scoped. Owner: the Stage 1 ownership pass.**
The screen is still reachable from the menu on this runtime. WooCommerce Multilingual narrows its two queries to
the session language through `woocommerce_report_low_in_stock_query_from` and
`woocommerce_report_out_of_stock_query_from`, in `WCML_Reports::filter_reports_stock_query()`, which runs in the
admin only. By reading the source, an English session would omit an Arabic-only item, and an all-languages
session would list a translated item twice. This was not exercised in an admin request. No register row is
known to own this screen; whether it is owned, or simply not exposed or recommended, is for the ownership pass.

## R-10. What was considered and not done

| Alternative | Why not |
|---|---|
| Stop copying stock to translations, or store stock once | It changes stock storage and synchronisation, which the owner ruled out, and B1 measured the current synchronisation as working |
| Merge duplicate lines in PHP after the query | Wrong totals and page boundaries, as P-019 measured |
| Force the session to one language | It trades the duplicate for the omission |
| Replace the report with a custom one | RPT-10 is P1-L, "standard" reporting. The native report is correct once its query is |

## R-11. Why the screen was wrong while the scenario passed. Added by the repair of 5 October 2026

**What staging showed.** In the signed-in browser the English context left out the Arabic-only item, and the
Arabic context listed only that item, while t24 passed on the same runtime the same day
(`docs/2026-10-05-staging-verification.md`).

**Reproduced first.** Scenario t26 opens the Analytics Stock screen over HTTP as a signed-in administrator, in
each admin language context, reads the REST address and the nonce the page hands the browser, and sends the
screen's own requests there. On the code of `main` it failed in the same way as staging:
`evidence/repair/t26-before.txt`, where t24 passes beside it.

**The cause, traced and not inferred.** `evidence/repair/root-cause-trace.txt` is the trace of one such request.
The correction asked the multilingual plugin for the session language "all", and the language read back
straight after the switch was unchanged.

| Fact | Where |
|---|---|
| The plugin accepts "all" as a language only inside wp-admin or WP-CLI | `SitePress::is_valid_language()`, WPML 4.9.7 |
| A refused switch is silent: `switch_lang()` keeps the old language and reports nothing | `SitePress::set_this_lang()` |
| The report's request is a REST request: `is_admin()` is false | The trace |
| In the Arabic admin context the screen sends that request under the Arabic address prefix, so its language is Arabic | The page's own `createRootURLMiddleware` address |
| t24 dispatches the endpoint inside a WP-CLI process, one of the two contexts where "all" is accepted | t24 |

So the representative condition, which was applied, ran on a list still narrowed to one language. A translated
item's representative is its English record, which the Arabic context had already excluded, and the Arabic-only
item's record was excluded by the English one.

**Classification.** Production code, and a test abstraction that hid it. Not a fixture defect, not a staging
configuration and not a stale cache: the dev runtime reproduces it on a clean seed.

**The mechanism now.** The multilingual plugin has a switch on the query itself: when a query's arguments carry
`suppress_wpml_where_and_join_filter`, its query filter adds neither its join nor its language condition
(`WPML_Query_Filter::is_join_filter_active()`). `PhysicalItems::everyLanguage()` sets it on the report's query
and on nothing else. It does not depend on the kind of request, it changes no session state, and there is
nothing to restore afterwards.

| Alternative | Why not |
|---|---|
| Keep the switch to "all" and force the plugin to accept it | It would mean pretending to be wp-admin, or rewriting the plugin's validation, for one query |
| Strip the language condition from the built SQL | It binds this code to the plugin's internal wording, the objection R-7 already made |
| Send the screen's request without the language prefix | It would correct the screen and leave the export and any other caller wrong |
| Deduplicate in PHP after the page is cut | Wrong totals and page boundaries, as P-019 measured |

## R-12. The summary under the table. Added by the repair of 5 October 2026

The screen shows five figures under the table: products, out of stock, low stock, on backorder, in stock. They
come from a second endpoint, `wc-analytics/reports/stock/stats`, whose data store counts with statements of its
own, written directly against the tables, and keeps each result for thirty days. No query filter reaches those
statements, so they count language records in every context: staging read "7 Low stock" beside a list of four.

WooCommerce passes the finished figures through one filter, `woocommerce_analytics_stock_stats_query`, and that
endpoint is its only caller in WooCommerce 11.1.0. `StockReport::summaryOfPhysicalItems()` replaces each figure
there with the total of the list it summarises, asked of the report's own controller. The summary and the lists
therefore cannot disagree, and no second copy of the low-stock rule exists.

| Alternative | Why not |
|---|---|
| Copy the data store's statements and add the representative condition | Two copies of the low-stock rule, which would drift from the list's |
| Replace the data store | Its counting methods are private; the replacement would be the same copy |
| Leave the summary | It is the same report on the same screen, and AC-246-06 is read there by the operator |

The figures are read live and are not cached: a summary that lags behind the list beside it is the fault being
corrected. The cost is in R-13.

## R-13. Cost at the sizing baseline, after the repair

t25 at 5,000 physical items stored as 10,000 records, on the developer's machine. The figures are those of the
committed run, `evidence/repair/final-suite.txt`, and are not a production figure.

The total is 5,000 and the first page is 25 distinct source-language records, in process in all three session
languages and over HTTP in the Arabic admin context. The last page, 200, is full. The scenario records the time
of a signed-in REST request that reads no product beside the two it measures, so that the cost of the report can
be read apart from the cost of any request on this machine.

Filtering, ordering, the total and the paging all run in the one SQL statement, on physical items, before the
page is cut. Nothing is deduplicated in PHP.
