# ERP question pack: readiness to send

5 October 2026. Owner PBI: #243. The eighteen must-answer questions are in `docs/2026-10-04-erp-technical-meeting-questions.md` and are not rewritten here.

Draft, 5 October 2026. Review of `C:\wamp64\www\mizzey\platform\docs\2026-10-04-erp-technical-meeting-questions.md`
(271 lines, "ERP technical meeting: question set", prepared 4 October 2026).

The eighteen must-answer questions, M1 to M18, are left exactly as they are. Nothing below rewrites them.

## 1. Verdict

**The content is ready. The pack is not.** The question set is a strong internal working document: the business
rules are stated with their register ids, the one constraint the store brings is separated from the proposal, the
eighteen decisions each carry their consequence, and the appendix and the acceptance criteria are complete enough to
write a specification from. What it lacks is everything a reader outside the repository needs in order to act on
it: who is asking, what is fixed, what is wanted by when, how to answer, and how it relates to the document they
already hold.

## 2. What is missing, in order of importance

### 2.1 Must be settled before it is sent

| # | Gap | Detail |
|---|---|---|
| 1 | **No cover note** | Nothing says who sends it, to whom, why now, what is asked, what is not asked, by when, or how to reply. Drafted in section 3 |
| 2 | **The fixed invariants are not in one place** | The document states eight contracted business rules and one invariant (one commercial item, one ERP stock item). D-10 fixes six. Two of the six, "no duplicate decrement" and "idempotency, retry, logging and reconciliation paths exist", appear only as questions (M11 to M15), never as things the store side commits to. A reader cannot tell what is settled on our side from what is open. Drafted in section 4 |
| 3 | **No dates** | "Targeted for week 4" is the only timing, and week 4 is counted from a delivery clock that starts "when the inputs listed in What You Need to Provide are available", not from a calendar date. No meeting date, no date for written answers before the meeting, no date for the remainder. I found no record of the clock's start date |
| 4 | **No named recipient** | The Document Guide asks the client to "Name your ERP technical contact". I found no record that one has been named |
| 5 | **No reply format** | Eighteen decisions and 64 appendix questions will come back as a meeting conversation unless a format is given. A short template is in section 5 |
| 6 | **Its relationship to the document the client already holds is not stated** | The signed pack already contains `09 ERP TECHNICAL MEETING - Preparation and Open Decisions.pdf` (MS-ERP-2026-031 version 0.5), with the instruction "Pass it to your ERP developers". The two overlap and, on one point, disagree. See section 6. The cover note has to say how they fit |
| 7 | **The heading says fifteen, the tables hold eighteen** | "Fifteen decisions." introduces M1 to M15, and M16 to M18 follow in a second table. This is one word in the introductory sentence, not a change to any question |
| 8 | **One reference points nowhere** | M18 cites "12.x (ERP-07)" as its full question. The appendix has sections 1 to 11 and no section 12, and no appendix question is about ERP-07. Criterion 11.8 is the only place ERP-07 appears. Similarly M16 cites 3.1 and 3.4, neither of which asks how a SKU change is handled; 3.3 is the nearest |

### 2.2 Needed for it to stand alone

| # | Gap | Detail |
|---|---|---|
| 9 | **Register ids the reader cannot look up** | The ERP developers do not hold the Feature Register. The document cites ERP-01 to ERP-10, OD-41, OD-42, OD-27, EX-35, INT-14, INT-16, NFR-04, NFR-05, NFR-07, NFR-10, BR-003, BR-007, PAY-04, RET-07, ADM-70 to ADM-72, ADM-78, MIG-13 and MIG-14. It needs either a one page extract of register Section I2 (I2.1 and I2.2, verbatim) attached as an annex, or a statement that the ids are for our tracing and can be ignored |
| 10 | **References into the repository** | A reader outside cannot follow: "`docs/2026-10-04-multilingual-data-integrity-workstream.md`", "`docs/2026-10-04-option-b-backlog-structure.md`", "constitution M-3", "Infrastructure (E-PRE-1)", "HND rows", "the product-cost pilot", "the B1 and A12 probe results". None of them needs to be explained; they need to be dropped or footnoted as internal |
| 11 | **Store-side terms the ERP side may not know** | M16 to M18 depend on how the store holds a bilingual product: two records, a translation relationship, a duplicate that shares its original's SKU, and a per-record stock setting. The section "One constraint we bring" explains the first two. A three line glossary would cover the rest without touching the questions |
| 12 | **No list of what to bring** | The requests for material are scattered: 2.1 (documentation, "may we have it now rather than at the meeting?"), 3.8 (a full item list), 10.1 (test environment). Document 09 section 4 already has a "Data samples required before the meeting" list. One list, in the cover note, is enough |
| 13 | **No statement of what the ERP side is not being asked to do** | The document says "Do not expand the integration beyond stock" as an instruction to ourselves. The ERP team needs it as an assurance: stock only, no orders, customers, prices or accounting, no customer personal data, no access to the live ERP during build |
| 14 | **No form** | It is a repository markdown file. If it goes to the client as a document, it either becomes a new version of MS-ERP-2026-031 or a new Launch Platform document through `brand-render`. `verify-launch.mjs` forbids every other issued document from citing MS-ERP-2026-031, so the choice matters |

