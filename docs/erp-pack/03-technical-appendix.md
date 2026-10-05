# Mizzey online store and the ERP: stock integration

## Technical appendix to the question pack

* **From:** Mustafa Shaaban, developer of the Mizzey online store
* **For:** the ERP developers working with Mizzey
* **Date:** [date]

This appendix holds everything the cover note refers to: what is already fixed on the store side, the eighteen
decisions to reach together (M1 to M18), the complete question list (sections 1 to 10), the proposed acceptance
criteria (section 11) and what a good meeting produces. Answers go on the reply sheet, which uses the same numbers.

Two tables carry a last column headed "Reference for Mizzey and the developer". It lists row numbers from the
agreement between Mizzey and the developer. The ERP team can ignore that column.

## Glossary

* **The store**: the new Mizzey online shop. It is built on WooCommerce, a shop system that runs on WordPress.
* **SKU**: the stock keeping unit code of one sellable item. A product without variants has one SKU. Each variant
  of a product has its own.
* **Variant**: one buyable version of a product, for example a size or a colour.
* **Language versions**: the store holds each product in English and Arabic, both language versions carry the same
  SKU, and both always resolve to the same single ERP stock item.
* **The specification**: the ERP Integration Specification, the written record of what we agree. Mizzey approves it
  in writing before the store side is built to it.

## What is fixed on the store side

These points hold whatever your answers are. They describe how the store is built. They do not choose a method for
the ERP, and none of them is a request to the ERP side.

| # | Fixed point | Where it comes from | What stays open, and where it is asked | Reference for Mizzey and the developer |
|---|---|---|---|---|
| 1 | **The ERP is the source of truth for stock.** | A rule agreed between Mizzey and the developer | What the stock figure means and how it is read: M6, M7 | ERP-01 |
| 2 | **One commercial item or variant is one ERP stock item, whatever the language.** The English and Arabic versions of a product carry the same SKU and resolve to the same single ERP stock item, never to two balances | The developer's engineering commitment. It follows from point 1 and from the SKU baseline below | How a SKU is resolved, at which level stock is held, and how a SKU change is handled: M4, M5, M16, M17 | ERP-08 |
| 3 | **The store holds no independent authoritative stock.** Any figure the store holds is a copy of the ERP's, never a balance of its own | A rule agreed between Mizzey and the developer | Whether a copy may be kept for display, and for how long: M7. What the store shows for a SKU with no usable answer: M18 | ERP-01, ERP-09, OD-42 |
| 4 | **A final sale fails closed.** If the ERP cannot validate the stock, including when it cannot be reached, the order is not confirmed | A rule agreed between Mizzey and the developer | Whether stock can be reserved, and when it is reduced: M8, M9 | ERP-05, OD-41 |
| 5 | **No duplicate reduction.** One sale reduces the ERP stock once. The store is built so that a repeated click, a repeated payment confirmation or a retry never produces a second reduction from its side | The developer's engineering commitment, serving the agreed rule that a sale reduces ERP stock | What makes a repeated call safe on your side, and what happens on a timeout: M11, M12, M13 | ERP-04, PAY-05, NFR-07 |
| 6 | **Idempotency, retry, logging and reconciliation paths exist on the store side.** Every call to the ERP goes through one integration component, carries a reference by which a repeat can be recognised, is logged, and can be reconciled per order. How far a repeat is safe from end to end depends on your side | The developer's engineering commitment | Partial success, reversal and who owns reconciliation: M10, M14, M15 | INT-16, NFR-10 |

Points 1, 3 and 4 restate rules agreed between Mizzey and the developer. Points 2, 5 and 6 are the developer's
engineering commitments, which is how the developer meets those rules.

### The SKU baseline

This is also agreed between Mizzey and the developer, and it is the starting point for every question in section 3.

| Baseline | Reference for Mizzey and the developer |
|---|---|
| Every sellable product and variant in the store carries a SKU that matches the ERP exactly, before the catalogue is loaded. Keeping the SKUs consistent is Mizzey's responsibility | CR-18 |
| Every variant has its own SKU, and the store reads stock per variant from the ERP by variant SKU | ADM-31, ADM-34 |
| How the match works technically, in both languages, is written into the specification | ERP-08 |

**The SKU is the matching key between the store and the ERP. This pack does not ask what the key should be.** It
asks you to confirm how the SKU behaves on your side: how it is resolved, how unique and how stable it is, and what
happens when it changes or cannot be found.

