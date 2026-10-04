# Implementation Plan: Bilingual platform baseline

**Branch**: `002-bilingual-platform-baseline` | **Date**: 4 October 2026 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/002-bilingual-platform-baseline/spec.md`, PBI #241.

## Summary

Five P1 S1 register rows ask for a bilingual storefront with English default and Arabic fully supported right to
left, no hard-coded storefront text, and a browser baseline. **Most of it already works.** Measurement established
that once the environment has rewrite configuration, a real `/ar/` request serves
`<html dir="rtl" lang="ar">` and the English root serves `<html lang="en-US">`, from the same templates, with
**zero lines of code written**.

So this plan is deliberately small, and most of it is not production code:

| Work | Kind | Why it is needed |
|---|---|---|
| Rewrite and permalink configuration in the test baseline | Test harness | Without it no language-prefixed URL resolves, so none of the criteria can be verified by a served request |
| Arabic records for the WooCommerce system pages | Platform configuration | Clarification C-2. Without them the Arabic storefront falls back to English page records |
| Load the `mizzey-site` text domain for the site plugin and the theme | Production code, small | Nothing loads it today. CoreX loads only its own `corex` domain, hardcoded |
| A check that fails on hard-coded user-facing storefront text | Test infrastructure | FR-007, the repeatable verification constitution M-7 requires for AC-7 |
| A served-request integration scenario | Test | The evidence for AC-1 to AC-8 |

**No multilingual synchronisation of any kind is built.** Not for metadata, not for stock, not for price. The
product-cost pilot, A11, A12, B1 and the price matrix each concluded against it, and nothing in these five rows
asks for it.

## Technical Context

**Language/Version**: PHP 8.3.6. Python 3.10 for the check, matching the existing checks under `tools/tests/`.

**Primary Dependencies**: WordPress 7.1.2, WooCommerce 11.1.0, WPML 4.9.7, WPML String Translation 3.5.4,
WCML 5.5.7, CoreX 0.42.0. All as recorded in `stack.lock.json`, which is what this plan was verified against (M-8).

**Storage**: No new tables, no new options, no new post meta. The feature uses WPML's existing language tables and
WordPress's existing page records.

**Testing**: the existing integration harness, `mizzey-site/tests/integration/run.py`, on a scripted clean
baseline. A new scenario issues **real HTTP requests**, which is new for this harness and is the only way to
assert a rendered document attribute honestly.

**Target Platform**: WordPress on Apache. Production hosting is undecided: OD-27 is open and nothing here assumes
a host, a domain or a URL.

**Project Type**: WordPress client site. A theme and a site plugin, on a framework that provides nothing for this
feature.

**Performance Goals**: none stated by the five rows, and none invented.

**Constraints**: CoreX is not modified. The admin stays English. No Arabic-specific template is introduced.

**Scale/Scope**: two languages, one text domain, five system pages, one new scenario, one new check. Nine criteria.

## Constitution Check

*GATE: passed before Phase 0. Re-checked after Phase 1 design, below.*

- [x] **M-1 Scope**: all five ids are P1, an obligation scope. Each criterion restates its row's wording. The one
      place this was tested hardest: AC-7 originally carried "and a repeatable check proves it", which NFR-04a
      does not say. Clarification C-4 moved the check out of the criterion and into FR-007, so the criterion now
      states only what the row obliges.
- [x] **M-2 Open items**: no `open-items.json` entry touches these five ids; CX-01 and PRE-09 do not reach here.
      OD-27 is cited in the spec as bearing on evidence, and this plan does not resolve it: it configures the
      **test** environment and decides nothing about production hosting.
- [x] **M-3 P1-E**: no P1-E id is in scope. Nothing here is ERP dependent.
- [x] **M-4 Native first**: every capability that works natively is left alone. The only production code is text
      domain loading, justified by the Native coverage row recording that nothing loads it, confirmed twice: no
      `load_theme_textdomain` or `load_plugin_textdomain` in this engagement's code, and none in CoreX for any
      domain but its own.
- [x] **M-5 Three lists**: nine contractual criteria; two optional safeguards (S-1, S-2) needing approval; six
      future or Option C items recorded and not built.
- [x] **M-6 Stage**: S1 for all five rows, unchanged. Running first is delivery order, recorded in the spec's
      Delivery stage section.
- [x] **M-7 Tests**: every task below names its test. No money, stock, permission, refund, payment or
      shipment-state path is touched by this feature, so none needs that class of coverage here.
- [x] **M-8 Versions**: the versions above, from `stack.lock.json`. The runtime is evidence for those only.
- [x] **CoreX**: nothing under `app/corex` is edited. The inspection below is read-only, and where CoreX lacks a
      capability the client site supplies it rather than the framework being patched.
- [x] **Guard Gate**: **wp-guard** on the text domain loading and the theme file, **test-guard** on the new
      scenario and the new check, **docs-guard** on this spec and plan. **woo-guard** has nothing to review: no
      order, product, cart, checkout or money code is written. **clean-code-guard** on the production code.

## Phase 0: what the platform and the framework actually provide

Full measurements in [research.md](research.md). The four findings that shaped this plan:

### 1. CoreX v0.42.0 provides nothing for this feature, and that is a finding, not a complaint

Inspected rather than assumed, and the load-bearing absences were each confirmed by a second independent search:

| Looked for | Found |
|---|---|
| An i18n service or driver interface | **None.** `docs/internal/COREX-FRAMEWORK.md:648` documents an `I18nHandler` that wraps WPML and Polylang. It was never built: no such class, no `src/I18n/`, no `config/i18n.php`, and `MWP_I18N_DRIVER` in `.env.example` is an orphan key nothing reads |
| A language, locale, translation or direction hook | **None** among CoreX's 5 actions and 22 filters |
| `load_theme_textdomain` | **Zero occurrences** in any CoreX PHP file |
| `after_setup_theme`, `add_theme_support` | **Zero occurrences** |
| A template hierarchy or view layer | **None.** No `template_include`, `locate_template` or `get_template_part`. Rendering is deferred entirely to WordPress block templates and server-rendered blocks |
| RTL stylesheet handling | **None.** `wp_style_add_data` is never called, and `Corex\Assets\Style::enqueue()` has no RTL option |
| Admin locale handling | **None.** CoreX does not force, override or constrain the admin language. The admin follows WordPress |
| A text domain for a client's own strings | **Not loaded.** CoreX loads one hardcoded domain, `corex`, at `CoreServiceProvider.php:127`. Its CLI stubs declare a client `Text Domain:` header and no generated code ever loads it |

**Consequence for this plan**: the client site loads its own text domain. There is no framework seam to use and
none is added to CoreX. This is the gap M-4 requires before custom code, and it is recorded in the spec.

### 2. Direction and language tag are already correct on a real request

Measured against served HTTP, not inferred from an in-process switch:

| Request | Status | Document element |
|---|---|---|
| `/` | 200 | `<html lang="en-US">`, no direction attribute |
| `/shop/` | 200 | `<html lang="en-US">` |
| `/ar/` | 200 | `<html dir="rtl" lang="ar">` |
| `/ar/shop/` | 200 | `<html dir="rtl" lang="ar">` |

**AC-3, AC-4 and AC-6 are met natively.** Nothing is written for them. They get tests, not code.

This also settled the one measurement the spec left open. An **in-process** `wpml_switch_language` to Arabic
emitted `dir="rtl" lang="en-US"`, the direction following and the language tag lagging. On a **real** Arabic
request the tag is `lang="ar"`. The lag was an artifact of switching mid-request, not a defect, which is why it
was recorded as a measurement to repeat rather than as a finding.

### 3. The environment, not the platform, was what stopped Arabic resolving

WPML uses directory negotiation (`language_negotiation_type=1`, `directory_for_default_language=0`), which needs
the language segment at the root of the path. The runtime served PATHINFO permalinks with no `.htaccess`, so
`/ar/` reached Apache as a missing directory and `/index.php/ar/` reached WordPress as a missing post.
`?lang=ar` correctly does nothing, because the negotiation type is directories and not parameters.

`mod_rewrite` is loaded in the local Apache and the vhost sets `AllowOverride All`, so pretty permalinks plus a
standard `.htaccess` make real Arabic URLs serve, which is how the table above was produced.

**One wrinkle the plan has to carry**: `got_mod_rewrite()` returns false in this runtime because PHP runs as CGI
and `apache_get_modules()` is unavailable, so **WordPress refuses to write `.htaccess` itself**. The baseline
script therefore writes it, rather than relying on `wp rewrite flush --hard`, which silently does nothing here.

### 4. Arabic URLs currently fall back to English records

`/ar/cart/` serves, and serves page 6, the English cart. No page has an Arabic counterpart. That is the gap
clarification C-2 assigns to this feature: the translated system-page records, and nothing about their content.

**One apparent defect was bounded and dismissed.** `/ar/checkout/` resolves to the Cart page, which looked like a
language-resolution defect. English `/checkout/` resolves to the Cart page too: it is WooCommerce redirecting an
empty cart, identical in both languages, and not multilingual behaviour at all. It is recorded so nobody
rediscovers it as a bug.

## Phase 1: design

### Where the strings mechanism sits

Clarification C-3 binds each kind of string to the mechanism that can carry it. In code that means one small
class, loaded early, doing one thing:

- The site plugin loads `mizzey-site` from `mizzey-site/languages/`.
- The theme loads the same domain from `mizzey-theme/languages/`, because both headers already declare
  `Text Domain: mizzey-site` and a second domain would double the work for every later slice.
- Nothing else. No locale filter, no direction handling, no language switcher, no admin locale interference.

**Why not rely on WordPress just-in-time loading.** WordPress can load a domain on first use without an explicit
call, and for a plugin in its ordinary location it usually does. This site is not in an ordinary location: CoreX
mounts the client under `sites/<client>` through a junction, and the CoreX inspection found its own generated
stubs declare a text domain that nothing loads, which is the same latent problem. An explicit load is two lines
and removes the question.

**Where it hooks.** `CostTranslationSync` is registered on `plugins_loaded` at priority 20 today, which already
avoids the race that `Corex::onReady()` exists to solve, because it touches no CoreX container service. The text
domain loading touches no container service either, so it uses `init` for the plugin domain and
`after_setup_theme` for the theme domain, the ordinary WordPress hooks. **`Corex::onReady()` is not needed here**,
and is named in research.md so that a later slice that does use the container knows it exists and why.

### The hard-coded-text check

FR-007 needs a repeatable check that names a file and line. It goes in `tools/tests/`, not `tools/`:
`tools/*.py` is the `tooling` path class, which a `requirement` pull request may not touch, while `tools/tests/`
is `test-infra`, which it may. This is the same constraint that moved the backlog totals logic into a test, and it
was verified against the checker rather than assumed.

The check scans this engagement's storefront PHP for a user-facing string literal that is not inside a
translation function, and reports `path:line`. It is deliberately narrow:

- It looks only at `mizzey-site/src/` and `mizzey-theme/`, which is what this engagement writes. It does not
  judge WordPress, WooCommerce or CoreX.
- It ignores anything that is not user-facing: hook and option names, text domains, CSS classes, meta keys,
  capability strings, URLs, and anything with no letters.
- It fails **closed** on a new file it does not understand, rather than passing silently.

Its own tests assert it catches a hard-coded string, passes clean code, and keeps passing when a string is
correctly wrapped. The baseline it protects is currently empty, which is the cheapest possible moment to add it.

### Structure Decision

Real paths, each inside a path class a `requirement` pull request may touch:

```text
specs/002-bilingual-platform-baseline/        feature-spec
├── spec.md, plan.md, research.md
├── checklists/requirements.md
├── tasks.md
└── verification.md                           written at the verify step, not now

mizzey-site/                                  site-code
├── mizzey-site.php                           registers the localisation bootstrap
├── src/I18n/Localisation.php                 new: loads the text domain. Nothing else
└── languages/mizzey-site.pot                 generated, committed

mizzey-theme/                                 site-code
├── functions.php                             new: loads the theme's copy of the domain
└── languages/                                the theme's .mo files

mizzey-site/tests/integration/                site-tests
├── t21-bilingual-baseline.php                new: served-request scenario for AC-1 to AC-8
└── baseline/reset-runtime.sh                 gains permalink and .htaccess setup, and the Arabic page records

tools/tests/test_storefront_strings.py        test-infra: the FR-007 check and its own tests
```

**Not touched**: `app/corex` and anything under it, `app/wp/wp-content`, `stack.lock.json`, `corex.lock`,
`docs/scope/`, the CI workflow, and every other feature's spec.

### Re-check of the Constitution gate after design

All ten still hold. The design added no register id, no criterion and no behaviour beyond the five rows; it moved
one piece of work out of `tools/` to respect the path policy; and it reduced the production code to the one thing
measurement proved was missing.

## Phase 2: tasks

In [tasks.md](tasks.md). Ordered so that each task is verifiable before the next depends on it, and so the
configuration work, which is most of the feature, lands before the code that assumes it.

## Complexity Tracking

No constitution violation to justify. The one thing worth recording is a **deliberate** decision not to build:

| Considered | Rejected because |
|---|---|
| An i18n abstraction in the client site, mirroring the `I18nHandler` CoreX documents but never built | Nothing in the five rows asks for one, and no second translation plugin is in scope. It would be a framework for one caller. If CoreX later ships the seam, this feature has nothing to unpick |
| Loading a second text domain for the theme | Both headers already declare `mizzey-site`. Two domains means two `.pot` files and two chances for a later slice to pick the wrong one |
| An RTL replacement stylesheet | The baseline has no stylesheet to replace, and direction already comes from the platform. A later design slice decides this, with logical CSS properties the expected answer |
| Writing the string check as `tools/i18n_strings.py` | The path policy forbids a `requirement` pull request from touching `tools/*.py`. Verified against the checker, not assumed |
