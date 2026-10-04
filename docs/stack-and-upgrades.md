# Stack versions and the upgrade regression path

## Governance-model limitation: the compatibility pin and the tested runtime are one field

Recorded 4 October 2026. The model has no single place to say "we pin the 7.1 line, and what we actually tested
was 7.1.2".

`tools/repo_checks.py` requires `stack.lock.json`'s `components.wordpress.version` to equal `corex.lock`'s
`wordpress` entry exactly. `corex.lock` pins `7.1`, which is the line `tools/corex-sync.mjs` installs. The runtime
reached `7.1.2` by WordPress's own patch update, and every probe and every integration scenario ran against
`7.1.2`. The equality constraint means that for WordPress alone, `version` carries the **compatibility pin**,
while for every other component `version` carries the **exact tested version**. One field, two meanings,
component-dependent.

**What was done instead of changing anything.** The WordPress component now carries `compatibility_pin` (`7.1`,
equal to `corex.lock`) and `observed_runtime` (`7.1.2`), so both facts are recorded and neither is misstated.
`version` is unchanged, so `repo_checks.py` stays green and `corex.lock` is untouched.

**`corex.lock` was deliberately not changed.** Changing it would make the checker green by moving the pin, which
is a different decision from recording an observation, and it is not this file's job to make. The separate point
that `corex.lock` is a `version-lock` path the `governance` category may not touch is true, and it was verified
against the checker rather than read from it:

```
SENSITIVE corex.lock (version-lock)
PR: internal:governance may not change corex.lock (version-lock)
```

That is a path-policy fact, not a reason to reclassify the work. A category is chosen for what the change *is*,
never for the paths it would unlock.

**How to close it properly, when it matters.** Separate the two facts in the checker: compare `corex.lock` with
`compatibility_pin`, and let `version` mean the exact tested version for every component including WordPress.
That is a small change to `tools/repo_checks.py` and a `governance-control` path, so it belongs in its own
`internal:governance` PR with its own test. It is worth doing when the patch level next drifts, which for
WordPress it will. Deliberately not bundled here, because this PR exists to record an observation.

Nothing in the delivery depends on it. The versions the tests ran against are recorded in each verification
record, and now in `observed_runtime`.

## The two lock files

| File | Holds | Read by |
|---|---|---|
| `corex.lock` | The CoreX tag and commit, WordPress version, PHP floor | `tools/corex-sync.mjs`, which builds `../app/` from it |
| `stack.lock.json` | Every component's tested version and test status, including WooCommerce, WPML, WCML, Bosta and, once selected, the payment plugin | People and `tools/repo_checks.py` |

`tools/repo_checks.py` fails when the CoreX or WordPress entries in the two files disagree.

## What "tested" means

A `tested` status means the discovery probes or the automated tests ran against that version in the local
disposable runtime on the recorded date. It is **evidence for that version only**. It is not a production
compatibility guarantee. Production differs in PHP build, database engine, caching, hosting and the real licences.
`installed-untested` means present in the runtime and not exercised. `unverified` means not established.
`not-selected` means no choice has been made yet.

Known gaps at 22 September 2026: the database engine behind the local runtime is not recorded, and no payment
plugin is selected.

## Regression path for any upgrade

WordPress, WooCommerce, CoreX, WPML, WCML, a payment or shipping plugin, or PHP: each is upgraded on its own branch,
one component per PR, classified `internal:security-maintenance` (or `requirement` when the upgrade is needed for a
contracted row).

1. **Read the changelog** for every version skipped. Note anything touching HPOS, Cost of Goods Sold, the Store API,
   checkout blocks, order statuses, translation of products and orders, or stock.
2. **Back up** the local runtime database. Production upgrades happen only after staging passes.
3. **Change the version**: `corex.lock` for CoreX or WordPress, the plugin itself for the others. Rebuild with
   `node tools/corex-sync.mjs`, then `wp corex migrate` for CoreX.
4. **Run the automated suites**: discovery gate and tests, tool tests, the Pest suite, `php -l`.
5. **Re-run the probes that cover the component** (see `discovery/data/probes.json`), for example P-016 and P-017
   for WooCommerce cost, P-006, P-019 and P-020 for WPML/WCML, and P-012 for Bosta. Probes run only against the
   disposable runtime.
6. **Bilingual storefront pass**: English and Arabic, right to left, product, cart and checkout. Admin English only.
7. **Record** the new version, status and date in `stack.lock.json`, and any behaviour change in `DECISIONS.md`.
8. **Revert path**: the PR is one component, so reverting it restores the previous version. For plugins, keep the
   previous plugin zip until the upgrade has run on staging.

Security releases follow the same path, compressed in time, never skipped.
