# Verification record: the inventory report counts one physical item once

Feature `004-inventory-report-one-item-once`, PBI #246. One register row: RPT-10, P1-L, S1.

**Three states, reported separately, per constitution M-7:**

| State | Where this stands |
|---|---|
| **Workflow complete** | Yes. Spec, clarification, research, plan, checklist, tasks, analysis, implementation, verification |
| **Technically verified** | **AC-246-01 to AC-246-08 on the disposable runtime, through the signed-in HTTP request the screen makes**, after the repair of 5 October 2026. The first verification, in process only, was disproved on staging the same day. **Re-run on staging after the repair, on a staging reset and reseeded from `main` at `25c391e`: 7 of 7 checks without a finding in the signed-in browser, and the same lists over signed-in HTTP.** The board status returned to Verified on that result |
| **Contractually accepted** | **No criterion.** Acceptance happens only through the Acceptance and UAT Plan MS-UAT-2026-027 |

**Closure still owes three things**, which this record does not weaken:

| Item | Wording | State |
|---|---|---|
| **AC-246-11** | "The figures the report shows are the figures the ERP supplies" | **Open, `pending PRE-09`.** The ERP Integration Specification is not approved. Nothing here chooses where those figures are stored |
| **DOD-04** | "Tests written and executed per system layer, **plus manual testing on staging**" | **Run on staging on 5 October 2026 as a scripted pass in a real browser, and it found three faults.** The report is right in the English admin context and wrong outside it. **AC-246-03 and AC-246-04 do not hold in the signed-in browser session**, although the scenario passes. See "Staging pass" below |
| DOD-09 | "Client approval of acceptance criteria before a feature moves to production" | Outstanding, a client gate rather than an engineering one |

**No staging result is claimed for the repaired code.** The staging pass recorded below was run on the code
before the repair, and is the reason verification was reopened. The rendered report has not been looked at by a
person on a staging environment, and nothing in this record says it has.

**How to read this record.** Everything from "Criteria" to "At the sizing baseline" is the first verification,
of 5 October 2026 in the morning, kept as written: it is historical evidence of what the in-process scenario
measured, and its claim for AC-246-03 and AC-246-04 did not hold in use. "Staging pass" is what disproved it.
"Repair" is where verification stands now.

Versions this is evidence for, and no others (M-8): WordPress 7.1.2, WooCommerce 11.1.0, WPML 4.9.7, WooCommerce
Multilingual 5.5.7, PHP 8.3, MySQL 8.3.0.

## Scope, as decided

Decision D-246-1 (Mustafa, 5 October 2026): RPT-10 is satisfied on the current standard stock report, the
Analytics Stock report, and on nothing else. The dashboard's low-stock and out-of-stock figures are ADM-10 and
ADM-11, with their own future owner; the older stock report screen has no row. Neither is changed by this
feature, and both measurements are kept in `research.md` R-9.

## Criteria

Fixtures of t24, six physical items: a simple product in both languages (low), one in Arabic only (low), one in
English only (low), one low variation of a translated variable product, a translated product whose English
original is a draft (low), and a simple product in both languages (out of stock). Five low physical items and one
out-of-stock physical item are owed, in every language context.

| # | Criterion | Verified by | Result |
|---|---|---|---|
| AC-246-01 | A bilingual item is one line in the low-stock report | t24, in English, Arabic and all-languages sessions | **PASS** |
| AC-246-02 | A bilingual item is one line in the out-of-stock report | t24, the same three sessions | **PASS** |
| AC-246-03 | No item is left out because of its record's language | t24: the Arabic-only item in an English session, the English-only item in an Arabic session, and the item whose original is a draft | **PASS** |
| AC-246-04 | The same items in every language context | t24: the three sessions return identical lists and identical totals | **PASS** |
| AC-246-05 | One stock figure, never a sum | t24: the pair shows 2, where each of its two records holds 2 | **PASS** |
| AC-246-06 | The total and the paging count physical items | t24: total equals lines, and a two-per-page walk yields five distinct lines over three pages. t25: 5,000 items give a total of 5,000 and a full last page | **PASS** |
| AC-246-07 | A translated variation is one line | t24: the low variation of a translated variable product | **PASS** |
| AC-246-08 | The export is the same list | t24: WooCommerce's own exporter in an English and an Arabic session holds each item once, with the screen's total | **PASS** |
| AC-246-11 | The figures are the ERP's | Not testable before PRE-09 | **OPEN, pending PRE-09** |

