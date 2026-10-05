# Verification record: development and staging environments

Feature `005-local-staging-environment`, PBI #244. Two register rows, NFR-08 and NFR-09, both P1 and S1.

**Three states, reported separately, per constitution M-7:**

| State | Where this stands |
|---|---|
| **Workflow complete** | Yes for development and staging: spec, plan, tasks, implementation, verification. **Not for production**, which is not provisioned |
| **Technically verified** | **AC-244-01 to AC-244-11**, on the developer's machine at the recorded versions. AC-244-12 and AC-244-13 are `pending OD-27` and are not started |
| **Contractually accepted** | **No criterion.** Acceptance happens only through the Acceptance and UAT Plan MS-UAT-2026-027 |

**What is not claimed.** No production environment exists. No tunnel has been opened, so staging has no public
address and no one outside the machine has seen it. Staging lives on the developer's machine by owner decision
(D-10, D-12), which the client has not confirmed.

Versions this is evidence for, and no others (M-8): WordPress 7.1.2, WooCommerce 11.1.0, WPML 4.9.7, WPML String
Translation 3.5.4, WooCommerce Multilingual 5.5.7, CoreX 0.42.0, PHP 8.3.6, MySQL 8.3.0, Apache 2.4.59, on
Windows. Staging ran the code of `main` at `5f161d9` (`evidence/staging-build.json`).

## Criteria

| # | Criterion, in short | How it was checked | Result |
|---|---|---|---|
| AC-244-01 | Development and staging both exist and each is reachable | `staging.py up`; then `http://127.0.0.1:8088/` and `http://mizzey.local/` requested in the same minute | **PASS.** Both answer 200 |
| AC-244-02 | Staging is separate: server, directory, database, account, prefix, keys, uploads | `staging.py db-create` lists what the staging account can see; `wp config list` on each runtime | **PASS.** The account sees exactly `mizzey_staging`. Development reports database `mizzey`, prefix `mz_`, type `local`, no staging plugin, and was unchanged throughout |
| AC-244-03 | Staging runs a named commit | `BUILD.json` | **PASS.** `origin/main`, `5f161d9`, `merged_to_main: true` |
| AC-244-04 | Destructive commands refuse the wrong environment | Each run without the confirmation; the development reset pointed at the staging tree; a restore from a damaged set | **PASS.** `reset`, `restore` and the concurrency harness refuse without `MIZZEY_CONFIRM_STAGING=yes`; the development reset refuses the staging tree; a set whose archive was altered is refused before anything is dropped, and staging still answered |
| AC-244-05 | Invented data only, rebuilt to the same state by one command | Three resets; the fingerprint counts of the second and third compared | **PASS.** Both: 32 products, 12 variations, 12 orders, 2 refunds, 3 accounts, 123 translation records, 83 tables. The translation step verified every pair as one group with one SKU, 0 problems |
| AC-244-06 | No message leaves, no outside request is made | A test message through `wp_mail`; the messages the seed itself caused; the configuration | **PASS.** The test message returned true and was written to the capture folder with its recipient and subject; the seed's order and account messages are there too. `WP_HTTP_BLOCK_EXTERNAL` is on, and a direct `mail()` is pointed at a dead port |
| AC-244-07 | Every screen is marked; closed to the public and to search engines when reachable from outside | The marker searched for in the home, Arabic home and sign-in pages and on the admin toolbar; requests carrying tunnel headers | **PASS.** Marker present on all. A forwarded request with no sign-in: 401. Wrong password: 401. Signed in, for an allowed host name: pages whose links are HTTPS on that name. A host name not on the list: the local address. Every response carries `X-Robots-Tag: noindex, nofollow, noarchive` |
| AC-244-08 | Backup of database and media with manifest and checksums | `staging.py backup` | **PASS.** A dated set with `database.sql.gz`, `uploads.tar.gz` and a manifest holding sizes, SHA-256 checksums, the build and the data fingerprint |
| AC-244-09 | Retention | The eighth backup | **PASS.** "kept 7 of 8 sets": the oldest was removed |
| AC-244-10 | A restore performed end to end and shown to be the same | `staging.py restore-test`, three recorded runs | **PASS on the final run**, and see below for all three |
| AC-244-11 | The procedure written from the performed restore | `docs/staging-environment.md`, "Backup, retention and restore" | **PASS** |
| AC-244-12 | A production environment | | **Not started.** `pending OD-27` |
| AC-244-13 | Production backup and restore | | **Not started.** `pending OD-27` |

## The restore, in full

The restore test takes the data fingerprint and the site's answers, backs up, drops the database and deletes
the media, records the damage, restores from the set, and compares. Each run wrote a record.

