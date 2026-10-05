# Implementation plan: development and staging environments

Feature `005-local-staging-environment`, PBI #244. Rows NFR-08 and NFR-09.

## Summary

Build a staging and demonstration runtime beside the development one, on the developer's machine, from the
installed programs and with nothing else shared. Give it one tool that builds, starts, resets, seeds, backs up,
restores and tests the restore, and three harnesses that use it: a browser matrix, a scripted staging pass and a
parallel-request harness. Prepare the Cloudflare Tunnel up to the account sign-in. Provision nothing for
production.

## Constitution Check

| Principle | How this plan meets it |
|---|---|
| M-1 scope traced | Two rows, NFR-08 and NFR-09. Each criterion is read against its row's words. The safeguards are listed as safeguards and approved in D-12 |
| M-2 contradictions | None touches these rows. Nothing is resolved here |
| M-3 P1-E | No P1-E row is traced. Staging connects to no ERP, and nothing measured on it is ERP evidence |
| M-4 native first | WordPress's environment type, mail filter and outbound block; the database's own dump and load; WooCommerce's cash on delivery. No backup plugin: the gap is recorded in the spec |
| M-5 three lists | Contractual criteria, safeguards S-1 to S-5, and not-built items are separate in the spec |
| M-6 stage fixed | S1 for both rows. Staging is delivered ahead of the hosting decision; the stage does not move |
| M-7 evidence | The restore is performed and compared. Every claim has a command that repeats it. Statuses are `final`, `provisional` and `pending OD-27` only |
| M-8 versions | Staging copies the tested versions from development. No version changes. `stack.lock.json` is untouched |
| M-9 languages | The seed is bilingual. The admin stays English |
| M-10 missing inputs | OD-27 and OD-14 are launch gates. Provider accounts are stood in for by the test payment method and by refusing outside requests |

**Role Gate.** Client site mode. Nothing under `../app/corex` or in CoreX is edited: staging links to the pinned
checkout read-only. Nothing in `mizzey-site/src` or `mizzey-theme` changes: this feature adds no product code.

## Design decisions

| Decision | Chosen | Why, and what was rejected |
|---|---|---|
| Where staging code lives in the repository | `mizzey-site/tests/staging/` | It is test infrastructure, like `tests/integration/baseline/`, and that path class is open to a requirement change and excluded from the deployable build. A new top-level directory is not allowed without a policy change |
| Web server | A second Apache process from the installed binaries, own configuration, `127.0.0.1:8088` | A virtual host in the development server would share its process, logs and restarts, and editing the machine's server configuration is not this repository's to do. PHP's built-in server is single-threaded on Windows and cannot produce the simultaneity B6 needs |
| Address | The loopback address, and public host names only from an allow-list | A Host header from outside must never choose the address WordPress builds links for |
| Code on staging | `git archive` of a named commit, tests removed | "What is reviewed is what was merged". A link to the working tree would show unmerged work |
| CoreX on staging | Linked to the pinned checkout | It is fixed by `corex.lock` and never edited. Copying it would duplicate its vendor tree for no isolation gain |
| Database isolation | Own database, own account with one grant | The staging account cannot name the development database at all, which is stronger than a different prefix |
| Baseline | The same three baseline scripts development runs | One source for the configuration both runtimes get, so they cannot drift |
| Seed | Four steps, each its own process, sources before translations | Finding A11: creating and translating in one process is the measured corruption trigger |
| Mail | `pre_wp_mail` capture to files, and a dead SMTP setting as a second stop | No mail catcher service to install or leave running |
| Search engines | `X-Robots-Tag` from the web server, not WordPress's setting | WordPress's setting also disables the sitemap and rewrites robots.txt, which #242's check has to see. Found on the first run |
| Tunnel gate | Basic sign-in required whenever tunnel headers are present | cloudflared connects from the machine itself, so "local" alone would admit everyone |
| Backup | Dump and archive with a manifest and a data fingerprint | A restore can then be compared, not just reported |
| Restore test | Destroy for real, record the damage, restore, compare | NFR-08 asks for a restore procedure. An unperformed one is a guess |
| Secrets | Generated at build into `../app-staging`, read from an option file | Never in the repository, a command line, a log or a traceback |

## Structure

```text
mizzey-site/tests/staging/
  staging.py                     build, db-create, up, down, status, reset, seed, backup, restore, restore-test, tunnel-config
  httpd-staging.conf.tmpl        the staging web server
  wp-config-staging.php.tmpl     the staging WordPress configuration
  cloudflared.yml.tmpl           the tunnel, filled in after the sign-in
  mu-plugins/mizzey-staging.php  marker, mail capture, test payment method
  fingerprint.php                the data fingerprint the restore test compares
  seed/10-users.php .. 40-orders.php
  browser-matrix.cjs             both directions in real browser windows
  staging-pass.cjs               the scripted pass for #242 and #246
  concurrency.py                 last-units and read-load
docs/staging-environment.md      the operating guide and the restore procedure
```

## Risks

| Risk | Handling |
|---|---|
| A destructive command reaches development | Guards on directory, database, account, prefix, host and environment type, read from the running configuration; an explicit confirmation variable |
| Staging is mistaken for the live site | Marker on every screen, different admin colour, title prefix |
| Staging leaks to the public or a crawler | Listens on the loopback only; tunnel requests must sign in; `noindex` header; host allow-list |
| A real customer is emailed | No real customer exists in the data, and mail cannot leave |
| The harness results are read as acceptance | Each tool states what it is not. Findings are recorded against the owning PBI and nothing is corrected here |
