# Verification: security baseline, the site-level controls

Three states, kept apart (constitution M-7): technically verified, verified on staging, accepted by the client. Only
the first is claimed here.

## 1. Technical verification, 29 October 2026, development runtime

Stack: WordPress 7.1.4, WooCommerce 11.1.0, WPML 4.9.7, WCML 5.5.7, CoreX v0.47.0.

| Evidence | What it shows |
|---|---|
| `evidence/before.txt` | t39 to t41 run before any production code: each failed |
| `evidence/clean-baseline.txt` | The runtime reset to the clean baseline before the suite (exit 0) |
| `evidence/final-suite.txt`, `final-suite.json` | The whole suite, serially: 28 scenarios, 23 pass, 5 fact-finding, 0 failed |
| `evidence/final-rerun.txt` | t21 and t39 to t41 again on the final code, after text-only edits made while the suite ran: all pass |

| AC | Scenario | Result |
|---|---|---|
| AC-1 | t39 | Pass: no generator mark in pages, feeds or the head, in English and Arabic |
| AC-2 | t39 | Pass: every XML-RPC method and `?rsd` answer 403; no pingback header or discovery link |
| AC-3 | t39 | Pass: users routes refused to visitors (pretty and `?rest_route=`); editor keeps them; numeric author queries 404 in both languages; no users sitemap, product sitemap kept |
| AC-4 | t40 | Pass: application passwords for administrators only, in process and over HTTP |
| AC-5 | t40 | Pass: one generic message on both sign-in pages and in both languages; CoreX's lockout and empty credentials unchanged |
| AC-6 | t40 | Pass: recovery limits per account and address on both handlers; refusal sends no email; Arabic refusal read over HTTP |
| AC-7 | t41 | Pass: comments closed on posts and pages, refused over HTTP; product review stored with its rating; trackback refused |
| AC-8 | t41 | Pass: the command exits 0 on the baseline and 1 naming a control switched off |

Unit and repository checks: `python -m unittest discover -s tools/tests -t .`, `repo_checks.py`, `discovery.check`,
`house_rules.py` and `scope_trace.py`, as reported in the pull request.

## 2. Staging

Not done. T017 waits for the owner's deployment approval. The person's own staging review (DOD-04) is separate from
any automated check run there.

## 3. Client acceptance

Not done. T018, through MS-UAT-2026-027 (DOD-09). This feature is a slice of NFR-05: its completion does not establish
the row's acceptance.
