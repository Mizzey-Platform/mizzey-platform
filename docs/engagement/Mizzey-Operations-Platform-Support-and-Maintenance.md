# Mizzey Operations Platform
## Support and Maintenance Terms

**Document:** MS-SUP-2026-015
**Version:** 1.0
**Date:** 5 September 2026
**Prepared for:** Mizzey.com
**Prepared by:** Mustafa Shaaban
**Contract role:** Annex E to the Services Agreement, MS-AGR-2026-010

---

## 1. What happens after launch

Two different things happen after your store goes live, and they are often confused with each other.

**The warranty.** Sixty calendar days during which anything that does not work as agreed is corrected at no charge. It is included in the fee, it is not optional, and it is not a service you buy.

**Support.** Everything after that, and anything during the warranty that is not a defect. Support is **entirely optional.** There is no mandatory retainer, no minimum term, and no requirement to buy support from the developer in order to have received these prices.

This document sets out both, and says plainly what each does and does not cover.

> **You are not obliged to buy support**
>
> The store is delivered with full documentation, an Arabic administrator manual, a technical handover and all source code in a repository you own. You can run it yourself, or engage any other qualified team.
>
> That is deliberate. Support priced as a genuine option is worth having. Support that has to be bought because there is no alternative is not support, it is a lock-in, and it is not how this engagement is built.

---

## 2. The warranty

### 2.1 What it is

| Item | Value |
|---|---|
| **Warranty period** | **60 calendar days from Production Go-Live** |
| Cost | Included in the fee |
| Scope | Reproducible defects against the agreed scope and acceptance criteria |

Sixty days is longer than the warranty on the other two packages, because this is the largest system and your team should not be paying for support while still learning it.

### 2.2 What it covers

Any reproducible defect where the delivered implementation does not conform to the scope in the Feature Register MS-ANX-2026-001 and the acceptance criteria in MS-UAT-2026-013 is corrected at no charge.

This includes the S3 and S4 defects recorded on the known issues list at handover.

### 2.3 What it is not

**The warranty is not a service level agreement.** It carries no guaranteed response time, no uptime commitment and no availability guarantee. Response targets are given in section 5 as targets, and that is what they are.

It does not cover:

* faults in third-party services: hosting, the payment provider, the courier, or any third-party plugin
* changes made to the system by you or by anyone else after handover
* new requirements, new features, or a change of mind. Those are Change Requests
* content, product data or configuration errors introduced by you
* problems caused by not following the documented operating procedures
* training beyond the handover session and the written manual
* recovery of data lost through a third-party failure, beyond restoring the most recent working backup

### 2.4 During the warranty

A defect is reported in writing, with what you did, what you expected and what happened instead. It is reproduced, corrected and confirmed with you.

Work that is **not** a defect can still be requested during the warranty period. It is quoted, at the rates in section 3, and is not taken from the warranty.

---

## 3. Support after the warranty

Support is available in four forms. Choose one, change between them, or take none.

| Option | What it is | Rate |
|---|---|---:|
| **Ad-hoc support** | Paid by the hour, as and when you need it. No commitment | **1,100 EGP per hour** |
| **Support 8** | Up to 8 support or development hours per monthly period | **8,000 EGP per month** |
| **Support 14** | Up to 14 support or development hours per monthly period | **12,000 EGP per month** |
| **Emergency or out of hours** | Subject to availability. **No 24/7 guarantee** | **1,650 EGP per hour** |

Support 8 works out at 1,000 EGP per hour, and Support 14 at approximately 857 EGP per hour, against 1,100 EGP ad-hoc. The monthly plans are cheaper per hour because scheduled work is easier to plan around than unscheduled work.

### 3.1 How the monthly plans work

* Billed monthly in advance
* **No minimum term.** Cancel with 15 days written notice before the next period
* Hours are counted in 15 minute increments, rounded up to the nearest quarter hour
* **Unused hours do not carry over** to the following month
* Hours beyond the plan are billed at the ad-hoc rate of 1,100 EGP per hour, and you are told before the plan limit is reached, not after
* A plan can be upgraded or downgraded at the start of any period
* Plans can be paused, for example during a quiet season, and restarted later at the same rate

### 3.2 What a support hour covers

