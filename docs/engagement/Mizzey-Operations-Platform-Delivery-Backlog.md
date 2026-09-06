# Mizzey Operations Platform
## Delivery Backlog and Sprint Plan

**Document:** MS-BLG-2026-020
**Version:** 1.0
**Date:** 6 September 2026
**Prepared for:** Mizzey.com
**Prepared by:** Mustafa Shaaban
**Contract role:** Stage 1 deliverable under the Statement of Work, MS-SOW-2026-009, satisfying PRE-04
**Companion documents:** Functional Specification MS-SPC-2026-018, Project Plan MS-PLN-2026-011, Feature Register MS-ANX-2026-001

---

# PART ONE: HOW THIS PLAN IS BUILT

## 1. What this document is

This is the work list promised as part of the Stage 1 deliverable: the project broken into milestones and
deliverables, as PRE-04 requires. It takes the 205 user stories in the Functional Specification and places
every one of them in a sprint, against a payment stage, with an estimate and its dependencies.

It is the document the delivery board is generated from. Every ticket on that board exists here first.

## 2. Two clocks, and why they are not the same

**The stage is contractual. The sprint is executional.** They are recorded separately throughout, because
conflating them is how a plan starts to lie.

| | What it is | Who it binds |
|---|---|---|
| **Stage** | One of the six delivery stages in the Statement of Work. Its gate releases a payment | Both parties. Fixed by contract |
| **Sprint** | One week of work. Sixteen of them | The developer only. Changeable without a Change Request |

A story may be **built earlier than the stage that accepts it**, where its dependencies allow. Nine stories
are scheduled that way and each is marked. The import engine, for example, needs the product model and the
admin shell, both of which land in Stage 2. Building it then, rather than leaving it until Stage 5, is the
difference between a plan that fits and one that does not. **Its acceptance gate does not move:** it is
still reviewed and accepted at the Stage 5 gate, and still releases the Stage 5 payment there.

## 3. What a point means

Points are Fibonacci and relative. They come from two things, and the second is the one that matters.

**The count of acceptance criteria**, as a proxy for surface area, with a multiplier where the story carries
a key requirement.

**How much WooCommerce already does.** An epic Woo implements is configuration, theming and proving. An epic
Woo has nothing for is a module written from scratch on CoreX. Estimating both at the same rate is the most
common way a WordPress commerce estimate goes wrong, so the leverage factor is explicit rather than buried:

| Leverage | Factor | Meaning | Epics |
|---|---|---|---|
| **Native** | 0.7 to 0.8 | WooCommerce implements it. The work is configuration, the theme layer, and proving it | E04, E07, E08, E10, E16, E17, E18, E19 |
| **Partial** | 1.0 to 1.1 | Woo provides the mechanism, the required behaviour extends it | E01, E02, E03, E06, E09, E12, E21, E24, E25, E26, E27 |
| **Mixed** | 1.1 to 1.3 | Half configuration, half custom rule work | E05, E11, E14 |
| **Custom** | 1.5 to 1.7 | Woo provides nothing. Written on CoreX from scratch | E13, E15, E20, E22, E23 |

**Where the effort actually sits.** By points, not by story count:

| Leverage | Stories | Points | Share of effort |
|---|---|---|---|
| Native | 58 | 123 | 18% |
| Partial | 74 | 250 | 37% |
| Mixed | 29 | 86 | 12% |
| Custom | 44 | 211 | 31% |
| **Total** | **205** | **670** | |

> **Read the custom row before anything else in this document.** Close to a third of the estimated effort
> sits in five epics WooCommerce contributes nothing to: the customer returns workflow, the Operations
> Console, the returns administration, the self service storefront controls, and the catalogue migration
> tooling. Those five are also where the schedule risk sits, and section 7 says what is being done about it.

## 4. Definition of ready

A ticket is not started until all five hold. This exists so that a blocked ticket is found at planning,
not halfway through the week.

| # | A ticket is ready when |
|---|---|
| 1 | Its acceptance criteria are the ones in the Functional Specification, unchanged |
| 2 | Every open decision it depends on has been answered |
| 3 | Every client input it depends on has arrived |
| 4 | The interface design covering its screens is approved, where it has any |
| 5 | Its dependencies on other tickets are closed, or sit in the same sprint |

## 5. Definition of done for a ticket

The nine criteria in Annex A Section M apply to every ticket. In board terms:

