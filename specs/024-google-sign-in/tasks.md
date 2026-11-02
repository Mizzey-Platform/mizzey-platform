# Tasks: Google sign-in

**Spec**: [spec.md](spec.md) | **Plan**: [plan.md](plan.md)

Each task names its test. `t65` is `mizzey-site/tests/integration/t65-google-sign-in.php`.

| Id | Task | Test |
|---|---|---|
| T001 | `GoogleSignIn`: the start, the callback, the ID token checks | t65 AC-1 |
| T002 | The account: linked, by verified email, or new; staff refused | t65 AC-2 |
| T003 | Wire into `mizzey-site.php`; Arabic for every message | t65, t39 |
| T004 | Mutation checks; Codex review | `evidence/mutation.txt`, `analysis.md` |
| T005 | Staging, on the owner's approval, with a developer test OAuth client | `verification.md` |
| T006 | The client's OAuth client in their Google Cloud project before launch (vendor input) | `verification.md` |
| T007 | Client acceptance through MS-UAT-2026-027, with Stage 3 after Stage 2 (D-22) | `verification.md` |
