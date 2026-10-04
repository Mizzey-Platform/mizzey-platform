# ERP technical meeting: question set

Prepared 4 October 2026, for the technical meeting with the client's ERP developers, targeted for week 4. The
output of that meeting is the **ERP Integration Specification (PRE-09)**, which the client approves in writing
before any dependent work begins (Services Agreement MS-AGR-2026-023 section 6.5).

## The agreed business rules, which are not up for discussion

These are contracted in Feature Register section I2.1 and are the frame for every question below. The meeting
defines **how**, not **whether**.

| Rule | Register id |
|---|---|
| The ERP is the source of truth for stock. The store does not operate an independently maintained stock balance that can disagree with it | **ERP-01** `[key]` |
| The stock available to customers reflects ERP inventory through the buying journey, at the relevant product, cart and checkout steps. How that is achieved is for the specification; it is not a promise of a call to the ERP on every page view | ERP-02 |
| A sale on the website reduces the corresponding stock in the ERP | **ERP-04** `[key]` |
| The store does not confirm a sale against stock that cannot be validated as available in the ERP, including when the ERP cannot be reached | **ERP-05** `[key]`, confirmed at **OD-41** |
| Only stock is integrated. Orders, customers, prices, products, invoices and accounting are not (EX-35, INT-14 is P3) | I2.1, EX-35 |
| The store does not update Amazon or any other channel. Other channels stay in step through their own connections to the ERP | I2.1 |
| No interim operation on a separately maintained stock balance is contracted. Any interim arrangement needs a separate written agreement | **ERP-09** is OUT, confirmed at **OD-42** |
| The integration is built and tested against a test environment, not live stock | ERP-10 |

**Do not expand the integration beyond stock** in this meeting. If a participant proposes order, customer, price or
accounting synchronisation, it is noted and routed to Change Control (MS-CHG-2026-028), not agreed at the table.

## One constraint we bring, rather than ask about

The store holds each product in two languages, as two WordPress records with their own metadata. The product-cost
pilot established that identity must be resolved through the translation relationship and that a SKU can match
either language version.

**Therefore: the Arabic and English versions of a product are one ERP stock item, never two.** The mapping to the
ERP's identity is held once, against the source-language product or variation, and the translated record resolves
to it. This follows from ERP-01 and is settled before the meeting. What we need from the ERP side is which key to
map to, not whether to map once.

---

## 1. Authentication and access

| # | Question | Why it matters |
|---|---|---|
| 1.1 | What authentication does the API use: API key, OAuth client credentials, mutual TLS, IP allow-list, a session token? | Decides secret storage and rotation (NFR-05) |
| 1.2 | How are credentials issued, rotated and revoked, and by whom? | Operational ownership at handover (HND rows) |
| 1.3 | Are there separate credentials per environment? | ERP-10 requires a test environment |
| 1.4 | Is the API reachable from outside the client's network, and from a host outside Egypt? | **OD-27** has hosting outside Egypt pending. If the ERP is only reachable on a local network, that changes the hosting decision |
| 1.5 | Any VPN, tunnel or static outbound IP requirement? | Infrastructure (E-PRE-1) |

## 2. API availability and documentation

| # | Question |
|---|---|
| 2.1 | Is there written API documentation, and may we have it now rather than at the meeting? |
| 2.2 | What protocol and format: REST and JSON, SOAP, a database view, a file drop, something else? |
| 2.3 | Is the API already in production use by another consumer, or would ours be the first? |
| 2.4 | Is the interface versioned, and what is the deprecation practice? |
| 2.5 | Who maintains it, and what is the normal turnaround for a change request on the ERP side? |
| 2.6 | **Is any ERP-side development needed to satisfy ERP-01 to ERP-05?** If so, who does it, and on what timeline? |

Question 2.6 is the one that can change the project plan. The register anticipates it: if the meeting shows a real
problem of scope, cost, time or feasibility, it is written down and agreed under Change Control before the
dependent work proceeds.

## 3. Product and variant identifiers

| # | Question | Register id |
|---|---|---|
| 3.1 | What identifies a stock item in the ERP: SKU, an internal product id, a variant id, a barcode or EAN, or another external key? | ERP-08 |
| 3.2 | If there is both a product and a variant level, which one carries the stock balance? | ERP-08, ADM-70 |
| 3.3 | Is that identifier stable for the life of the item, or can it change on an ERP edit or re-import? | ERP-08 |
| 3.4 | Is the SKU unique in the ERP, and is it enforced or merely conventional? | ERP-08, MIG-13 |
| 3.5 | Can two ERP items share a SKU, and can one item carry several SKUs or barcodes? | ERP-08 |
| 3.6 | Is the identifier case-sensitive, and are leading zeros or whitespace significant? | ERP-08 |
| 3.7 | Does the ERP hold any language-specific product records, or one record with translated labels? | ERP-08, NFR-04 |
| 3.8 | Can the ERP export a full item list, so the initial mapping can be built and reconciled rather than typed? | MIG-14 |

