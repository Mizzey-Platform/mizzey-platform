# Staging regression after the repairs, 5 October 2026: #241, #242, #246 and the placeholder guard

The first staging checks of the day (`docs/2026-10-05-staging-verification.md`) disproved recorded verification
in #246 and #242. Both were moved from Verified to In progress, repaired, and merged. This is the focused staging
regression that followed. **Nothing here is client acceptance, and a person has still not looked.**

**How it was run.** By the agent, in real browser windows and over real signed-in HTTP, against the local staging
address. Staging was rebuilt from `main` at `25c391e` (the code of #329, #330 and #331) and **reset and reseeded
from nothing** before the run. It is a scripted pass and is recorded as one.

**The environment.** `http://127.0.0.1:8088`, WordPress 7.1.2, WooCommerce 11.1.0, WPML 4.9.7, WooCommerce
Multilingual 5.5.7, invented data only. No public address exists yet: no tunnel has been opened.

## Summary

| Item | What was run | Result |
|---|---|---|
| #246, the stock report | The signed-in administrator's screen in Chrome, in the English, Arabic and all-languages admin contexts, low stock and out of stock, the summary under the table, and the download. Then the same requests over signed-in HTTP, outside the browser | **7 of 7 checks without a finding.** Four low and four out of stock in every context, each item once, the Arabic-only item included, and the summary reads 4 and 4 |
| #242, pages and addresses | 54 checks: the 52 of the first pass, and a collection archive in both languages | **54 of 54 without a finding.** The Arabic brand archive's canonical link and three language links are its documented addresses and each answers 200 |
| #241, the browser matrix | Seven pages in both reading directions on the installed Chrome, the installed Edge and the installed release Firefox | **12 of 14 page loads without a finding on each of the three.** The one finding is F-241-1, unchanged and owned by #306. **AC-9 is still not verified**: three device rows are owed |
| The placeholder guard | Every page of both passes and of the matrix is checked for the commerce "coming soon" page and for a WordPress error or maintenance page | None seen. On the development runtime the guard scenario t27 passes in the suite of `main` |

## #246: the stock report on staging

The seed holds four physical items low on stock and four out of stock. One low item exists in English only and
one out-of-stock item in Arabic only.

| Admin language context | The request the screen made | Low stock, 4 owed | Out of stock, 4 owed | Summary under the table |
|---|---|---|---|---|
| English | `/wp-json/wc-analytics/reports/stock` | 4 lines, each item once | 4 lines, each item once | 4 low stock, 4 out of stock |
| Arabic | `/ar/wp-json/wc-analytics/reports/stock` | 4 lines, the same four | 4 lines, the same four | 4 low stock, 4 out of stock |
| All languages | `/wp-json/wc-analytics/reports/stock` | 4 lines, the same four | 4 lines, the same four | 4 low stock, 4 out of stock |

The download of the low-stock list holds the same four items. The first pass of the day read 4, 0 and 4 low
lines and 3, 1 and 3 out-of-stock lines in those three contexts, with a summary of 7 and 7.

| Finding of the first pass | State |
|---|---|
| F-246-1, an Arabic-only item left out outside the Arabic context (AC-246-03) | **Corrected.** It is listed in every context |
| F-246-2, a different list in each admin language context (AC-246-04) | **Corrected.** The three lists and their totals are identical |
| F-246-3, the summary counted language records (AC-246-06) | **Corrected.** Each figure equals the total of its list |

One thing about the run itself: the first browser attempt after the code was refreshed timed out while the admin
page loaded for the first time, and found nothing. The pass was run again and completed, and again after the
reset. The record that is kept is of the pass after the reset.

## #242: pages and addresses on staging

| What | Checks | Result |
|---|---|---|
| The sixteen pages with a record, a product, a category archive, a brand archive, **a collection archive**, search results and the lost-password screen, each in English and Arabic | 42 | 42 without a finding |
| The checkout with an empty cart, in each language | 2 | Lands on the cart of the same language |
| An address that names nothing, in each language | 2 | 404, in its own language |
| `robots.txt`, the sitemap | 2 | Served; the sitemap lists both languages |
| Signed in as the demonstration customer: sign-in, orders and addresses in each language, one order opened | 6 | Each on its own endpoint in its own language |

On every page: the status, the address landed on, the language and direction, the canonical link, the language
links and **whether each one answers**, the staging marker, and that the page is not a placeholder. On each of the
three archives, in each language: the term's own name on the page and at least one product listed.

| Finding | State |
|---|---|
| F-242-1, the Arabic brand archive's canonical and language links answer 404 (AC-242-09) | **Corrected.** They are `/ar/brand/{term}/` and `/brand/{term}/`, and each answers 200 |
| F-GUARD-1, the collection archive answers 200 with nothing on it (AC-242-05) | **Corrected.** It shows the collection and its three products, in both languages. The first pass could not have seen this: the seed held no collection |

**One new observation, not a defect of #242 and not corrected.** On the Arabic collection archive the breadcrumb
shows the taxonomy's own label, "Collections", in English, beside the Arabic term. It is a storefront string of
the collection page, of the same kind as the untranslated "Shop" title and "Category" label recorded in the first
pass, and belongs with the page's content and design.

## #241: the browser matrix

The approved matrix (D-09) is six rows, both reading directions on each. A row counts only when a real, installed
browser of that kind ran it. **Playwright's own Firefox build does not count as current Firefox**, a WebKit
build is not Safari, and a phone-sized window is not a phone (decided 5 October 2026, D-13).

**A. Rows exercised on a real, installed, current browser**

| Approved row | What ran | Result |
|---|---|---|
| Chrome, current, desktop | The installed Google Chrome 154.0.8037.94 | 12 of 14 page loads without a finding |
| Edge, current, desktop | The installed Microsoft Edge 154.0.4258.53 | 12 of 14 |
| Firefox, current, desktop | **The installed release Firefox 157.0**, the current stable release on the day, driven over WebDriver BiDi. The browser's own user-agent string is recorded with the row | 12 of 14 |

The two loads with a finding on each row are the empty cart in each direction: F-241-1, the 8 pixel overflow,
unchanged, attached to #306 and not corrected here.

**B. Rows that still need the actual browser or device**

| Approved row | State |
|---|---|
| Safari, current, desktop | **Not exercised.** Needs Safari on a current supported macOS |
| Safari on iOS, current | **Not exercised.** Needs an iPhone or iPad |
| Chrome on Android, current | **Not exercised.** Needs an Android phone |

A reputable device cloud may stand for one of these rows if it really runs the named browser on the named
system and the evidence says so. These rows are acceptance verification. They do not block development.

Run as indications only, and not offered for any row: the Firefox 155 build Playwright ships, a WebKit 26.6
build, and the installed Chrome at a phone-sized window. Each showed the same 12 of 14.

**AC-9 is not verified.** Three of six rows are exercised.

## The placeholder guard

| Where | What holds |
|---|---|
| The development baseline | The store is open to visitors. The baseline's own summary reports the switch as off |
| Integration scenarios | A scenario that is served the commerce "coming soon" page, a maintenance page or a WordPress error page fails by name. t27 proves it against the real placeholder in both languages and the real maintenance page, in the suite of `main`: 24 scenarios, 0 failed |
| Staging | Both browser tools fail a page that is a placeholder, whatever its language. None was seen on any of the 54 checks or the 84 matrix page loads |

## What this does and does not establish

| | State |
|---|---|
| #246, AC-246-01 to AC-246-08 | Technically verified on the disposable runtime and on staging, through the request the screen makes. AC-246-11 `pending PRE-09` |
| #242, AC-242-01 to AC-242-15, G-1 | Technically verified on the disposable runtime and on staging |
| #241, AC-9 | **Not verified.** Three device rows owed |
| DOD-04, "manual testing on staging" | A scripted pass in real browsers. **A person's own pass is still owed**, and the client's through the tunnel |
| Contractual acceptance | **None.** DOD-09, through MS-UAT-2026-027 |

## Evidence

`specs/005-local-staging-environment/evidence/regression-2026-10-05/`: the browser matrix record with each
browser's version and user-agent string, the two pass records with the requests and answers, the signed-in HTTP
reading, the download, and a selection of the screenshots. The full screenshot sets stay on the developer's
machine under `../app-staging/evidence/`.