An internal ERP item id may supplement the match if the specification decides that is safer. In that case the store
would keep the id in addition to the SKU. It would never replace the SKU, and it would never contradict the rule
that the store's SKUs match the ERP.

### Scope

Stock only. Orders, customers, prices, product details, invoices and accounting are outside this work. So is any
update of Amazon or another sales channel by the store: other channels stay in step through their own connections
to the ERP. To reduce stock the store sends an order reference, a SKU and a quantity. It sends no customer personal
data.

## What is deliberately not fixed

Four things are open, and none of them is chosen before your answers:

* whether stock is reserved, and how;
* when stock is reduced: at payment authorisation, at capture, at order confirmation or at dispatch;
* whether stock changes are pushed to the store or polled by it;
* how long a figure may be treated as current.

The matching key is not on this list. It is the SKU, as set out above.

## The eighteen decisions, M1 to M18

Eighteen decisions, M1 to M18. Each one can change the design or the schedule, which is why they come before the
full list. The last column points to the detailed questions in sections 1 to 10. Anything not in this table can be
answered in writing afterwards. Anything in it that is left open needs a named owner and a date, because the
specification cannot be written without it.

| # | Decision | If the answer is unfavourable | Detailed questions |
|---|---|---|---|
| M1 | **Is development needed on the ERP side** to support the agreed stock rules? If so, by whom, and on what timeline? | The schedule changes. The scope and timing of that work are agreed in writing with Mizzey before anything depends on it | 2.6 |
| M2 | **Authentication and connectivity**: what method, and can the interface be reached from a server outside Mizzey's network and outside Egypt? | Can decide where the store is hosted, or require a tunnel and a fixed outbound IP address | 1.1, 1.4, 1.5 |
| M3 | **Is there a test environment** with data that can safely be reduced? | Mizzey and the developer have agreed that the integration is built and tested against a test environment, not live stock. Without one it cannot be built and tested as agreed | 10.1 |
| M4 | **SKU resolution.** The store's products and variants carry SKUs that match the ERP exactly. That is the agreed baseline and is not in question. What exact endpoint or query resolves a SKU to its stock item? Does the ERP also expose an immutable internal item id, and if so, should the store keep it in addition to the SKU, for resilience? How are a simple product and a product with variants each represented? | If a SKU cannot be resolved directly, the specification needs an extra lookup step or a maintained cross-reference. The SKU stays the matching baseline either way. An internal id can supplement it and never replaces it | 3.1, 3.2, 3.3, 3.4 |
| M5 | **SKU uniqueness and normalisation.** Is the SKU unique in the ERP, and is that enforced by the system or only a convention? Can two items share a SKU, or one item carry several? Are case, leading zeros and whitespace significant, and does the ERP normalise them? | If uniqueness is only a convention, duplicates must be found and corrected before the catalogue is loaded, a duplicated SKU cannot be sold until it is corrected, and keeping the internal item id beside the SKU becomes the safer design. If the two systems normalise differently, "matches exactly" needs a written rule | 3.6, 3.7, 3.8 |
| M6 | **What the stock figure means**: physical on hand, available to sell, net of reservations, net of a safety buffer? One location or several? | Changes what the store may show customers, and what "on hand" and "available" mean on the store's admin screens | 4.2, 4.3 |
| M7 | **Lookup mechanism, freshness and caching**: one call per item, a batch, a snapshot, a delta feed; push or poll; and how long a figure may be treated as current | Decides whether the store can reflect ERP stock through the buying journey without a call to the ERP on every page view | 4.1, 4.5, 4.6, 4.7 |
| M8 | **Is reservation supported at all?** | If not, there is a window between checking stock and completing payment, and the specification has to say what the store does in it | 5.1, 5.5 |
| M9 | **Reduction mechanism and timing**: what call, and at which point (payment authorised, payment captured, order confirmed, dispatch)? | Decides the checkout flow and makes acceptance criterion 11.5 concrete | 6.1, 6.4 |
| M10 | **Partial success on an order with several lines**: what is returned when line 2 fails after line 1 succeeded? | Decides whether the store needs its own logic to undo the lines that succeeded | 6.3 |
| M11 | **Idempotency**: is there an idempotency key or a client reference that makes a repeated call safe? | Without it, no retry is safe from end to end. That becomes a recorded risk with an operating procedure to control it | 8.1, 8.2 |
| M12 | **Outcome lookup after a timeout**: after a timeout, can the store ask whether the call actually landed? | Without it a timeout cannot be resolved automatically, and reconciliation becomes manual | 8.3 |
| M13 | **Two simultaneous reductions of the last unit**: what happens, and is the write transactional? | Decides whether overselling is possible at the ERP boundary | 8.4 |
| M14 | **Reversal and restoration**: a compensating increase, a credit document, or a cancellation of the original; and is a second reversal refused? | Decides how stock is restored after a failed payment or a cancellation, and the second half of criterion 11.5 | 7.1, 7.2, 7.3 |
| M15 | **Who owns reconciliation** when the two sides disagree, how is it detected, and what happens to an order caught in the middle? | The ERP is authoritative, so the store corrects itself from it. Who notices, and how fast, has to be agreed | 10.6, 10.7 |
| M16 | **SKU change.** Can the SKU of an existing ERP item change? If it can: what happens to the item and its balance, how is a renamed or replaced SKU reconciled with the old one, are old SKUs kept as aliases, and how does the store learn of the change? On the store side a SKU change is applied to the English and Arabic versions together, as a deliberate step and not automatically | If a SKU can change with no alias and no notice, the product stops matching and cannot be sold until the store is corrected. SKU changes then need an agreed procedure with notice, and an internal item id kept beside the SKU becomes the safeguard | 3.9, 3.10, 3.11 |
| M17 | **Stock per variant, or only per parent product?** The store reads stock per variant, by variant SKU. Does the ERP hold a balance for each variant SKU, or only at parent or product level? | If stock exists only at parent level, availability per variant cannot be read from the ERP as agreed. That is a gap for the specification, and for a written agreement with Mizzey if it affects scope, cost or time. If stock is per variant, as expected, the store also checks on its own side that both language versions of every variant carry the same SKU | 3.4, 3.5 |
| M18 | **SKUs with no usable stock answer.** What does the ERP return for a SKU that is unknown, inactive, duplicated, or held without a stock balance? The store has its own stock-tracking switch on a product. It will follow the ERP's answer for the SKU, identically on the English and the Arabic version | If the ERP cannot tell these cases apart, the store treats every one of them as not available for sale and lists the SKU for correction. The specification then states what the store's admin screens show for such a SKU | 3.12, 3.13 |

