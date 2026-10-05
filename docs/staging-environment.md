# The staging and demonstration environment

How to build, run, reset, back up and restore staging, and how to show it to the client. Owner PBI: #244
(`specs/005-local-staging-environment`). Decided in D-10 and D-12.

**This is not production, and nothing here may be pointed at production.** Production hosting waits for the
client's written instruction (OD-27) and the operating budget (OD-14).

## What it is

| | Development | Staging and demonstration |
|---|---|---|
| Purpose | Building, and the automated suite | Manual checks, the browser matrix, client review, the store operations walkthrough |
| Directory | `../app/wp` | `../app-staging/wp` |
| Web server | The WAMP Apache service, port 80, `mizzey.local` | Its own Apache process, `http://127.0.0.1:8088` |
| Database | `mizzey` | `mizzey_staging`, through an account that can reach no other database |
| Table prefix | `mz_` | `mzs_` |
| Keys and sessions | Its own | Its own, generated at build. A session of one is not valid on the other |
| Code | The working tree, linked | An export of a named commit, copied, recorded in `../app-staging/BUILD.json` |
| Data | Scenario fixtures, removed on finish | Invented data only, from `mizzey-site/tests/staging/seed/` |
| Mail | Whatever the machine does | Captured to `../app-staging/mail/`. None is sent |
| Outside requests | Allowed | Refused (`WP_HTTP_BLOCK_EXTERNAL`) |
| Marker | None | A strip on every storefront, admin and sign-in screen, and a different admin bar colour |
| Search engines | Not reachable | `noindex` on every response |

Shared, and only this: the installed Apache, PHP and MySQL programs, and the pinned CoreX checkout that
`corex.lock` fixes to one commit and that nobody edits.

Everything staging generates lives in `../app-staging/`, outside the repository: its configuration, its
credentials (`CREDENTIALS.json`), its logs, its captured mail, its backups and its evidence. **None of it is
committed.**

## Commands

All from the repository root. The tool is `mizzey-site/tests/staging/staging.py`.

| Command | What it does |
|---|---|
| `python mizzey-site/tests/staging/staging.py build` | Assembles the staging tree from `origin/main` and writes its configuration. `--ref <commit>` builds another commit and records that it is not merged |
| `... staging.py db-create` | Creates the staging database and its account. Run once, and after a password change |
| `... staging.py up` / `down` / `status` | Starts, stops or reports the staging web server |
| `MIZZEY_CONFIRM_STAGING=yes ... staging.py reset` | Backs up, then reinstalls staging, applies the same baseline development gets, and loads the invented data. About four minutes |
| `... staging.py backup` | Database and media into a dated set under `../app-staging/backups/` |
| `MIZZEY_CONFIRM_STAGING=yes ... staging.py restore <set>` | Puts a backup set back |
| `MIZZEY_CONFIRM_STAGING=yes ... staging.py restore-test` | Backs up, destroys, restores and compares. Writes an evidence file |
| `... staging.py tunnel-config <hostname>` | Allows a public host name and writes the tunnel configuration |

The first time: `build`, `db-create`, `up`, then `reset`.

**Every command that destroys or replaces data checks first** that the directory is the staging tree, that the
database is `mizzey_staging` on this machine through the staging account with the staging prefix, and that the
environment type is `staging`. It also needs `MIZZEY_CONFIRM_STAGING=yes`. The development reset
(`baseline/reset-runtime.sh`) refuses any path but the development runtime in the same way, so neither can be
turned on the other.

## What the seed loads

Invented data, shaped by what a review has to exercise. Nothing in it is the client's.

| What | Detail |
|---|---|
| Accounts | `staging_admin` (Administrator), `client_operator` (Shop manager, for the client to drive), `demo_customer` (Customer). Passwords are generated at each reset into `CREDENTIALS.json` |
| Catalogue | Twelve simple products and two with three variants each, in three categories and two brands, each in English and Arabic; one product in English only and one in Arabic only; one item for the concurrency harness. SKUs start with `DEMO-` |
| Stock | Healthy, low and exhausted, so the stock report has every case |
| Cost | Present on most, zero on one, missing on two |
| Orders | Twelve, in every state: pending, processing, on hold, completed, cancelled, failed, refunded, and one partial refund. Two placed in Arabic. Phone numbers written in four formats |
| Checkout settings | Cash on delivery on, one invented shipping price for Egypt, guest checkout on, the "coming soon" screen off |
| Payment | Cash on delivery, and a test payment method that moves no money. An email beginning with `decline` produces a refused payment |

Sources are created first and translations in a separate process, which is the sequencing rule finding A11
established for migration.

## Backup, retention and restore (NFR-08)

**A backup set** is a directory under `../app-staging/backups/` holding `database.sql.gz`, `uploads.tar.gz` and
`manifest.json`. The manifest records when it was taken, the commit staging was running, the size and SHA-256
checksum of each file, and a fingerprint of the data: table count, row count, a checksum over every stable
table, the number of products, variations, orders, refunds, accounts and translation records, and a checksum over
every media file.

**Retention.** The seven most recent sets are kept, and the oldest is removed when an eighth is taken. A backup
is also taken automatically before every reset.

