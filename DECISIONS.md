# Mizzey — Decision Log

Record each non-trivial decision (context · decision · why · status).

## 2026-09-09 - Product cost basis and the cost snapshot

**OD-12 is the client's decision.** Recommendation to put to them: **purchase cost, a single field**,
entered through the product screen and the catalogue import mapping, restricted to roles carrying
financial permission per ROLE-06.

**Why not landed cost.** It is purchase price plus freight, duty and clearing, all of which arrive on
a purchase order. ENT-18 Supplier and Purchase Order is P2 and explicitly not built, so there is no
entity to hold those values and no process producing them. Landed cost at launch would be a hand
typed number with nothing behind it. The Technical Design already sent to the client commits to a
"New field", singular.

**Decided regardless of the answer:** store the cost with a **basis label**, and **freeze the cost
onto the order line at sale**. ENT-08 makes the price snapshot native and says nothing about cost, so
as designed, editing a cost silently rewrites every historical margin. ADM-27 promises accurate
history and cannot deliver it without the snapshot, which is why this is treated as lane one rather
than as new scope. It must exist before the first order; no later work recovers the cost that applied
on the day.

Full reasoning, the lane one and lane two split, and the corrections to three gaps I raised that turn
out to be contracted already (SRCH-08, ADM-86, and the actor and timestamp design rule):
[docs/decisions/2026-09-09-product-cost-and-data-capture.md](docs/decisions/2026-09-09-product-cost-and-data-capture.md).

Probes P-016 and P-017 added rather than assuming the answer: US-16-05 is classified `native` while
the Technical Design calls ADM-27 a new field, and one of the two is wrong.
