# Implementation Plan: Product cost captured from day one

**Branch**: `001-product-cost-capture` | **Date**: 2026-09-22 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/001-product-cost-capture/spec.md`

## Summary

Capture product and variation cost from the first day (ADM-27, RPT-11), and carry cost in the initial catalogue
import (MIG-13), using WooCommerce's native Cost of Goods Sold. The deliverable is configuration plus integration
tests that prove AC-1 to AC-5 in the disposable runtime. Custom code or configuration files are added only when a
contract test fails. AC-6 (staff visibility) waits on CX-01, and AC-7 (cost basis) waits on OD-12.

## Technical Context

**Language/Version**: PHP 8.3.6 (WordPress 7.1), Python 3.10 for the test runner

**Primary Dependencies**: WooCommerce 11.1.0 (Cost of Goods Sold, CSV importer, REST v3 as a product write
channel, Store API as a public read surface only), WPML 4.9.7,
WCML 5.5.7

**Storage**: WordPress post meta (`_cogs_total_value`, `_cogs_value_is_additive`), HPOS order item meta
(`_cogs_total_value`), MySQL 8.3.0 locally

**Testing**: WP-CLI integration scripts under `mizzey-site/tests/integration/`, run by `run.py` (research R-3)

**Target Platform**: the disposable runtime `../app/wp` (WAMP, Windows), built by `tools/corex-sync.mjs`. Never
production

**Project Type**: WordPress site (CoreX client site): configuration and tests, no application code expected

**Performance Goals**: none specific. Cost capture adds no request-time work

**Constraints**: native first (M-4); no CoreX internals edited; the admin is English only (ADM-159); no stack lock
change in this PR (governance-control path)

**Scale/Scope**: a test catalogue of one simple and one variable product (two variations), one Arabic translation,
two orders (t04 English, t06 Arabic) plus one for the customer order view in t08, one import fixture

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

- [x] M-1 Scope: ADM-27, RPT-11 and MIG-13 are obligation rows, and every behaviour here is supported by their wording
- [x] M-2 Open items: CX-01 and OD-12 are cited, and AC-6 and AC-7 stay pending. Nothing here resolves them
- [x] M-3 P1-E: not applicable. No row is P1-E
- [x] M-4 Native first: one custom class, `MizzeySite\Catalogue\CostTranslationSync`, justified by failing AC-3 cases in t11 (REST, WP-CLI and front-end saves never reach translations). The configuration-only attempt (wpml-config.xml) was tested first and removed as ineffective
- [x] M-5 Three lists: the spec keeps contract, safeguards (S-1, S-2) and future items apart
- [x] M-6 Stage: S1 for all three rows, unchanged
- [x] M-7 Tests: every task in tasks.md names its test. Cost (money) paths are covered by the integration scripts
- [x] M-8 Versions: verified against WordPress 7.1, WooCommerce 11.1.0, WPML 4.9.7, WCML 5.5.7, PHP 8.3.6
  (`stack.lock.json`), plus MySQL 8.3.0 observed
- [x] CoreX: no CoreX framework internals are edited
- [x] Guard Gate: test-guard on the test scripts; woo-guard and wp-guard on any contingent configuration

Post-design re-check: unchanged, all pass.

## Project Structure

### Documentation (this feature)

```text
specs/001-product-cost-capture/
├── spec.md
├── plan.md              # this file
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/
│   └── cost-exposure.md # where cost must and must not appear
├── checklists/requirements.md
└── tasks.md             # /speckit-tasks
```

### Source Code (repository root)

```text
mizzey-site/
├── mizzey-site.php                    # registers the sync on plugins_loaded
├── src/Catalogue/CostTranslationSync.php  # the only production code (AC-3 gap)
├── wpml-config.xml                    # declares the stored cost key as copied to translations (FR-008, repair of 6 October 2026)
└── tests/integration/
    ├── run.py                         # runs each scenario with wp eval-file, collects JSON verdicts
    ├── _bootstrap.php                 # feature flag (FR-001 check), fixtures, cleanup, verdict
    ├── _workflows.php                 # translation methods and cost channels, each in its real request context
    ├── baseline/reset-runtime.sh      # scripted clean runtime (destructive, disposable only)
    ├── baseline/setup.php             # WooCommerce, WPML and WCML configuration for the baseline
    ├── baseline/admin-visit.php       # one wp-admin visit, which is when WPML parses plugin config files
    ├── t02-simple-cost.php            # AC-1
    ├── t03-variation-cost.php         # AC-1, zero vs blank
    ├── t04-order-line-snapshot.php    # AC-2
    ├── t07-csv-import-cost.php        # AC-4
    ├── t08-no-public-exposure.php     # AC-5
    ├── t09-staff-visibility.php       # AC-6 fact-finding only, no verdict
    ├── t11-cost-sync-matrix.php       # AC-3: translation methods x product types x channels (replaces t05, t06, t10)
    └── fixtures/                      # import CSV, and the temporary front-end endpoint used by crud-web
```

**Structure Decision**: one production class in `src/Catalogue/`, plus integration scenarios in the plugin's
`tests/` folder. Each script creates its own fixtures and removes them, and restores the feature flag to the value
it had before the run. The runtime baseline is scripted so a result can be reproduced.

## Complexity Tracking

No constitution violations.

Deviation from the Spec Kit plan workflow: step 4 (agent-context update in `CLAUDE.md`) is not run. Under the
Mizzey governance `CLAUDE.md` is a fixed pointer, and `tools/repo_checks.py` fails if a Spec Kit managed block is
added. The plan is referenced from `PROGRESS.md` instead.
