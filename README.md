# Mizzey Launch Platform

A bilingual (English primary, Arabic fully supported, right to left) single-vendor store for Mizzey.com, built on
WordPress and WooCommerce with the CoreX framework layer.

**This repository is private and holds client confidential material.** Do not make it public, and do not add
collaborators without the client's agreement.

---

## Status

| | |
|---|---|
| **Engagement** | Option B, the Launch Platform, chosen by the client on 21 September 2026 |
| **Contract pack** | MZ-02 REV4 (22 September 2026) |
| **Scope authority** | Feature Register MS-ANX-2026-006 v1.5. **Only this defines scope** |
| **Acceptance** | Functional Specification MS-SPC-2026-032 v1.2 and Acceptance and UAT Plan MS-UAT-2026-027 |
| **Architecture** | Technical Design MS-TDD-2026-033 v1.2, refined by accepted ADRs in [`docs/adr/`](docs/adr/) |
| **ERP** | Stock integration included. ERP-dependent (P1-E) rows wait for the ERP Integration Specification (PRE-09) |
| **Current phase** | Engineering governance and the product-cost pilot. See [PROGRESS.md](PROGRESS.md) |

The contract documents live outside this repository. The repository carries only what building needs: the register
ids, scope values and stages in [`docs/scope/register-ids.json`](docs/scope/register-ids.json), generated from the
signed register with its hash recorded in [`docs/scope/SOURCE.md`](docs/scope/SOURCE.md).

Before 21 September 2026 this repository was built for Option C, the Operations Platform, which was never signed.
That history is kept in git, in [DECISIONS.md](DECISIONS.md), in `discovery/`, and on the archived GitHub Project #4.
None of it is an Option B obligation.

## The rule that matters

Only register rows marked **P1, P1-L, P1-E or DLV** create a delivery obligation. Functional work cites at least one
such id. A valid id is necessary but not sufficient: the behaviour must be supported by that row's wording.
Anything else is a Change Request under MS-CHG-2026-028. Governance, CI, tooling, test infrastructure, security
maintenance and documentation are internal work items. They need no register id, and they may not add
client-facing behaviour. The full rules are in the [constitution](.specify/memory/constitution.md).

## Where to start

| Read | For |
|---|---|
| [AGENTS.md](AGENTS.md) | How any agent or developer works here. Start here |
| [.specify/memory/constitution.md](.specify/memory/constitution.md) | The rules every spec, plan and PR is checked against |
| [CONTRIBUTING.md](CONTRIBUTING.md) | Branches, commits, PR classification, checks |
| [docs/tooling.md](docs/tooling.md) | Spec Kit, skills, CI, the push guard, and what each check does |
| [docs/stack-and-upgrades.md](docs/stack-and-upgrades.md) | Tested versions and the upgrade regression path |
| [docs/scope/open-items.json](docs/scope/open-items.json) | Contract contradictions and gates that specs must cite |

## Stack

| Layer | Choice | Tested version |
|---|---|---|
| CMS | WordPress | 7.1 |
| Commerce | WooCommerce | 11.1.0 |
| Framework | [CoreX](https://github.com/MustafaShaaban/corex), pinned in `corex.lock` | v0.42.0 |
| Translation | WPML with WooCommerce Multilingual (client-held licence) | 4.9.7 / WCML 5.5.7 |
| Shipping | Bosta (register §2.2, recommended) | 4.5.7 |
| Payments | Paymob (register §2.1, recommended). Not yet installed | not selected |

Exact versions and their test status are in [`stack.lock.json`](stack.lock.json). **CoreX supplies the framework,
not a store.** `corex-kit-woo` is a reserved seam, not a commerce kit. Commerce behaviour comes from WooCommerce
first, and from code in `mizzey-site/` only where a recorded gap requires it.

## How this repository meets CoreX

**Mizzey is never inside the CoreX repository, and CoreX is never inside this one.** They are joined only on disk,
in a runtime directory that is not under version control and can be deleted and rebuilt at any moment.

```text
C:\wamp64\www\corex\          CoreX framework development. Holds no client code, ever.

C:\wamp64\www\mizzey\
  platform\                   this repository, the committed source
    corex.lock                the CoreX release the site is built against
    stack.lock.json           tested versions of the whole stack
    mizzey-site\              the client plugin (namespace MizzeySite\)
    mizzey-theme\             the client theme
    tools\corex-sync.mjs      wires the runtime below to the two sources above
  app\                        the runtime. Disposable, not committed, rebuildable
    corex\                    a CoreX checkout pinned to corex.lock
    wp\                       WordPress, wp-content junctioned back to both sources
```

### Local setup

```bash
node tools/corex-sync.mjs
cd ../app/wp && wp db create && wp core install --url=mizzey.local --title=Mizzey --admin_user=<user> --admin_email=<email> --prompt=admin_password
git config core.hooksPath .githooks
```

`node tools/corex-sync.mjs --check` reports drift and changes nothing. It fails if a framework link has been replaced
by a real directory, which is the one mistake that silently shadows the framework source.

### Building the deployable artifact

```bash
node tools/build-dist.mjs --production
```

This drives the stock CoreX shared-host builder. The artifact carries no `.git`, `node_modules`, tests, `.env`,
`wp-config.php` or any document from `docs/`. Until [corex#201](https://github.com/MustafaShaaban/corex/issues/201)
is fixed, a lean artifact and a complete CoreX CLI are mutually exclusive. See
[COREX-WORKAROUNDS.md](COREX-WORKAROUNDS.md).

### Upgrading CoreX or any other component

Follow [docs/stack-and-upgrades.md](docs/stack-and-upgrades.md). For CoreX, the change is one edit to `corex.lock`
on a branch, `node tools/corex-sync.mjs`, `wp corex migrate`, the regression path, then merge or revert.

### A framework bug, mid build

CoreX is a separate product and is not patched for one client. Open a CoreX issue with no client material in it,
work around it inside `mizzey-site/` through a hook, record the workaround in `COREX-WORKAROUNDS.md`, and remove it
when the fix ships in a pinned tag.

---

*Client confidential. Prepared by Mustafa Shaaban.*
