# Verification record: bilingual platform baseline

Feature `002-bilingual-platform-baseline`, PBI #241. Register rows FIX-04, FIX-04a, NFR-04, NFR-04a, NFR-14.

**Three states are reported separately, per constitution M-7**, and they are not the same thing:

| State | Where this feature stands |
|---|---|
| **Workflow complete** | Yes. Spec, clarification, plan, research, checklist, tasks and analysis done; implementation done for AC-1 to AC-8 |
| **Technically verified** | **AC-1 to AC-8 only**, on the disposable runtime, at the versions recorded below |
| **Contractually accepted** | **No criterion.** Acceptance happens only through the Acceptance and UAT Plan MS-UAT-2026-027 |

Versions this is evidence for, and no others (M-8): WordPress 7.1.2, WooCommerce 11.1.0, WPML 4.9.7, WPML String
Translation 3.5.4, WCML 5.5.7, CoreX 0.42.0, PHP 8.3.6, MySQL 8.3.0.

## What was built, and what turned out not to need building

Measurement came first, and it shrank the feature. Three criteria were already met natively:

| Criterion | Outcome | Code written |
|---|---|---|
| AC-3, Arabic resolves right to left with `lang="ar"` and `dir="rtl"` | Met natively | **None** |
| AC-4, English resolves left to right with `lang="en-US"` | Met natively | **None** |
| AC-6, one template set renders both languages | Met natively | **None** |

What was actually needed:

| Work | Kind | Task |
|---|---|---|
| Pretty permalinks and the rewrite rules in the test baseline | Test harness | T-01 |
| Arabic records for the WooCommerce system pages, and their per-language assignment | Platform configuration | T-02 |
| Loading the `mizzey-site` text domain for the plugin and the theme | **Production code**, two files | T-03, T-04 |
| The translation template | Generated file | T-05 |
| The hard-coded-text check | Test infrastructure | T-06 |
| The served-request scenario | Test | T-07 |

**No synchronisation of any kind was built**: not for metadata, not for stock, not for price. No product data is
written by this feature at all.

## Automated evidence: AC-1 to AC-8

`mizzey-site/tests/integration/t21-bilingual-baseline.php`, run from a scripted clean baseline. It is the first
scenario in this harness to issue **real HTTP requests**, and it has to be: an in-process language switch to
Arabic reports `dir="rtl"` with `lang="en-US"`, the direction following and the cached language tag lagging, so
only a served request can tell a correct document from an artifact.

| # | Criterion | Assertion | Result |
|---|---|---|---|
| 1 | AC-1, AC-4 | **Clean session.** `GET /` returns 200 with `<html lang="en-US">` and no direction attribute | **PASS** |
| 2 | AC-1 | **One carried session, three requests.** `GET /`, then `GET /ar/`, then `GET /` again, each carrying the cookies the previous response set. The third request is still `lang="en-US"` and not right to left | **PASS** |
| 2a | AC-1 | The middle request of that session genuinely served Arabic, so the sequence cannot pass vacuously | **PASS** |
| 3 | AC-1 | The default language is `en` | **PASS** |
| 4 | AC-2, AC-3 | `GET /ar/` returns 200 with `<html dir="rtl" lang="ar">` | **PASS** |
| 5 | AC-2 | `GET /ar/shop/` returns 200 with `<html dir="rtl" lang="ar">` | **PASS** |
| 6 | AC-2 | Shop, cart and my account each have a distinct Arabic record | **PASS** |
| 7 | AC-5 | A pair forms one translation group, `ar, en`, with the English record as source | **PASS** |
| 8 | AC-6 | No Arabic-specific template exists, and both languages render | **PASS** |
| 9 | AC-7 | The text domain loader is hooked, and the theme has a `languages` directory | **PASS** |
| 10 | AC-8 | A string bound to the domain resolves in Arabic, and the English source survives as a control | **PASS** |
| 11 | boundary | The administrative interface language is unchanged | **PASS** |

### The authoritative run

**One run, on a genuinely clean baseline: 17 scenarios, 17 accounted for, 0 failed.**

| | |
|---|---|
| Baseline | `reset-runtime.sh`, exit 0, with `permalink_structure` `/%postname%/`, `.htaccess` present, and the four Arabic system pages created |
| Contract scenarios | **12 pass**, t02 to t16 |
| Open on a client decision | **1**, t09 `FACT (pending CX-01)` |
| Fact-finding probes | **4**, t17 to t20, which return no verdict by design |
| This feature | **t21 PASS**, AC-1 to AC-8 |
| Failed | **0** |

Evidence, committed beside this record:

| File | What it holds |
|---|---|
| `evidence/clean-baseline.txt` | The reset that preceded the run, with its configuration summary and exit code |
| `evidence/final-suite.txt` | The complete suite output, every scenario's notes included |
| `evidence/final-suite.json` | The same verdicts, machine readable |
| `evidence/probe-ac8-loader-removed.txt` | The negative proof described below |

