# Mizzey online store and the ERP: stock integration

## Reply sheet

* **Completed by:** [name and role]
* **Date:** [date]
* **Return to:** Mustafa Shaaban, through [name] at Mizzey

The numbers match the technical appendix, which holds the full wording of each question. Short answers are enough.
"We do not know yet" is a useful answer: please give an owner and a date for it. Please do not put passwords, keys
or any customer personal data on this sheet.

## Part 1: the eighteen decisions, M1 to M18

| # | Decision | Answer | Conditions or limits | If not known yet: owner and date | Document or reference |
|---|---|---|---|---|---|
| M1 | Whether ERP-side development is needed, by whom, and when | | | | |
| M2 | Authentication method, and reachability from outside the network and Egypt | | | | |
| M3 | Test environment with data that can safely be reduced | | | | |
| M4 | SKU resolution: endpoint, internal item id, simple and variant items | | | | |
| M5 | SKU uniqueness, duplicates, case, leading zeros and whitespace | | | | |
| M6 | Meaning of the stock figure, and which location | | | | |
| M7 | Lookup mechanism, push or poll, freshness and caching | | | | |
| M8 | Whether stock reservation is supported at all | | | | |
| M9 | Stock reduction call, and the point at which it is made | | | | |
| M10 | Partial success on an order with several lines | | | | |
| M11 | Idempotency key or client reference for repeated calls | | | | |
| M12 | Finding the outcome of a call after a timeout | | | | |
| M13 | Two simultaneous reductions of the last unit | | | | |
| M14 | Reversal and restoration, and refusal of a second reversal | | | | |
| M15 | Reconciliation: owner, detection, and orders caught in the middle | | | | |
| M16 | SKU change: effect, old and new reconciled, aliases, notice | | | | |
| M17 | Stock held per variant, or only per parent product | | | | |
| M18 | Answer for an unknown, inactive, duplicated or untracked SKU | | | | |

## Part 2: the complete question list, sections 1 to 10

### 1. Authentication and access

| # | Question, in short | Answer | Conditions or limits | If not known yet: owner and date | Document or reference |
|---|---|---|---|---|---|
| 1.1 | Authentication method | | | | |
| 1.2 | Issue, rotation and revocation of credentials | | | | |
| 1.3 | Separate credentials per environment | | | | |
| 1.4 | Reachable from outside the network and outside Egypt | | | | |
| 1.5 | VPN, tunnel or fixed outbound IP address | | | | |

### 2. Interface availability and documentation

| # | Question, in short | Answer | Conditions or limits | If not known yet: owner and date | Document or reference |
|---|---|---|---|---|---|
| 2.1 | Written documentation, available before the meeting | | | | |
| 2.2 | Protocol and format | | | | |
| 2.3 | Already used in production by another consumer | | | | |
| 2.4 | Versioning, and notice of changes | | | | |
| 2.5 | Maintainer, and turnaround for a change | | | | |
| 2.6 | Development needed on the ERP side | | | | |

### 3. Product and variant identifiers: how the SKU behaves

| # | Question, in short | Answer | Conditions or limits | If not known yet: owner and date | Document or reference |
|---|---|---|---|---|---|
| 3.1 | Endpoint or query that resolves a SKU | | | | |
| 3.2 | Immutable internal item id, and whether it is exposed | | | | |
| 3.3 | Whether the store should keep that id beside the SKU | | | | |
| 3.4 | How a simple product and a product with variants are represented | | | | |
| 3.5 | Stock per variant, or only at parent or product level | | | | |
| 3.6 | SKU uniqueness: enforced or a convention | | | | |
| 3.7 | Shared SKUs, and several SKUs or barcodes on one item | | | | |
| 3.8 | Case, leading zeros, whitespace and normalisation | | | | |
| 3.9 | What happens when a SKU changes | | | | |
| 3.10 | How a renamed or replaced SKU is reconciled | | | | |
| 3.11 | Whether old SKUs are retained as aliases | | | | |
| 3.12 | Response for an unknown, inactive or duplicated SKU | | | | |
| 3.13 | Items held without a stock balance | | | | |
| 3.14 | Language-specific product records | | | | |
| 3.15 | Full item list export | | | | |

### 4. Stock lookup and availability

| # | Question, in short | Answer | Conditions or limits | If not known yet: owner and date | Document or reference |
|---|---|---|---|---|---|
| 4.1 | How stock is read | | | | |
| 4.2 | What the stock figure means | | | | |
| 4.3 | Locations or warehouses, and which is the store's | | | | |
| 4.4 | Response times, typical and worst case | | | | |
| 4.5 | Keeping a copy of the figure, and for how long | | | | |
| 4.6 | Push mechanism, or polling | | | | |
| 4.7 | Acceptable polling frequency | | | | |
| 4.8 | A light "is this quantity available" call | | | | |

