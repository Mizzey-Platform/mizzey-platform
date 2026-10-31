# Stage 3 on staging: 31 October 2026

The Stage 3 waves (`docs/2026-10-30-stage3-build-plan.md`) verified on the staging server, release v0.1.9 (9ca91c8),
on the owner's order of 31 October 2026. Technically verified and verified on staging; nothing here is accepted by
the client (constitution M-7).

## The release

| Step | Result |
|---|---|
| Backup before anything | Set 20261031-184401-manual |
| Release | v0.1.9, published by the release workflow; the server's own download stalled, so the archive was downloaded on the development machine, its checksum checked, copied and deployed with `bin/deploy mizzeystaging v0.1.9 --file` (checksum matches; the five WPML plugins added from the server's folder). Rollback: `bin/deploy mizzeystaging --rollback` |
| `staging.py build` | No problems |
| `staging.py check` | 6 of 6 pass |
| `wp mizzey security check` | Every baseline passes; `client_operator` still to enrol in two-factor sign-in, reported as before |

## How the scenarios ran

Each scenario ran in its own WP-CLI process inside the staging container (MariaDB 11.4, Redis object cache), from
the test folder copied into the container's `/tmp` and removed afterwards. t53 to t55, t61, t62 and t67 make no HTTP
request and ran as they are. For the others, a scenario's own HTTP requests to the
store's address would leave through Cloudflare Access and get its sign-in page; they were sent instead to the
server's web server (Caddy, inside the Docker network) with the same address and `X-Forwarded-Proto: https`, so each
went through Caddy and PHP-FPM as a visitor's request does. That routing lives only in the test process: nothing on
the site changed.

## Results

| Slice | Issue | Spec | Scenario | On staging |
|---|---|---|---|---|
| ERP adapter seam | #245 | 012 | t53 | 18 of 18 |
| Launch staff roles | #251 | 013 | t54 | 15 of 15 |
| Promotions in admin | #289 | 014 | t55 | 12 of 12 |
| Wishlist | #307 | 015 | t56 | 25 of 25 |
| Launch business rules | #290 | 016 | t57 | 39 of 39 |
| Accounts | #305 | 017 | t58 | 27 of 27 |
| Cart | #306 | 018 | t59 | 31 of 31 |
| Checkout | #255 | 019 | t60 | 29 of 29 |
| Reviews | #309 | 020 | t61 | 14 of 14 |
| Emails | #285 | 021 | t62 | 8 of 8, captured by the staging plugin, none sent |
| My Account | #308 | 022 | t63 | 10 of 10 |
| Invoice, ADM-92 | #280 | 023 | t64 | 10 of 10, with `mizzey-site/lib/` in the release |
| Google sign-in | #305 | 024 | t65 | 11 of 11, Google's side answered by the scenario's stand-in |
| Photos in reviews (gift) | #309 | 025 | t66 | 6 of 6, after the two fixes below |
| Stock in the admin | #316, #317 | 026 | t67 | 13 of 13; AC-1 and AC-2 stay pending PRE-09 |

## Found on staging

- **No Egyptian governorates.** The carrier plugin builds Egypt's governorate list from Bosta's public district
  list, fetched over the web. Staging refuses outside requests, so the list was empty and every address was refused
  (t63's first run). Development's copy is now carried to staging beside WPML's files (#463), and the staging plugin
  answers the carrier plugin's request from it: 27 governorates listed. t57 to t60, t63 and t64 were run again with
  it and pass.
- **Photos in reviews.** Uploads were refused (503) on staging, as designed, for two reasons. The server is Caddy,
  which does not read `.htaccess`, so the private folder was not closed: on the owner's choice the staging site's
  Caddy file now refuses `/wp-content/uploads/mizzey-review-photos/*` (hosting-stack 8b093ec, backup
  20261031-210006-manual first). And the feature's folder check asks the site's own address, which on the server
  went out through Cloudflare Access and met its sign-in redirect: the staging plugin now sends the site's requests to
  itself to Caddy inside Docker (#466). t66 then passed, 6 of 6. Two attempts before that left test records
  (two products, set to draft; two held reviews, trashed; six test users, deleted).
- **Staging memory.** The staging PHP container is capped at 512 MB with up to 8 workers of about 130 MB each. One
  worker was killed for memory during a t66 attempt that ran inside that container (502). The passing run ran the
  scenario from the cron container. Raising the cap is the owner's server setting; production is a different host.
- **WooCommerce Multilingual at shutdown.** As on v0.1.8, a scenario that deletes products ends, after its verdict,
  with a fatal error in WooCommerce Multilingual's attribute lookup. Once it stopped t53's clean-up before its test
  page was removed; that page (283) was moved to the trash. No other test record was left: no test user, coupon or
  order, and staging's own shipping zone is back at order 0.

## Not done here

- A real Google sign-in round trip: it needs a developer test OAuth client (a vendor input).
- The screens, in a browser: they wait for the design (D-22, PRE-03b), and a staff sign-in needs the owner's
  authenticator.