## The complete question list

Sections 1 to 10 are the full technical checklist, and the working document for the specification. "We do not know
yet" is a useful answer: a name and a date for it is enough.

### 1. Authentication and access

| # | Question | Why it matters |
|---|---|---|
| 1.1 | What authentication does the interface use: API key, OAuth client credentials, mutual TLS, IP allow-list, a session token? | Decides how the store keeps the secret safe and how it is rotated |
| 1.2 | How are credentials issued, rotated and revoked, and by whom? | Mizzey needs to know who owns this once the store is live |
| 1.3 | Are there separate credentials per environment? | The store is built against a test environment and must never use live credentials there |
| 1.4 | Is the interface reachable from outside Mizzey's network, and from a server outside Egypt? | Where the store will be hosted is not yet decided. If the ERP is reachable only on a local network, that decision changes |
| 1.5 | Is a VPN, a tunnel or a fixed outbound IP address required? | It has to be planned into the store's hosting |

### 2. Interface availability and documentation

| # | Question | Why it matters |
|---|---|---|
| 2.1 | Is there written documentation of the interface, and may we have it before the meeting? | Much of this list can then be answered by reading, which saves your time |
| 2.2 | What protocol and format: REST and JSON, SOAP, a database view, a file drop, something else? | Decides the shape of the store's integration component |
| 2.3 | Is the interface already used in production by another consumer, or would the store be the first? | A first consumer should plan more testing time on both sides |
| 2.4 | Is the interface versioned, and how are changes and retirements announced? | The checkout depends on it. An unannounced change stops sales |
| 2.5 | Who maintains it, and what is the normal turnaround for a change on the ERP side? | Sets realistic dates in the specification |
| 2.6 | **Is any development needed on the ERP side to support the agreed stock rules?** If so, who does it, and on what timeline? | The one answer that can change the project plan |

Question 2.6 is the one that can change the project plan. If the meeting shows a real problem of scope, cost, time
or feasibility, it is written down and agreed with Mizzey before the dependent work proceeds.

### 3. Product and variant identifiers: how the SKU behaves

The SKU is the agreed matching key. These questions confirm the technical behaviour around it.

