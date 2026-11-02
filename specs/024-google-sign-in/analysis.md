# Analysis: Google sign-in

Read-only reviews by Codex (gpt-6.1-sol, effort high), each finding checked against WordPress 7.1.4's, WooCommerce
11.1.0's and Google's documented behaviour before it was applied, and each fix given a t65 check that fails when the
fix is removed (`evidence/mutation.txt`), unless the record says why it cannot have one.

| Round | Verdict | Findings, and what was done |
|---|---|---|
| 1 | FIX FIRST | One High, two Medium, two Low. Google's email verification can be stale for an address it is not the authority for: only Gmail and the token's Workspace domain are linked. A manual and a Google registration at once could make two accounts: both take one lock per email and check again inside it. The tests did not see the sign-in cookie, the guest orders or the squatter's session: they do (and `wp_set_password()` was found not to end sessions, so they are ended explicitly). The state could be used twice at once: it is claimed by its delete. `azp` is checked whenever present |
| 2 | FIX FIRST | One High, one Medium. A new account met the sign-up check (AUTH-15) with no permit, which WP-CLI's trust hid: the callback's own request gets a permit for the verified email. A non-authoritative email could leave a dormant link: Google sign-in is refused for any email Google is not the authority for |
| 3 | READY | No High or Medium findings |

Limits, recorded: a successful callback end to end over HTTP would need Google's token endpoint mocked inside the web
server, which needs test code in production or a must-use plugin, both refused here; the callback is tested in
process through `rest_do_request()` with the real sign-up check. Concurrency cannot be made in one PHP process.
