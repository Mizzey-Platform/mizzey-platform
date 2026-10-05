# Tasks: development and staging environments

Feature `005-local-staging-environment`, PBI #244. Every task names its check. All are done unless marked.

## Phase 1: the runtime

- [x] T001 Staging tree assembled from a named commit, with a build record (FR-001, AC-244-03). Check: `staging.py build`, then `BUILD.json` names the commit and `merged_to_main`
- [x] T002 Own web server process and configuration on `127.0.0.1:8088` (FR-002, AC-244-01). Check: `staging.py up`, both sites answer at once
- [x] T003 Own database and an account with one grant (FR-002, AC-244-02). Check: `staging.py db-create` prints the databases the account can see, and fails unless it is exactly one
- [x] T004 Own configuration: prefix, keys, environment type, host allow-list, outbound block, no file changes, no self-update (FR-002, FR-005, FR-006). Check: `wp config list` on staging; a foreign Host header gets the local address
- [x] T005 Guards on every destructive command (FR-003, AC-244-04). Check: the guard reads the running configuration; without the confirmation variable the command refuses

## Phase 2: what staging shows

- [x] T006 The staging plugin: marker, mail capture, test payment method (FR-005, FR-006, S-1). Check: the marker is in the home, Arabic home and sign-in pages; a test message is captured and not sent
- [x] T007 The tunnel gate and `noindex` (FR-006, AC-244-07). Check: a request with tunnel headers and no sign-in answers 401; a wrong password answers 401; every response carries `X-Robots-Tag`
- [x] T008 The seed, each step its own process, failing loudly, ending with the import of the orders into the reports (FR-004, AC-244-05). Check: the translation step verifies every pair is one group with one SKU; a second reset produces the same counts

## Phase 3: backup and restore

- [x] T009 Backup set with manifest, checksums and fingerprint; retention (FR-007, AC-244-08, AC-244-09). Check: `staging.py backup`, then the manifest and the retention line
- [x] T010 Restore, refusing a damaged or foreign set (FR-008). Check: `staging.py restore <set>`
- [x] T011 The restore test, performed (FR-008, AC-244-10). Check: `staging.py restore-test` ends PASS with identical fingerprints; evidence in `evidence/`
- [x] T012 The restore procedure written from that run (FR-009, AC-244-11). Check: `docs/staging-environment.md`

## Phase 4: the harnesses and the tunnel

- [x] T013 Browser matrix runner (S-2). Check: it fails on the "coming soon" screen and on missing page content, shown on its first runs
- [x] T014 Scripted staging pass for #242 and #246 (S-4). Check: it records the request the screen makes and the answer it is given
- [x] T015 Parallel-request harness (S-3). Check: the release spread of the simultaneous requests is under one millisecond in the recorded runs
- [x] T016 Tunnel configuration prepared to the sign-in (S-5). Check: the forwarded path exercised locally with the same headers
- [ ] T017 **Open the tunnel.** Not done: it needs Mustafa's Cloudflare sign-in, or his agreement to Cloudflare's terms for a temporary address. No public address exists

## Not tasks of this feature

- [ ] Production environment and its backups: `pending OD-27` (AC-244-12, AC-244-13)
- [ ] A fake ERP adapter on staging: #245
- [ ] Corrections to the findings the staging passes made on #241, #242 and #246: each belongs to its own PBI