### 2.3 Already good, and should not be touched

* "The agreed business rules, which are not up for discussion", with the register id against each.
* The separation of "The invariant (mandatory, not negotiable at the meeting)" from "The proposal (our architecture position, which is not the invariant)".
* The "If the answer is unfavourable" column on every must-answer decision.
* Section 11, the proposed acceptance criteria, and the explanation of why 11.5 is conditional.
* "What a good meeting produces".
* It contains no fee, no payment and no commercial figure, so it is safe to pass to a third party in that respect.

## 3. Draft cover note

Plain text, for the client to forward with the pack. Fields in square brackets are for you to complete. Nothing in
it states that a meeting has been arranged.

---

**Subject: Mizzey website and the ERP: stock integration, questions ahead of our technical meeting**

To: [name], ERP technical lead for Mizzey
Through: [name], Mizzey
From: Mustafa Shaaban, developer of the Mizzey website

Dear [name],

I am building the new Mizzey online store. Mizzey and I have agreed that the store takes its stock from your ERP,
and I would like to prepare our technical meeting properly so that it costs you as little time as possible.

**What has been agreed, between Mizzey and me.** Four business rules. The ERP is the source of truth for stock. The
store keeps no stock balance of its own that could disagree with it. A sale on the website reduces the stock in the
ERP. And the store does not confirm a sale when the ERP cannot confirm the stock, including when the ERP cannot be
reached. Only stock is involved. Orders, customers, prices, products and accounting are not part of this work.

**What has not been agreed, and is yours to tell me.** How. No interface, no method and no timing has been chosen.
I have deliberately not designed around an assumption about your system. The attached pack sets out what is fixed
on the store side, so you can see what you are being asked to fit with, and then asks what your side can do.

**What is attached.**

1. This note.
2. The fixed points on the store side. One page.
3. Eighteen decisions, M1 to M18, that we need to reach together. Each says what follows if the answer is unfavourable, so you can see why it is asked.
4. The complete question list, sections 1 to 11, as an appendix. This is the working checklist for the specification.
5. A reply sheet.
[6. An extract of the contract section that defines the stock rules, for reference.]

**What I am asking of you.**

| | What | By |
|---|---|---|
| Before the meeting | Any existing interface documentation (question 2.1); whether a test environment exists (M3); how the store would reach and authenticate to the ERP (M2); and what identifies a stock item (M4, M5). A full item list, if one can be exported (3.8) | [date] |
| At the meeting | The eighteen decisions, M1 to M18 | [meeting date] |
| After the meeting | Written answers to whatever remains of sections 1 to 10 | [date] |

"We do not know yet" is a useful answer. Where something is not decided, a name and a date for it is all I need.

**What I am not asking.** I am not asking you to build anything before we have met. I am not asking for access to
the live ERP: the integration is built and tested against a test environment only. I am not asking for any access
beyond stock, and the store will not send customer personal data to the ERP.

**How your system is treated.** The ERP and its interface are yours. If the meeting shows that work is needed on
your side, that is written down with its scope and timing and agreed with Mizzey before anything depends on it.

**What happens afterwards.** I write up what we agree as the ERP Integration Specification. Mizzey reviews and
approves it in writing, and you confirm that it describes your system correctly. The store side is then built to
it.