Question 3.4 matters more than it looks: we have measured that a shared SKU inside the store already makes a
SKU-addressed lookup ambiguous. If SKU uniqueness is also only conventional on the ERP side, SKU cannot be the
mapping key at all.

## 4. Stock lookup and availability

| # | Question | Register id |
|---|---|---|
| 4.1 | How is stock read: one item per call, a batch, a full snapshot, a delta feed? | ERP-02 |
| 4.2 | What does the figure mean: physical on hand, available to sell, on hand minus reservations, minus a safety buffer? | ERP-01, ADM-70, ADM-72 |
| 4.3 | Is there more than one location or warehouse, and if so which one is the store's? | ADM-78 is P3, so we need one answer, not many |
| 4.4 | Typical and worst-case response time for a single lookup and for a batch? | ERP-02 performance |
| 4.5 | **May the store cache the figure, and for how long may it be considered current?** | ERP-02 explicitly leaves this to the specification |
| 4.6 | Is there a push mechanism (webhook, message queue) when a balance changes, or must we poll? | ERP-02 |
| 4.7 | If polling: acceptable frequency for the whole catalogue, and for a single hot item? | ERP-02, rate limits |
| 4.8 | Is there a lightweight "is this quantity available" call distinct from "what is the balance"? | ERP-03, ERP-05 |

## 5. Reservation

| # | Question | Register id |
|---|---|---|
| 5.1 | Does the ERP support reserving stock at all? | ERP-03, ADM-71 |
| 5.2 | If yes: how is a reservation created, identified, confirmed and released? | ERP-03 |
| 5.3 | Does a reservation expire on its own, and after how long? | ERP-03 |
| 5.4 | Does a reservation reduce the available figure other consumers see? | ERP-01 |
| 5.5 | If no reservation exists, what does the ERP expect a consumer to do between validating stock and completing payment? | ERP-05, BR-003 |

Question 5.5 is the heart of ERP-05. Without reservation there is a window between the check and the sale, and the
specification has to say what the store does in it and what the customer sees.

## 6. Sale decrement and write-back

| # | Question | Register id |
|---|---|---|
| 6.1 | What call reduces stock: an absolute set, a relative decrement, a document (sales order, issue note, invoice)? | ERP-04 |
| 6.2 | What does the ERP need from us: item identity, quantity, an order reference, a date, a location, anything else? | ERP-04 |
| 6.3 | Is a partial success possible on a multi-line order, and what is returned if line 2 fails after line 1 succeeded? | ERP-04, NFR-07 |
| 6.4 | At what point should the store write back: payment authorised, payment captured, order confirmed, dispatch? | ERP-04, BR-007 |
| 6.5 | Does the ERP reject a decrement that would take the balance negative, or does it allow it? | ERP-04, ERP-05 |
| 6.6 | Does the write-back create any document a human must then process in the ERP, and whose job is that? | ERP-04, handover |

## 7. Cancellation, restoration and returns

| # | Question | Register id |
|---|---|---|
| 7.1 | How is a decrement reversed: a compensating increment, a credit document, a cancellation of the original? | ERP-06 |
| 7.2 | Is the reversal linked to the original, so a double reversal is refused? | ERP-06, NFR-07 |
| 7.3 | Failed payment after a decrement: is restoration immediate and automatic, or does it need a call? | ERP-06, PAY-04 |
| 7.4 | A parcel returned after dispatch: does the ERP receive it as a return, and does the store trigger that or does a human? | ERP-06, RET-07 |
| 7.5 | Partial return of a multi-unit line? | ERP-06 |
| 7.6 | Is a returned unit restored as sellable immediately, or does it pass through an inspection state? | ERP-06 |

## 8. Idempotency, retries and concurrency

| # | Question | Register id |
|---|---|---|
| 8.1 | **Does the API accept an idempotency key or a client reference that makes a repeated call safe?** | ERP-04, NFR-07, INT-16 |
| 8.2 | If the same decrement is sent twice, what happens: rejected as a duplicate, or applied twice? | NFR-07 |
| 8.3 | If a call times out, is there a way to ask whether it actually landed? | ERP-04, INT-16 |
| 8.4 | How does the ERP behave under two simultaneous decrements of the last unit? Is the write transactional? | ERP-05, BR-003 |
| 8.5 | Are there per-item locks, and can one of our calls block another consumer? | ERP-01 |
| 8.6 | Is ordering guaranteed between a decrement and a subsequent lookup, or can a lookup still return the old figure? | ERP-02 |