Also asserted, and not criteria: four sort orders keep one line per item and their order; the report's unfiltered
view follows the same rule; reading the report changes no stored stock value on any record; an ordinary product
query in an English session is still narrowed to English; the session language is unchanged after every report.

## Before and after

`evidence/t24-before.txt` is t24 run on a clean baseline with the correction unhooked: 17 assertions fail and 19
pass. It reproduces both faults and shows they reach the export.

| Session | Low-stock list before | Low-stock list after |
|---|---|---|
| English | 3 lines: the Arabic-only item and the draft-original item missing | 5 lines |
| Arabic | 4 lines: the English-only item missing, and the lines are the Arabic records | 5 lines |
| All languages | 7 lines for 5 items | 5 lines |

## The suite

**One run, on a genuinely clean baseline, serially: 21 scenarios, 0 failed.** Sixteen contract scenarios pass and
five are fact-finding scenarios that give no verdict: t09 stays `FACT (pending CX-01)` as its own spec wrote it,
and t17 to t20 stay probes. A line reading `0 failed` means the harness held and the contract scenarios passed,
not that every measured behaviour in a fact-finding scenario is correct.

Two new scenarios: `t24-stock-report-physical-items.php` (AC-246-01 to AC-246-08) and
`t25-stock-report-volume.php` (AC-246-06 at the sizing baseline). The verdicts of t02 to t23 are unchanged from
the run recorded for #242, which is the evidence that the feature changes no stock and no synchronisation.

| File | What it is |
|---|---|
| `evidence/clean-baseline.txt` | The reset preceding the run, its configuration summary, exit code, and what the runtime held afterwards |
| `evidence/final-suite.txt` | The complete suite output, every scenario's notes |
| `evidence/final-suite.json` | The same verdicts, machine readable |
| `evidence/t24-before.txt` | t24 with the correction unhooked |

To repeat it, with the database client on the path:

```bash
MIZZEY_CONFIRM_RESET=yes sh mizzey-site/tests/integration/baseline/reset-runtime.sh ../app/wp
python mizzey-site/tests/integration/run.py --wp ../app/wp
```

## At the sizing baseline

t25, 5,000 physical items stored as 10,000 product records (OD-15, an owner working decision, not client
confirmed). The total is 5,000 in all three language contexts, the first page is 25 distinct source-language
records, and the last page, 200, is full. The low-stock read took 655 ms in an English session, 405 ms in an
Arabic session and 20 ms in an all-languages session, on a developer machine. **Those times are facts about this
machine and are not a production figure.** The scenario passes or fails on correctness only.

## What is not verified

- **The rendered report in a browser**, by a person. DOD-04, on staging. A scripted pass in a real browser has
  now been run, and its findings are below; a person has still not looked.

## Staging pass, 5 October 2026

Run on the staging environment of #244, on the code of `main`, in the installed Chrome, signed in as the invented
staging administrator. The seed holds four physical items low on stock and four out of stock; one low item
exists in English only and one out-of-stock item in Arabic only. The record holds the request the screen itself
made and the answer it was given.

| Admin language context | Low stock, expected 4 | Out of stock, expected 4 |
|---|---|---|
| English | 4 lines, each item once | **3 lines**: the Arabic-only item is missing |
| Arabic | **0 lines** | **1 line**: only the Arabic-only item |
| All languages | 4 lines, by the same request as English | **3 lines**: the Arabic-only item is missing |

