# Tasks: accounts

**Spec**: [spec.md](spec.md) | **Plan**: [plan.md](plan.md)

Each task names its test. `t58` is `mizzey-site/tests/integration/t58-accounts.php`.

| Id | Task | Test |
|---|---|---|
| T001 | `VerificationOnRegistration`: the verification email sent when a customer account is created | t58 AC-5, AC-8 |
| T002 | `MarketingConsent`: the unticked box and its record | t58 AC-1, AC-9 |
| T003 | `PhoneSignIn`: the optional number, one per account, accepted at sign-in | t58 AC-2, AC-3 |
| T004 | `SignInLink`: the REST request, the hashed single-use token, the shared limits, following the link | t58 AC-11 |
| T005 | Wire into `mizzey-site.php`; Arabic for every customer-facing message | t58, t39 |
| T006 | Write `t58-accounts`, the native rows (AC-3, AC-4, AC-6, AC-7, AC-10) included | itself |
| T007 | Full suite; mutation checks; Codex review | suite |
| T008 | Google sign-in (AUTH-10, AUTH-13), on the owner's approval to install the approved plugin and with a client-owned OAuth client: a second PR | not started |
| T009 | Staging, on the owner's approval: t58 on staging | `verification.md` |
| T010 | Client acceptance through MS-UAT-2026-027, with Stage 3 after Stage 2 (D-22) | `verification.md` |