| # | A ticket is done when |
|---|---|
| 1 | Every acceptance criterion on it passes, verified by hand on staging |
| 2 | It works in English and in Arabic, right to left, with no layout break |
| 3 | It works on a phone, a tablet and a desktop |
| 4 | Exception paths behave as specified, not only the successful path |
| 5 | Where it touches money, stock, permissions or refunds, an automated test covers it |
| 6 | Where it changes sensitive data, the audit entry is written |
| 7 | Any analytics event named on it fires with the correct parameters |
| 8 | Any setting it introduces is documented |
| 9 | It is merged to the main branch through a pull request that passes CI |

---

# PART TWO: THE SIXTEEN SPRINTS

## 6. The plan at a glance

| Sprint | Week | Stage | Payment at the stage gate | Stories | Points | Epics |
|---|---|---|---|---|---|---|
| **1** | 1 | 1 | 58,000 EGP | 1 | 3 | E27 |
| **2** | 2 | 1 | 58,000 EGP | 0 | 0 | Specification approval gate |
| **3** | 3 | 2 | 58,000 EGP | 8 | 32 | E01 |
| **4** | 4 | 2 | 58,000 EGP | 12 | 33 | E16, E23 |
| **5** | 5 | 2 | 58,000 EGP | 12 | 47 | E17, E21, E23, E27 |
| **6** | 6 | 3 | 58,000 EGP | 24 | 58 | E02, E05, E11 |
| **7** | 7 | 3 | 58,000 EGP | 22 | 57 | E04, E06, E10 |
| **8** | 8 | 3 | 58,000 EGP | 18 | 53 | E03, E07 |
| **9** | 9 | 4 | 43,500 EGP | 11 | 61 | E15 |
| **10** | 10 | 4 | 43,500 EGP | 19 | 64 | E12, E14, E22 |
| **11** | 11 | 4 | 43,500 EGP | 24 | 64 | E08, E09, E18, E19 |
| **12** | 12 | 5 | 43,500 EGP | 18 | 75 | E20, E23, E24, E27 |
| **13** | 13 | 5 | 43,500 EGP | 20 | 82 | E13, E22, E25, E26 |
| **14** | 14 | 6 | 29,000 EGP | 10 | 33 | E12, E27 |
| **15** | 15 | 7 | - EGP | 3 | 4 | E10, E19 |
| **16** | 16 | 7 | - EGP | 3 | 4 | E03, E12, E24 |
| | | | | **205** | **670** | |

## 7. Where the load is, and where the contingency goes

The Statement of Work prices fourteen weeks, and the Project Plan states a fourteen to sixteen week band.
That band is not padding. It is load bearing, and this section says exactly where.

| Stage | Weeks | Stories | Points | Points per week | Verdict |
|---|---|---|---|---|---|
| 1. Start and specification | 1 to 2 | 1 | 3 | 2 | **Comfortable** |
| 2. Foundation and products | 3 to 5 | 23 | 69 | 23 | **Comfortable** |
| 3. Shop front complete | 6 to 8 | 64 | 168 | 56 | **At capacity** |
| 4. Operations system | 9 to 11 | 57 | 204 | 68 | **Over capacity** |
| 5. Migration and reports | 12 to 13 | 44 | 185 | 92 | **Over capacity** |
| 6. Live and handed over | 14 | 10 | 33 | 33 | **Comfortable** |
| 7. Second release and contingency | 15 to 16 | 6 | 8 | 4 | **Comfortable** |

The average build week carries **55 points**. That is the capacity this plan assumes, and it already
assumes a developer working alone, without interruption.

**Two stages exceed it, and the reason is structural rather than a scheduling mistake.** Stage 5 is the
shortest build stage at two weeks, and it contains the heaviest custom work in the project: the catalogue
migration tooling, which carries the highest leverage factor in the estimate. Stage 4 contains the
Operations Console, the returns administration and the self service controls, which are three of the other
four custom epics. The contracted stage boundaries put the tightest weeks around the most expensive work.

**Three things are being done about it, and none of them is optimism.**

**Work is pulled forward into Stage 2, which has real slack.** Stage 2 runs well under capacity. Nine
stories whose dependencies are already satisfied by the Stage 2 deliverables are built there instead: the
import engine foundation, and the roles and audit log. Their acceptance gates do not move.

**The two contingency weeks attach to sprints 12 and 13, not to the end of the project.** Those two sprints
carry the highest load in the plan. A week that overruns there consumes contingency at the point of
overrun, rather than pushing every subsequent sprint back one.

**The Project Plan already said this.** It records that the two week band sits across stages 3 to 6, where
the volume of work and the number of external dependencies are highest. This plan agrees with it, and names
the weeks.