**The runtime is left clean, measured rather than asserted.** After the run: 0 products, 0 orders, and 11 pages,
which is exactly the baseline (7 WordPress and WooCommerce pages, two of them drafts, plus the 4 Arabic
translations). No scenario page survives. The count is recorded at the end of `evidence/clean-baseline.txt`.

That claim had to be earned. The first authoritative run left **two pages behind**, and the cause was in the
shared harness: `Scenario::track_post()` ignores any post type other than `product` and `product_variation`,
silently, and t21 is the first scenario to create pages. Deletion itself works, which an isolated reproduction
confirmed. t21 now registers its page deletions through `on_finish()`, the hook it already uses for the temporary
catalogue. `track_post()` was **not** widened, because twelve merged pilot scenarios depend on it; its docblock
now states that it is product-only by design. The evidence above was then regenerated from the corrected code,
because evidence that does not match the committed code is not evidence.

**Read the figure precisely.** It is from a single run preceded by a reset. An earlier run of mine was not, and
four pilot scenarios failed in it; those scenarios require a clean baseline, so that was a defect in my method,
not in the code, and it is not evidence of anything. No isolated scenario pass and no dirty-runtime rerun is
mixed into the number above.

### Two negative proofs, because a check that cannot be shown to fail is not evidence

**AC-8 fails without the production loader.** The body of `Localisation::loadPluginTextdomain()` was removed and
t21 re-run on a clean baseline: `FAIL AC-8: a string bound to the site text domain did not resolve in Arabic`,
with the probe returning its English source in both languages. The loader was then restored byte-identical, which
`git diff` confirmed as empty. Recorded in `evidence/probe-ac8-loader-removed.txt`.

That run also showed something worth keeping: with the loader gone, **just-in-time loading did not rescue the
translation either**. The `.mo` sits in the plugin's own `languages/` directory, which the just-in-time path does
not search without the registration `load_plugin_textdomain()` performs. So AC-8 depends on this feature's code
specifically, and not on WordPress happening to find the file.

**A fresh checkout carries the theme's `languages/` directory.** Git does not track an empty directory, and t21
asserts the directory exists, so the feature would have passed locally and failed on a clean clone. Resolved with
a tracked `mizzey-theme/languages/.gitkeep` that states why it is there, and **verified by cloning the branch at
its head into a new directory**: the file is tracked, the directory exists, and `is_dir()` returns true in the
clone.

`tools/tests/test_storefront_strings.py`, 8 cases, in CI through the existing tooling-test step: the FR-007 check
for AC-7. It passes on the tree, fails on a deliberately hard-coded string with the file and line, ignores
identifiers, and fails closed on a file it cannot read.

### AC-1's same-session evidence, and what the cookies measured

The requirement is that after a customer has viewed Arabic, requesting the English root **in the same session**
still resolves to English. Proving that needs one carried session, and an earlier version of this scenario did
not have one: `t21_get()` sent an empty cookie jar on every request, so "after Arabic" proved only that a second
clean request defaults to English. **External review caught it.**

The method now, with real cookies only and nothing invented:

| Step | Request | Jar sent | Result | Set-Cookie |
|---|---|---|---|---|
| 1 | `GET /` | empty, clean session | 200, `<html lang="en-US">` | **none** |
| 2 | `GET /ar/` | whatever step 1 set | 200, `<html dir="rtl" lang="ar">` | **none** |
| 3 | `GET /` | whatever steps 1 and 2 set | 200, `<html lang="en-US">` | - |

**The measured fact is that neither request sets any cookie**, so the jar carried into step 3 was empty. That is
recorded rather than worked around: the jar was still carried, the sequence was still one session, and the
absence is the result.

**It is also the strongest evidence FR-001 could have.** FR-001 asks that the language resolve from the URL with
no dependence on stored or session state. A storefront that set a language cookie would make that a question of
cookie precedence; one that sets none cannot drift, because there is no stored state to drift from. WPML is
configured for directory negotiation, so the path *is* the language, and the measurement confirms the
configuration behaves that way for an anonymous visitor.

**Both halves are kept and labelled** in the scenario output, `[clean session]` and `[one session]`, because they
test different things and only one of them is the contractual requirement.

### Findings the runs produced, each fixed at its cause

The scenario earned its place by failing first.

