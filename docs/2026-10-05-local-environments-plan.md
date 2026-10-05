# Local development and staging plan (#244, NFR-08 and NFR-09)

5 October 2026. Owner PBI: #244. The plan for the environments D-10 approved as the engineering approach. **None
of this is production, and nothing here is to be called production.**

**Built the same day.** The staging environment now exists: PR #327, `specs/005-local-staging-environment/`, and
the operating guide `docs/staging-environment.md`. This plan is kept as the plan. Three things came out
differently from it, each for a recorded reason: staging has its own web server process on its own port and not
a host name of the development server; mail is captured by the store itself and no mail catcher runs; and the
site is kept out of search engines by the web server's header and the tunnel sign-in, not by the store's own
setting, which would also switch the sitemap off. A scheduled backup was not built: staging runs only during a
review and is backed up before every reset. The tunnel is prepared and not opened.

## What is contracted, and what D-10 decided

| Row | Wording | What this plan covers |
|---|---|---|
| NFR-09 | Development, staging and production; risky changes never tested on the live site | Development and staging. Production is not provisioned |
| NFR-08 | Database and media backup, retention, and a documented restore procedure | Backup and an exercised restore on staging. Production retention waits for production |

Production hosting stays a launch gate: the contract requires the client's written instruction on hosting
location before production infrastructure is provisioned (OD-27, **not client-approved**), and the operating
budget (OD-14) is decided with it. #244 can be verified for development and staging and cannot be accepted until
production exists.

## The two runtimes

| | Development | Staging and demonstration |
|---|---|---|
| Purpose | Building and the automated suite | Manual testing (DOD-04), the browser matrix, client review, the store operations walkthrough |
| Location | The developer's machine, `../app/wp` | The developer's machine, a separate directory |
| Database | Its own, reset by the suite | **A separate database**, never shared with development |
| Uploads | Its own | Its own |
| Reset | `baseline/reset-runtime.sh`, destructive, refuses any other path | Its own reseed command, which refuses the development path and the other way round |
| Data | Scenario fixtures, removed on finish | **Synthetic data only.** No real customer, no real order, no personal production data |
| Reachable from | The machine only | The internet, through a Cloudflare Tunnel, while a review is running |
| Code | The working tree | A built copy of a named commit, so what is reviewed is what was merged |

Isolation is by directory, database, uploads folder, table prefix, salts and site URL. The two never share a
cookie domain.

## What staging provides

| Capability | How | Why |
|---|---|---|
| Synthetic catalogue and orders | A seed script: products in both languages, variations, an Arabic-only and an English-only product, orders in each status | The data the DOD-04 checks need, with nothing real in it |
| Reset and reseed | One command, guarded like the development reset | A review always starts from a known state |
| Cloudflare Tunnel | A named tunnel to the staging host | Review from a phone or by the client without opening the machine to the network |
| Access control | An access policy on the tunnel, and the site not indexed | A staging copy must not be public or crawled |
| Environment indicator | A visible banner and an admin bar label on every staging screen, and a different admin colour scheme | Nobody mistakes staging for the live site, or for development |
| Safe mail handling | All outgoing mail captured to a local catcher; no message leaves the machine | A test order must never email a real address |
| Sandbox and fake services | Paymob in test mode; Bosta, the ERP and any other provider behind a fake adapter where no sandbox exists | D-10: missing credentials do not block development |
| Backup and restore | A scheduled database and uploads backup of staging, and **a restore performed end to end**, with the procedure written from that run | NFR-08 asks for a documented restore procedure, and an untested one is not a procedure |
| Browser testing | The D-09 matrix through the tunnel, in both reading directions | AC-9 of #241 and the DOD-04 checks of #242 and #246 are waiting for exactly this |
| Concurrency and load | A parallel-request harness against staging | Workstream item B6 and the BR-003 concurrency test can run nowhere else |

## Order of work

1. Staging directory, database and configuration, isolated from development.
2. The seed and the guarded reset.
3. The environment indicator and the mail catcher.
4. The fake and sandbox service adapters that exist so far.
5. Backup, then a restore performed and written up.
6. The concurrency harness.
7. The Cloudflare Tunnel, last.

## The one manual step

**Signing in to Cloudflare is a single action only Mustafa can take.** It is raised when the tunnel is ready to
connect, in step 7, and not before. Nothing earlier depends on it.

## What staging unblocks

| Waiting item | PBI |
|---|---|
| AC-9, the six-browser matrix in both directions | #241 |
| DOD-04, manual testing on staging | #242, #246, and every later PBI |
| The store operations walkthrough, PRE-07 | #267 |
| The concurrency test, B6 | #233 and #255 |

## What stays out

- Production infrastructure, its backups and retention, its domain and its mail: a launch gate.
- Any real customer or order data, at any time.
- Any real provider credential stored in the repository.
