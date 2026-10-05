# Staging verification, 5 October 2026: #241, #242, #246 and workstream item B6

The first checks run on the staging and demonstration environment (#244). They are the checks three merged
features were waiting for. **Nothing here is client acceptance**, and nothing here was corrected: each finding is
recorded against the PBI that owns it.

> **What happened next.** #246 and #242 were moved from Verified to In progress on these findings, repaired and
> merged the same day, and re-run on a reset staging: `docs/2026-10-05-staging-regression-after-repairs.md`. The
> development baseline's "coming soon" screen was switched off and guarded:
> `docs/2026-10-05-storefront-placeholder-guard.md`. This record is kept as it was written.

**How it was run.** By the agent, driving real browser windows on the developer's machine against the local
staging address, on the code of `main`. It is repeatable, and it is **not a person looking at the screens**. The
definition of done asks for "manual testing on staging" (DOD-04). What was done is a scripted pass in a real
browser plus a reading of the screenshots. A pass by a person, and by the client through the tunnel, is still
owed.

**The environment.** Staging on `http://127.0.0.1:8088`, WordPress 7.1.2, WooCommerce 11.1.0, WPML 4.9.7,
WooCommerce Multilingual 5.5.7, built from the commit recorded in the evidence, seeded with invented data
(`docs/staging-environment.md`). No public address existed: no tunnel has been opened.

## Summary

| Item | What was run | Result |
|---|---|---|
| #241, AC-9, the browser matrix | Seven pages in both reading directions in the installed Chrome and Edge, in the Firefox build Playwright ships, and, as an indication only, in a WebKit build and at a phone-sized window | **Not verified.** Three of the six approved rows cannot be run on this machine. On the rows that ran, every page loaded in the right direction with its own content, and one finding was made on the cart page |
| #242, DOD-04 on staging | 52 checks: every page of the URL map in both languages, the account screens signed in as a customer, the empty-cart checkout, unknown addresses, robots.txt and the sitemap | **51 of 52 without a finding. One defect**: the Arabic brand archive points its canonical and its language links at addresses that do not exist |
| #246, DOD-04 on staging | The stock report as a signed-in administrator, low stock and out of stock, in the English, Arabic and all-languages admin contexts, and its export | **Holds in the English context, and fails in two ways outside it.** Three findings, two of them against criteria recorded as technically verified |
| B6, concurrent orders | Buyers ordering the last 3 units at the same instant, in English and split between English and Arabic, four runs | **Overselling measured in every run.** A finding for the checkout PBI, not corrected here |

## #241: AC-9, the browser matrix

The approved matrix (D-09) is six rows, both reading directions on each.

| Approved row | What ran | Result | State of the row |
|---|---|---|---|
| Chrome, current, desktop | The installed Google Chrome 154, in a window | 12 of 14 page loads without a finding | Exercised, with a finding |
| Edge, current, desktop | The installed Microsoft Edge 154, in a window | 12 of 14 | Exercised, with a finding |
| Firefox, current, desktop | Firefox 155 as Playwright ships it, in a window. **Not the release channel**: Firefox is not installed on this machine | 12 of 14 | Exercised on that build, with a finding. Whether it stands for the release channel is Mustafa's call |
| Safari, current, desktop | Nothing that is Safari. A WebKit 26.6 build ran as an indication: 12 of 14 | Indication only | **Not exercised.** Needs a Mac |
| Chrome on Android, current | Nothing that is Android. The installed Chrome at a phone-sized window ran as a layout indication: 12 of 14 | Indication only | **Not exercised.** Needs an Android phone |
| Safari on iOS, current | Nothing | | **Not exercised.** Needs an iPhone or iPad |

The seven pages: home, shop, a simple product, a product with variants, cart, account and a content page, each
in English (left to right) and Arabic (right to left). Checked on each: the response, the language and direction
the browser computed, that the page holds its own content in its own language, that nothing a visitor can see
sits past either edge of the window, console errors, failed requests, and the staging marker. A screenshot of
each is in the evidence.

**Finding F-241-1, every engine, both directions.** On the cart page with an empty cart, the "New in store"
product grid is 8 pixels wider than the window, so the page scrolls sideways. It is the same in English and in
Arabic, so it is not a mirroring fault. The theme is the bare baseline with no design, and the cart page is
owned by #306.

**What the screenshots show, to the eye.** Arabic pages are mirrored: the product image sits on the right, text
and prices are right-aligned, the product grid starts from the right. Three labels are not translated on Arabic
product pages: "Category", the attribute name "Size" and its values, and the page title "Shop". The first is a
storefront string for the product-page PBI; the second is invented seed data; the third is page copy, which is
a client input.

**Two things the first runs taught, kept because the checks were changed by them.** The first run passed every
page while a "coming soon" screen stood in front of every store page: a fresh WooCommerce hides the store from
visitors, and the checks read structure and not content. Each page now names something that must be on it, and
the run fails on that screen. Second, a full-page capture of an Arabic page in Chrome showed text cut off at the
right edge. The same page in Firefox, and in Chrome's own window, is intact: the capture was cropped by the
width of the scrollbar. The matrix now captures the window. Neither was a defect of the site.

**AC-9 is not verified.** It is partly exercised. It can be completed only on a Mac, an iPhone and an Android
phone, through the tunnel.

## #242: DOD-04 on staging

| What | Checks | Result |
|---|---|---|
| The sixteen pages with a record, a product, a category archive, a brand archive, search results and the lost-password screen, each in English and Arabic | 40 | 39 without a finding |
| The checkout with an empty cart, in each language | 2 | Lands on the cart of the same language |
| An address that names nothing, in each language | 2 | 404, in its own language |
| `robots.txt` | 1 | Served, with the sitemap line |
| The sitemap | 1 | 34 page addresses, 16 of them Arabic |
| Signed in as the demonstration customer: sign-in, orders and addresses in each language, one order opened | 6 | Each lands on its own endpoint in its own language; the order list holds the customer's orders |

On every page: the status, the address landed on, the language and direction, the canonical link, the language
alternates and whether each one answers, the meta description, the staging marker.

**Finding F-242-1, a defect.** On the Arabic brand archive, `/ar/brand/{term}/`, which the URL map documents and
which answers 200, the page's canonical link and all three of its language links (`ar`, `en` and `x-default`)
carry a translated form of the word "brand" in the address. **All four point at addresses that answer 404.** The
English brand archive and both category archives are correct. This is AC-242-09, and the language links are
NFR-03's. The automated scenario did not catch it. The cause is not diagnosed here.

**Not a finding, and recorded so it is not rediscovered.** The lost-password screen's canonical link is the
account page. That is correct: it is an endpoint of that page, not a page.

**A change to staging this pass forced.** Staging first told WordPress to discourage search engines. That
setting also switches the sitemap off and rewrites `robots.txt`, so neither could be checked. Staging is now
kept out of search engines by the web server's header and the tunnel sign-in instead.

## #246: DOD-04 on staging

The seed holds four physical items that are low on stock and four that are out of stock. One of the low items
exists in English only, and one of the out-of-stock items exists in Arabic only. The expected answer was read
from the store itself, one entry per physical item.

| Admin language context | Low stock, expected 4 | Out of stock, expected 4 |
|---|---|---|
| English | **4 lines, each item once.** Correct | **3 lines.** The Arabic-only item is missing |
| Arabic | **0 lines.** All four missing | **1 line.** Only the Arabic-only item |
| All languages | 4 lines. The screen makes the same request as the English context | 3 lines. The Arabic-only item is missing |

The export of the low-stock list, downloaded in the English context, holds the same four items as the screen.

The record holds the request the screen itself made and the answer it was given, so these are not a reading of
the page's markup. In the Arabic context the screen asks under the Arabic address prefix and is given a
different list.

**Finding F-246-1.** An item that exists only in Arabic is left out of the report in the English and
all-languages contexts. This is AC-246-03.

**Finding F-246-2.** The report does not list the same items in every admin language context: in the Arabic
context it lists none of the items that exist in both languages. This is AC-246-04.

**Finding F-246-3.** Under the table the screen reads "7 Low stock" and "7 Out of stock" while the lists hold 4
and 3 lines: the summary counts language records, not physical items. AC-246-06 says the total counts physical
items. The automated scenario checks the list's own total, which is right; the summary under it is a second
figure that the scenario does not read and the specification does not mention.

**What this means for #246.** AC-246-03 and AC-246-04 are recorded as technically verified, and the feature's
own scenario passes on staging too: it was run against the staging runtime on the same day, 0 failed. **The
scenario and the screen disagree.** The scenario calls the report inside one process; the screen calls it over
HTTP, signed in, under the address prefix of the admin's language. So the verification of those two criteria
does not hold for the way the report is actually used, and #246 needs a correction and a scenario that goes
through the real request before either criterion can be called verified again. The duplicate-line fault the
feature was built to remove (AC-246-01, AC-246-02) is gone in the English context, and the export is right.

## B6: concurrent orders against the last units

Twelve buyers, each with their own cart holding one unit of the same item, three units in stock, every order
request released at the same instant (spread under one millisecond), cash on delivery.

| Run | Buyers, units | Orders confirmed | Units oversold | Stock afterwards |
|---|---|---|---|---|
| All buyers on the English record | 12, 3 | 6 | 3 | -3 on both language records |
| Half on the English record, half on the Arabic record | 12, 3 | 4 (all four English) | 1 | -1 on both language records |
| The same bilingual case, an earlier run the same day | 12, 3 | 6 (four English, two Arabic) | 3 | **-1** on both language records, where 3 less 6 is -3 |
| An earlier English run | 10, 3 | 4 | 1 | -1 on both language records |

**Finding F-B6-1.** More orders are confirmed than there are units, in every run: four of four. The check and the
decrement are separate steps, as probe P-009 predicted, and simultaneous orders pass the check together. The
number oversold varies from run to run, as a race does.

**Finding F-B6-2.** In one of the two bilingual runs the stock figure afterwards does not equal the starting
stock less the orders confirmed: six orders were confirmed against three units and the figure fell by four. A
decrement was lost as well as the stock oversold. It did not recur in the second bilingual run, so it is recorded
as seen once.

**What held.** The two language records of the item showed the same stock figure after every run.

These are four runs on the developer's machine against the store's own stock figure. **They are not evidence
about the ERP**: the contracted rule is that the ERP validates the stock and a sale fails closed (ERP-05), and
that mechanism does not exist yet. They are evidence that the store's native check cannot be what protects the
last unit, which is what BR-003, CHK-12 and AC-05 will have to be built against in #255.

A read-load run (16 clients, 160 page requests) answered every request with 200. Its timings are in the
evidence and are about the harness and the developer's machine, not a performance result.

## What follows from this

| Finding | Owner | What is needed |
|---|---|---|
| F-241-1, cart grid 8 pixels too wide | #306, the cart page, with the frame of #253 | Resolved by the page's own layout when it is built |
| AC-9, three rows not exercised | #241 | A Mac, an iPhone and an Android phone, through the tunnel |
| F-242-1, Arabic brand archive links | #242 | A correction, and a scenario that fetches the page as a visitor |
| F-246-1, F-246-2, F-246-3 | #246 | A correction, and a scenario through the real signed-in request |
| F-B6-1, F-B6-2 | #255, and the ERP adapter of #245 | Built against, not patched: the final sale validates against the ERP |
| The development baseline leaves the "coming soon" screen on | Test infrastructure | A check of which existing scenarios fetch store pages as a visitor |
| A person has not looked | All three | Mustafa's own pass, and the client's through the tunnel |

## Evidence

`specs/005-local-staging-environment/evidence/`: the browser matrix record, the two staging pass records with the
requests and answers, the export, the concurrency records, and a selection of the screenshots. The full
screenshot sets stay on the developer's machine under `../app-staging/evidence/`.