| Finding | Cause | Fix |
|---|---|---|
| `/ar/shop/` answered **301**, not 200 | The Arabic page was inserted while English was the current language, so WordPress's slug-uniqueness check did not know it was a translation and suffixed the slug to `shop-2`. The request then redirected to the canonical URL | The language is switched **before** the insert. WPML registers a new post under the current language at insert time, which is the same behaviour the product-cost pilot recorded |
| The translation probe returned Arabic **in English too** | The scenario's `gettext` filter replaced the string unconditionally, which made the English half of the assertion worthless | The filter is language aware, and the English control is now asserted |
| `is_textdomain_loaded` read **false** | Measured: `load_plugin_textdomain()` returns **true** and works, but WordPress 7.1 materialises a catalogue only when a string is first requested, so the flag reads false in between while `__()` translates correctly | AC-8 asserts **the resolved string**, which is what NFR-04a is about and what holds across versions. The lazy-loading behaviour is recorded in research.md under M-8 |
| T-03's gate still asserted **`is_textdomain_loaded()`** | **External review.** It contradicted the lazy-loading measurement already recorded here and in research.md | Replaced with the measured behavioural requirement: the loader registers, and a real string resolves in Arabic through it with the English source as control. `test_spec_consistency.py` now refuses the stale sentence and any line stating the flag as a gate, proved by restoring it |
| AC-1's same-session assertion **carried no session** | **External review.** `t21_get()` sent an empty cookie jar on every request | The helper carries a jar; AC-1's second half is one session across three requests, and both halves are labelled. Measured: neither request sets a cookie |
| The AC-8 probe would have passed with `Localisation.php` deleted | Found by **test-guard**. It asserted a `gettext` filter changed a string under Arabic, which is WordPress's own behaviour | The probe writes a real `.mo`, calls the production loader, asserts resolution with the English source as control, and removes the file. Nothing test-shaped is shipped |
| The text domain reached `load_plugin_textdomain()` as a class constant | Found by **wp-guard**. String-extraction tools read that argument statically | Passed as a literal; the constant stays for PHP callers |

### One apparent defect, bounded and dismissed

`/ar/checkout/` resolves to the cart page. So does English `/checkout/`: WooCommerce redirects an empty cart away
from checkout, identically in both languages. It is standard behaviour, not multilingual behaviour, and the
scenario deliberately does not request it, because the response would say nothing about language resolution.
Recorded so the checkout slice does not rediscover it as a bug.

## Staging and browser evidence: AC-9, not verified

**Approved matrix** (D-09, 4 October 2026). Both reading directions on every row.

| Browser | LTR | RTL | State |
|---|---|---|---|
| Chrome, current, desktop | - | - | **Not exercised** |
| Safari, current, desktop | - | - | **Not exercised** |
| Edge, current, desktop | - | - | **Not exercised** |
| Firefox, current, desktop | - | - | **Not exercised** |
| Chrome on Android, current | - | - | **Not exercised** |
| Safari on iOS, current | - | - | **Not exercised** |

**Every row is unexercised, and none may be marked otherwise until it is run on staging.** A command-line runtime
has no browsers. Nothing was substituted for one: no user-agent string, no headless approximation and no CSS
inspection is offered as browser acceptance.

**Blocked on #244**, the environments PBI (PRE-03a, PRE-03b). This is the only item this feature cannot close.

Excluded by the same decision, and not added unless separately required: Internet Explorer, Opera Mini, in-app
browsers and any named device model.

## Boundaries held

| Boundary | Held |
|---|---|
| No Arabic administrative interface | Yes. Nothing here touches admin locale, and the scenario asserts it is unchanged |
| No per-locale currency, number or date formatting | Yes. NFR-04b is owned by another slice |
| No URL structure, hreflang or SEO work | Yes. Owned by #242 |
| No language switcher component | Yes. A navigation component, behind the approved design |
| No general multilingual metadata synchronisation | Yes |
| No stock synchronisation | Yes |
| No price synchronisation, and no programmatic product price write | Yes. This feature writes no product data at all |
| SKU never used as multilingual identity | Yes. AC-5 requires translation identity explicitly |
| Product Cost not reopened | Yes. No file under `specs/001-product-cost-capture` was touched |
| No migration logic | Yes |
| CoreX not modified | Yes. Nothing under `app/corex` was changed; the inspection was read-only |

## Guard Gate

| Guard | Scope | Outcome |
|---|---|---|
| **wp-guard** | `Localisation.php`, the plugin bootstrap, `functions.php`, `style.css` | One finding, fixed: the text domain reached `load_plugin_textdomain()` as a class constant rather than a literal, which the string-extraction tools read statically. Clean on security: no output, no request data, no SQL, no queries |
| **test-guard** | `t21-bilingual-baseline.php`, `test_storefront_strings.py`, the baseline changes | Applied |
| **docs-guard** | The spec, plan, research, checklist, analysis and this record | Applied |
| **clean-code-guard** | The production code | Applied |
| **woo-guard** | - | **Nothing to review.** No order, product, cart, checkout or money code is written |

## Open, and owned elsewhere

| Item | Owner |
|---|---|
| AC-9, the browser matrix | **#244**, staging |
| The production URL and rewrite configuration | **OD-27**, hosting in writing. The rewrite configuration here is the test environment's and decides nothing about production |
| Arabic page copy for the system pages | The storefront slices. This feature created the records, deliberately not their content |
