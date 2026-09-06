# Mizzey Operations Platform
## Change Control Procedure

**Document:** MS-CHG-2026-014
**Version:** 1.0
**Date:** 5 September 2026
**Prepared for:** Mizzey.com
**Prepared by:** Mustafa Shaaban
**Contract role:** Annex D to the Services Agreement, MS-AGR-2026-010

---

## 1. Why this exists

Scope changes on every project. That is normal, and it is usually a sign that the business is thinking, not that the planning was poor.

What is not normal, and what damages projects, is scope changing **without anyone deciding that it should.** Small additions accumulate, the timeline moves without explanation, and eventually one party feels the other has taken advantage. Neither party set out to cause that, and both end up unhappy.

This procedure prevents it with one rule: **a change happens when both parties agree in writing what it is, what it costs, and what it does to the dates. Not before.**

The procedure protects both sides equally. It protects you from being charged for something you did not agree to, and it protects the project from work being expected that was never priced.

---

## 2. What counts as a change

The Feature Register MS-ANX-2026-001 classifies every requirement. That classification decides the answer.

| Classification | Meaning | Is it a change? |
|---|---|---|
| **P1** | Phase 1 contract, delivered in full | No. It is already contracted |
| **P1-L** | Phase 1 contract, delivered to a defined reduced specification | No, at its reduced specification. Asking for the full specification **is** a change |
| **DLV** | A contractual deliverable rather than a feature | No. It is already contracted |
| **S2** | Contracted, delivered in the second release after go-live | No. It is already contracted and already paid for |
| **P2** | Growth phase, not contracted | **Yes.** Quoted when requested |
| **P3** | Long-term vision, no commitment | **Yes.** No price, no date and no warranty until it is scoped |
| **OUT** | Expressly excluded | **Yes.** Excluded means excluded until agreed otherwise |
| Not in the register at all | A new idea | **Yes** |

**Only P1, P1-L and DLV rows create a delivery obligation.** That is the contract rule from the Feature Register, and it is what makes this table decidable rather than arguable.

### 2.1 Things that are not changes

To be fair in both directions, these are not Change Requests and carry no charge:

* A defect, being anything that does not do what the register says it does. Defects are corrected at no charge, per MS-UAT-2026-013 section 3
* A clarification of something already in scope
* A better way of building something already in scope, where the outcome for you is the same
* Correcting something the developer got wrong

---

## 3. The procedure

### Step 1. The change is raised

Either party may raise one, in writing, using the form in section 7. It does not need to be formal to start with: a message describing what is wanted is enough to begin, and the form is completed from it.

### Step 2. Impact assessment

Within **3 working days**, the developer responds with:

* what exactly would be built or changed
* the effect on the fee, with the basis of the price shown
* the effect on the timeline, in working days
* the effect on anything already built or already accepted
* any risk the change introduces
* what happens if the change is not made, so the decision has both sides

An impact assessment is provided at no charge. **Assessing a change is not chargeable work. Building it is.**

### Step 3. Decision

You accept, reject, or ask for a revision. There is no obligation to accept, and a rejected Change Request has no cost and no consequence.

### Step 4. Written agreement

An accepted change is confirmed in writing by both parties, by signature on the form or by clear written confirmation from the named decision maker.

### Step 5. The work happens

**No work on a change begins before step 4 is complete.** If a change is urgent enough that waiting is worse than proceeding, both parties can agree to that in writing too, but the agreement still comes first.

---

## 4. How a change is priced

| Basis | When it is used |
|---|---|
| **Fixed price** | The default. The change is understood well enough to quote a firm figure |
| **1,100 EGP per hour** | Where a fixed price is not sensible, typically investigation, or work whose size genuinely depends on what is found. An estimated maximum is given, and it is not exceeded without asking |
| **No charge** | Where the change reduces work, or is a straightforward substitution of similar size |

A change that **removes** scope is assessed the same way. If work has not started, the fee reduces by the same basis it would have increased by. If it has started, the assessment says what has already been spent.

### 4.1 Effect on the payment schedule

A change that increases the fee is invoiced with the stage in which it is delivered, unless both parties agree otherwise. It does not change the percentages of the existing stages.

---

## 5. Timing, and when a change is expensive

The cost of a change depends heavily on when it is raised.

| Raised during | Typical cost | Why |
|---|---|---|
| Stage 1, specification | Lowest | Nothing has been built. Often no cost at all |
| Stage 2, foundation | Low to moderate | The product model can still absorb it |
| Stage 3, storefront | Moderate | Design and implementation may both be affected |
| Stage 4 to 5 | Moderate to high | Existing work may need reopening and retesting |
| Stage 6, UAT | Highest | Retesting, and the launch date is at risk |
| After go-live | Priced as new work | Quoted separately, on the needs at that time |

**This is not a reason to withhold a change.** It is a reason to raise it as soon as it is thought of. A change discussed in week 2 and rejected costs nothing. The same change raised in week 13 may cost a great deal, and by then the reason for it has usually not changed at all.

---

## 6. Growth phase and future work

Items recorded as **P2** in the Feature Register are real, understood and scoped, but not contracted. They are quoted when you commission them, on the requirements at that time.

Items recorded as **P3** are your longer-term vision. They carry no price, no date and no warranty until they are scoped, and are recorded so that the platform is built without closing the door on them.

Neither creates an obligation on either party, and neither expires.

---

## 7. Change Request form

Copy this form for each change. One change per form.

| Field | Entry |
|---|---|
| Change Request number | CR- |
| Date raised | |
| Raised by | |
| Related Feature Register id, if any | |
| **What is wanted** | |
| **Why it is wanted** | |
| Priority: urgent, needed before launch, or can follow | |

To be completed by the developer:

| Field | Entry |
|---|---|
| Date assessed | |
| **What would be built** | |
| Effect on other work already built | |
| **Effect on the fee** | |
| Pricing basis: fixed price, or hourly at 1,100 EGP | |
| **Effect on the timeline, in working days** | |
| Risks introduced | |
| What happens if this change is not made | |

Decision:

| | Client, Mizzey | Developer |
|---|---|---|
| Accepted, rejected, or revised | | |
| Name | | Mustafa Shaaban |
| Signature | | |
| Date | | |

---

## 8. The register of changes

Every Change Request, accepted or not, is recorded in a running list kept with the project documents. The list shows the number, the date, what was asked, the decision, the fee effect and the timeline effect.

At the end of the project the list is delivered with the handover pack, so that the difference between what was originally contracted and what was actually delivered is a matter of record rather than of memory.

| CR | Date | Summary | Decision | Fee effect | Timeline effect |
|---|---|---|---|---:|---|
| | | | | | |
| | | | | | |
| | | | | | |

---

*A change that is worth making is worth writing down. A change that is not worth writing down is usually not worth making.*