| Run | Result | What happened |
|---|---|---|
| First attempt, no record | **Failed, and staging was down** | The dump was handed to the database client still compressed, so nothing loaded. The backup set itself was sound: staging was restored from it by hand once the fault was corrected, and the data matched |
| `restore-test-20261005-165211.json` | **PASS** | Before and after identical. After the destruction: 0 tables, 0 media files, every address landing on the installation screen. Restored in under ten seconds |
| `restore-test-20261005-173450.json` | **FAIL** | One figure differed: a total row count that included the tables WordPress rewrites on any request, which had grown by about a hundred rows between the reading and the dump. The checksum over the 74 stable tables, every count and every media file were identical. **The fault was in the comparison, not in the restore** |
| `restore-test-20261005-174416.json` | **PASS** | With the volatile tables reported and not compared. 83 tables, 6,926 rows in the stable tables, the same checksum over them, 32 products, 12 orders, 7 media files with the same checksum, and the same answers from the site, before and after |

Both failures are kept because they are why a restore is tested: neither would have been found by writing the
procedure down.

## The safeguards

| Id | Check | Result |
|---|---|---|
| S-1, the test payment method | Two orders placed through the public checkout interface with it | Both confirmed and set to processing, stock reduced by two. The refused-payment case and the method's appearance in the checkout screen were **not exercised** |
| S-2, the browser matrix | Run against staging | Recorded under #241 in `docs/2026-10-05-staging-verification.md`. The runner was shown to fail on a placeholder screen and on missing content |
| S-3, the parallel-request harness | Four last-unit runs and a read-load run | Release spread under 1.2 milliseconds in each. Findings recorded under B6 |
| S-4, the staging pass | Run for #242 and #246 | 51 of 52, and 1 of 7, without a finding. Findings recorded against those PBIs |
| S-5, the tunnel | The forwarded path exercised locally with the same headers | As AC-244-07. **No tunnel opened** |

## What staging found in other features

Staging did its job on its first day: it found defects that the automated suite had not. They are recorded in
`docs/2026-10-05-staging-verification.md` and in the verification records of the features concerned, and
**nothing was corrected under this feature**: the Arabic brand archive's links (#242), the stock report outside
the English context (#246), the cart grid's width (#306), and overselling under simultaneous orders (#255).

One finding is about the test infrastructure itself: a fresh WooCommerce shows visitors a "coming soon" screen
in place of every store page, and **the development baseline leaves it on**. Staging switches it off. Which
existing scenarios fetch a store page as a visitor, and therefore read that screen, has not been checked.

## Boundaries held

| Boundary | Held |
|---|---|
| No product code changed | Yes. Nothing under `mizzey-site/src`, `mizzey-site/mizzey-site.php` or `mizzey-theme` is touched |
| CoreX not modified | Yes. Staging links to the pinned checkout and writes nothing into it |
| The development runtime not modified | Yes. Its database, prefix, type and plugin set were read before and after and are unchanged; its reset script is not edited |
| No production infrastructure, no real provider credential, no real customer data | Yes |
| No staging secret in the repository, on a command line or in a log | Yes for the repository and the evidence, which was checked against the actual secrets before it was committed. **One exception, corrected:** the first database client call passed the password as an argument, and a failure printed it to the session. The password was rotated, and the client now reads it from an option file |
| No tunnel opened and no third-party terms accepted | Yes. Both need Mustafa |
| No version changed | Yes. `stack.lock.json` and `corex.lock` are untouched |

## Guard Gate

| Guard | Scope | Outcome |
|---|---|---|
| **wp-guard** | The staging plugin, the seed, the fingerprint | Four changes made: identifiers through `%i` placeholders, an unbounded query bounded, direct-access guards added, and the marker's styles enqueued instead of printed. The marker's wording is English and untranslated on purpose, and says so |
| **woo-guard** | The test payment method | **Not run as a skill.** Reviewed by reading: it uses the gateway class and order methods, stores nothing, and is exercised only for the approved case |
| **test-guard**, **clean-code-guard**, **docs-guard** | The harnesses and the documents | **Not run as skills.** Applied by reading. This is stated so that it is not assumed |

## Open, and owned elsewhere

| Item | Owner |
|---|---|
| Production and its backups | OD-27 and OD-14, the client's |
| A public address for staging | Mustafa's Cloudflare sign-in, or his agreement to the terms of a temporary address |
| A person's own look at staging, and the three device rows of the browser matrix | Mustafa, then the client |
| The findings on #241, #242, #246 and B6 | Each PBI |
| The "coming soon" screen on the development baseline | A test-infrastructure follow-up |