**How this pack relates to the earlier document.** You may already have been given "ERP Technical Meeting:
Preparation and Open Decisions", dated 22 September 2026. That document sets out scenarios and one possible design
for discussion. This pack is the question list for the meeting. [Where the two differ on how a product is matched
between the store and the ERP, this pack is the current position.]

I can be reached at [email] and [telephone]. Thank you for your time.

Kind regards,
Mustafa Shaaban

---

Notes on the draft, for you:

* The bracketed sentence about which document prevails supersedes part of a document that is in the signed pack as a draft. It is your call whether to say it.
* The line "you confirm that it describes your system correctly" follows document 09 section 13, whose approval table has a column "ERP developers, capability confirmed". The contract itself requires only the client's written approval (Agreement 6.5 step 5).
* The three dates are blank because I found no meeting date, no named contact and no start date for the delivery clock.

## 4. Draft section: what is fixed on the store side

To sit in the pack before the eighteen decisions. It restates D-10 item 5 for an outside reader and adds nothing to
it.

---

### What is fixed on the store side

These six points hold whatever your answers are. They describe how the store is built. They do not choose a method
for the ERP, and they place no obligation on the ERP beyond what Mizzey has already agreed.

| # | Fixed point | Where it comes from | What stays open, and where it is asked |
|---|---|---|---|
| 1 | **The ERP is the source of truth for stock.** | Contracted between Mizzey and the developer (register ERP-01) | What the stock figure means and how it is read: M6, M7 |
| 2 | **One commercial item or variant is one ERP stock identity, whatever the language.** The English and Arabic versions of a product in the store resolve to the same single ERP stock item, never to two balances | The developer's fixed position, following from point 1 and from the contracted matching "in both languages" (register ERP-08) | Which key identifies that item, and at which level: M4, M5, M16, M17 |
| 3 | **The store holds no independent authoritative stock.** Any figure the store holds is a copy of the ERP's, never a balance of its own | Contracted (register ERP-01; no interim operation on separate stock, ERP-09, OD-42) | Whether a copy may be kept for display, and for how long: M7. How the store admin shows stock: M18 |
| 4 | **A final sale fails closed.** If the ERP cannot validate the stock, including when it cannot be reached, the order is not confirmed | Contracted (register ERP-05, OD-41) | Whether stock can be reserved, and when the decrement happens: M8, M9 |
| 5 | **No duplicate decrement.** One sale reduces the ERP stock once. The store is built so that a repeated click, a repeated payment confirmation or a retry never produces a second decrement from its side | The developer's engineering commitment, serving the contracted sale-reduces-stock rule and duplicate protection (register ERP-04, PAY-05, NFR-07) | What makes a repeated call safe on your side, and what happens on a timeout: M11, M12, M13 |
| 6 | **Idempotency, retry, logging and reconciliation paths exist on the store side.** Every call to the ERP goes through one adapter, carries a reference by which a repeat can be recognised, is logged, and can be reconciled per order. How far a repeat is safe from end to end depends on your side | The developer's engineering commitment, serving the contracted adapter rule (register INT-16) and observability (NFR-10) | Partial success, reversal and who owns reconciliation: M10, M14, M15 |

Points 1, 3 and 4 restate rules in the signed contract between Mizzey and the developer. Points 2, 5 and 6 are how
the developer commits to meeting those rules. They are not requests to the ERP side.

**What is deliberately not fixed.** Whether stock is reserved, when it is decremented, whether changes are pushed or
polled, how long a figure may be treated as current, and which key maps a product to the ERP. None of these is
chosen before your answers.

**Scope.** Stock only. Orders, customers, prices, product details, invoices and accounting are outside this work,
and so is any update of Amazon or another channel by the store.

---

Notes on the draft, for you:

* D-10 records all six as **owner decisions that are not client confirmations**. The table says which three restate signed rows and which three are your engineering commitments, so nothing is presented to a third party as agreed by the client that is not.
* Point 3 uses "copy" and not "snapshot". Document 09 proposes a "display snapshot"; the question set chooses no mechanism, so the neutral word is safer.
* The existing section "The agreed business rules, which are not up for discussion" covers points 1, 3 and 4 already. This table does not replace it; it joins the rules, the language invariant and the two unstated invariants into one list.

## 5. Draft reply sheet

