# Product cost and what must be captured from day one

9 September 2026. Prepared for the OD-12 answer and for the catalogue module design.

**OD-12 is the client's decision, not ours.** It sits in the Feature Register open-decisions table,
marked urgent, due before catalogue load. This document gives the recommendation to put to them and
the reasoning behind it. It does not answer it.

---

## 1. What is already contracted

| Id | Text | Class | Stage |
|---|---|---|---|
| ADM-27 | Product cost: captured from day one so margin reporting can later be built on accurate history | P1, key | S1 |
| RPT-11 | Product cost captured from day one | P1, key | S1 |
| ROLE-06 | Accountant permission profile, including product cost (ADM-27, RPT-11) | P1-L, key | S1 |
| ENT-18 | Supplier / Purchase Order | **P2, not built** | - |
| RPT-02 | Profitability, COGS, gross margin, product margin | **P2, not contracted** | - |

Technical Design section 9 commits the shape:

> ADM-27 Product cost. **New field** on product and variation. Captured from day one so margin
> reporting can be built later on real history.

Singular. One field. That document has been sent to the client.

**Story US-16-05, "Product cost captured from day one", is classified `native` and flagged key.**
Native means WooCommerce provides it. That conflicts with the Technical Design calling it a new field.
Both cannot be right, and neither has been tested. See section 5.

## 2. The recommendation for OD-12

### Cost basis: purchase cost, single field

Landed cost is purchase price plus freight, customs duty, and clearing charges. Those values arrive on
a purchase order, and **ENT-18 Supplier and Purchase Order is classified P2 and is explicitly not
built**. There is no entity to hold them, no process that produces them, and no one contracted to
maintain them. Landed cost at launch would mean a number typed by hand with nothing behind it, which
is worse than a purchase cost that is honest about what it is.

This also matches what the client's own reporting document concedes: it asks for the two "as separate
fields where practical" and, more firmly, for **a written definition of which cost is used**. The
definition is the part they actually need, and it is the part we can give.

### Who enters it

Two entry points, both restricted to roles carrying financial permission per ROLE-06:

- The product and variation edit screen, for single corrections
- The catalogue import mapping, for the bulk load and for reruns by SKU

### Make the answer reversible at no cost

Store the cost as a value **and a basis label**, with `purchase` as the only value used at launch.

This costs nothing now and buys the thing that is otherwise expensive: if the client later contracts
landed cost, historical rows stay correctly labelled as purchase-basis instead of being silently
reinterpreted as landed. Without the label, adding a second basis later makes every past margin
figure ambiguous, and there is no way to tell which rows meant what.

## 3. The one gap that cannot be repaired later

**Cost is not frozen onto the order line at sale, and nothing contracted says it is.**

Technical Design section 9, ENT-08: "**Price snapshot native.** Attribution fields added for MKT-09."
Price. Cost is not mentioned, there or anywhere.

ADM-27 is a field on the product and the variation. A field is mutable. So as written:

> Edit a product's cost today, and every historical margin for that product changes retroactively.

That is not a reporting bug that can be fixed when the report is built. The cost that applied on the
day the order was placed is simply gone. **No later work recovers it.**

ADM-27's contracted purpose is "so margin reporting can later be built on **accurate history**". A
design where history rewrites itself does not deliver accurate history. So freezing cost onto the
order line is not new scope, it is what ADM-27 already promises. That is our position; if the client
ever disputes it, the fallback is a change request measured in hours, not a lost year of data.

**Cost to build: one value written at order creation. It must exist before the first order.**

## 4. What I previously flagged that is in fact already covered

Correcting three things I raised earlier as gaps. They are contracted:

| Concern | Actually covered by | Stage |
|---|---|---|
| Search terms must be logged from launch or the S2 report starts empty | **SRCH-08, Search term logging for later analysis**, P1 | S1 |
| Order status transitions need a history | **ADM-86**, order detail including a **timeline**, P1 | S1 |
| New entities need actor and timestamp | Technical Design section 9 design rule: "**Every new table carries actor and timestamp.** It is a column pair on everything that changes" | S1 |

The returns lifecycle (ENT-12: reason, status, attachments, decision, actor, timestamps), the audit
log (ENT-17, append only per ADM-133), the import runs (ENT-19), and COD collected versus remitted
(ENT-10, SHIP-16) are all contracted new tables at S1.

The capture position is much stronger than I said. The cost snapshot is the real exception.

## 5. Partial gap: shipment status timestamps

ENT-10 Shipment holds "carrier, tracking, rate, status". That is the current status. Average delivery
time and RTO rate need the transitions: dispatched at, delivered at, returned at.

SHIP-17 makes returned-to-origin a distinct state and forbids recording it silently as delivered, and
SHIP-14 requires the carrier-status-to-order-status mapping be visible in admin. Both argue for
recording transitions rather than overwriting one column. The row-level actor and timestamp pair gives
when the row last changed, not when each transition happened.

Recommendation: record shipment status transitions, same as the order timeline in ADM-86. Cheap while
the table is being designed, expensive to reconstruct afterwards, and never recoverable for shipments
already closed.

The reports that consume it (C-RPT-15 carrier performance) remain lane two and are charged.

## 6. Lane one and lane two

| Item | Lane | Grounds |
|---|---|---|
| Cost field on product and variation | One | ADM-27, RPT-11, both P1 key |
| Cost frozen onto the order line at sale | One | ADM-27's "accurate history" is not deliverable without it |
| Basis label on the cost value | One | Costs nothing, and protects the ADM-27 history if OD-12 is ever revisited |
| Search term logging | One | SRCH-08, P1 |
| Order timeline | One | ADM-86, P1 |
| Shipment status transitions | One, arguable | SHIP-14, SHIP-17. Raise it explicitly rather than assume |
| Carrier cost per shipment | **Two** | Nothing contracts it. Needed only for contribution margin, which the client marked optional |
| RPT-02 profitability and margin reports | **Two** | P2. Priced through MS-CHG-2026-014 |
| RPT-04, RPT-05, RPT-06 customer, promotion, returns reports | **Two** | P2 |

The line to hold: **capture is lane one where a contracted requirement depends on it. Reports are lane
two and are charged.** That is also the strongest commercial position, because it lets us say the data
is being kept at no extra cost and the reports will have full history from launch whenever they are
bought.

## 7. What waits on evidence

Two probes added to the discovery set rather than assumed:

| Probe | Question |
|---|---|
| P-016 | Does WooCommerce provide a native product cost field, or must ADM-27 be a field we add? |
| P-017 | Is cost frozen onto the order line at sale the way price is, or does the line read a mutable current cost? |

P-016 exists because US-16-05 is classified `native` while the Technical Design calls ADM-27 a new
field. One of the two is wrong. WooCommerce has carried no cost-of-goods field for most of its life
and recent versions have begun to add one, so the answer depends on the version installed and must be
read off the running site, not from memory.

Neither probe can run until WooCommerce is installed on the runtime.

## 8. What to do next

1. Put OD-12 to the client: **purchase cost, single field, entered by roles with financial permission
   through the product screen and the import mapping.** Ask them to confirm, and record the written
   definition their reporting document asks for
2. Run P-016 and P-017 once WooCommerce is on the runtime
3. Design the cost field and the order-line snapshot together, with the basis label, before the
   catalogue module is built and well before the first order

Nothing here is blocked by the client's answer except the wording of the definition. Purchase cost,
snapshotted, with a basis label, is correct under either outcome.
