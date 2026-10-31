# Analysis and review record: ERP adapter seam

Written and built by Claude Code (lead). Codex (gpt-6.1-sol, read-only) reviewed as a second opinion, two rounds,
until no High or Medium finding remained.

| Round | Verdict | Findings, and what was done |
|---|---|---|
| 1 | FIX FIRST | Two Medium. A failing logger, or an adapter whose `name()` fails, could turn a failed answer into an exception: diagnostics are now isolated and the name has a fallback (checked by t53, mutation M6). The local stock check could pass for a wrong reason, because the source's last answer equalled the seeded stock: it now seeds 50 against a source going from 8 to 5 and reads the stored product. Applied (`7ce1979`) |
| 2 | READY | No High or Medium findings |

Codex also confirmed: identity resolution includes drafts and trashed records, returns no identity for a missing
source or variation mapping, and does not let an intact parent group stand in for a variation; retry keys stay stable.
Exactly-once application depends on the adapter honouring durable, atomic deduplication, which is the contract the
interface documents and PRE-09's adapter must meet.