* Investigating and correcting problems that are not warranty defects
* Small changes: wording, settings, content structure, a discount rule, a report tweak
* Assistance with an operation your team has not done before
* Platform, plugin and dependency updates, and testing after them
* Checking on a performance or a security concern
* Advice, and answering questions

### 3.3 What a support hour does not cover

* **New features.** These are never taken from support hours. They are scoped and quoted separately, and approved by you before any work starts
* Work created by a third party changing or breaking something
* Content creation, translation, photography or copywriting
* Marketing campaign management
* Hosting administration beyond what the platform needs, where you have a hosting provider who does that

The reason new features are never taken from support hours is that it would put the two of us on opposite sides of every conversation about how long something took. A feature is quoted, agreed and built. Support keeps the system running.

---

## 4. What is always free

These do not consume support hours and are not charged, at any time:

* Answering a short question by message
* Telling you whether something is a defect or a change
* Assessing a Change Request, per MS-CHG-2026-014 section 3
* Pointing you to the part of the documentation that answers your question

---

## 5. Response targets

These are targets based on availability, not guarantees, and no 24/7 service is offered or implied.

| Severity | Meaning | Target first response | Target for a fix or a workaround |
|---|---|---|---|
| **S1, Critical** | The store cannot take orders, payment is failing, or data is at risk | Within 4 working hours | Same or next working day |
| **S2, Major** | A core function is failing, with no workaround | 1 working day | 3 working days |
| **S3, Moderate** | Something works incorrectly, with a workaround available | 2 working days | Next scheduled support period |
| **S4, Minor** | Cosmetic or a small inconsistency | 3 working days | Next scheduled support period |

Working hours are Sunday to Thursday, excluding official public holidays in Egypt. Work outside those hours is subject to availability at the emergency rate.

**If a genuine emergency happens and you have no support plan, you are not turned away.** It is handled at the ad-hoc or emergency rate.

---

## 6. How to raise something

| Kind | How |
|---|---|
| Anything urgent | Message or call, followed by an email so it is recorded |
| Everything else | Email, with what you did, what you expected, what happened, and a screenshot if there is one |

A reported item is acknowledged, classified by severity, and given a plan. You are told which category it falls into, warranty or support or change, and why, before any billable work starts.

---

## 7. Starting, changing and stopping

| Action | How |
|---|---|
| Start a plan | Written confirmation. It begins at the start of the next monthly period, or immediately if you prefer |
| Change a plan | Written notice before the start of a period |
| Pause a plan | Written notice, 15 days before the next period |
| Stop a plan | Written notice, 15 days before the next period. No cancellation fee, no minimum term |
| Take no plan at all | Nothing to do. Ad-hoc support remains available at 1,100 EGP per hour |

Rates are held for **12 months from Production Go-Live.** Any change after that is notified at least 30 days in advance, and you are free to stop.

---

## 8. If you move to another developer

If at any point you engage another team, the following is true and remains true:

* All Mizzey-specific source code is already in a repository you own, with its development history
* The documentation, data model, deployment and recovery procedures are already yours
* The licence for the Corex framework layer is perpetual, irrevocable and royalty-free, and extends to any developer you engage, per MS-AGR-2026-010 section 9.3
* A reasonable handover to the incoming team is provided at the ad-hoc rate

Nothing is withheld, and no cooperation depends on buying support. That is the practical meaning of the ownership terms in the Agreement.

---

## 9. Summary

| | |
|---|---|
| Warranty | 60 calendar days from Production Go-Live, included |
| Support | Optional. No retainer, no minimum term |
| Ad-hoc | 1,100 EGP per hour |
| Support 8 | 8,000 EGP per month, up to 8 hours |
| Support 14 | 12,000 EGP per month, up to 14 hours |
| Emergency or out of hours | 1,650 EGP per hour, subject to availability |
| New features | Never from support hours. Quoted per feature |
| Rate validity | 12 months from Production Go-Live |

| | Client, Mizzey | Developer |
|---|---|---|
| Support option selected | | |
| Name | | Mustafa Shaaban |
| Signature | | |
| Date | | |

The signature block is completed only if a support plan is taken. Nothing in this document requires one.

---

*Support is optional on this engagement, and the store is built so that it stays optional.*