> **What would change the answer.** If the client decides during Stage 2 that the second release items can
> be dropped rather than deferred, or that a P1-L reduction can be reduced further, the load falls. Neither
> is proposed here. Both would be Change Requests, and both are the client decision, not the developer's.

## 8. Sprint by sprint

### Sprint 1. Week 1. Stage 1, Start and specification

**1 stories, 3 points.** **Needed before it starts:** Signature. Confirmation of the business decisions in MS-DEP-2026-012 section 5, or a date by which each will be made. **Ends with:** Client approves the specification and the acceptance model

| Story | Title | Epic | Scope | Pts | Notes |
|---|---|---|---|---|---|
| US-27-10 | Pre development deliverables | E27 | DLV | 3 | - |

### Sprint 2. Week 2. Stage 1, Start and specification

**0 stories, 0 points.** **Needed before it starts:** The same. This is the client review week, not a build week. **Ends with:** Stage 1 gate. Payment released

No build stories. This is the client review period for the Stage 1 deliverables.

### Sprint 3. Week 3. Stage 2, Foundation and products

**8 stories, 32 points.** **Needed before it starts:** Approved brand identity (CR-02, OD-01). Written hosting instruction (CR-10, OD-27). **Ends with:** Platform foundation on staging

| Story | Title | Epic | Scope | Pts | Notes |
|---|---|---|---|---|---|
| US-01-01 | Bilingual storefront with full right to left support | E01 | P1 | 13 | Key |
| US-01-02 | Language switcher | E01 | P1 | 3 | - |
| US-01-03 | No hard coded storefront text | E01 | P1 | 2 | - |
| US-01-04 | Locale aware formatting | E01 | P1 | 2 | - |
| US-01-05 | Header and navigation | E01 | P1 | 3 | - |
| US-01-06 | Mobile header and navigation | E01 | P1 | 2 | - |
| US-01-07 | Trust and support pages | E01 | P1 | 5 | - |
| US-01-08 | Premium visual treatment | E01 | P1 | 2 | - |

### Sprint 4. Week 4. Stage 2, Foundation and products

**12 stories, 33 points.** **Needed before it starts:** The real Amazon sample export file (CR-06). The interface design is presented for approval this week. **Ends with:** Product model exercised against the real catalogue structure

| Story | Title | Epic | Scope | Pts | Notes |
|---|---|---|---|---|---|
| US-16-01 | Product lifecycle | E16 | P1 | 2 | - |
| US-16-02 | Every product field is editable | E16 | P1, with compare at price, wei | 5 | Key |
| US-16-03 | Product content in both languages | E16 | P1 | 2 | Key |
| US-16-04 | Product variants | E16 | P1, with weight and dimensions | 2 | - |
| US-16-05 | Product cost captured from day one | E16 | P1 | 3 | Key |
| US-16-06 | SEO fields per product | E16 | P1 | 2 | - |
| US-16-07 | Bulk import and export | E16 | P1 | 2 | - |
| US-16-08 | Price and stock change history | E16 | P1-L | 2 | - |
| US-16-09 | Categories, brands and collections | E16 | P1 | 3 | - |
| US-23-01 | File formats accepted | E23 | P1 | 3 | built early, accepted at the Stage 5 gate, Custom, no Woo support |
| US-23-02 | Encoding detection verified against Arabic | E23 | P1 | 5 | Key, built early, accepted at the Stage 5 gate, Custom, no Woo support |
| US-23-05 | Preview | E23 | P1 | 2 | built early, accepted at the Stage 5 gate, Custom, no Woo support |

### Sprint 5. Week 5. Stage 2, Foundation and products

**12 stories, 47 points.** **Needed before it starts:** Written approval of the interface design. Product cost basis (OD-12). **Ends with:** Stage 2 gate. Payment released

