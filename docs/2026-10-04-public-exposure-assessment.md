# Historical public exposure: assessment, and what a purge would cost

Written 4 October 2026, immediately after the repository was returned to private. **Nothing has been rewritten,
nothing force-pushed, and the backup bundle is intact.** This document exists so the purge decision can be made
on facts rather than on alarm.

## Current state

| Fact | Value |
|---|---|
| Repository | `Mizzey-Platform/mizzey-platform` |
| Visibility | **PRIVATE**, confirmed by two independent reads (`gh repo view` and the REST API) |
| Forks | 0 |
| Stars | 0 |
| Network count | 0 |
| Tags | none |
| `main` | `2206dd699cd2e7404ea5b63c53387e51c9c1efbd` |

Zero forks matters: a fork would hold its own copy of every object, and deleting objects from this repository
would not reach it. There are none.

## 1. What was publicly reachable

The engagement pack lived at `docs/engagement/` from the first commit until D-03 removed it on 22 September 2026.
Every commit before the removal still contains it, so the files were reachable by anyone who could read the
repository, through the GitHub UI, the API, or a clone.

**35 files, 13.7 MB.** Eight of them are commercial or signed:

| Kind | Files |
|---|---|
| **Signed** | `signed/Mizzey-Operations-Platform-Services-Agreement-SIGNED.pdf` (705 KB), `signed/Mizzey-Operations-Platform-Statement-of-Work-SIGNED.pdf` (656 KB) |
| **Agreement** | `Mizzey-Operations-Platform-Services-Agreement.md`, `.pdf` |
| **Statement of Work** | `Mizzey-Operations-Platform-Statement-of-Work.md`, `.pdf` |
| **Invoice** | `Mizzey-Operations-Platform-Invoice.md`, `Mizzey-Operations-Platform-Invoice-FILLABLE.pdf` |

The other 27 are the specification and planning set: the Feature Register, Functional Specification, Technical
Design, Delivery Backlog, Event Tracking Plan, Acceptance and UAT Plan, Change Control, Project Plan, Support and
Maintenance, Meeting and Decision Record, Client Scope Summary, Design Brief and RFP, Scope Overview, and What You
Need To Provide, each as markdown and PDF.

## 2. Which commits contain them

