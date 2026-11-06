# The 2 November work on staging

The slices built on 2 November 2026 (`pm` work list, D-27) verified on the staging server, releases v0.1.14 to
v0.1.16. Technically verified and, where the table says so, verified on staging; nothing here is accepted by the
client (constitution M-7).

## The releases

| Release | Commit | Backup before it | Deploy | `staging.py build` | `staging.py check` |
|---|---|---|---|---|---|
| v0.1.14 | 80680fb (through #492) | 20261102-172040-manual | `bin/deploy mizzeystaging v0.1.14`, checksum matches | No problems | 6 of 6 |
| v0.1.15 | 2010712 (through #496) | 20261102-174036-manual | `bin/deploy mizzeystaging v0.1.15` | No problems | 6 of 6 |
| v0.1.16 | d623f13 (through #497) | 20261102-174831-manual | `bin/deploy mizzeystaging v0.1.16` | No problems | 6 of 6 |

Rollback at any point: `bin/deploy mizzeystaging --rollback`.

## How the scenarios ran

Each scenario ran in its own WP-CLI process inside the staging PHP container (MariaDB 11.4, Redis object cache), from
the `mizzey-site/tests` and `specs` folders of the deployed tag copied into the container's `/tmp` and removed
afterwards. The staging plugin sends the store's requests to itself to the server's web server, so each HTTP check
went through Caddy and PHP-FPM. The evidence files are on the server under `~/mizzey-staging/evidence/scenarios-*`.

## Results

| Slice | Issue | Spec | Scenario | On staging |
|---|---|---|---|---|
| Refunds with their references | #292, #293 | 029 | t69 | Pass |
| Order management | #280 | 030 | t70 | Pass (v0.1.15, after #496) |
| Staff activity log | #275 | 031 | t71 | Not run to a verdict: see below |
| Image handling | #269 | 032 | t72 | Pass |
| Customer records | #279 | 033 | t73 | Pass |
| Sales report | #286 | 034 | t74 | Fails only where it opens the Analytics screen as a staff user: see below |
| Store dashboard | #294 | 035 | t75 | Pass |
| Product management | #276 | 036 | t76 | Passes but for AC-7's bulk import over HTTP as a staff user: see below |
| Everyday admin tasks | #278 | 037 | t77 | Fails where it opens admin screens as a staff user: see below |
| Search engine controls | #287 | 038 | t78 | Pass |
| Observability | #270 | 039 | t79 | Pass |
| Data model | #273 | 040 | t80 | Pass |
| Shipping through Bosta | #291 | 041 | t81 | Pass (v0.1.16, after #497) |
| Event tracking plan | #274 | 042 | t82 | Pass |
| Measurement tools | #288, #284 | 043 | t83 | Pass |
| Commerce events | #312 | 044 | t84 | Not run to a verdict: see below |
| Email transport | #284 | 045 | t85 | Pass |
| Catalogue load | #247, #295, #296, #297 | 046 | t86 | Pass (v0.1.16, after #497) |
| Second release, behind its switch | S2 rows | 047 | t87 | Pass |

**Staff sign-in on staging.** Staging enforces two-factor sign-in for staff (D-21). t71 and t84 sign a freshly made
staff user in, and t74, t76 (AC-7) and t77 open admin screens over HTTP as one; on staging that user is sent to
enrol in two-factor sign-in, so those checks cannot reach the screen. This is the security baseline working, not a
defect in the slices. Those scenarios pass on the development runtime (their pull requests' evidence) and stay
there until a staging test account enrolled in two-factor sign-in is arranged; their slices are not marked verified
on staging.

**Found by staging and fixed.** t70 assumed the sign-in address `wp-login`, which the staging baseline moves; t81
assumed WooCommerce's Egypt state codes, which the Bosta plugin replaces there (#496). The catalogue load let its
plan, which holds costs, be written under the uploads folder when that folder sits outside the WordPress folder, as it
does on the server; and t81's zone order was stored unsigned (#497). Both fixed, both pass on v0.1.16.

**Seen, not ours.** The Paymob plugin tries to write gateway files into the read-only release folder on every
WP-CLI load (warnings only). WooCommerce Multilingual's attribute lookup table raises an error at shutdown after a
scenario deletes its products, after the verdict, as recorded on 31 October.

## 3 November 2026: the staff screens on staging

The scenarios that could not reach a verdict on staging were fixed as tests, not as site code: staff test accounts
are now enrolled in two-factor sign-in where it is enforced, admin requests carry the secure sign-in cookie (#512),
the staging plugin's self-request routing keeps curl's own address on a redirect or a space, and the importer is
given the path the web process sees (#514). Release v0.1.19 (1e11d25, through #514), backup 20261103-162856-manual
before it, `staging.py build` no problems, `staging.py check` 6 of 6, security baseline passes.

| Slice | Issue | Spec | Scenario | On staging |
|---|---|---|---|---|
| Staff activity log | #275 | 031 | t71 | Pass (v0.1.19; also v0.1.18) |
| Sales report | #286 | 034 | t74 | Pass (v0.1.19; also v0.1.18) |
| Product management | #276 | 036 | t76 | Pass, AC-7 included (v0.1.19) |
| Everyday admin tasks | #278 | 037 | t77 | Pass, AC-3 included (v0.1.19) |
| Commerce events | #312 | 044 | t84 | Pass (v0.1.18); the issue's status is held by its own inputs |

Evidence on the server: `~/mizzey-staging/evidence/scenarios-20261103-133040` (t71, t84 on v0.1.18),
`scenarios-20261103-163218` (t76, t77) and `scenarios-20261103-163706` (t71, t74). #275, #276, #278 and #286 move
from In review to Verified. Verified is technical; none of them is accepted by the client (constitution M-7).

## 6 November 2026: agency access and the add-to-cart stock check

Release v0.1.25 (e0afc75, through #546), backup 20261106-144944-manual before it, `staging.py build` no problems,
`staging.py check` 6 of 6. The stored two-factor policy predated the agency role, so `wp mizzey security
two-factor-baseline` was run (approved 5 November 2026, specs/064 S-1); every security baseline then passes. The
add-to-cart stock check went on staging with v0.1.23 on 5 November (#541).

| Slice | Issue | Spec | Scenario | On staging |
|---|---|---|---|---|
| Agency access, MKT-21 to MKT-25 | #298 | 064 | t104 | Pass (v0.1.25) |
| Add-to-cart stock check, ERP-03 | #314 | 062 | t102 | Pass (v0.1.23) |

Evidence: `specs/064-agency-access/evidence/staging-2026-11-06.txt` (#547) and
`specs/062-add-to-cart-stock/evidence/staging-2026-11-05.txt` (#541). Both move from Ready to In progress, not to
Verified: #298's MKT-26, the Third-Party Access Protocol, is drafted and not yet delivered, and #314 has no final
acceptance criteria before the ERP Integration Specification (PRE-09, constitution M-3).