| Story | Title | Epic | Scope | Pts | Notes |
|---|---|---|---|---|---|
| US-17-01 | Stock levels | E17 | P1, with reserved stock at P1- | 2 | - |
| US-17-02 | Stock adjustments | E17 | P1-L | 2 | - |
| US-17-03 | Low stock thresholds and alerts | E17 | P1 | 2 | - |
| US-17-04 | Bulk stock import | E17 | P1 | 2 | - |
| US-21-01 | Staff users and roles | E21 | P1-L | 5 | built early, accepted at the Stage 4 gate |
| US-21-02 | Two factor authentication for admin | E21 | P1 | 2 | built early, accepted at the Stage 4 gate |
| US-21-03 | Audit log | E21 | P1-L, with the deletion protec | 8 | built early, accepted at the Stage 4 gate |
| US-23-03 | Column mapping | E23 | P1, with brand mapping at P1-L | 8 | Key, built early, accepted at the Stage 5 gate, Custom, no Woo support |
| US-23-04 | Saved mapping profiles | E23 | P1 | 2 | built early, accepted at the Stage 5 gate, Custom, no Woo support |
| US-23-06 | Dry run | E23 | P1, with per field overwrite c | 8 | Key, built early, accepted at the Stage 5 gate, Custom, no Woo support |
| US-27-03 | Security | E27 | P1 | 3 | - |
| US-27-05 | Backups and environments | E27 | P1 | 3 | - |

### Sprint 6. Week 6. Stage 3, Shop front complete

**24 stories, 58 points.** **Needed before it starts:** Approved interface design. Storefront content in both languages beginning to arrive (CR-15). **Ends with:** Home, listing and cart on staging

| Story | Title | Epic | Scope | Pts | Notes |
|---|---|---|---|---|---|
| US-02-01 | Home page sections | E02 | P1 | 3 | - |
| US-02-02 | Trust signals and signup prompt | E02 | P1 | 2 | - |
| US-02-03 | Home page content is editable | E02 | P1 | 2 | - |
| US-02-04 | Home page section order | E02 | P1-L | 2 | - |
| US-05-01 | Cart contents | E05 | P1 | 3 | - |
| US-05-02 | Mini cart | E05 | P1 | 2 | - |
| US-05-03 | Totals breakdown | E05 | P1 | 2 | - |
| US-05-04 | Free shipping progress | E05 | P1 | 3 | - |
| US-05-05 | Cross sell in the cart | E05 | P1-L | 2 | - |
| US-05-06 | Coupon entry in the cart | E05 | P1 | 3 | - |
| US-05-07 | Stock re validation in the cart | E05 | P1 | 2 | - |
| US-05-08 | Empty cart | E05 | P1 | 1 | - |
| US-05-09 | Cart persists for signed in customers | E05 | P1 | 3 | Key |
| US-05-10 | Guest cart survives registration | E05 | P1 | 3 | Key |
| US-05-11 | Guest cart merges on sign in | E05 | P1 | 8 | Key |
| US-05-12 | Guest cart survives Google sign in | E05 | P1 | 2 | Key |
| US-05-13 | Guest cart survives a browser close | E05 | P1 | 2 | - |
| US-11-01 | Submit a review | E11 | P1 | 2 | - |
| US-11-02 | Review moderation | E11 | P1 | 2 | - |
| US-11-03 | Review invitation | E11 | P1 | 2 | - |
| US-11-04 | Wishlist | E11 | P1 | 3 | - |
| US-11-05 | Move wishlist to cart | E11 | P1 | 1 | - |
| US-11-06 | Guest wishlist transfers on sign in | E11 | P1-L | 1 | - |
| US-11-07 | Recently viewed | E11 | P1 | 2 | - |

### Sprint 7. Week 7. Stage 3, Shop front complete

**22 stories, 57 points.** **Needed before it starts:** Product photography (CR-05). Authenticity and warranty policy (OD-13). **Ends with:** Product pages and customer accounts on staging

| Story | Title | Epic | Scope | Pts | Notes |
|---|---|---|---|---|---|
| US-04-01 | Product media | E04 | P1 | 2 | - |
| US-04-02 | Product identity and price | E04 | P1 | 1 | - |
| US-04-03 | Variants and stock | E04 | P1 | 2 | - |
| US-04-04 | Add to cart, buy now and wishlist | E04 | P1 | 2 | - |
| US-04-05 | Product information | E04 | P1 | 2 | - |
| US-04-06 | Delivery, returns and authenticity | E04 | P1-L for the delivery estimate | 2 | - |
| US-04-07 | Reviews on the product page | E04 | P1-L | 2 | - |
| US-04-08 | Related and recently viewed | E04 | P1 | 1 | - |
| US-04-09 | Out of stock handling | E04 | P1 | 2 | - |
| US-04-10 | Sharing and structured data | E04 | P1 | 1 | - |
| US-06-01 | Register with email and password | E06 | P1 | 5 | - |
| US-06-02 | Sign in and password reset | E06 | P1 | 3 | - |
| US-06-03 | Sign in and register with Google | E06 | P1 | 8 | Key |
| US-06-04 | One account per email | E06 | P1 | 5 | Key |
| US-06-05 | Account security | E06 | P1 | 3 | - |
| US-06-06 | Guest checkout | E06 | P1 | 2 | - |
| US-06-07 | One step account creation after ordering | E06 | P1 | 8 | Key |
| US-10-01 | Account dashboard | E10 | P1 | 1 | - |
| US-10-02 | Order history, detail and tracking | E10 | P1 | 2 | - |
| US-10-03 | Invoice download | E10 | P1-L | 1 | - |
| US-10-04 | Address book | E10 | P1 | 1 | - |
| US-10-05 | Profile and password | E10 | P1 | 1 | - |

