import json, io, collections, os

# Retired 22 Sep 2026: an Option C board and backlog generator. It writes to GitHub or to client documents,
# and scripts/issues.json is missing, so a rerun would duplicate issues. See scripts/README.md.
if os.environ.get('MIZZEY_LEGACY_BOARD_SCRIPTS') != '1':
    raise SystemExit('Retired Option C script. Not for Option B use. See scripts/README.md.')

HERE = os.path.dirname(os.path.abspath(__file__))
S = json.load(io.open(os.path.join(HERE, 'stories.json'), encoding='utf-8'))
O = io.StringIO(); W = O.write

PAY = {1:'58,000',2:'58,000',3:'58,000',4:'43,500',5:'43,500',6:'29,000',7:'-'}
AMT = {1:58000,2:58000,3:58000,4:43500,5:43500,6:29000}
STAGE_NAME = {1:'Start and specification',2:'Foundation and products',3:'Shop front complete',
              4:'Operations system',5:'Migration and reports',6:'Live and handed over',
              7:'Second release and contingency'}
STAGE_WK = {1:'1 to 2',2:'3 to 5',3:'6 to 8',4:'9 to 11',5:'12 to 13',6:'14',7:'15 to 16'}
STAGE_W  = {1:2,2:3,3:3,4:3,5:2,6:1,7:2}
LEVNAME  = {'native':'Native','partial':'Partial','mixed':'Mixed','custom':'Custom'}
SPST = {}
for sp in range(1, 17):
    SPST[sp] = 1 if sp <= 2 else 2 if sp <= 5 else 3 if sp <= 8 else 4 if sp <= 11 else 5 if sp <= 13 else 6 if sp == 14 else 7

tot = sum(s['points'] for s in S)

DEP = {
 1:('Signature. Confirmation of the business decisions in MS-DEP-2026-012 section 5, or a date by which each will be made.','Client approves the specification and the acceptance model'),
 2:('The same. This is the client review week, not a build week.','Stage 1 gate. Payment released'),
 3:('Approved brand identity (CR-02, OD-01). Written hosting instruction (CR-10, OD-27).','Platform foundation on staging'),
 4:('The real Amazon sample export file (CR-06). The interface design is presented for approval this week.','Product model exercised against the real catalogue structure'),
 5:('Written approval of the interface design. Product cost basis (OD-12).','Stage 2 gate. Payment released'),
 6:('Approved interface design. Storefront content in both languages beginning to arrive (CR-15).','Home, listing and cart on staging'),
 7:('Product photography (CR-05). Authenticity and warranty policy (OD-13).','Product pages and customer accounts on staging'),
 8:('Welcome discount rules (OD-03). Free shipping definition (OD-04).','Stage 3 gate. The full customer journey walked in both languages, on a phone and a desktop'),
 9:('Approved payment merchant account (CR-01). Carrier account, API key and rate card (CR-03).','The Operations Console on staging'),
 10:('Cash on delivery decision (OD-05). Payment methods for launch (OD-19). VAT and invoicing (OD-09).','Promotions and order administration'),
 11:('Return window (OD-08). Carrier remittance cycle (OD-20). COD verification decision (OD-29).','Stage 4 gate. The client team, not the developer, processes test orders and a test return'),
 12:('Full catalogue export (CR-09). Written image and content rights confirmation (CR-08, OD-26).','Migration tooling run against the real catalogue'),
 13:('Advertising platforms for launch (OD-21). Legal and policy text (CR-04).','Stage 5 gate. The client re runs the migration tool on a changed file'),
 14:('Client availability for acceptance testing (CR-14). Domain, sender email, support numbers, legal entity (CR-12, OD-18).','Stage 6 gate. Acceptance sign off, then Production Go-Live'),
 15:('Nothing. Contingency, and the second release items.','Second release delivered, inside the Phase 1 fee'),
 16:('Nothing. Contingency.','Reserve'),
}
GATE_SPRINT = {1:2,2:5,3:8,4:11,5:13,6:14}
STAGE_ENDS = {1:'Week 2',2:'Week 5',3:'Week 8',4:'Week 11',5:'Week 13',6:'Week 14'}

W("""# Mizzey Operations Platform
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

""")

lev = collections.Counter()
for s in S:
    lev[s['leverage']] += s['points']