| # | Question | Why it matters |
|---|---|---|
| 3.1 | What exact endpoint, call or query resolves a SKU to its stock item? What does it take as input, and what does it return? | Every stock read and every stock reduction starts here |
| 3.2 | Does the ERP also hold an internal item id that never changes for the life of the item, and is it exposed through the interface? | A second, stable identifier can protect the match if a SKU is ever edited |
| 3.3 | If it does, do you recommend that the store keeps that id in addition to the SKU, for resilience? | The store would hold the id beside the SKU, never instead of it. The SKU stays the agreed key |
| 3.4 | How is a simple product (no variants) represented in the ERP, and how is a product with variants: one item per variant with its own SKU, or a parent record with child records? | The store has both kinds of product, and each variant carries its own SKU |
| 3.5 | Does stock exist per variant, or only at parent or product level? If both levels exist, which one carries the balance? | The store reads and shows stock per variant |
| 3.6 | Is the SKU unique in the ERP, and is uniqueness enforced by the system or only a convention? | A duplicated SKU cannot be sold safely, so a duplicate must be impossible or detectable |
| 3.7 | Can two ERP items share a SKU, and can one item carry several SKUs or barcodes? | Either case makes a lookup by SKU ambiguous |
| 3.8 | Is the SKU case-sensitive? Are leading zeros, leading or trailing spaces, or spaces inside the SKU significant? Does the ERP normalise a SKU when it is entered or when it is looked up? | "Matches exactly" needs one written rule that both systems follow |
| 3.9 | Can the SKU of an existing item be changed in the ERP? If so, what happens to the item, its stock balance and its history, and how would the store learn of the change? | A changed SKU stops matching the store until the store is corrected |
| 3.10 | When a SKU is renamed, or an item is replaced by a new item with a new SKU, how is the old SKU reconciled with the new one? Is there a recorded link between them? | Decides whether a change can be followed automatically or needs a procedure |
| 3.11 | Are old SKUs retained as aliases, so that a lookup by an old SKU still resolves? For how long? | An alias lets sales continue while the store is being corrected |
| 3.12 | What does a lookup return, and what does a stock reduction return, for a SKU that is unknown, for one that is inactive or retired, and for one that matches more than one item? | The store must tell these cases apart and must never sell on a SKU the ERP does not recognise |
| 3.13 | Is every sellable item stock-tracked in the ERP? Are any items held without a stock balance, and what does a lookup return for them? | The store has to know whether "no figure" means "not tracked" or "none left" |
| 3.14 | Does the ERP hold any language-specific product records, or one record with translated labels? | The store holds English and Arabic versions under one SKU. Two ERP records for one item would need care |
| 3.15 | Can the ERP export a full item list with SKUs, and with the internal item id if one exists? | Lets the SKUs on both sides be compared and corrected before the catalogue is loaded |

None of these questions reopens the choice of key. Question 3.6 matters more than it looks: if SKU uniqueness is
only a convention on the ERP side, duplicates must be found and corrected before the catalogue is loaded, and an
internal item id kept beside the SKU (3.2, 3.3) becomes the safer design. An internal id may supplement the SKU if
the specification decides that is safer. It never replaces it.

### 4. Stock lookup and availability

| # | Question | Why it matters |
|---|---|---|
| 4.1 | How is stock read: one item per call, a batch, a full snapshot, a delta feed? | Decides how pages that list many products get their figures |
| 4.2 | What does the figure mean: physical on hand, available to sell, on hand minus reservations, minus a safety buffer? | Decides what the store may show a customer, and what its admin screens label "on hand" and "available" |
| 4.3 | Is there more than one location or warehouse, and if so which one is the store's? | The store works with one figure per item, not one per location |
| 4.4 | What is the typical and the worst-case response time for a single lookup and for a batch? | A checkout that waits on a slow answer loses the customer |
| 4.5 | **May the store keep a copy of the figure, and for how long may it be treated as current?** | The agreed rules leave this to the specification. It decides how often the ERP is called |
| 4.6 | Is there a push mechanism (webhook, message queue) when a balance changes, or must the store poll? | Decides how the store's copy stays current |
| 4.7 | If polling: what frequency is acceptable for the whole catalogue, and for a single fast-selling item? | Keeps the store inside the load your system accepts |
| 4.8 | Is there a light "is this quantity available" call, distinct from "what is the balance"? | Useful at the cart and at checkout, where only yes or no is needed |

### 5. Reservation

| # | Question | Why it matters |
|---|---|---|
| 5.1 | Does the ERP support reserving stock at all? | Decides how the last unit is protected while a customer pays |
| 5.2 | If yes: how is a reservation created, identified, confirmed and released? | The store has to carry out each step |
| 5.3 | Does a reservation expire on its own, and after how long? | An abandoned payment must not hold stock for ever |
| 5.4 | Does a reservation reduce the available figure that other consumers see? | Decides whether another sales channel can still take a reserved unit |
| 5.5 | If there is no reservation, what does the ERP expect a consumer to do between checking stock and completing payment? | This is the window in which the last unit can be sold twice |