### Sprint 8. Week 8. Stage 3, Shop front complete

**18 stories, 53 points.** **Needed before it starts:** Welcome discount rules (OD-03). Free shipping definition (OD-04). **Ends with:** Stage 3 gate. The full customer journey walked in both languages, on a phone and a desktop

| Story | Title | Epic | Scope | Pts | Notes |
|---|---|---|---|---|---|
| US-03-01 | Product listing grid | E03 | P1 | 8 | - |
| US-03-02 | Filtering | E03 | P1 | 5 | - |
| US-03-04 | Sorting | E03 | P1 | 2 | - |
| US-03-05 | Empty results | E03 | P1 | 2 | - |
| US-03-06 | Search | E03 | P1 | 5 | - |
| US-03-07 | Arabic search handling | E03 | P1-L [attn] | 3 | - |
| US-03-08 | Search term logging | E03 | P1 | 2 | - |
| US-03-09 | Collection and brand pages | E03 | P1 | 2 | - |
| US-07-01 | Address entry and saved addresses | E07 | P1 | 3 | - |
| US-07-02 | Shipping method and cost | E07 | P1 | 2 | - |
| US-07-03 | Payment method selection | E07 | P1, with method availability c | 3 | - |
| US-07-04 | Order review and terms | E07 | P1 | 2 | - |
| US-07-05 | Coupon at checkout | E07 | P1 | 2 | - |
| US-07-06 | Stock validation at placement | E07 | P1 | 3 | Key |
| US-07-07 | Duplicate order and duplicate charge protection | E07 | P1 | 3 | Key |
| US-07-08 | Order confirmation | E07 | P1-L | 2 | - |
| US-07-09 | Mobile checkout | E07 | P1 | 2 | - |
| US-07-10 | Order status model | E07 | P1, with ORD-14 at P1-L | 2 | - |

### Sprint 9. Week 9. Stage 4, Operations system

**11 stories, 61 points.** **Needed before it starts:** Approved payment merchant account (CR-01). Carrier account, API key and rate card (CR-03). **Ends with:** The Operations Console on staging

| Story | Title | Epic | Scope | Pts | Notes |
|---|---|---|---|---|---|
| US-15-01 | The Operations Console | E15 | P1 | 8 | Key, Custom, no Woo support |
| US-15-02 | Orders requiring action queue | E15 | P1 | 8 | Key, Custom, no Woo support |
| US-15-03 | Stock requiring attention | E15 | P1 | 3 | Custom, no Woo support |
| US-15-04 | Today summary | E15 | P1 | 3 | Custom, no Woo support |
| US-15-05 | Saved views | E15 | P1-L | 3 | Custom, no Woo support |
| US-15-06 | Add a product in under three minutes | E15 | P1 | 5 | Custom, no Woo support |
| US-15-07 | Inline edit grid | E15 | P1-L | 8 | Key, Custom, no Woo support |
| US-15-08 | Bulk order operations | E15 | P1, with carrier dispatch and | 5 | Custom, no Woo support |
| US-15-09 | Bulk product operations | E15 | P1, with category assignment a | 8 | Custom, no Woo support |
| US-15-10 | Find anything quickly | E15 | P1 | 5 | Custom, no Woo support |
| US-15-11 | Admin language and mobile use | E15 | P1, with mobile admin at P1-L | 5 | Custom, no Woo support |

### Sprint 10. Week 10. Stage 4, Operations system

**19 stories, 64 points.** **Needed before it starts:** Cash on delivery decision (OD-05). Payment methods for launch (OD-19). VAT and invoicing (OD-09). **Ends with:** Promotions and order administration