| Finding | Criterion |
|---|---|
| **F-246-1.** An item that exists only in Arabic is left out in the English and all-languages contexts | AC-246-03 |
| **F-246-2.** The list differs by admin language context: in Arabic it holds none of the items that exist in both languages | AC-246-04 |
| **F-246-3.** The summary under the table reads "7 Low stock" and "7 Out of stock" beside lists of 4 and 3 lines: it counts language records | AC-246-06 as a reader meets it. The list's own total is right; the summary is a second figure the scenario does not read |

What held: in the English context each physical item is one line (AC-246-01, AC-246-02), and the downloaded
export matches the screen (AC-246-08).

**The scenario and the screen disagree.** `t24` was run against the staging runtime the same day and passed, 0
failed. It calls the report inside one process. The screen calls it over HTTP, signed in, under the address
prefix of the admin's language, and is given a different list. **AC-246-03 and AC-246-04 are therefore not
verified for the way the report is used.** The feature needs a correction and a scenario that goes through the
real signed-in request. The cause is not diagnosed here. Full record: `docs/2026-10-05-staging-verification.md`;
evidence: `specs/005-local-staging-environment/evidence/`. **Diagnosed and corrected the same day: see "Repair".**

- **AC-246-11**, the ERP's figures. `pending PRE-09`.
- **The older stock report screen** in an admin request. Out of scope by D-246-1, and recorded as a finding by
  source reading only.
- **Why P-020 saw unscoped lists in September.** `research.md` R-3 gives what is established and labels the rest
  a hypothesis.
- **A catalogue many times the baseline.** Measured at 5,000 items and no further.

## Repair, 5 October 2026

**Why.** Staging disproved AC-246-03 and AC-246-04 in the signed-in browser session, and showed a summary that
counted language records. The board status went from Verified to In progress before any code changed.

**The cause.** The correction widened the language scope by switching the session language to "all languages".
The multilingual plugin accepts that value only inside wp-admin and WP-CLI. The screen reads the report through
a REST request, which is neither, so the switch did nothing there, silently, and the representative condition
ran on a list still narrowed to one language. t24 dispatches the endpoint inside a WP-CLI process, one of the
two contexts where the switch works. Traced, not inferred: `evidence/repair/root-cause-trace.txt`, `research.md`
R-11.

**The correction.** The report's query now carries the multilingual plugin's own per-query switch, which lifts
its language filter from that one query in any kind of request. No session language is touched. The summary
under the table takes each figure from the total of the list it summarises (`research.md` R-12).

**The regression, written first.** `t26-stock-report-real-request.php` opens the Analytics Stock screen over HTTP
as a signed-in administrator in the English, Arabic and all-languages admin contexts, reads the REST address and
nonce the page hands the browser, and sends the screen's requests there. In the Arabic context that address is
under the Arabic prefix, because the page says so.

| | t24, in process | t26, the screen's request |
|---|---|---|
| On the code of `main` before the repair (`evidence/repair/t26-before.txt`) | PASS | **FAIL: 23 assertions fail, 19 pass** |
| After the repair (`evidence/repair/final-suite.txt`) | PASS | **PASS: 42 assertions, none failing** |

What t26 read on the code of `main`, which is what staging showed: seven fixture items, five low and two out of
stock.

| Admin language context | Low stock, 5 owed | Out of stock, 2 owed | In process, the same list |
|---|---|---|---|
| English | 3: the Arabic-only item and the item whose original is a draft missing | 1: the Arabic-only item missing | 5 |
| Arabic | 2: only the two items whose listed record is Arabic | 1: only the Arabic-only item | 5 |
| All languages | 3, by the same request as English | 1 | 5 |

### Criteria, through the request the screen makes

Fixtures of t26, seven physical items: those of t24, and a second Arabic-only item that is out of stock, because
the out-of-stock list is where staging found the omission.