Question 5.5 is the heart of the rule that the store does not confirm a sale it cannot validate. Without reservation
there is a window between the check and the sale, and the specification has to say what the store does in it and
what the customer sees.

### 6. Sale reduction and write-back

| # | Question | Why it matters |
|---|---|---|
| 6.1 | What call reduces stock: an absolute set, a relative reduction, a document (sales order, issue note, invoice)? | Decides what the store sends for each sale |
| 6.2 | What does the ERP need from the store: item identity (the SKU), quantity, an order reference, a date, a location, anything else? | The store sends only what is needed, and no customer personal data |
| 6.3 | Is a partial success possible on an order with several lines, and what is returned if line 2 fails after line 1 succeeded? | Decides whether the store must undo the lines that succeeded |
| 6.4 | At what point should the store write the reduction: payment authorised, payment captured, order confirmed, dispatch? | Decides the checkout flow |
| 6.5 | Does the ERP reject a reduction that would take the balance below zero, or does it allow it? | Decides whether the ERP itself is the last guard against overselling |
| 6.6 | Does the reduction create a document that a person must then process in the ERP, and whose job is that? | Mizzey's staff need to know what arrives for them to handle |

### 7. Cancellation, restoration and returns

| # | Question | Why it matters |
|---|---|---|
| 7.1 | How is a reduction reversed: a compensating increase, a credit document, a cancellation of the original? | Needed when a payment fails or an order is cancelled |
| 7.2 | Is the reversal linked to the original, so that a second reversal is refused? | Stops the same unit being put back twice |
| 7.3 | A payment fails after stock was reduced: is restoration immediate and automatic, or does it need a call? | Decides whether the store must act, and how soon the unit can be sold again |
| 7.4 | A parcel returned after dispatch: does the ERP receive it as a return, and does the store trigger that or does a person? | Returned parcels are received in the ERP. The route has to be agreed |
| 7.5 | How is a partial return of a line with several units handled? | Only part of the stock should come back |
| 7.6 | Is a returned unit sellable again at once, or does it pass through an inspection state? | Decides when the store may show it as available |

### 8. Idempotency, retries and concurrency

| # | Question | Why it matters |
|---|---|---|
| 8.1 | **Does the interface accept an idempotency key or a client reference that makes a repeated call safe?** | Without it, a retry after a lost answer can reduce stock twice |
| 8.2 | If the same reduction is sent twice, what happens: refused as a duplicate, or applied twice? | The same risk, seen from the ERP side |
| 8.3 | If a call times out, is there a way to ask whether it actually landed? | Otherwise a timeout leaves an order whose stock state nobody knows |
| 8.4 | How does the ERP behave under two simultaneous reductions of the last unit? Is the write transactional? | Decides whether overselling is possible at the ERP boundary |
| 8.5 | Are there per-item locks, and can one of the store's calls block another consumer? | The store must not slow your other channels |
| 8.6 | Is ordering guaranteed between a reduction and a following lookup, or can a lookup still return the old figure? | Decides whether the store can trust a read made straight after a sale |

Questions 8.1 to 8.4 decide whether the integration can be made correct at all. A reduction with no idempotency and
no way to query the outcome cannot be retried safely. That would be written into the specification as an accepted
risk with an operating procedure, and not passed over.

### 9. Errors, limits and downtime

| # | Question | Why it matters |
|---|---|---|
| 9.1 | What error responses exist, and what separates "retry this" from "do not retry"? | The store retries only what is worth retrying |
| 9.2 | Are errors returned as HTTP status codes, a code in the body, or free text? Is there a list? | The store maps each error to the right message for the customer |
| 9.3 | Rate limits: requests per second or per minute, per credential or per IP address, and what happens at the limit? | The store is built to stay under them |
| 9.4 | Expected retry behaviour: backoff, maximum attempts, anything the ERP side objects to? | Agreed once, so the store never becomes a load problem for you |
| 9.5 | When are maintenance windows planned, and how will the store's side be told? | No sale is confirmed while the ERP cannot be reached, so customers meet every window |
| 9.6 | Unplanned downtime so far, candidly: how often, and for how long? | Sets how much care the "please try again shortly" message needs |
| 9.7 | During downtime, is there a reduced read-only path, or is the ERP simply unavailable? | Decides what customers can still see while orders are paused |