| Story | Title | Epic | Scope | Pts | Notes |
|---|---|---|---|---|---|
| US-12-01 | Account created notification | E12 | P1 email | 2 | - |
| US-12-02 | Order confirmation notification | E12 | P1 email | 2 | - |
| US-12-03 | Payment failed notification | E12 | P1 email | 1 | - |
| US-12-04 | Shipping and delivery notifications | E12 | P1 email | 2 | - |
| US-14-01 | Promotion engine | E14 | P1, with stacking, priority an | 13 | Key |
| US-14-02 | Welcome discount | E14 | P1 | 3 | - |
| US-14-03 | Free shipping rule | E14 | P1 | 3 | - |
| US-14-04 | Promotion stacking is explicit | E14 | P1 for the rule, P1-L for the | 5 | Key |
| US-14-05 | Price snapshot | E14 | P1 | 3 | Key |
| US-14-06 | Refund integrity | E14 | P1 | 3 | Key |
| US-14-07 | Stock returns on cancellation | E14 | P1 | 3 | Key |
| US-14-08 | No negative totals | E14 | P1 | 1 | - |
| US-14-09 | Merchandising controls | E14 | P1 | 3 | - |
| US-22-01 | Nothing a customer reads is locked in code | E22 | P1 | 5 | Key, Custom, no Woo support |
| US-22-02 | Home page sections | E22 | P1 | 3 | Custom, no Woo support |
| US-22-03 | Show and hide sections | E22 | P1 | 3 | Custom, no Woo support |
| US-22-04 | Category and brand page content | E22 | P1 | 3 | Custom, no Woo support |
| US-22-05 | Collection content and ordering | E22 | P1 | 3 | Custom, no Woo support |
| US-22-06 | Navigation and footer | E22 | P1 | 3 | Custom, no Woo support |

### Sprint 11. Week 11. Stage 4, Operations system

**24 stories, 64 points.** **Needed before it starts:** Return window (OD-08). Carrier remittance cycle (OD-20). COD verification decision (OD-29). **Ends with:** Stage 4 gate. The client team, not the developer, processes test orders and a test return

| Story | Title | Epic | Scope | Pts | Notes |
|---|---|---|---|---|---|
| US-08-01 | Payment abstraction layer | E08 | P1 | 3 | Key |
| US-08-02 | No card data on Mizzey servers | E08 | P1 | 2 | - |
| US-08-03 | Callback verification | E08 | P1 | 2 | Key |
| US-08-04 | Payment failure handling | E08 | P1 | 2 | - |
| US-08-05 | Payment records | E08 | P1 | 2 | - |
| US-08-06 | Refunds | E08 | P1 | 8 | Key |
| US-08-07 | Cash on delivery capability | E08 | P1 capability, conditional on | 2 | - |
| US-09-01 | Shipping zones and rates | E09 | P1 | 3 | - |
| US-09-02 | Carrier integration | E09 | P1-L | 2 | - |
| US-09-03 | Bulk dispatch, labels and pickups | E09 | P1-L | 3 | - |
| US-09-04 | Carrier status retrieval | E09 | P1-L | 2 | - |
| US-09-05 | Explicit carrier status mapping | E09 | P1 | 3 | Key |
| US-09-06 | Returned to origin is never Delivered | E09 | P1 | 5 | Key |
| US-09-07 | Cash on delivery reconciliation | E09 | P1-L | 5 | Key |
| US-18-01 | Order search and filtering | E18 | P1 | 2 | - |
| US-18-02 | Order detail | E18 | P1 | 2 | - |
| US-18-03 | Status updates | E18 | P1 | 2 | - |
| US-18-04 | Shipments from the order | E18 | P1-L | 2 | - |
| US-18-05 | Cancel an order | E18 | P1 | 2 | - |
| US-18-06 | Invoices and packing slips | E18 | P1-L | 2 | - |
| US-18-07 | Order export | E18 | P1 | 2 | - |
| US-19-01 | Customer profile | E19 | P1 | 2 | - |
| US-19-02 | Internal notes on a customer | E19 | P1-L | 2 | - |
| US-19-03 | Disable or suspend an account | E19 | P1-L | 2 | - |

### Sprint 12. Week 12. Stage 5, Migration and reports

**18 stories, 75 points.** **Needed before it starts:** Full catalogue export (CR-09). Written image and content rights confirmation (CR-08, OD-26). **Ends with:** Migration tooling run against the real catalogue

