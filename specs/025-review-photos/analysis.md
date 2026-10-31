# Analysis: photos in reviews

Read-only reviews by Codex (gpt-6.1-sol, effort high), each finding checked against WordPress 7.1.4's, PHP GD's and
Apache's behaviour before it was applied, and each fix given a t66 check that fails when the fix is removed
(`evidence/mutation.txt`), unless the record says why it cannot have one.

| Round | Verdict | Findings, and what was done |
|---|---|---|
| 1 | FIX FIRST | Two High, four Medium. A small 6000 by 6000 PNG exhausted memory while decoding: a pixel cap and a memory check first. An upload finishing as a moderator approved published unmoderated photos, approved photos were cached a day, private storage relied on an unchecked `.htaccess`, and two uploads at once lost each other's photos: see round 2 onwards. The tests skipped the real upload: t66 uploads over HTTP with the REST nonce, with Exif, XMP, ICC, a trailing script, WebP and a decompression bomb |
| 2 | FIX FIRST | Two High, two Medium. The memory estimate was too low for PNG's decoder: ten bytes a pixel, and at most 12 million pixels. Timestamps did not close the approval race: approving a review records its photos, under the review's lock, and only those are shown. The probe of the folder could be fooled: every image kind is probed, and only a 401, 403 or 404 counts. A deletion during an upload could orphan files: the deletion holds the lock until done |
| 3 | FIX FIRST | One High, one Medium. An approval between an upload's status read and its save: the upload reads the status after the save, under the lock, and takes its photos back. A deletion that cannot take the lock cannot be stopped in WordPress: unreferenced photos are swept, after it and daily. The stricter probe found the shipped `.htaccess` answered 500 on Apache 2.4 without the compatibility module: rewritten for both versions |
| 4 | FIX FIRST | One Medium. A list left on a deleted review kept its files from the sweep: only lists on existing reviews count |

Limits, recorded: two requests at once cannot be made in one PHP process, so the per-review lock has no concurrency
test; on a server that ignores `.htaccess` the folder stays closed only by a hosting rule or `MIZZEY_REVIEW_PHOTOS_DIR`,
and uploads are refused until the probe sees it refused.
| 5 | READY | No High or Medium findings |
