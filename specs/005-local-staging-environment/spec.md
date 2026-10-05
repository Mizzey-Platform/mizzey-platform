# Feature Specification: Development and staging environments, with a backup that has been restored

**Feature Branch**: `005-local-staging-environment`

**Created**: 5 October 2026

**Status**: Implemented for development and staging, technically verified. Production not provisioned

**Work type**: requirement

**Input**: PBI #244. D-12 made it the highest-priority internal pre-development work: a staging and
demonstration environment that is separate from development, holds invented data only, cannot send mail or reach
a real provider, says what it is on every screen, can be rebuilt and reseeded by one command, is backed up, **has
actually been restored from that backup**, and can be shown to the client over HTTPS through a Cloudflare Tunnel.
Three merged features (#241, #242, #246) have a criterion or a definition-of-done row that could not be checked
without it.

## Register trace [checked]

Two rows, and only these two. Citation corrected by D-11: the four document and design rows this PBI once cited
are owned by #266 and #250.

| ID | Scope | Stage | Register wording (short, verbatim) |
|---|---|---|---|
| NFR-09 | P1 | S1 | Development, staging and production; risky changes never tested on the live site |
| NFR-08 | P1 | S1 | Database and media backup, retention, and a documented restore procedure |

## Context rows (no obligation here)

| ID | Scope | Why it matters here |
|---|---|---|
| DOD-04 | P1-L | "plus manual testing on staging". Owned by the definition-of-done PBI. Staging is where it happens for every feature |
| NFR-14 | P1 | The browser matrix. Owned and accepted by #241; its criterion AC-9 needed staging to be exercised at all |
| MKT-24 | P1-L | Third parties receive staging access. Owned by #298. This feature provides the environment, not the access protocol |
| NFR-05 | P1 | Secret management. Owned elsewhere. Applied here as: no staging secret is in the repository |
| NFR-02 | P1 | Performance. Owned elsewhere. The load figures measured here are about the harness, not a performance result |
| ERP-10 | P1-E | The integration is tested against a test environment. Owned by #245. Staging connects to no ERP |
| PAY-09 | P1 | Cash on delivery. Owned by the payments PBI. Staging switches the native capability on so an order can be placed |

## Open contract items

- **OD-27**, the client's written instruction on hosting outside Egypt, is **not given**, and **OD-14**, the
  operating budget, is not decided. The contract requires that instruction before production infrastructure is
  provisioned (R-03, CR-10). **No production environment exists and none is provisioned by this feature.** Every
  criterion about production is `pending OD-27`.
- **Staging runs on the developer's machine by owner decision (D-10, D-12), not by client confirmation.** The
  register words NFR-09 as three environments and does not say where staging lives. The Technical Design and the
  Statement of Work describe staging as following the hosting instruction. The criteria that rest on the owner's
  approach are therefore `provisional`, never `final`, until the client confirms it or production hosting settles
  it (M-10).
- No contradiction in `docs/scope/open-items.json` touches NFR-08 or NFR-09.

## Contractual acceptance criteria [checked]

"Staging" means the staging and demonstration environment. "Development" means the existing disposable runtime
the automated suite runs on.

| # | Criterion | Traces | Status |
|---|---|---|---|
| AC-244-01 | A development environment and a staging environment both exist, and each can be started and reached on its own | NFR-09 | provisional |
| AC-244-02 | Staging is separate from development: its own web server process and address, directory, database, database account, table prefix, keys and uploads. The staging database account can reach no other database | NFR-09 | provisional |
| AC-244-03 | Staging runs the code of a named commit, recorded with the build, so what is reviewed is what was merged | NFR-09 | provisional |
| AC-244-04 | A command that destroys or replaces data refuses any environment other than the one it was written for, checked against the running configuration | NFR-09 | final |
| AC-244-05 | Staging holds invented data only, and can be rebuilt and reseeded to the same known state by one command | NFR-09 | provisional |
| AC-244-06 | Nothing staging does can reach a customer or a live provider: every outgoing message is captured and none is sent, and requests to outside hosts are refused | NFR-09 | provisional |
| AC-244-07 | Every staging screen says that it is staging, and staging is closed to the public and to search engines when it is reachable from outside | NFR-09 | provisional |
| AC-244-08 | The staging database and media are backed up by one command into a dated set with a manifest and checksums | NFR-08 | provisional |
| AC-244-09 | Backup sets are kept to a stated retention count, the oldest removed first | NFR-08 | provisional |
| AC-244-10 | A restore has been performed end to end on staging: backed up, destroyed, restored, and shown to be the same data and the same working site | NFR-08 | final |
| AC-244-11 | The restore procedure is written down, from the restore that was performed | NFR-08 | final |
| AC-244-12 | A production environment exists, separate from development and staging, and no risky change is tested on it | NFR-09 | pending OD-27 |
| AC-244-13 | Production database and media are backed up to the agreed retention, and the documented restore has been performed against a production backup | NFR-08 | pending OD-27 |

AC-244-04, AC-244-10 and AC-244-11 are `final` because they are facts about the mechanism and the procedure
that hold wherever staging lives. The rest of the staging criteria are `provisional` for the reason given under
Open contract items.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - A reviewer opens staging and knows what it is (Priority: High)

A reviewer, the client or the developer, opens the staging address and sees the store with invented products and
orders, in English and Arabic, with a strip on every screen saying it is a demonstration copy.

**Why this priority**: every later feature's manual check, the browser matrix and the store operations
walkthrough happen here.

**Independent Test**: start staging, open the home page, a product and the admin in both languages.

**Acceptance Scenarios**:

1. **Given** staging is built and seeded, **When** it is started, **Then** it answers on its own address while development answers on its own (AC-244-01, AC-244-02)
2. **Given** a staging screen, storefront, admin or sign-in, **When** it is opened, **Then** it carries the staging marker (AC-244-07)
3. **Given** a request that arrived through the tunnel, **When** it carries no sign-in, **Then** it is refused, and every response tells search engines not to index it (AC-244-07)
4. **Given** the build record, **When** it is read, **Then** it names the commit staging runs and whether that commit is on the main branch (AC-244-03)

### User Story 2 - The developer resets staging without touching anything else (Priority: High)

**Acceptance Scenarios**:

1. **Given** the reset command, **When** it is pointed at anything but the staging tree and the staging database, **Then** it refuses (AC-244-04)
2. **Given** staging in any state, **When** it is reset, **Then** it holds the same invented catalogue, orders and accounts as after the previous reset (AC-244-05)
3. **Given** an order placed or an account created on staging, **When** the store sends its message, **Then** the message is captured on the machine and not sent (AC-244-06)

### User Story 3 - The developer loses staging and gets it back (Priority: High)

**Acceptance Scenarios**:

1. **Given** a seeded staging, **When** it is backed up, **Then** a dated set holds the database, the media, a manifest and their checksums (AC-244-08)
2. **Given** more sets than the retention count, **When** a backup is taken, **Then** the oldest is removed (AC-244-09)
3. **Given** a backup set, **When** the staging database is dropped and its media deleted and the set is restored, **Then** the data fingerprint and the site's answers are the same as before (AC-244-10)

### Edge Cases

- A restore from a set whose files do not match their checksums is refused before anything is dropped.
- A backup set taken from another environment is refused.
- A Host header that is not on the allowed list gets the local address, never a page built for that host.
- A seed step that fails stops the run. It is not reported as done.

## Native coverage

| Capability | Evidence | Status |
|---|---|---|
| Environment type | WordPress `WP_ENVIRONMENT_TYPE` and `wp_get_environment_type()` | VERIFIED: staging reports `staging`, development `local` |
| Mail interception | WordPress `pre_wp_mail` filter short-circuits `wp_mail()` | VERIFIED: a test message and the seed's own messages were captured |
| Outbound request block | WordPress `WP_HTTP_BLOCK_EXTERNAL`, `WP_ACCESSIBLE_HOSTS` | VERIFIED in configuration |
| Search engine exclusion | WordPress `blog_public`, and an `X-Robots-Tag` header from the web server | VERIFIED |
| Database dump and load | `mysqldump` and `mysql`, as the staging account | VERIFIED by the restore test |
| Cash on delivery | WooCommerce native gateway, switched on in staging | VERIFIED: orders placed through the public checkout interface |
| A backup plugin | Not used. **Gap recorded**: staging needs a backup whose restore is scripted and comparable, on a developer machine with no scheduler; the production backup mechanism is chosen with the hosting (OD-27) and is not prejudged here | n/a |

The staging tooling is test infrastructure under `mizzey-site/tests/staging/`. It adds nothing to the site plugin
or the theme and is excluded from every deployable build.

## Requirements

### Functional Requirements

- **FR-001**: Staging MUST be assembled into its own directory from a named commit, with the build recorded (AC-244-03)
- **FR-002**: Staging MUST have its own web server process, database, database account, table prefix, keys and uploads (AC-244-01, AC-244-02)
- **FR-003**: Destructive commands MUST check directory, database name, database host, account, table prefix and environment type against the running configuration, and need an explicit confirmation (AC-244-04)
- **FR-004**: The seed MUST create invented data only, sources first and translations in a separate process, and MUST fail the run if a step fails (AC-244-05)
- **FR-005**: Staging MUST capture every outgoing message, refuse outside requests, and carry no real provider credential (AC-244-06)
- **FR-006**: Staging MUST mark every screen, refuse tunnel requests that are not signed in, accept only allowed public host names, and tell search engines not to index it (AC-244-07)
- **FR-007**: The backup MUST hold the database and media with a manifest, checksums and a data fingerprint, and MUST apply the retention count (AC-244-08, AC-244-09)
- **FR-008**: A restore test MUST back up, destroy, show the damage, restore and compare, and write its evidence (AC-244-10)
- **FR-009**: The restore procedure MUST be documented from the performed restore (AC-244-11)
- **FR-010**: No staging secret is written to the repository, to a command line or to a log (AC-244-02)

### Key Entities

- **Build record**: the commit staging runs, whether it is on the main branch, the CoreX release, the time.
- **Backup set**: database dump, media archive, manifest with checksums and a data fingerprint.
- **Restore evidence**: the five recorded steps of a restore test and its result.

## Optional safeguards (not owed)

Approved by Mustafa in D-12 item 5, which lists them. None is a client deliverable, and none is in a deployable
build.

| Id | Safeguard | Why | Custom code? |
|---|---|---|---|
| S-1 | A test payment method that moves no money, with a refused-payment case | A reviewer can complete and fail a payment before a provider account exists | Yes, staging plugin only |
| S-2 | A browser-matrix runner over both reading directions | AC-9 of #241 needed real browser windows | Yes, test tooling |
| S-3 | A parallel-request harness | Workstream item B6 could not be measured in one process | Yes, test tooling |
| S-4 | A scripted staging pass for #242 and #246 | Repeatable evidence for DOD-04 on staging | Yes, test tooling |
| S-5 | Cloudflare Tunnel configuration prepared up to the account sign-in | The external demonstration address | Configuration only |

## Future or Option C items (not built)

| Item | Where it sits |
|---|---|
| Production infrastructure, its backups, retention period and monitoring | AC-244-12 and AC-244-13, `pending OD-27`; OD-14 |
| A scheduled staging backup | Not built: staging runs only during a review, and a backup is taken before every reset. Scheduling belongs with production |
| A fake ERP adapter on staging | #245, the adapter seam. No adapter exists yet, so staging has no ERP connection of any kind |
| A payment provider sandbox | The payments PBI. The provider's test mode replaces S-1 when the provider is wired |
| Third-party staging access and its protocol | MKT-24 and MKT-26, #298 |

## Success Criteria

- **SC-001**: Development and staging answer at the same time on different addresses, and a change to one is not visible in the other.
- **SC-002**: The staging database account lists exactly one database.
- **SC-003**: Two consecutive resets produce the same counts of products, variations, orders and accounts.
- **SC-004**: The restore test ends with identical fingerprints before and after, and its evidence file says so.
- **SC-005**: A request carrying tunnel headers and no sign-in is answered 401.

## Delivery stage

Contractual stage: S1 for both rows. Engineering timing: development and staging are delivered now, ahead of the
hosting decision, by owner decision D-10 and D-12. Production follows OD-27 and is not started. The contractual
stage does not move.

## Assumptions

- The developer's machine runs the WAMP Apache, PHP 8.3.6 and MySQL 8.3.0 recorded in `stack.lock.json`. Staging
  uses those installed binaries with its own configuration; it does not use the development web server.
- CoreX is linked from the pinned checkout that `corex.lock` fixes to one commit. Staging and development
  therefore share that read-only checkout, and a CoreX upgrade reaches staging only when it is rebuilt.
- The translation plugin on staging is the same unlicensed local test copy as on development. The client holds
  the production licence.