| Story | Title | Epic | Scope | Pts | Notes |
|---|---|---|---|---|---|
| US-20-01 | Return case queue | E20 | P1 | 5 | Key, Custom, no Woo support |
| US-20-02 | Review a return case | E20 | P1 | 2 | Custom, no Woo support |
| US-20-03 | Decide a return | E20 | P1 | 8 | Key, Custom, no Woo support |
| US-20-04 | Record receipt and condition | E20 | P1, with condition recording a | 3 | Custom, no Woo support |
| US-20-05 | Refund from the return case | E20 | P1 | 8 | Key, Custom, no Woo support |
| US-20-06 | Return audit trail | E20 | P1 | 3 | Custom, no Woo support |
| US-23-07 | Product variations | E23 | P1 | 5 | Key, Custom, no Woo support |
| US-23-08 | Background processing | E23 | P1 | 3 | Custom, no Woo support |
| US-23-09 | Safe re runs | E23 | P1 | 5 | Key, Custom, no Woo support |
| US-23-10 | Error report | E23 | P1 | 3 | Custom, no Woo support |
| US-23-11 | Import history | E23 | P1 | 3 | Custom, no Woo support |
| US-23-12 | Fields imported and export | E23 | P1, with SEO fields, images an | 13 | Custom, no Woo support |
| US-24-01 | Dashboard figures | E24 | P1, with average order value a | 5 | - |
| US-24-02 | Operational panels on the dashboard | E24 | P1, with the refunds and retur | 1 | - |
| US-24-03 | Sales report | E24 | P1-L | 3 | - |
| US-24-04 | Inventory report | E24 | P1-L | 1 | - |
| US-24-05 | Funnel and traffic reporting | E24 | P1-L, delivered through the an | 3 | - |
| US-27-06 | Observability | E27 | P1-L | 1 | - |

### Sprint 13. Week 13. Stage 5, Migration and reports

**20 stories, 82 points.** **Needed before it starts:** Advertising platforms for launch (OD-21). Legal and policy text (CR-04). **Ends with:** Stage 5 gate. The client re runs the migration tool on a changed file

| Story | Title | Epic | Scope | Pts | Notes |
|---|---|---|---|---|---|
| US-13-01 | Request a return | E13 | P1 | 13 | Key, Custom, no Woo support |
| US-13-02 | Return eligibility | E13 | P1-L | 5 | Custom, no Woo support |
| US-13-03 | Return status visibility | E13 | P1 | 3 | Custom, no Woo support |
| US-13-04 | Refund visibility | E13 | P1 | 3 | Custom, no Woo support |
| US-22-07 | Static and policy pages | E22 | P1 | 3 | Custom, no Woo support |
| US-22-08 | Promotional banners and campaign pages | E22 | P1-L | 5 | Custom, no Woo support |
| US-22-09 | Content maintained independently in both languages | E22 | P1 | 5 | Key, Custom, no Woo support |
| US-22-10 | Preview before publish | E22 | P1 | 3 | Custom, no Woo support |
| US-22-11 | Revert a content change | E22 | P1-L | 1 | Custom, no Woo support |
| US-25-01 | Measurement and advertising tags | E25 | P1 | 5 | - |
| US-25-02 | Product feeds | E25 | P1-L | 5 | Key |
| US-25-03 | Campaign attribution through to the order | E25 | P1-L | 3 | Key |
| US-25-04 | Consent management | E25 | P1-L | 2 | - |
| US-25-05 | Redirect manager | E25 | P1, with the missing page log | 3 | Key |
| US-25-06 | Technical SEO foundations | E25 | P1 | 5 | - |
| US-25-07 | Third party access controls | E25 | P1, with the personal data lim | 8 | Key |
| US-25-08 | Third Party Access Protocol | E25 | DLV | 1 | - |
| US-26-01 | Standard ecommerce event set | E26 | P1 | 5 | - |
| US-26-02 | Secondary events | E26 | P1-L | 1 | - |
| US-26-03 | Event tracking plan | E26 | DLV | 3 | - |

### Sprint 14. Week 14. Stage 6, Live and handed over

**10 stories, 33 points.** **Needed before it starts:** Client availability for acceptance testing (CR-14). Domain, sender email, support numbers, legal entity (CR-12, OD-18). **Ends with:** Stage 6 gate. Acceptance sign off, then Production Go-Live

| Story | Title | Epic | Scope | Pts | Notes |
|---|---|---|---|---|---|
| US-12-05 | Return update notifications | E12 | P1 email | 1 | - |
| US-12-06 | Refund notification | E12 | P1 email | 1 | - |
| US-12-07 | Consent, frequency and compliance | E12 | P1, with NOTF-12 at P1-L | 5 | - |
| US-27-01 | Mobile first and responsive | E27 | P1 | 2 | - |
| US-27-02 | Performance | E27 | P1 | 3 | - |
| US-27-04 | Data integrity | E27 | P1 | 5 | Key |
| US-27-07 | Accessibility | E27 | P1-L | 2 | - |
| US-27-08 | Privacy | E27 | P1, with automated request han | 3 | - |
| US-27-09 | Browser support and scalability | E27 | P1-L | 3 | - |
| US-27-11 | Handover deliverables | E27 | DLV | 8 | - |