| Commit | Date | What it did |
|---|---|---|
| `dc9f1d55241c` | 2026-09-06 | Added 30 of the files, with the repository |
| `d51688ec315b` | 2026-09-06 | Added the remaining 5 (the Stage 1 deliverables) |
| `9cd382edf8b4` | 2026-09-22 | Removed all 35, on the D-03 branch |
| `914e53773ecc` | 2026-09-22 | The squash of that removal onto `main` (PR #236) |

**57 commits reachable from `main` contain the files**: every commit from `dc9f1d55241c` up to and including the
removal commit `914e53773ecc`. The 3 commits after it do not. A purge would therefore have to rewrite 57 commits,
which is most of the repository's history.

The blobs are reachable from `refs/heads/main` alone. They are also reachable from five merged branches that still
exist on the remote (`001-product-cost-capture`, `001-product-cost-capture-closeout`,
`documentation/d03-remove-engagement-copies`, `governance/environment-version-record`,
`governance/option-b-foundation`), which add no new exposure but are extra refs a purge must handle.

## 3. What is actually in them, measured rather than assumed

This is the part that changes the severity, so it was measured rather than inferred.

**The two SIGNED PDFs carry the developer's own signature and nothing else.** Each holds exactly **one** embedded
image, the same 251,371-byte signature graphic in both, and three form fields for the counterparty: `name`,
`title`, `date`. **All three are empty in both documents.** The client never signed: Option C was never executed,
and the client chose Option B on 21 September. So what was exposed is Mustafa's own signature image on an
agreement that no counterparty ever signed.

**No third-party personal data was found in any text layer.** Searched across the Services Agreement, Statement of
Work and Invoice, in both the markdown sources and the signed PDFs' text:

| Looked for | Found |
|---|---|
| 14-digit national ID or passport numbers | **0** |
| Phone numbers (+20 or 01x) | **0** |
| Email addresses | **0** |
| Account or IBAN-like numbers (10+ digits) | **0** |
| IBAN, SWIFT or "account number" keywords | **0** |
| Address keywords | 2 in the Agreement, 2 in the Invoice (contract boilerplate, not a street address) |
| Signature blocks | 8 to 11 per document (the blocks themselves, unsigned on the client side) |
| Money figures (EGP) | 13 to 18 per document |
| Bank keywords | 2 to 5 per document (payment-terms language) |

**No private key, certificate or credential was ever committed.** A search of every added file across all history
for signature assets, `.p12`, `.pfx`, `id_rsa` and `.key` returned nothing. The repository was deliberately placed
beside `personal identity/` rather than inside it, and that held.

### What this means

| Category | Present? | Assessment |
|---|---|---|
| Client personal data | **No** | Nothing to notify anyone about |
| Third-party signatures | **No** | The client side is blank |
| Bank or payment details | **No** | No account numbers anywhere |
| Government identifiers | **No** | None |
| **The developer's own signature image** | **Yes**, in two PDFs | The real sensitivity. A reusable signature graphic is worth protecting: it can be lifted and pasted onto another document |
| Commercial terms and pricing | **Yes**, throughout | An unsigned, superseded engagement (Option C, 290,000 EGP, never executed). Commercially awkward, not legally exposed |
| The client's requirements and specification | **Yes** | The client's SRS-derived material. Theirs, not ours, and a confidentiality matter if the Agreement had been executed, which it was not |

**The item that justifies a purge, if anything does, is the signature image.** Everything else is commercially
untidy rather than dangerous.

## 4. How long it was public

**Not precisely establishable from here, and that should be stated rather than guessed.** The organisation audit
log, which holds the exact timestamps, needs a scope this token does not have (`GET /orgs/.../audit-log` returns
404).

What is visible: the events API records a `PublicEvent` on **2026-09-06T08:00:39Z** by `MustafaShaaban`, which is
the day the repository was created and the day the files were added. Taken with the recorded migration to
`Mizzey-Platform` as a private repository on 22 September, the likely picture is **two windows**: roughly
6 to 22 September, and the short verification window today. The first is the longer and the more significant, and
it means the exposure is not new. It should be confirmed from the audit log in the browser, where Mustafa has the
access this token lacks.

## 5. What a history rewrite would affect

| Affected | What happens |
|---|---|
| **Commit SHAs** | Every one of the 57 rewritten commits changes SHA, and so does everything after them. `main` moves to a new SHA. The three merge commits for PRs #237, #238 and #239 are among them |
| **Open PRs** | None open right now, which makes this the cheapest moment there has been. A rewrite while a PR is open invalidates its head |
| **Old issue references** | The 232 historical Option C issues reference commits and paths by SHA. Those links break permanently. Issue bodies are not rewritten, so they will point at commits that no longer exist |
| **Merged PR records** | PRs #235 to #239 keep their descriptions, but their commit links and diffs break. GitHub does not re-associate them |
| **Local clones** | Every clone must be re-cloned or hard-reset. A `git pull` into an existing clone produces a divergent history and can reintroduce the old objects on the next push |
| **The backup bundle** | `C:\Mizzey-Backups\mizzey-platform\2026-09-22\mizzey-platform-full-history-2026-09-22.bundle` contains the old history by design. It must be kept, because it is the only remaining copy of what was removed, and it must never be pushed from |
| **Tags and branches** | No tags. Five merged remote branches still hold the old objects and must be deleted before or during the rewrite, or they will keep the blobs alive |
| **GitHub caches and forks** | 0 forks and 0 network, so nothing to chase there. GitHub keeps unreachable objects accessible by exact SHA for a period after a rewrite, which is why support must be asked to garbage-collect. Anything a crawler, a mirror or a person fetched while the repository was public is beyond recall, which is true of every public exposure and is not changed by a purge |
| **CI** | The workflows compare against `origin/main`. After a rewrite the first push rebuilds cleanly; no CI state is stored outside the repository |
| **`tools/run_trusted.py`** | It fetches the base branch's copy of the checkers. It works against the new history with no change |

**The honest limit of a purge:** it removes the files from this repository's future clones. It does not
un-publish what was already public. Anyone who cloned, or any crawler that fetched, still has them. A purge is
worth doing to stop further distribution, not to undo the exposure.

## 6. The safest purge procedure, if it is approved

**Not performed. This is the plan, for a separate explicit approval.**

1. **Confirm the window** from the organisation audit log in the browser, so the assessment above is based on
   timestamps rather than inference.
2. **Decide the scope.** Two honest options:
   - *Narrow*: the two `signed/` PDFs only. One blob, the actual sensitivity, and far less history disturbed.
   - *Full*: all 35 files under `docs/engagement/`. Cleaner, and it rewrites the same 57 commits either way, so
     the cost is nearly identical once a rewrite is happening at all.
3. **Take a second backup** before touching anything: a fresh `git bundle create ... --all` plus a `gh` export of
   issues and PRs, stored beside the 22 September bundle with its own SHA-256. Verify both bundles restore into a
   scratch clone before proceeding.
4. **Delete the five merged remote branches**, so they cannot keep the blobs alive.
5. **Rewrite** with `git filter-repo --invert-paths --path docs/engagement/` (or `--path` per file for the narrow
   option). Not `filter-branch`: it is slower, and it is wrong more often. Run it on a fresh clone, never on the
   working checkout.
6. **Verify** in the rewritten clone: the paths are absent from every commit
   (`git log --all --oneline -- docs/engagement/` returns nothing), the tree of the new `main` is byte-identical to
   the old `main`'s tree, and the full check suite passes.
7. **Force-push** `main` with `--force-with-lease`. This needs branch protection relaxed and the repository's
   pre-push hook bypassed deliberately, which is itself a decision to record. **This is the only step in this
   project that is allowed to rewrite shared history, and only with written approval.**
8. **Ask GitHub Support to garbage-collect** unreachable objects. Until they do, the old commits remain fetchable
   by exact SHA even though no ref points at them.
9. **Re-clone locally.** Do not pull into the existing checkout.
10. **Record it** in `DECISIONS.md`, including that the old history survives only in the backup bundles, and
    reverse the D-03 "preserve history" position explicitly rather than leaving the two records contradicting.

**Rotate the signature asset, regardless of whether the purge happens.** A signature graphic that was publicly
reachable should be treated as compromised for future use: generate a new one for new documents and keep the old
one only for the documents that already carry it. That is cheaper than a rewrite, it addresses the one real
sensitivity found, and nothing in the repository depends on it.

## 7. Recommendation

Three things, in order of value for effort:

1. **Done already, and the important one:** the repository is private again.
2. **Rotate the signature asset.** Low cost, and it closes the only genuinely sensitive item.
3. **Then decide on the purge with a clear head.** The case for it is weaker than it first looked: no client
   personal data, no counterparty signature, no payment details, 0 forks, and a superseded unsigned engagement.
   The case against is that it rewrites 57 of 60 commits, breaks every link in 232 historical issues and in five
   merged PR records, and cannot un-publish what was already fetched. If it is done, the narrow option (the two
   signed PDFs) buys most of the benefit.

The D-03 position was "preserve history, do not rewrite". That position was taken when the repository was private.
It is now reconsidered rather than assumed, and this document is the input to that decision. **No purge is
performed without a separate explicit approval.**
