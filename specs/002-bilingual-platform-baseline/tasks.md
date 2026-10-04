# Tasks: bilingual platform baseline

Feature `002-bilingual-platform-baseline`, PBI #241. Register rows FIX-04, FIX-04a, NFR-04, NFR-04a, NFR-14.

Ordered so that each task is verifiable before anything depends on it, and so the configuration work lands before
the code that assumes it. **Every task names its test (M-7).** Production code is four tasks out of eleven, and
two of those four are a `.pot` file and a two-line bootstrap.

| Legend | |
|---|---|
| Kind | what path class the task touches, which decides whether this work type may touch it |
| Gate | what must be true before the task is considered done |

## Phase A: make the environment able to prove anything

### T-01. Give the test baseline rewrite configuration

**Kind**: site-tests (`mizzey-site/tests/integration/baseline/reset-runtime.sh`).

Set the permalink structure to `/%postname%/` and **write `.htaccess` directly**. Do not use
`wp rewrite flush --hard`: `got_mod_rewrite()` is false in this runtime because PHP runs as CGI, so the hard
flush reports success and writes nothing. Read the permalink option back after setting it, because a shell can
mangle a leading `/%postname%/` into a path.

**Test**: the scenario in T-07 requests `/shop/` and asserts 200 with a WordPress-rendered document. A baseline
without rewrite configuration fails there, not silently.

**Gate**: on a freshly reset runtime, `/` and `/shop/` both serve 200.

---

### T-02. Create the Arabic records for the WooCommerce system pages

**Kind**: site-tests for the baseline fixture (`baseline/setup.php`), plus whatever the operational runbook needs
recording in the spec. **Not production code**: this is platform configuration, per clarification C-2.

Create the Arabic counterpart of shop, cart, checkout and my account, each in one translation group with the
English record as source, and assign the per-language WooCommerce page options so the Arabic storefront addresses
its own records.

**The boundary**: records and assignment only. No page content, no layout, no interface. Those belong to the
storefront slices.

**Apply the A11 invariant**: create all source records first and the translations second. Never interleave
creation and translation in one process, which is the measured trigger for translation-group corruption.

**Test**: T-07 asserts each Arabic system page resolves to its own Arabic record and not to the English one, and
asserts one translation group per pair with the English record as source.

**Gate**: `/ar/cart/` resolves to the Arabic cart record. Today it serves page 6, the English cart.

---

## Phase B: the strings mechanism

### T-03. Load the `mizzey-site` text domain

**Kind**: site-code (`mizzey-site/src/I18n/Localisation.php`, new; `mizzey-site/mizzey-site.php`, two lines).

One class, one job: load `mizzey-site` for the plugin from `mizzey-site/languages/`, and for the theme from
`mizzey-theme/languages/`. Plugin domain on `init`, theme domain on `after_setup_theme`.

**Do not** add a locale filter, direction handling, a language switcher, or anything that touches the admin
language. **Do not** use the CoreX container, so `Corex::onReady()` is not needed; research.md records why a later
slice that does use it must.

**Test**: a unit-level assertion that the domain is loaded and that a string with an Arabic translation returns
the Arabic text when the language is Arabic. Guard: **wp-guard** and **clean-code-guard** on the diff.

**Criteria**: AC-7 and AC-8. Without a loaded domain, a wrapped string cannot resolve to Arabic, so the
mechanism is the criterion's substance and T-06 is only its guard.

**Gate**, measured behaviour rather than a flag: the production loader **registers successfully**, and a real
string bound to `mizzey-site` **resolves in Arabic through that loader** while the English source string remains
the control.

**`is_textdomain_loaded()` is not acceptance evidence.** On WordPress 7.1.2 `load_plugin_textdomain()` returns
true and the catalogue is materialised lazily, on the first lookup, so the flag can read false while the
mechanism works. An earlier version of this gate asserted the flag and failed a working feature. See
`research.md` section 6; it is evidence for 7.1.2 and not a general platform guarantee.

---

### T-04. Add the theme bootstrap

**Kind**: site-code (`mizzey-theme/functions.php`, new).

The theme has no `functions.php` today, and CoreX provides no `after_setup_theme` or theme-support abstraction to
use instead, so the file is created. It loads the theme's copy of the domain and does nothing else. Presentation
stays in `theme.json` and `style.css`; the file header says so, because the next person will be tempted.

**Test**: as T-03. Guard: **wp-guard**.

**Gate**: the theme domain loads with no fatal and no notice, in both languages.

---

### T-05. Generate and commit `mizzey-site.pot`

**Kind**: site-code (`mizzey-site/languages/mizzey-site.pot`).

Generate with `wp i18n make-pot` over `mizzey-site/` and `mizzey-theme/`, domain `mizzey-site`. It will be nearly
empty, because this engagement has no user-facing strings yet. That is correct and is the point: the file exists
so the first later slice adds a string rather than inventing a mechanism.

The command, recorded here so it is not guessed later:

```bash
wp i18n make-pot . mizzey-site/languages/mizzey-site.pot --domain=mizzey-site --include=mizzey-site,mizzey-theme
```

**Test**: T-06's check reads it. A `.pot` that does not parse fails there.

**Gate**: the file exists, parses, and names the `mizzey-site` domain.

---

### T-06. Write the hard-coded-text check

**Kind**: test-infra (`tools/tests/test_storefront_strings.py`).