**The restore procedure**, as performed on 5 October 2026:

1. Stop nothing: the web server can stay up.
2. Choose the set: `python mizzey-site/tests/staging/staging.py status` lists the latest three.
3. Run `MIZZEY_CONFIRM_STAGING=yes python mizzey-site/tests/staging/staging.py restore <set>`.
4. The command verifies each file against its checksum and refuses a damaged set before it drops anything.
5. It drops and recreates the staging database, loads the dump, replaces the media folder from the archive, and
   clears the caches.
6. Check the result: open the home page and a product in both languages, sign in to the admin, and compare
   `python mizzey-site/tests/staging/staging.py status` with the set's manifest.

**The restore has been performed, not only written.** `restore-test` takes the fingerprint, backs up, drops the
database and deletes the media, records that the site then shows the installation screen and that no table and no
media file is left, restores, and compares. On 5 October 2026 the fingerprints before and after were identical
and the result was PASS. The record is `specs/005-local-staging-environment/evidence/`.

The first attempt at that test failed, and the failure is kept in the record because it is the reason a restore
is tested: the dump was handed to the database client still compressed. The backup set was sound, and staging
was restored from it once the fault was corrected.

**What this does not cover.** Production backups, their retention period, where they are stored and who is
alerted when one fails are decided with the production hosting (OD-27, OD-14). This procedure proves the
mechanism on staging and is not the production procedure.

## Showing staging to someone else: the Cloudflare Tunnel

Staging listens on this machine only. A reviewer elsewhere reaches it over HTTPS through a Cloudflare Tunnel,
which the machine opens outwards, so no port is opened on the network.

**What protects it.** A request that arrived through the tunnel carries headers the Cloudflare edge adds and a
visitor cannot remove. The staging web server asks any such request to sign in (user and password in
`CREDENTIALS.json`, under `tunnel_gate`) before WordPress sees it. Staging also accepts only the public host
names listed in `../app-staging/allowed-hosts.txt`, serves them as HTTPS, and tells search engines not to index
anything. A Cloudflare Access policy can be added on top for a named tunnel.

**Everything up to the sign-in is prepared. The sign-in itself is one action only Mustafa can take.** Two ways:

| | A temporary address | A fixed address on a domain in the Cloudflare account |
|---|---|---|
| Needs | Nothing but agreeing to Cloudflare's terms for account-less tunnels | A Cloudflare account, and a domain in it |
| Address | A random `https://<words>.trycloudflare.com`, new each time | For example `https://staging.<domain>` |
| Good for | A one-off review or the walkthrough | Repeated client review |

For a temporary address, with staging running:

```bash
cloudflared tunnel --url http://127.0.0.1:8088
```

Then allow the address it prints, without the `https://`:

```bash
python mizzey-site/tests/staging/staging.py tunnel-config <the-address>.trycloudflare.com
```

For a fixed address:

```bash
cloudflared tunnel login
cloudflared tunnel create mizzey-staging
cloudflared tunnel route dns mizzey-staging staging.<domain>
python mizzey-site/tests/staging/staging.py tunnel-config staging.<domain>
```

`tunnel login` opens a browser for the Cloudflare sign-in: that is the one manual step. `tunnel create` prints a
tunnel id and writes a credentials file; put the id in `../app-staging/run/cloudflared.yml`, move the credentials
file to the path that file names, and run:

```bash
cloudflared tunnel --config ../app-staging/run/cloudflared.yml run
```

Stop the tunnel when the review ends. Staging is not meant to be reachable when nobody is reviewing.

**Verified without a tunnel.** The path a tunnel uses was exercised locally by sending the same headers: a
forwarded request with no sign-in is refused with 401, a wrong password is refused, a signed-in request for an
allowed host name gets pages whose links are HTTPS on that host name, and a host name that is not allowed gets
the local address. **No tunnel has been opened, and no public address exists yet.**

## The harnesses

| Tool | Use |
|---|---|
| `node mizzey-site/tests/staging/browser-matrix.cjs` | Both reading directions in real browser windows: the installed Chrome and Edge, and the Firefox build Playwright ships. It cannot exercise Safari on a Mac or an iPhone, or Chrome on an Android phone, and says so |
| `node mizzey-site/tests/staging/staging-pass.cjs ia` or `stock` | The scripted staging pass for #242 and #246 |
| `python mizzey-site/tests/staging/concurrency.py last-units` | N buyers ordering the last K units at the same instant (workstream item B6) |
| `python mizzey-site/tests/staging/concurrency.py read-load` | Many clients reading storefront pages at once |

The browser tools use Playwright as test tooling on the developer's machine. It is not a dependency of the site.

## What staging does not have yet

- **A public address.** See above.
- **An ERP connection of any kind.** No adapter exists until #245. Stock on staging is the store's own invented
  figure and is not evidence for any ERP row.
- **A payment provider or a carrier.** No provider plugin is selected and the carrier plugin has no account. The
  test payment method and cash on delivery stand in.
- **A licence for the translation plugin.** It is the same unlicensed local test copy as development, and it
  shows a notice in the admin. The client holds the production licence.
- **The approved interface design.** The theme is the bare baseline. What staging shows is structure, not design.