The business answer to downtime is already agreed: the store does not confirm the sale. Questions 9.5 to 9.7 decide
how often customers will meet that, and so what the message to them has to say.

### 10. Environments, logging and ownership

| # | Question | Why it matters |
|---|---|---|
| 10.1 | Is there a test or sandbox environment, with test data that uses the real SKUs and can safely be reduced? | The integration is built and tested against a test environment, never against live stock |
| 10.2 | Does the test environment match production in interface and behaviour? | A test that passes must mean the live system behaves the same way |
| 10.3 | What does the ERP log about the store's calls, and who can read those logs? | Shortens fault finding when the two sides disagree |
| 10.4 | Can the ERP return a correlation id that the store records for support? | Lets one call be traced on both sides |
| 10.5 | Who is on call on the ERP side once the store is live? | Mizzey's staff need a name when orders are paused |
| 10.6 | **Who owns reconciliation when the two sides disagree, and what is the agreed procedure?** | The ERP is authoritative, so the store corrects itself from it |
| 10.7 | How is a disagreement detected: a scheduled comparison, an alert, a person noticing? | Decides how long a difference can go unseen |

Question 10.6 needs an answer in the specification. What has to be agreed is who notices, how quickly, and what
happens to an order caught in the middle.

## 11. Proposed acceptance criteria

The specification needs its own acceptance criteria. This is the proposed shape, to agree at the meeting. The reply
sheet has a row for each.

| # | Criterion | Reference for Mizzey and the developer |
|---|---|---|
| 11.1 | A product page, the cart and the checkout reflect the ERP balance at the steps the specification names, within the stated freshness window | ERP-02, ERP-03 |
| 11.2 | A completed sale reduces the ERP balance by exactly the quantity sold, once, and this can be verified | ERP-04 |
| 11.3 | A repeated or retried reduction does not reduce stock twice | ERP-04, NFR-07 |
| 11.4 | An order is not confirmed when the ERP cannot validate availability, and the customer sees the agreed message | ERP-05, OD-41 |
| 11.5 | **If** stock has already been reserved or reduced, a failed payment or a cancellation releases or reverses that effect **exactly once**, and the reversal is linked to the original so that a repeat is refused. **If no stock change has occurred**, no compensating change is sent. Either way the outcome can be reconciled: the store can state, per order, whether a change happened and whether it was reversed | ERP-06, NFR-07 |
| 11.6 | A returned parcel is received in the ERP by the agreed route | ERP-06 |
| 11.7 | An order placed in Arabic and an order placed in English for the same item reduce the same single ERP stock item, found by the same SKU | ERP-08, CR-18, NFR-04, NFR-07 |
| 11.8 | Stock fields on the store's admin screens behave as the specification states, and an administrator cannot create a figure that contradicts the ERP | ERP-07 |
| 11.9 | The initial stock load lands in the agreed destination with a reconciliation report | MIG-14 |
| 11.10 | Every criterion is demonstrated against the test environment, not live stock | ERP-10 |

Criterion 11.5 is deliberately conditional. Nothing has yet been chosen about whether stock is reserved, or whether
it is reduced at authorisation, at capture or at dispatch. A criterion that assumed a reduction always comes before
a failure would assert a decision nobody has made. What does not change with the timing is the pair of obligations:
a change that happened is reversed exactly once, and a change that never happened is not compensated. In both cases
the state can be reconciled per order.

Criterion 11.7 comes from the store side of the table. It is why the SKU baseline and the rule of one ERP stock item
per commercial item are settled before the meeting and not at it.

## What a good meeting produces

1. Written answers to sections 1 to 10, or a named owner and a date for each one still open.
2. Confirmation of the SKU resolution behaviour (section 3, questions 3.1 to 3.13): how a SKU is resolved, whether
   an internal item id is kept beside it, the uniqueness and normalisation rules, and what happens when a SKU
   changes or cannot be resolved. The catalogue load and the integration both depend on it. The SKU itself is
   already agreed as the matching key.
3. A clear yes or no on reservation (section 5) and on idempotency (8.1).
4. Test environment access, or a date for it (10.1).
5. Agreement on whether development is needed on the ERP side, and if so its scope and timeline (2.6), agreed in
   writing with Mizzey if it affects scope, cost or time.
6. Agreed acceptance criteria (section 11).

Until Mizzey has approved the ERP Integration Specification in writing, no method is chosen for the ERP, and
nothing is built that depends on one.
