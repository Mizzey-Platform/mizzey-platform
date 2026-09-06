# Mizzey Operations Platform
## Acceptance and UAT Plan

**Document:** MS-UAT-2026-013
**Version:** 1.0
**Date:** 5 September 2026
**Prepared for:** Mizzey.com
**Prepared by:** Mustafa Shaaban
**Contract role:** Annex C to the Services Agreement, MS-AGR-2026-010

---

## 1. What this document decides

This document answers one question: **how do both parties know when something is finished?**

Without a written answer, that question is settled by opinion, and opinion changes with mood, memory and pressure. With one, it is settled by a test that either passes or does not.

Nothing in this document is new. The definition of done and the acceptance scenarios come from your own requirements document, are recorded in the Feature Register MS-ANX-2026-001, and are reproduced here so that the acceptance process is a single document rather than a cross-reference.

---

## 2. The acceptance model

Acceptance happens twice.

**Stage acceptance, during the build.** At the end of each of the six stages, the stage deliverable is presented on staging. You review it against the criteria for that stage. Accepting a stage releases its payment.

**User acceptance testing, before go-live.** In stage 6, your team runs the acceptance script in section 5 against the complete system. Passing it is what permits Production Go-Live.

Stage acceptance is not a rehearsal for UAT and does not replace it. A stage can be accepted and a defect still be found in UAT, because UAT tests the whole system working together, which stage acceptance cannot.

### 2.1 The review period

Every item submitted for acceptance carries a **5 working day** review period.

If a written response is received within it, the response governs. If none is received, the item is treated as accepted and its payment falls due. If you need longer, ask before the period ends and it will be given.

### 2.2 What is being tested against

A deliverable is accepted when it satisfies:

1. the definition of done in section 4, which applies to every feature, and
2. the requirements in Annex A that the stage covers, at their recorded scope classification, and
3. for stage 6, the acceptance scenarios in section 5.

**A P1-L item is tested against its reduced specification, not against the full one.** Feature Register Part One section 1.2 records exactly where each reduction sits. A request for the full specification of a P1-L item is a Change Request, not a defect.

---

## 3. What is a defect, and what is not

This distinction is the single most common source of friction on software projects, so it is settled here in advance.

| It is a defect when | It is a Change Request when |
|---|---|
| The system does not do what Annex A says it does | The system does what Annex A says, and something else is now wanted |
| A documented acceptance criterion fails | A new criterion is proposed |
| Something worked at an earlier stage and no longer does | Something never in scope is expected |
| An error, a crash, or data recorded incorrectly | The behaviour is correct but the preference has changed |
| Arabic or English behaves incorrectly against the agreed bilingual scope | A new language or a new content type is wanted |

**Defects are corrected at no charge.** Change Requests are priced and agreed in writing before any work on them starts, under MS-CHG-2026-014.

Neither party benefits from misclassifying one as the other, and each classification is recorded with its reason.

---

## 4. Definition of done

Adopted from your requirements document section 17.1. It applies to every feature, in every stage.

| Id | Criterion |
|---|---|
| DOD-01 | Interface implemented as approved, across all mobile and desktop states |
| DOD-02 | Business logic and exception cases implemented, **not only the successful path** |
| DOD-03 | Validation and error messages clear and correct **in both English and Arabic** |
| DOD-04 | Tests written and executed per system layer, plus manual testing on staging |
| DOD-05 | Analytics events verified where required |
| DOD-06 | Staff permissions tested |
| DOD-07 | No known errors affecting usage |
| DOD-08 | Settings needed for operation documented |
| DOD-09 | Client approval of acceptance criteria before a feature moves to production |

DOD-04 is delivered at its recorded P1-L scope: tests covering critical business logic, being pricing, promotions, refunds, stock and duplicate-order protection, rather than full automated coverage of every feature.

---

## 5. The acceptance script

These are your own critical test cases, from requirements section 17.2, plus five added by the developer. **They are the UAT script, verbatim.** Every one is verified before Production Go-Live.

| Id | Scenario | Required result | Pass |
|---|---|---|---|
| AC-01 | Customer adds one item | Cart shows that adding one more unlocks free shipping | |
| AC-02 | Customer adds two items | Shipping becomes free per the active promotion, **without code change** | |
| AC-03 | New customer registers | Welcome discount applies only if conditions are met | |
| AC-04 | Invalid coupon | Clear reason shown, total unchanged | |
| AC-05 | Stock runs out during checkout | Unavailable quantity not confirmed, customer informed | |
| AC-06 | Place Order clicked twice | Exactly one order created, no duplicate charge | |
| AC-07 | Duplicate payment confirmation | Handled safely with no duplicate effect | |
| AC-08 | Payment fails | Order does not incorrectly become Paid or Confirmed | |
| AC-09 | Price changed after a prior purchase | The earlier order retains its original price | |
| AC-10 | Partial refund | Cannot exceed the amount actually paid after discount | |
| AC-11 | Staff without permission | Cannot refund, or edit prices or roles | |
| AC-12 | English and Arabic | All core pages render correctly in both languages **without layout break**. English is the canonical design. Arabic maintains equivalent core functionality and passes right to left layout validation | |
| AC-13 | Guest checkout | Purchase succeeds without an account, order can later be linked securely | |
| AC-14 | Customer return request | Customer sees status, admin sees workflow and history | |
| AC-15 | Out of stock | Product page, cart and checkout consistent, no unintended sale | |
| AC-16 | Parcel returned to origin | Recorded as Returned, not Delivered. Stock and payment state remain correct | |
| AC-17 | Catalogue import re-run | Re-running the same file updates existing products and creates no duplicates | |
| AC-18 | Guest fills a cart, then registers or signs in | The cart carries over intact, no items lost, no duplicates | |
| AC-19 | Customer signs in with Google | Account created or matched correctly, no duplicate account for the same email | |
| AC-20 | Client edits a product and a storefront section unaided | Changes appear correctly on both the English and Arabic storefront with no developer involvement | |