W("**Where the effort actually sits.** By points, not by story count:\n\n")
W("| Leverage | Stories | Points | Share of effort |\n|---|---|---|---|\n")
for k in ('native', 'partial', 'mixed', 'custom'):
    n = sum(1 for s in S if s['leverage'] == k)
    W("| %s | %d | %d | %d%% |\n" % (LEVNAME[k], n, lev[k], lev[k] * 100 // tot))
W("| **Total** | **%d** | **%d** | |\n\n" % (len(S), tot))

W("""> **Read the custom row before anything else in this document.** Close to a third of the estimated effort
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

""")

W("| Sprint | Week | Stage | Payment at the stage gate | Stories | Points | Epics |\n|---|---|---|---|---|---|---|\n")
for sp in range(1, 17):
    g = [s for s in S if s['sprint'] == sp]
    es = sorted(set(x['epic'] for x in g))
    focus = ', '.join(es) if es else 'Specification approval gate'
    W("| **%d** | %d | %d | %s EGP | %d | %d | %s |\n" %
      (sp, sp, SPST[sp], PAY[SPST[sp]], len(g), sum(x['points'] for x in g), focus))
W("| | | | | **%d** | **%d** | |\n\n" % (len(S), tot))

W("""## 7. Where the load is, and where the contingency goes

The Statement of Work prices fourteen weeks, and the Project Plan states a fourteen to sixteen week band.
That band is not padding. It is load bearing, and this section says exactly where.

""")
W("| Stage | Weeks | Stories | Points | Points per week | Verdict |\n|---|---|---|---|---|---|\n")
for st in range(1, 8):
    g = [s for s in S if s['stagenum'] == st]
    if not g:
        continue
    ppw = sum(x['points'] for x in g) / STAGE_W[st]
    v = 'Comfortable' if ppw < 40 else 'At capacity' if ppw <= 60 else 'Over capacity'
    W("| %d. %s | %s | %d | %d | %.0f | **%s** |\n" %
      (st, STAGE_NAME[st], STAGE_WK[st], len(g), sum(x['points'] for x in g), ppw, v))

build = [s for s in S if 3 <= s['sprint'] <= 14]
W("\nThe average build week carries **%.0f points**. That is the capacity this plan assumes, and it already\n"
  % (sum(s['points'] for s in build) / 12.0))
W("""assumes a developer working alone, without interruption.

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

""")

for sp in range(1, 17):
    g = sorted([s for s in S if s['sprint'] == sp], key=lambda x: x['id'])
    st = SPST[sp]
    W("### Sprint %d. Week %d. Stage %d, %s\n\n" % (sp, sp, st, STAGE_NAME[st]))
    W("**%d stories, %d points.** " % (len(g), sum(x['points'] for x in g)))
    W("**Needed before it starts:** %s **Ends with:** %s\n\n" % (DEP[sp][0], DEP[sp][1]))
    if not g:
        W("No build stories. This is the client review period for the Stage 1 deliverables.\n\n")
        continue
    W("| Story | Title | Epic | Scope | Pts | Notes |\n|---|---|---|---|---|---|\n")
    for s in g:
        note = []
        if s['key']:
            note.append('Key')
        if s['early']:
            note.append('built early, accepted at the Stage %d gate' % s['stagenum'])
        if s['prio'] == 'Later':
            note.append('Second release')
        if s['leverage'] == 'custom':
            note.append('Custom, no Woo support')
        sc = s['scope'].replace('[key]', '').replace('**', '').strip().rstrip('.')
        if len(sc) > 30:
            sc = sc[:30].rstrip()
        W("| %s | %s | %s | %s | %d | %s |\n" %
          (s['id'], s['title'], s['epic'], sc, s['points'], ', '.join(note) or '-'))
    W("\n")

W("""---

# PART THREE: PAYMENTS AND THE BOARD

## 9. The payment schedule against the plan

Each stage gate releases its payment when the stage deliverable is available on staging, or issued in
writing for a written deliverable, and meets the acceptance criteria for that stage. The review period is
5 working days, and silence is acceptance.

""")
W("| Stage | Ends | The gate | Stories accepted | Payment | Cumulative |\n|---|---|---|---|---|---|\n")
cum = 0
for st in range(1, 7):
    g = [s for s in S if s['stagenum'] == st]
    cum += AMT[st]
    W("| %d. %s | %s | %s | %d | %s EGP | %s EGP |\n" %
      (st, STAGE_NAME[st], STAGE_ENDS[st], DEP[GATE_SPRINT[st]][1], len(g),
       format(AMT[st], ','), format(cum, ',')))
accepted = sum(len([s for s in S if s['stagenum'] == x]) for x in range(1, 7))
W("| **Platform total** | | | **%d** | **%s EGP** | |\n" % (accepted, format(cum, ',')))
W("""| Interface design | On written approval, expected week 4 | The client approves the interface design | Not a story | 20,000 EGP | 310,000 EGP |

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
""")

out = r'C:\wamp64\www\mizzey\final docs\Client\Branded\Mizzey-Operations-Platform-Delivery-Backlog.md'
io.open(out, 'w', encoding='utf-8', newline='').write(O.getvalue())
print('written %d chars to %s' % (len(O.getvalue()), out))
