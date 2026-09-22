# Quickstart: validate product cost capture

## Prerequisites

- The disposable runtime is built: `node tools/corex-sync.mjs`, with WordPress, WooCommerce, WPML and WCML at the
  versions in `stack.lock.json`, and languages `en` (default) and `ar`.
- WP-CLI on the PATH. Python 3.10.
- Never run against production.

## Configuration (the deliverable for FR-001)

```bash
wp --path=../app/wp option update woocommerce_feature_cost_of_goods_sold_enabled yes
```

The same setting goes into the go-live runbook for staging and production, before the first live order (optional
safeguard S-1 adds a scripted check).

## Run the integration scenarios

```bash
python mizzey-site/tests/integration/run.py --wp ../app/wp
```

Each scenario prints one JSON line (`test`, `criterion`, `pass`, `observed`), and the runner exits non-zero if any
contract scenario fails. t09 reports facts with `pass: null`. Scripts clean up their fixtures and restore the
feature flag.

## Expected outcome

| Scenario | Expect |
|---|---|
| t02 to t04 | pass (every scenario also checks FR-001 enablement) |
| t05, t06 | pass, or a recorded WPML/WCML gap. A gap blocks AC-3 until it is fixed |
| t07 | pass; blank cost stays blank |
| t08 | pass: no cost in any visitor or customer response |
| t09 | facts only, for the CX-01 discussion |
| t10 | fails until decision WPML-1 is made (programmatic cost edits do not reach translations) |