AC-16 to AC-20 were added by the developer beyond your requirements document. AC-20 in particular is worth running yourself rather than watching: it is the test of whether you can operate your own store.

---

## 6. Additional operational checks

The acceptance script above is customer-facing. The Operations Platform adds an operational layer, and these checks confirm it works for the people who use it every day. They are run by your team in stage 6.

| Id | Check | Required result | Pass |
|---|---|---|---|
| OPS-01 | Daily operations screen | Orders needing action, stock problems, returns queue and revenue of the day all shown and accurate | |
| OPS-02 | Return processed end to end | Request, review, approve, refund and notify, all from the operations system | |
| OPS-03 | Bulk action on orders | Many orders updated in one action, with the correct result on each | |
| OPS-04 | Bulk action on products | Many products updated in one action, with the correct result on each | |
| OPS-05 | Staff role restriction | A restricted staff account cannot see or do what its role forbids | |
| OPS-06 | Activity log | A sensitive change is recorded with what changed and who changed it | |
| OPS-07 | Advanced discount rule | A rule with conditions, limits, scheduling and priority behaves as configured | |
| OPS-08 | Migration tool, first run | Catalogue imported with variants and Arabic text intact, errors reported clearly | |
| OPS-09 | Migration tool, repeat run | Changed file updates existing products, creates no duplicates, per AC-17 | |
| OPS-10 | Business reports | Figures reconcile against the underlying orders | |

---

## 7. Defect severity

Every defect found in UAT is classified. The classification decides what it means for launch, not how loudly it was reported.

| Severity | Meaning | Effect on go-live | Correction |
|---|---|---|---|
| **S1, Critical** | Customers cannot buy, payment or refund is incorrect, data is lost or corrupted, or a security fault | **Blocks go-live** | Immediately, before launch |
| **S2, Major** | A core function fails, or an acceptance scenario in section 5 does not pass | **Blocks go-live** | Before launch |
| **S3, Moderate** | A feature works but incorrectly in a specific case, with a workaround available | Does not block go-live by itself | Within the warranty period |
| **S4, Minor** | Cosmetic, wording, or a small inconsistency | Does not block go-live | Within the warranty period, or in the second release |

**Every S1 and S2 defect is corrected before Production Go-Live.** S3 and S4 defects are recorded on a known issues list, agreed with you at handover, and corrected within the warranty period.

A disagreement about severity is resolved by looking at the acceptance criterion, not by negotiation.

---

## 8. How UAT runs

1. The developer confirms the system is ready for UAT and that internal testing is complete.
2. A UAT environment is prepared with realistic data, including your real catalogue.
3. Your team runs the script in sections 5 and 6, recording a pass or a fail against each row.
4. Failures are reported in writing, each identifying the row that failed and what happened.
5. The developer classifies each by severity, corrects, and resubmits.
6. Corrected items are retested. Only the affected rows are rerun, unless a correction touched something wider.
7. When every S1 and S2 is closed and every row in section 5 passes, UAT is complete.

**UAT is run by you.** Support, guidance and a walkthrough are provided, but the person who will operate the store is the person who should test it. A test run by the developer proves the developer understood the requirement, which was never the thing in doubt.

Allow **five working days** for the UAT run itself, within stage 6.

---

## 9. Go-live checklist

Confirmed jointly before Production Go-Live.

| | Item | Done |
|---|---|---|
| 1 | Every row in section 5 passes | |
| 2 | Every check in section 6 passes | |
| 3 | No open S1 or S2 defects | |
| 4 | Known issues list agreed and signed | |
| 5 | Payment provider live, not in test mode, with a real transaction verified | |
| 6 | Carrier integration live, with a real shipment created | |
| 7 | Domain pointed, certificate valid, both languages reachable | |
| 8 | Analytics and advertising tracking verified | |
| 9 | Backups running and a restore tested | |
| 10 | Rollback procedure documented and tested | |
| 11 | Legal policy pages published in both languages | |
| 12 | Admin accounts created, credentials handed over, developer access reduced to what support requires | |
| 13 | Documentation and Arabic administrator manual delivered | |
| 14 | Training session completed | |

---

## 10. Acceptance and sign-off

By signing, the Client confirms that the acceptance script has been run, that every S1 and S2 defect is closed, that the known issues list has been reviewed and agreed, and that the Platform is accepted.

**Production Go-Live occurs on this signature, and the 60 calendar day warranty period begins on that date.**

| | Client, Mizzey | Developer |
|---|---|---|
| Name | | Mustafa Shaaban |
| Signature | | |
| Date | | |
| Production Go-Live date | | |
| Warranty period ends | | |

---

*Acceptance is not the end of the relationship. It is the point at which the warranty starts and the second release is scheduled.*
