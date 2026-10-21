# ADR-0003: CoreX legacy baseline, capability ownership and revamp migration

**Status:** Proposed. The direction was approved by Mustafa on 21 October 2026 (D-18). Proposed means direction,
not permission to build or to upgrade (`docs/adr/README.md`).
**Date:** 2026-10-21
**Traces:** Internal architecture decision. It adds no Feature Register obligation and changes no row's wording,
scope, stage or accepting PBI. Context PBIs: #275 (staff log), #282 (merchandising), #285 (customer emails), #287
(search engine controls), #291 (Bosta shipping), #311 (self-service storefront), #317 (ERP stock).
**Refines:** MS-TDD-2026-033 v1.2, for the boundary between the client site and the framework only. D-14 item 4
(the next upgrade named as v0.43.0) is superseded by D-18 for the version target only.
**Evidence:** `docs/2026-10-21-corex-mizzey-ownership-audit.md` (the ownership matrix) and
`docs/2026-10-21-corex-v0.47.0-migration-readiness.md` (the upgrade assessment). Both are static source reviews.

## Context

Mizzey is a CoreX client site. Its business logic is in `mizzey-site/`, its presentation in `mizzey-theme/`, and
CoreX arrives through `corex.lock` as an uncommitted, disposable checkout built by `tools/corex-sync.mjs`. Three
versions have to be kept apart:

| | Version | Meaning |
|---|---|---|
| **Current Mizzey pin** | v0.42.0, commit `12dde49` | What `corex.lock` and `stack.lock.json` say and what every Mizzey test ran against. It stays until a separate, approved upgrade PR changes it |
| **Proposed frozen legacy target** | v0.47.0, commit `3a54765` | The last release of the current CoreX generation, published on 21 October 2026. The version the upgrade PR would move to. Not installed or tested for Mizzey |
| **Future CoreX revamp** | Not released | Its version and API are not published. v0.47.0 calls itself "the first release of the revamp" (spec 112), and the CoreX changelog announces that CoreX screens will check their own abilities in a later release. Nothing else about the revamp's interfaces is public |

Two facts from the source review shape this decision:

1. **Mizzey's code calls no CoreX API today.** `MizzeySiteServiceProvider` is empty and is not registered with
   CoreX (D-14, class B); every Mizzey class hooks WordPress, WooCommerce or WPML directly. The upgrade risk is
   therefore what the active CoreX plugins do on the site (heads, options, schema, mail, roles), not a broken call.
2. **Several concepts already have two candidate owners**, one in CoreX and one in WordPress, WooCommerce, a
   third-party plugin or a contracted Mizzey surface: the logo, the email logo, the sender identity, the coming-soon
   page, the section library, the staff log and, in the Bosta plugin, stock. The audit lists each one.

The project remains design first (D-14). ADRs implement obligations; they do not add deliverables, redefine the
register or approve functional work.

## Decision

1. **Ownership boundaries.** A reusable framework capability, a reusable fix and any future framework API belong in
   the CoreX repository. Contracted Mizzey behaviour and adapters belong in `mizzey-site/`; layout, templates and
   tokens in `mizzey-theme/`. Native WordPress, WooCommerce and WPML functionality is preferred when it is proven to
   fit (M-4). The ERP is the stock authority, subject to PRE-09 (M-3).
2. **No framework fork.** CoreX inside the disposable runtime is never patched. A legacy CoreX defect that blocks
   Mizzey is handled by a documented public hook or a thin adapter in `mizzey-site/`, recorded in
   `COREX-WORKAROUNDS.md` with its CoreX issue and its removal condition, and the reusable fix goes to the CoreX
   revamp backlog. A frozen CoreX tag is never modified.
3. **One authority per setting or business concept.** Each has one storage key, one write path and one named owner,
   with every reader listed. Several screens may display the same value. They may not keep competing values. A
   CoreX product setting may coexist with a Mizzey storefront setting only where they are deliberately different
   concepts, recorded as such in the audit (for example the CoreX login logo against the storefront logo).
4. **Derived assets, not duplicate uploads.** The storefront logo is WordPress's Site Logo (`custom_logo`). Email
   and other renditions derive from it through an adapter or a fallback; staff do not upload the same mark twice.
   CoreX's own email-logo override stays empty so that CoreX mail falls back to the same mark.