### Sprint 15. Week 15. Stage 7, Second release and contingency

**3 stories, 4 points.** **Needed before it starts:** Nothing. Contingency, and the second release items. **Ends with:** Second release delivered, inside the Phase 1 fee

| Story | Title | Epic | Scope | Pts | Notes |
|---|---|---|---|---|---|
| US-10-06 | Reorder | E10 | P1-L | 1 | Second release |
| US-10-07 | Account deletion request | E10 | P1-L | 1 | Second release |
| US-19-04 | Customer metrics and export | E19 | P1-L | 2 | Second release |

### Sprint 16. Week 16. Stage 7, Second release and contingency

**3 stories, 4 points.** **Needed before it starts:** Nothing. Contingency. **Ends with:** Reserve

| Story | Title | Epic | Scope | Pts | Notes |
|---|---|---|---|---|---|
| US-03-03 | Filter by rating | E03 | P1-L | 1 | Second release |
| US-12-08 | Notification templates | E12 | P1-L | 2 | Second release |
| US-24-06 | Second release reports | E24 | P1-L | 1 | Second release |

---

# PART THREE: PAYMENTS AND THE BOARD

## 9. The payment schedule against the plan

Each stage gate releases its payment when the stage deliverable is available on staging, or issued in
writing for a written deliverable, and meets the acceptance criteria for that stage. The review period is
5 working days, and silence is acceptance.

| Stage | Ends | The gate | Stories accepted | Payment | Cumulative |
|---|---|---|---|---|---|
| 1. Start and specification | Week 2 | Stage 1 gate. Payment released | 1 | 58,000 EGP | 58,000 EGP |
| 2. Foundation and products | Week 5 | Stage 2 gate. Payment released | 23 | 58,000 EGP | 116,000 EGP |
| 3. Shop front complete | Week 8 | Stage 3 gate. The full customer journey walked in both languages, on a phone and a desktop | 64 | 58,000 EGP | 174,000 EGP |
| 4. Operations system | Week 11 | Stage 4 gate. The client team, not the developer, processes test orders and a test return | 57 | 43,500 EGP | 217,500 EGP |
| 5. Migration and reports | Week 13 | Stage 5 gate. The client re runs the migration tool on a changed file | 44 | 43,500 EGP | 261,000 EGP |
| 6. Live and handed over | Week 14 | Stage 6 gate. Acceptance sign off, then Production Go-Live | 10 | 29,000 EGP | 290,000 EGP |
| **Platform total** | | | **199** | **290,000 EGP** | |
| Interface design | On written approval, expected week 4 | The client approves the interface design | Not a story | 20,000 EGP | 310,000 EGP |

**Interface design sits outside the stage schedule**, because it is finished before the storefront it is
used to build. It is paid once, on approval, and it is produced by the developer under Statement of Work
section 9. **It is not brand identity**, which is a client input (CR-02) and blocks all design work until
it arrives.

## 10. How the board mirrors this document

The delivery board is a GitHub Project. It is generated from this plan and carries nothing this plan does
not contain.

| Board object | What it is | Count |
|---|---|---|
| **Milestone** | One payment stage. Closing it is what releases a payment | 6, plus one for the second release |
| **Iteration** | One sprint, one week | 16 |
| **Epic issue** | One epic from the Functional Specification, listing its stories | 27 |
| **Story issue** | One user story, carrying its acceptance criteria as a checklist | 205 |
| **Label** | Epic, scope classification, WooCommerce leverage, priority, key requirement | |

**A ticket is worked, not written.** Its acceptance criteria arrive from the Functional Specification and
are not edited on the board. If a criterion is wrong, the specification is corrected and the ticket is
regenerated, so that the board and the contract cannot drift apart.

---

*Mizzey Operations Platform. Delivery Backlog and Sprint Plan. MS-BLG-2026-020, version 1.0, 6 September 2026.
Stage 1 deliverable under the Statement of Work MS-SOW-2026-009, satisfying PRE-04. Read with the Functional
Specification MS-SPC-2026-018, which holds the acceptance criteria, and the Project Plan MS-PLN-2026-011,
which holds the stage gates and the client dependencies.*