| # | Criterion | Verified by | Result |
|---|---|---|---|
| AC-246-01 | A bilingual item is one line in the low-stock report | t26, over HTTP, in the English, Arabic and all-languages admin contexts | **PASS** |
| AC-246-02 | A bilingual item is one line in the out-of-stock report | t26, the same three contexts | **PASS** |
| AC-246-03 | No item is left out because of its record's language | t26: an Arabic-only item in each list, the English-only item, and the item whose original is a draft, in every context | **PASS** |
| AC-246-04 | The same items in every language context | t26: the three contexts return identical lists, totals and summaries; the in-process dispatch agrees with each | **PASS** |
| AC-246-05 | One stock figure, never a sum | t26: the pair shows 2 over HTTP in every context | **PASS** |
| AC-246-06 | The total and the paging count physical items | t26: total equals lines; a two-per-page walk and three sort orders in the Arabic context; each figure of the summary under the table equals its list's total. t25: 5,000 items, in process and over HTTP | **PASS** |
| AC-246-07 | A translated variation is one line | t26, over HTTP | **PASS** |
| AC-246-08 | The export is the same list | t26: WooCommerce's exporter run in a front-end request, neither wp-admin nor WP-CLI, under each language prefix, holds each item once with the screen's total. When every row is on the page the screen builds its download in the browser from those rows, which the list checks cover | **PASS** |
| AC-246-11 | The figures are the ERP's | Not testable before PRE-09 | **OPEN, pending PRE-09** |

Also asserted, and not criteria: an ordinary product query in that same front-end request is still narrowed to
its language, in English and in Arabic.

### The suite after the repair

**One run, on a clean baseline, serially: 22 scenarios, 0 failed.** Seventeen contract scenarios pass and
5 are fact-finding scenarios that give no verdict, as before. `evidence/repair/clean-baseline.txt` is the
reset that preceded it, `evidence/repair/final-suite.txt` and `final-suite.json` the run.

### At the sizing baseline, after the repair

t25, 5,000 physical items stored as 10,000 records. The total is 5,000 and the first page is 25 distinct
source-language records in all three session languages in process (588 ms, 295 ms and 19 ms) and over
HTTP in the Arabic admin context. The last page, 200, is full. Over HTTP the list took 1631 ms for the whole
request and the summary 2801 ms, where a signed-in REST request that reads no product takes 1661 ms on the
same machine. **Those times are facts about a developer machine and are not a production figure.** Filtering,
ordering, the total and the paging run in one SQL statement on physical items before the page is cut; nothing is
deduplicated in PHP.

### What the repair does not claim

- **Staging, as first written.** The staging pass is repeated on the repaired `main` after merge. Until it passes
  there, the board status stays In progress.

### Staging after the repair, 5 October 2026

Run on a staging reset and reseeded from `main` at `25c391e`, in the installed Chrome, signed in as the invented
staging administrator, and again over signed-in HTTP outside the browser.

| Admin language context | Low stock, 4 owed | Out of stock, 4 owed | Summary under the table |
|---|---|---|---|
| English | 4 lines, each item once | 4 lines, each item once, the Arabic-only item among them | 4 low stock, 4 out of stock |
| Arabic, asked under the Arabic address prefix | the same 4 | the same 4 | 4 low stock, 4 out of stock |
| All languages | the same 4 | the same 4 | 4 low stock, 4 out of stock |

**7 of 7 checks without a finding**, the download included. F-246-1, F-246-2 and F-246-3 are corrected. The first
browser attempt after the code was refreshed timed out while the admin page first loaded and found nothing; the
pass was run again, and again after the reset, and the record kept is the last. Record: `docs/2026-10-05-staging-regression-after-repairs.md`; evidence:
`specs/005-local-staging-environment/evidence/regression-2026-10-05/`.

**The board status returned to Verified on this result.** It is a scripted pass in a real browser.
- **A person's look.** Still owed under DOD-04.
- **AC-246-11.** `pending PRE-09`.
- **Contractual acceptance.** Pending DOD-09, through MS-UAT-2026-027.

## Boundaries held

No stock write, no change to how stock is synchronised between language records, no storefront behaviour, no ERP
mechanism, no new report, column or filter, no dashboard change, no change to the older stock report screen, and
CoreX, WooCommerce and the multilingual plugins unmodified.