One row per decision, M1 to M18, then one row per appendix question. The questions themselves are not repeated or
reworded here; the sheet carries their numbers only.

| # | Answer | Conditions or limits | If not known yet: owner and date | Document or reference |
|---|---|---|---|---|
| M1 | | | | |
| M2 | | | | |
| M3 | | | | |
| ... to M18 | | | | |
| 1.1 | | | | |
| ... to 10.7 | | | | |

For section 11, the proposed acceptance criteria, a separate short sheet: criterion number, "agreed", "agreed with
this change", or "cannot be met, because".

## 6. How it sits beside document 09

`final docs/Client/Branded/Mizzey-Launch-Platform-ERP-Stock-Integration.md`, MS-ERP-2026-031 version 0.5, is in the
signed pack as file 09. The 4 October question set was written later and from measured results. They are not the
same document and neither refers to the other.

| Topic | Document 09, 22 September, with the client | Question set, 4 October, repository only |
|---|---|---|
| Purpose | Questions for Mizzey (M-1 to M-8), questions for the ERP developers (E-1 to E-12), one proposed design, fifty scenarios, six decisions with proposals | The contracted rules, one invariant, eighteen must-answer decisions, the full checklist, proposed acceptance criteria |
| Product matching | SC-32: "Both language versions carry the same SKU and read the same ERP figure". SC-30: "The store refuses to save a duplicate SKU". EC-01 reads availability "for a list of SKUs" | "Resolve the canonical commercial item through WPML translation identity, never by SKU alone". It records that a SKU can match either language version, and M16 records that a duplicate holds the same SKU |
| Mechanism | Proposes one: an all or nothing reserve (EC-02), reservations that expire (EC-04), a display snapshot, a 30 minute hold, a 5 minute refresh | Chooses none: "no ERP mechanism is chosen" |
| Labels | M-1 to M-8 are questions for Mizzey | M1 to M18 are decisions for the ERP developers. The two series will be confused in a meeting |
| Name of the result | Section 9 calls it "the ERP Interface Record, an appendix to this document"; elsewhere "ERP Integration Specification" | "ERP Integration Specification (PRE-09)", which is the contract's name for it (Agreement 6.5, Annex G) |

The product matching row is the one that matters. SC-30 and SC-32 were written before the pilot measured that a
translated product shares its original's SKU, and they describe the opposite of what the question set now holds.
If the ERP team has read document 09, they have been told the SKU is the key.

Document 09 also has stale internal references of its own (it points to "section 4" for the capabilities, which
are in 5.0b, and to "section 8" for the joint session, which is section 9). It is marked a draft, and this is
noted only because the ERP team may be reading it.

## 7. Timing the contract does fix

For the "by when" cells, these are the only anchors in the signed set. None is a calendar date.

| Event | Contract timing | Where |
|---|---|---|
| Joint technical meeting | "targeted for week 4" | Register PRE-09, CR-17; Agreement 6.5 step 2 |
| Client's review of the specification | "within 10 Working Days of its submission to you" | Register CR-17; Agreement 6.4 |
| ERP test environment and interface documentation | "By the date agreed in PRE-09"; and the Statement of Work names "your ERP's test environment, by the start of Stage 4" | Register CR-16; Statement of Work section 5 |
| ERP-dependent build and test | Stage 4, planned weeks 6 to 9 | Project Plan 3.4 |
| Live ERP with live stock | "Before stage 5" | Project Plan section 4 |
| If the ERP side is late | "the timeline extends", and any change to Stage 4 or its payment "requires the written agreement of both Parties" | Agreement 6.5 |

## 8. Decisions that are yours before anything is sent

1. Whether the pack goes out as a new version of MS-ERP-2026-031 or as a separate document, and whether it is rendered through `brand-render`.
2. Whether to tell the ERP team that the product matching proposal in document 09 is superseded.
3. The three dates and the named recipient.
4. Whether the register Section I2 extract is attached. It is contract text, so it goes to a third party only through the client.
5. The one word correction, fifteen to eighteen, and the M18 reference.

## 9. Evidence not found

1. A named ERP technical contact.
2. A meeting date, or the calendar date that "week 4" falls on.
3. Any reply from the client or the ERP team to document 09, including answers to its questions M-1 to M-8.
4. Any ERP interface documentation, item list or test environment detail.
5. Whether the ERP team has in fact received document 09.
