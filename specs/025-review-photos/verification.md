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

Not done. On the owner's approval; on a server that ignores `.htaccess`, the folder refusal set by the host or
`MIZZEY_REVIEW_PHOTOS_DIR` outside the public folder.

## 3. Client acceptance

Not done. The gift is presented with Stage 3 (D-23 item 2).