**It goes in `tools/tests/`, not `tools/`.** `tools/*.py` is the `tooling` path class, which a `requirement` pull
request may not touch; `tools/tests/` is `test-infra`, which it may. Verified against the checker.

Scan `mizzey-site/src/` and `mizzey-theme/` for a user-facing string literal outside a translation function, and
report `path:line`. Narrow by design: ignore hook and option names, text domains, CSS classes, meta keys,
capability strings, URLs and anything with no letters. Judge only this engagement's code, never WordPress,
WooCommerce or CoreX. Fail closed on a file it cannot parse.

**Criterion**: this is the verification FR-007 requires for **AC-7**, the criterion that the storefront contains
no hard-coded user-facing text. T-03, T-04 and T-05 give a string somewhere correct to go; T-06 is what keeps it
there once later slices start adding strings.

**Test**: its own cases. A deliberately hard-coded string must fail with its file and line; the real tree must
pass; a correctly wrapped string must pass; and an unparseable file must fail rather than be skipped. The last
case matters: a check that silently skips is the failure mode the board-metadata check already had once.

**Gate**: the check fails when the error is reintroduced, proved by doing it, not by assertion. Guard:
**test-guard**.

---

## Phase C: the evidence

### T-07. Write the served-request scenario

**Kind**: site-tests (`mizzey-site/tests/integration/t21-bilingual-baseline.php`, new).

The first scenario in this harness to issue **real HTTP requests**, because a rendered document attribute cannot
be asserted honestly from an in-process language switch. Research.md records why: an in-process switch to Arabic
reports `dir="rtl"` with `lang="en-US"`, while a real Arabic request reports both correctly.

Assertions, each naming its criterion:

| # | Assertion | Criterion |
|---|---|---|
| 1 | `/` serves 200, English, `lang="en-US"`, no direction attribute | AC-1, AC-4 |
| 2 | `/` after an Arabic request in the same session is still English | AC-1 |
| 3 | `/ar/` serves 200 with `dir="rtl" lang="ar"` | AC-2, AC-3 |
| 4 | `/ar/shop/` serves the Arabic shop | AC-2 |
| 5 | each Arabic system page resolves to its own Arabic record, not the English one | AC-2 |
| 6 | a translated pair shares one translation group with the English record as source | AC-5 |
| 7 | no Arabic-specific template file exists in the theme, and both languages render | AC-6 |
| 8 | a string with an Arabic translation renders in Arabic on the Arabic storefront | AC-8 |

**Record, do not assert**: that `/checkout/` resolves to the cart in both languages, which is WooCommerce
redirecting an empty cart and not multilingual behaviour. Recording it stops the checkout slice rediscovering it.

**Test**: the scenario is the test. It runs on a scripted clean baseline and leaves the runtime clean. Guard:
**test-guard**.

**Gate**: eight assertions pass from a reset runtime, repeatably.

---

### T-08. Write the browser acceptance matrix, unexercised

**Kind**: feature-spec (`specs/002-bilingual-platform-baseline/verification.md`).

Write the six-browser, two-direction matrix from clarification C-5, with **every row marked not exercised** and
the reason: staging does not exist, and it is owned by #244.

**Do not** mark any row verified. **Do not** substitute a user-agent string, a headless check or a CSS inspection
for a browser and call it browser acceptance.

**Test**: the matrix is a record, so its test is review. **docs-guard** on the diff.

**Gate**: AC-9 is reported as not verified, with the reason and the owning PBI.

---

### T-09. Write the verification record

**Kind**: feature-spec (`verification.md`).

Record what was run, what passed, and the three states separately: workflow complete, technically verified,
contractually accepted. AC-1 to AC-8 can reach technically verified. AC-9 cannot. **No criterion in this feature
reaches contractually accepted**, which happens only through the Acceptance and UAT Plan.

**Test**: review. **docs-guard**.

**Gate**: the record states each criterion's state and nothing is overstated.

---

## Phase D: close out

### T-10. Run the full gate

**Kind**: no file change.

House rules, scope trace, repo checks, the trusted checker, the discovery gate, the tooling tests, the integration
suite on a clean baseline, PHP lint, and the named guard skills. **Run them after committing**, because
`house_rules.py` reads the committed diff and sees nothing when run before.

**Gate**: every check reports zero problems against the committed diff.

---

### T-11. Open the pull request

**Kind**: no file change.

One `requirement` pull request citing the five register rows, with the spec, plan, research, checklist, tasks and
verification record, the production code, the check and the scenario. State plainly what was configuration and
what was code, and that AC-9 is not verified.

**Gate**: CI green, and the three states reported separately in the pull request body.

---

## What no task does

No task writes a product price, a stock value or any product data. No task adds a synchronisation layer for
metadata, stock or price. No task uses a SKU as multilingual identity. No task edits CoreX, `stack.lock.json`,
`corex.lock`, another feature's spec, or anything under `app/wp/wp-content`. No task creates a backlog item, an
issue or a Project change. No task touches repository visibility.

## Dependency order

```text
T-01 ─┬─> T-02 ──┐
      │          ├─> T-07 ─> T-09 ─> T-10 ─> T-11
T-03 ─┴─> T-04 ──┤          ▲
      └─> T-05 ──┴─> T-06 ──┘
                    T-08 ───┘
```

T-01 first: without rewrite configuration nothing downstream can be proved. T-08 is independent of all of it and
can be written at any point, because it records what cannot yet be run.