Questions 8.1 to 8.4 decide whether the adapter can be made correct at all. A decrement with no idempotency and no
way to query the outcome cannot be retried safely, and that would have to be written into the specification as an
accepted risk with an operational control, not glossed over.

## 9. Errors, limits and downtime

| # | Question | Register id |
|---|---|---|
| 9.1 | What error responses exist, and what distinguishes "retry this" from "do not retry"? | INT-16 |
| 9.2 | Are errors returned as HTTP status codes, a body code, or free text? Is there a list? | INT-16 |
| 9.3 | Rate limits: requests per second or per minute, per credential or per IP, and what happens at the limit? | ERP-02 |
| 9.4 | Expected retry behaviour: backoff, maximum attempts, anything the ERP side objects to? | INT-16 |
| 9.5 | Planned maintenance windows, and how we are told about them? | ERP-05 |
| 9.6 | Historic unplanned downtime, honestly: how often and how long? | ERP-05 |
| 9.7 | During downtime, is there a degraded read path, or is the ERP simply unavailable? | ERP-05, OD-41 |

ERP-05 and OD-41 already settle the business answer to downtime: the store does not confirm the sale. 9.5 to 9.7
decide how often customers will meet that, and therefore what the customer-facing message has to be.

## 10. Environments, logging and ownership

| # | Question | Register id |
|---|---|---|
| 10.1 | Is there a test or sandbox environment, with data we can safely decrement? | **ERP-10**, which requires it |
| 10.2 | Does the test environment match production in interface and behaviour? | ERP-10 |
| 10.3 | What does the ERP log about our calls, and who can read those logs? | NFR-10 |
| 10.4 | Can the ERP side give us a correlation id we can record on our side for support? | NFR-10, INT-16 |
| 10.5 | Who holds the ERP-side on-call responsibility once the store is live? | Handover |
| 10.6 | **Who owns reconciliation when the two sides disagree, and what is the agreed procedure?** | ERP-01, NFR-07 |
| 10.7 | How is a disagreement detected: a scheduled comparison, an alert, a human noticing? | ERP-01 |

Question 10.6 needs an answer in the specification. ERP-01 makes the ERP authoritative, which means the store
corrects itself from the ERP rather than the other way round. What has to be agreed is who notices, how quickly,
and what happens to an order caught in the middle.

## 11. Acceptance criteria for the integration

The specification needs its own acceptance criteria. Proposed shape, to agree at the meeting:

| # | Criterion | Register id |
|---|---|---|
| 11.1 | A product page, cart and checkout reflect the ERP balance at the steps the specification names, within the stated freshness window | ERP-02, ERP-03 |
| 11.2 | A completed sale reduces the ERP balance by exactly the quantity sold, once, verifiably | ERP-04 |
| 11.3 | A repeated or retried write-back does not decrement twice | ERP-04, NFR-07 |
| 11.4 | An order is not confirmed when the ERP cannot validate availability, and the customer sees the agreed message | ERP-05, OD-41 |
| 11.5 | A failed payment or cancellation restores the balance, once | ERP-06 |
| 11.6 | A returned parcel is received in the ERP by the agreed route | ERP-06 |
| 11.7 | An Arabic order and an English order against the same item decrement the same single ERP stock item | ERP-08, NFR-04, NFR-07 |
| 11.8 | Stock fields in the store admin behave as the specification states, and the admin cannot create a figure that contradicts the ERP | ERP-07 |
| 11.9 | The initial stock load lands in the agreed destination with a reconciliation report | MIG-14 |
| 11.10 | Every criterion is demonstrated against the test environment, not live stock | ERP-10 |

Criterion 11.7 is the one that comes from our side of the table, and it is the reason the identity constraint above
is settled before the meeting.

## What a good meeting produces

1. Written answers to sections 1 to 10, or a named owner and a date for each one still open.
2. A decision on the mapping key (section 3), because the migration and the adapter both depend on it.
3. A clear yes or no on reservation (section 5) and on idempotency (section 8.1).
4. Test environment access (10.1).
5. Agreement on whether ERP-side development is needed, and if so its scope and timeline (2.6), routed through
   Change Control if it affects scope, cost or time.
6. Agreed acceptance criteria (section 11).

Until PRE-09 is approved, no P1-E row receives final acceptance criteria (constitution M-3), and no ERP mechanism
is chosen. That covers 19 P1-E rows plus MIG-14, listed in `docs/2026-10-04-option-b-backlog-structure.md`.
