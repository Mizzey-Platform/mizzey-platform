# Verification: photos in reviews

Three states, kept apart (constitution M-7): technically verified, verified on staging, accepted by the client. Only
the first is claimed here.

## 1. Technical verification, 31 October 2026, development runtime

Stack: WordPress 7.1.4, WooCommerce 11.1.0, PHP 8.3 with GD, Apache (WAMP).

| Evidence | What it shows |
|---|---|
| t66 at the final commit | Passes |
| `evidence/mutation.txt` | Deliberate defects, each caught by t66 |
| `analysis.md` | The read-only review rounds |

| Item | Result in t66 |
|---|---|
| FR-001 | Pass. [HTTP] upload with the REST nonce, none and a wrong one refused; a WebP taken; a script named .jpg, over 6000 pixels, 36 million pixels, another customer, a sixth photo and a published review refused |
| FR-002, FR-003 | Pass. No Exif, XMP, ICC or trailing script left; 2000 pixels on the long side; a random name; [HTTP] the stored file cannot be fetched directly |
| AC-1, FR-004 | Pass over HTTP. 404 while held, 200 and never cached once approved, a photo attached after the approval hidden, 404 when sent back; moderators see held photos in the admin list; the alternative text |
| FR-005 | Pass. Deleting the review deletes the files |

## 2. Staging

Done 31 October 2026 on release v0.1.9 (`docs/2026-10-31-stage3-staging-verification.md`): t66 passes, 6 of 6.

Two staging fixes came first. On the owner's choice, the staging site's Caddy file refuses
`/wp-content/uploads/mizzey-review-photos/*` (404), since Caddy does not read the `.htaccess` the feature writes there
(hosting-stack 8b093ec). And the folder check asks the site's own address for a probe file, which on the server went
out through Cloudflare Access and met its sign-in redirect, so uploads stayed refused (503); the staging plugin now
sends the site's requests to itself to Caddy inside Docker (#466). Without a test wrapper, the probe then answers 404
and the store reports the folder closed.

| Check | Result on staging |
|---|---|
| AC-1 over HTTP | 201 with the REST nonce; 401 without; 403 with a wrong one |
| AC-1 refusals | a WebP attached (201); a script named .jpg, over 6000 pixels a side, 36 million pixels, another customer, a sixth photo, and a large picture in a busy worker refused (400, 400, 400, 403, 400, 400) |
| AC-2 | stored as 2000x1000 without location, camera, XMP or colour profile data or what followed the picture, under a random name; the file directly: 404 |
| AC-3 | served only while approved (404, 200, 404, 404), never cached (private, no-store, max-age=0); none added once published (403) |
| Clean-up | an orphan photo swept after ten minutes (2 swept); deleting a review deletes its photos |

Found: the staging PHP container is capped at 512 MB with up to 8 workers, and one worker was killed for memory
during an earlier t66 attempt (502). The passing run ran the scenario from the cron container and was not affected.
Staging capacity is the owner's server setting; production is a different host.

## 3. Client acceptance

Not done. The gift is presented with Stage 3 (D-23 item 2).
