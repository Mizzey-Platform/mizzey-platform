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

## 2026-09-09 - Cost snapshot: corrected by evidence

P-016 and P-017 run against WooCommerce 11.1.0. The previous entry's central claim was wrong.

**WooCommerce freezes cost onto the order** during `calculate_totals` (P-017, refuted): an order for
2 units at cost 100 recorded 200, the product cost was changed to 999, and the same order re-read from
storage still read 200. Mizzey does not need to build a snapshot. ADM-27 accurate history holds
natively.

**But the feature is off by default** (P-016, partial): `woocommerce_feature_cost_of_goods_sold_enabled`
is `no` on a fresh install, and an order placed while it is off carries no cost at all. Enabling it
later does not backfill.

So the unrecoverable risk moved rather than disappeared, and it is now cheap: **enable
`cost_of_goods_sold` before the first order, and prove it is on.** Added to the go-live gate rather
than treated as a build task, because nothing about a working store reveals the flag is off.

Consequences for the register: **US-16-05 `native` is defensible.** **Technical Design section 9 is
wrong** to call ADM-27 a "New field on product and variation"; it is a native field behind a flag.
That document is with the client, so the correction goes through the normal route.
