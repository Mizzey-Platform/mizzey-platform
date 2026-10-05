# ERP question pack: what it is, and how it is sent

5 October 2026. Owner PBI: #243, the ERP Integration Specification (PRE-09). Decided in D-12, item 6.

**Status: ready to send. Not sent.** No meeting is arranged, no ERP contact is named on file and no date is
agreed, so the four documents carry fields for those and nothing is filled in on a guess. **PRE-09 itself is not
written and not approved.** It waits for the ERP team's answers and for the client's written approval.

## The four documents that go out

They go to the client, who passes them to the ERP developers. They are written for a reader who has never seen
this repository or the contract: no internal reference, no file path, no fee, no legal entity name.

| # | Document | What it is for |
|---|---|---|
| 1 | [Cover note](01-cover-note.md) | Who is asking, what is already agreed, what is the ERP team's to tell us, what is asked before, at and after the meeting, and what is not being asked. One page |
| 2 | [Reply sheet](02-reply-sheet.md) | One row per decision, per question and per proposed acceptance criterion, for the ERP team to fill in |
| 3 | [Technical appendix](03-technical-appendix.md) | A short glossary, what is fixed on the store side, what is deliberately not fixed, the eighteen decisions M1 to M18, the complete question list, the proposed acceptance criteria |
| 4 | [Material to provide](04-material-to-provide.md) | One list, split into before the meeting and at the meeting |

The internal working copy, with register ids and the measured store-side detail, stays
[the question set](../2026-10-04-erp-technical-meeting-questions.md). Its decision numbers, question numbers and
criteria numbers are identical to the technical appendix, so an answer on the reply sheet maps straight back.

## The SKU is the baseline, not a question

The signed Feature Register already settles the store-side matching key. **Every sellable product and variant
carries a SKU that matches the ERP exactly** (CR-18), each variant has its own SKU (ADM-31), and **variant stock
is read from the ERP by variant SKU** (ADM-34). ERP-08 contracts the match in both languages and leaves its
technical method to the specification.

An earlier draft of the question set asked the ERP team what the matching key should be, and proposed never
matching "by SKU alone". That was wrong against the contract and is corrected. **The pack does not ask what the
key should be. It asks how the SKU behaves on the ERP side:**

| What is confirmed | Question | Decision |
|---|---|---|
| The exact endpoint or query that resolves a SKU | 3.1 | M4 |
| Whether the ERP also exposes an immutable internal item id | 3.2 | M4 |
| Whether the store should keep that id in addition to the SKU, for resilience | 3.3 | M4 |
| How a simple product and a product with variants are each represented | 3.4 | M4, M17 |
| Whether stock exists per variant or only at parent or product level | 3.5 | M17 |
| SKU uniqueness: enforced or conventional | 3.6, 3.7 | M5 |
| Case, whitespace and leading-zero normalisation | 3.8 | M5 |
| What happens when a SKU changes | 3.9 | M16 |
| How a renamed or replaced SKU is reconciled | 3.10 | M16 |
| Whether old SKU aliases are retained | 3.11 | M16 |
| What happens when a SKU is unknown, inactive or duplicated | 3.12 | M18 |
| Items held without a stock balance | 3.13 | M18 |

**An ERP internal id may supplement the mapping if the specification decides that is safer. It never replaces the
SKU, and it never contradicts the requirement that store SKUs match the ERP.** On the store side, the English
and Arabic versions of a product carry the same SKU and always resolve to the same single ERP stock item.

Question 3.13 is one more than D-12 listed. It was added because the last decision, on SKUs with no usable stock
answer, needed a question that asks about items the ERP holds without a balance. It can be dropped without
affecting the rest.

Also corrected: the references of M16, M17 and M18 now point at questions that exist and ask what the decision
says, and the count reads "eighteen decisions, M1 to M18" in one statement.

## Before it is sent: five things that are Mustafa's

| # | Decision | Note |
|---|---|---|
| 1 | The named recipient, and the three dates | The fields are `[name]` and `[date]`. None is on file |
| 2 | The form it is sent in | These are the texts. Sending them as branded documents goes through the document route, outside this repository |
| 3 | Whether the cover note asks the ERP team to check that the specification describes their system | The contract requires only the client's written approval. The sentence is a request, not an approval step, and can be deleted |
| 4 | Whether the baseline table says whose duty it is to keep the SKUs consistent | CR-18 is a client responsibility. The clause can be removed |
| 5 | Whether the glossary names the store's platform | One line. It can be removed |

## How it sits beside the document the client already holds

The signed pack holds "ERP Technical Meeting: Preparation and Open Decisions" of 22 September 2026, a draft for
discussion. Its statement that both language versions of a product carry the same SKU is consistent with this
pack. Where the two differ, this pack is the current question list, and the cover note says so. That document's
questions numbered M-1 to M-8 are questions for the client and are not the decisions M1 to M18 here.

The earlier review of the pack's readiness, [the readiness note](../2026-10-05-erp-question-pack.md), is kept as
written on 5 October 2026 with a note at its head: its draft cover note and its remarks on the matching key are
superseded by this pack.

## What does not wait for the answers

#243 stays in progress, awaiting ERP input. It does not occupy the development path: the adapter seam (#245) is
built against mocks and contracts on the fixed invariants, and no P1-E row receives final acceptance criteria
before PRE-09 is approved.