### 5. Reservation

| # | Question, in short | Answer | Conditions or limits | If not known yet: owner and date | Document or reference |
|---|---|---|---|---|---|
| 5.1 | Whether reservation is supported | | | | |
| 5.2 | How a reservation is created, confirmed and released | | | | |
| 5.3 | Reservation expiry | | | | |
| 5.4 | Effect of a reservation on the figure others see | | | | |
| 5.5 | Expected behaviour between check and payment, without reservation | | | | |

### 6. Sale reduction and write-back

| # | Question, in short | Answer | Conditions or limits | If not known yet: owner and date | Document or reference |
|---|---|---|---|---|---|
| 6.1 | The call that reduces stock | | | | |
| 6.2 | What the ERP needs from the store | | | | |
| 6.3 | Partial success on an order with several lines | | | | |
| 6.4 | The point at which the store writes the reduction | | | | |
| 6.5 | Reduction that would take the balance below zero | | | | |
| 6.6 | Documents a person must then process | | | | |

### 7. Cancellation, restoration and returns

| # | Question, in short | Answer | Conditions or limits | If not known yet: owner and date | Document or reference |
|---|---|---|---|---|---|
| 7.1 | How a reduction is reversed | | | | |
| 7.2 | Reversal linked to the original | | | | |
| 7.3 | Restoration after a failed payment | | | | |
| 7.4 | Parcel returned after dispatch | | | | |
| 7.5 | Partial return of a line with several units | | | | |
| 7.6 | Returned unit: sellable at once, or inspected first | | | | |

### 8. Idempotency, retries and concurrency

| # | Question, in short | Answer | Conditions or limits | If not known yet: owner and date | Document or reference |
|---|---|---|---|---|---|
| 8.1 | Idempotency key or client reference | | | | |
| 8.2 | The same reduction sent twice | | | | |
| 8.3 | Outcome lookup after a timeout | | | | |
| 8.4 | Two simultaneous reductions of the last unit | | | | |
| 8.5 | Per-item locks, and blocking of other consumers | | | | |
| 8.6 | Ordering between a reduction and a following lookup | | | | |

### 9. Errors, limits and downtime

| # | Question, in short | Answer | Conditions or limits | If not known yet: owner and date | Document or reference |
|---|---|---|---|---|---|
| 9.1 | Error responses, and which may be retried | | | | |
| 9.2 | Error format, and the list of errors | | | | |
| 9.3 | Rate limits | | | | |
| 9.4 | Expected retry behaviour | | | | |
| 9.5 | Planned maintenance windows, and notice | | | | |
| 9.6 | Unplanned downtime so far | | | | |
| 9.7 | Reduced read-only path during downtime | | | | |

### 10. Environments, logging and ownership

| # | Question, in short | Answer | Conditions or limits | If not known yet: owner and date | Document or reference |
|---|---|---|---|---|---|
| 10.1 | Test environment with real SKUs and reducible data | | | | |
| 10.2 | Test environment matches production | | | | |
| 10.3 | What the ERP logs, and who can read it | | | | |
| 10.4 | Correlation id for support | | | | |
| 10.5 | On call on the ERP side after launch | | | | |
| 10.6 | Owner of reconciliation, and the procedure | | | | |
| 10.7 | How a disagreement is detected | | | | |

## Part 3: the proposed acceptance criteria, section 11

Please mark one of the three columns for each criterion, and write the change or the reason in it.

| # | Criterion, in short | Agreed | Agreed with this change | Cannot be met, because |
|---|---|---|---|---|
| 11.1 | Product page, cart and checkout reflect the ERP balance | | | |
| 11.2 | A completed sale reduces the ERP balance exactly once | | | |
| 11.3 | A repeated or retried reduction does not reduce twice | | | |
| 11.4 | No order is confirmed when the ERP cannot validate stock | | | |
| 11.5 | A stock change that happened is reversed exactly once, and none is invented | | | |
| 11.6 | A returned parcel is received in the ERP by the agreed route | | | |
| 11.7 | Arabic and English orders reduce the same ERP stock item, by the same SKU | | | |
| 11.8 | The store's admin screens cannot create a figure that contradicts the ERP | | | |
| 11.9 | The initial stock load arrives with a reconciliation report | | | |
| 11.10 | Every criterion is demonstrated against the test environment | | | |