5. **A switch is valid only if the code obeys it.** Hiding a menu or a field is not disabling a capability. A
   capability is disabled by not activating its plugin, by a flag its code reads, or by a documented filter, and the
   disablement is verified against its hooks, side effects and stored data. No `corex_features_*` value is invented
   that the framework does not read.
6. **`corex-kit-woo` stays inactive.** It is a blueprint registered into a registry that only `corex-kit-company`
   provides, which Mizzey does not run, and its one side effect while active is an HPOS compatibility declaration.
   It is not activated unless a separate review shows a concrete, non-duplicating benefit. WooCommerce itself stays
   active and provides the contracted commerce functions.
7. **One notification inbox.** Contracted operational conditions that staff act on are raised in CoreX's existing
   inbox, through `NotificationService::publish` in category `operations`, by a thin Mizzey adapter. No second inbox,
   bell or alert engine is built. Customer transactional email stays the contracted WooCommerce flow (#285). CoreX's
   inbox delivers in the admin only, so no external or paid channel is introduced.
8. **No new paid dependency** without the owner's written approval. This does not remove existing contractual
   infrastructure, gateway, translation licence or email delivery obligations, which are not free and are flagged
   where they apply.
9. **A feature flag is not a version switch.** A CoreX version changes only through `corex.lock` on a dedicated
   branch and PR, with component regression tests, staging, migrations and a rehearsed backup restore. No admin
   toggle replaces framework code, on any environment.
10. **Revamp readiness.** Mizzey consumes CoreX through documented seams (`Corex::onReady`, `corex_booted`, public
    filters) and thin adapters. When a revamped CoreX publishes a supported version and API, each dependency is
    mapped and adapters that become unnecessary are removed. No adapter is written for an unpublished API.
11. **Change authority is unchanged.** Nothing here adds client-visible behaviour. A feature needs a signed register
    row or an approved Change Request, and no acceptance obligation is reduced.

## Depends on

- An isolated upgrade PR from v0.42.0 to v0.47.0, approved by the owner and classified `internal:security-maintenance`
  or `requirement` as `docs/stack-and-upgrades.md` says, made only when the owner lifts the D-14 restriction for that
  infrastructure change. Its gates are in the readiness assessment.
- OD-01 (brand identity) for the final logo and colours; PRE-09 for every ERP and stock behaviour; the accepting
  PBIs for each contracted surface; CX-01 and ROLE-06 for the Accountant role.
- A released, documented revamped CoreX API for any later major migration.

## Verification required before this is relied on

- For each overlapping setting, the audit row's runtime check: the stored key, its writers and readers, and its
  disable behaviour on a real install.
- Brand: changing the Site Logo changes every intended storefront and email placement, in both languages, with no
  second upload. **WooCommerce email branding is proved separately**: CoreX email branding does not reach
  WooCommerce mail.
- WooCommerce, HPOS, WPML records, checkout, payment and stock keep working with `corex-kit-woo` inactive.
- One deduplicated `operations` item appears, resolves, and is visible only to the roles granted
  `corex_manage_notifications`, with no extra inbox and no external channel.
- No path except the ERP adapter writes authoritative stock; the Bosta stock routes are closed and tested.
- The upgrade has a rehearsed restore. **A git revert alone is not a rollback**: CoreX has no down-migrations.
- Report three states separately: workflow complete, technically verified, contractually accepted.

## Consequences

Mizzey keeps an independent codebase and a pinned framework, and the upgrade becomes a bounded, testable change
rather than a guess. Design briefs for shared administration settings must name the single authority and mark any
mirror as read-only. Some contracted surfaces gain a documented native or CoreX authority instead of a new Mizzey
setting. The revamp's migration effort stays unknown until its API exists, and nothing here promises that it will be
small.

## Alternatives rejected

- **Patch the CoreX checkout inside Mizzey**: overwritten by the next sync, and an unmaintained fork.
- **Build a Mizzey notification centre or settings engine beside CoreX's**: duplicate storage, permissions and
  screens.
- **Hide duplicate inputs without disabling their writers**: inconsistent data and invisible side effects.
- **Activate `corex-kit-woo` by default**: no evidenced benefit on Mizzey.
- **Switch CoreX versions from an admin setting**: unsafe schema and dependency changes on a live site.
- **Stay on v0.42.0 indefinitely**: corex#201 is fixed only in later releases, and v0.47.0 is the last release of
  the generation Mizzey is built on, so staying would keep Mizzey on an intermediate release with a known CLI
  defect, without the fourteen releases published since.
