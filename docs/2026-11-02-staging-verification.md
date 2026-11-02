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
