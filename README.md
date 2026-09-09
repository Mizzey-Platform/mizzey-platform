# Mizzey Operations Platform

A premium single vendor store for Mizzey.com. English primary, Arabic fully supported at launch with
right to left throughout. Built on WordPress and WooCommerce with the CoreX framework layer.

**This repository is private and holds client confidential material.** Do not make it public, and do not
add collaborators without the client agreement.

---

## What this is

| | |
|---|---|
| **Client** | Mizzey.com |
| **Engagement** | Mizzey Operations Platform, the package selected on 5 September 2026 |
| **Contract** | Services Agreement MS-AGR-2026-010, Statement of Work MS-SOW-2026-009 |
| **Scope authority** | Feature Register MS-ANX-2026-001. **Only this defines scope** |
| **Acceptance authority** | Functional Specification MS-SPC-2026-018 and Acceptance Plan MS-UAT-2026-013 |
| **Schedule** | 14 weeks planned, 16 week band. Six payment stages |
| **Market** | Egypt only, Egyptian Pounds |

## The rule that matters

**Scope is defined by the Feature Register, and only by the Feature Register.** Only rows marked P1, P1-L
or DLV create a delivery obligation. Anything else is recorded for completeness and creates none.

Every issue in this repository traces to a requirement id in that register. **An issue that cannot cite
one is not work, it is a Change Request** and goes through MS-CHG-2026-014 before any code is written.

## Documents

Everything lives in [`docs/engagement/`](docs/engagement/), as markdown source beside the rendered PDF.

| Read this | When |
|---|---|
| [Feature Register](docs/engagement/Mizzey-Feature-Register.md) | You need to know whether something is in scope |
| [Functional Specification](docs/engagement/Mizzey-Operations-Platform-Functional-Specification.md) | You need the acceptance criteria for a story |
| [Delivery Backlog](docs/engagement/Mizzey-Operations-Platform-Delivery-Backlog.md) | You need to know which sprint something is in, and why |
| [Project Plan](docs/engagement/Mizzey-Operations-Platform-Project-Plan.md) | You need the stage gates and what the client owes before each |
| [Acceptance and UAT Plan](docs/engagement/Mizzey-Operations-Platform-Acceptance-and-UAT-Plan.md) | You need to know how something gets accepted |
| [Change Control](docs/engagement/Mizzey-Operations-Platform-Change-Control.md) | Someone wants something that is not in the register |

Signed contracts are in [`docs/engagement/signed/`](docs/engagement/signed/).

## How the board works

| Object | Meaning |
|---|---|
| **Milestone** | One payment stage. Closing it is what releases a payment |
| **`sprint-NN` label** | One week. Sixteen of them, 1 to 14 planned and 15 to 16 contingency |
| **`epic:ENN` label** | One epic from the Functional Specification |
| **Epic issue** | Lists its stories. Closed when they all are |
| **Story issue** | Carries its acceptance criteria as a checklist. Every box ticked is the definition of done |

A story issue is **generated from the Functional Specification and is not edited on the board**. If a
criterion is wrong, the specification is corrected and the issue regenerated, so the board and the
contract cannot drift apart.

## Stack

| Layer | Choice | Why |
|---|---|---|
| CMS | WordPress | Client requirements section 19.1 left the stack to the developer |
| Commerce | WooCommerce | Products, stock, cart, orders, coupons, refunds are mature rather than written from scratch |
| Framework | [CoreX](https://github.com/MustafaShaaban/corex) | Container, config, routing, admin shell, forms, blocks, CLI, mail, media |
| Site layer | `wp corex make:site Mizzey` | Client plugin and theme in their own namespace |
| Payments | Paymob, behind a payment abstraction | Recommended, pending client approval (OD-06) |
| Shipping | Bosta, behind a carrier abstraction | Recommended, pending client approval (OD-07) |

**CoreX supplies the framework, not a store.** `corex-kit-woo` is a reserved seam, not a commerce kit
(CoreX ROADMAP M9). The commerce modules in this repository are written for Mizzey.

## Branching

| Branch | Purpose |
|---|---|
| `main` | Always deployable. Protected. Merges arrive by pull request only |
| `feature/<issue>-<slug>` | One issue. Example: `feature/42-guest-cart-merge` |
| `fix/<issue>-<slug>` | A defect against an accepted story |

A pull request closes its issue, cites the requirement ids it delivers, and states how each acceptance
criterion was verified. CI must pass before merge.

## Environments

| Environment | Purpose |
|---|---|
| Local | WAMP. `C:\wamp64\www\mizzey` |
| Staging | Every stage gate is reviewed here. Risky changes are never tested on production |
| Production | Provisioned once the client hosting instruction (CR-10) and any required authorisation are in place |

## How this repository meets CoreX

**Mizzey is never inside the CoreX repository, and CoreX is never inside this one.** They are joined
only on disk, in a runtime directory that is not under version control and can be deleted and rebuilt
at any moment.

```text
C:\wamp64\www\corex\          CoreX framework development. Holds no client code, ever.

C:\wamp64\www\mizzey\
  platform\                   this repository, the committed source
    corex.lock                the CoreX release the site is built against
    mizzey-site\              the client plugin
    mizzey-theme\             the client theme
    tools\corex-sync.mjs      wires the runtime below to the two sources above
  app\                        the runtime. Disposable, not committed, rebuildable
    corex\                    a CoreX checkout pinned to corex.lock
    wp\                       WordPress, wp-content junctioned back to both sources
```

`app/` holds nothing original. Delete it and `node tools/corex-sync.mjs` builds it again.

### Local setup

```bash
node tools/corex-sync.mjs
```

Then create the database and install WordPress:

```bash
cd ../app/wp && wp db create && wp core install --url=mizzey.local --title=Mizzey --admin_user=admin --admin_email=mustafashaaban22@gmail.com --prompt=admin_password
```

`node tools/corex-sync.mjs --check` reports drift and changes nothing. It fails if a framework link has
been replaced by a real directory, which is the one mistake that silently shadows the framework source.

### Upgrading CoreX

An upgrade is a commit, not an event. On a branch:

1. Edit `corex.lock` to the new tag and commit hash
2. `node tools/corex-sync.mjs`
3. `wp corex migrate`
4. Run the smoke tests
5. Merge, or `git revert` the one file and you are back

Never track a moving branch. `corex.lock` is the only thing that decides which CoreX this site runs.

### A framework bug, mid build

CoreX is a separate product and is not patched for one client. When the framework is wrong:

1. Open an issue on [CoreX](https://github.com/MustafaShaaban/corex), describing the defect with no
   client material in it
2. Work around it **inside `mizzey-site/`**, through a hook, never by editing `app/corex/`
3. Record the workaround in `COREX-WORKAROUNDS.md` against the issue number
4. When the fix ships in a tag, bump `corex.lock` and delete the workaround

Anything edited inside `app/corex/` is lost on the next sync. That is deliberate.

---

*Client confidential. Prepared by Mustafa Shaaban, Software Engineering Lead.*
