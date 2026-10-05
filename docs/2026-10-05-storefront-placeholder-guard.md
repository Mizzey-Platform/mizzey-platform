# The storefront placeholder guard, and what it found

5 October 2026. Internal work, test infrastructure. No client-facing behaviour changes, and no production
setting is touched: whether the live store opens to visitors is the launch policy and is not decided here.

## What was wrong

A fresh WooCommerce answers every store page with a "coming soon" page until someone opens the store. That page
answers with status 200, in the language and direction of the address, with the real page's title and a
canonical link in its head. The development baseline left the switch on. Staging switched it off on its first
day, after its browser checks passed on the placeholder (`docs/2026-10-05-staging-verification.md`). The
development baseline was not corrected then, so integration scenarios kept fetching the placeholder.

Measured on the development runtime before the correction: the shop, the cart, a product, the terms page and all
three term archives are served the placeholder. The home page, the content pages and the account page are not:
the switch covers store pages only on this configuration.

## Which scenarios had been reading the placeholder

The guard was wired into every scenario that fetches a page as a visitor, and the four were run against the
baseline as it stood. All four failed, each naming the placeholder. A second run, logging instead of failing,
listed every fetch.

| Scenario | Fetches served the placeholder | What the scenario concluded from them |
|---|---|---|
| t08, no public exposure of cost | 2: the simple and the variable product page | "Cost absent". True of the placeholder, and no evidence about the product page |
| t21, bilingual baseline | 1: the Arabic shop | Arabic, right to left. True of the placeholder too |
| t22, information architecture | 9: the cart, the shop and the terms page in both languages, and the category, brand and collection archives | 200, right language, right direction |
| t23, SEO fundamentals | 3: the shop, the terms page, a product page | A canonical link, a meta description, and "no structured data yet" |

Fourteen fetches in four scenarios. None of those fetches was evidence of the page it named.

## What changed

| Change | Where |
|---|---|
| The test and development baseline opens the store to visitors | `mizzey-site/tests/integration/baseline/setup.php`. Staging already did, in `tests/staging/seed/15-store-settings.php` |
| A guard that fails a scenario, by name, when the answer is the commerce "coming soon" page, a maintenance page or a WordPress error page | `mizzey-site/tests/integration/_storefront.php`, `storefront_refuse_shell()`. The markers do not depend on the language of the page |
| A positive check: the part of the document a visitor sees must hold the page's own title or text, or the component it renders. The head is excluded, because the placeholder carries the real title there | `storefront_shows()` and `storefront_shows_page()`, used in t08, t21, t22 and t23 |
| A scenario that proves the guard against the real things: it switches the placeholder on in both languages, puts the runtime into maintenance, reads what a visitor is served, and restores both | `t27-storefront-guard.php`. It also fails if the baseline stops opening the store |
| A check that a new scenario cannot fetch a page without the guard: every direct fetch in a scenario reaches the guard within a few lines or states why it is exempt | `tools/tests/test_storefront_guard.py`. Three fetches are exempt: two JSON answers, and the guard scenario's own reading of the placeholder |
| The two browser harnesses recognise the placeholder by its language-independent marker, and a WordPress error or maintenance page | `tests/staging/staging-pass.cjs`, `tests/staging/browser-matrix.cjs` |

How to use it in a new scenario: fetch, then call `storefront_refuse_shell( $url, $status, $body )`, then assert
something the page must show with `storefront_shows()`. Status 200 is not evidence that a page rendered.

## What the re-run found, once the scenarios read the real pages

Full suite on a clean baseline with the store open and the guard in place, serially: **23 scenarios, 0 failed**. Eighteen contract scenarios pass, t27 among them, and five are fact-finding scenarios that give no verdict. The baseline's own summary reports the placeholder switch as off.

Two things the placeholder had hidden, both belonging to #242 and neither corrected here:

| Finding | What the scenario had recorded | What the real page shows | Owner |
|---|---|---|---|
| **F-GUARD-1.** The collection archive renders nothing | t22: "archive=200", counted as the term's archive | The address resolves and answers 200, and the page holds the site title and nothing else: no collection name and no product list. The category and brand archives show the term and the product list. The theme has no template for this taxonomy, and the platform's product archive template is not applied to it | #242, AC-242-05: "a URL that names a term serves that term's archive". Taken into the #242 repair, with the check that the archive shows its term |
| **F-GUARD-2.** A product page does emit structured data | t23: "structured-data types on the product page: none yet", with a comment that the generators never fire because the theme has no product template | The real product page emits `Product` and `BreadcrumbList`. The earlier note described the placeholder | #242, AC-242-13. The scenario's verdict does not change, because it asserts the mechanism. The false note and the record that repeats it are corrected in the #242 repair |

One check was added and needed a component named instead of text: the tracking page and the account page render
a form, not stored text, so t22 looks for the form.

## Staging log hygiene, found during the credential check

The check asked for after the staging database password was rotated found the cause of the exposure, which was
still active.

| Question | Answer |
|---|---|
| Old staging credential in the git history, on any ref, or in the stash | **Old staging credential searched for: not present** |
| In the working tree, the committed evidence, scripts, fixtures or configuration | **Not present** |
| In staging's retained evidence, backups, captured mail, run files or build record | **Not present** |
| In the shell histories | **Not present** |
| In staging's own PHP error log, a local file outside the repository | **Present, 28 times, and the current credential 2,946 times** |

The cause: the machine's PHP loads Xdebug in development mode, which writes a stack trace under every warning,
with the value of every argument. One of those arguments is the database object, which holds the password. Every
page load wrote it again.

| Remediation | State |
|---|---|
| Both values masked in place in the local log, same length, so the file a running server appends to stays intact | Done. Zero occurrences of either afterwards |
| Staging's web server starts with Xdebug off, for that process only | `staging.py`, `cmd_up`. After a restart and a full signed-in pass the log holds neither value |
| The rotated credential | Unchanged, and outside version control: it sits only in staging's own configuration and client option file, where it belongs |
| One local session transcript of the coding assistant still holds the old value | Not edited. The value is dead since the rotation. It is on the developer's machine, outside the repository |

No value was printed while checking: the search compared hashes and reported counts and file names.
