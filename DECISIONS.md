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

## 2026-09-09 - Shipment status timestamps on ENT-10

P-018 run against WooCommerce 11.1.0. **There is no structured record of order status transitions.**
Checked for a status-history table and found none; the only per-transition record is an English
sentence in an order note ("Order status changed from On hold to Completed"), timestamped but not
queryable. `date_paid` and `date_completed` are the only structured milestones and neither is dispatch
or delivery.

**US-18-02 `native` is right for displaying a timeline and wrong for measuring one.** ADM-86 is
satisfied by the notes; nothing measurable comes out of them.

**Decided: three timestamp columns on ENT-10, not a transition log.** `dispatched_at`, `delivered_at`,
`returned_at`. The shipment lifecycle is short and its states are known, so a general history table is
more machinery than the problem needs.

The grounds differ per column and that is recorded deliberately. **`delivered_at` is contractual**:
SHIP-16 records collected versus remitted per order, and outstanding remittance cannot be aged or
chased without knowing when collection happened. **`dispatched_at` and `returned_at` are prudence**,
nothing contracts them. They are the same trade as the cost flag: near zero to add while the table is
being designed, permanently unrecoverable for every shipment closed before anyone notices.

Recorded as a choice rather than a requirement so nobody later mistakes them for assumed scope.
C-RPT-15, which consumes them, stays lane two and is charged.

## 2026-09-09 - BR-003 concurrency: booked, not assumed

P-009 established the mechanism and could not settle the question. The stock write is atomic per
statement but does not refuse to go negative: two sequential decreases of 1 from a stock of 1 left the
quantity at -1. The guard against overselling is the validation step before payment, not the write, so
validation and decrement are separate steps with a gap in principle. One PHP process cannot create
simultaneity, so whether two buyers of the last unit both succeed is still open.

Technical Design section 6 classifies this **Native**. On the evidence, Native is not yet earned for
the concurrent case.

Booked as [#233](https://github.com/MustafaShaaban/mizzey-platform/issues/233), **sprint 8, Stage 3**,
linked from [#120](https://github.com/MustafaShaaban/mizzey-platform/issues/120) US-07-06 with a note
that acceptance of that story should wait on it.

**Why sprint 8.** Checkout is built in sprint 8, so it is the first sprint the test can run at all, and
at 53 points against a 55 average it is the least loaded sprint in Stage 3. Sprints 9 to 11 run at 61,
64 and 64; sprints 12 and 13 at 75 and 82. Every sprint after this one is worse. The only other place
BR-003 is verified today is US-27-04 Data integrity (#222) in **Stage 6, sprint 14**, which would mean
discovering an overselling store after the operations system and the migration are built on it.

**New label `type:task`**, for verification or enabling work that is not a story. The two-lanes rule
still binds it: #233 cites BR-003, CHK-12, AC-05 and AC-15, so it is lane one. A task that cannot cite
an Annex A id is still a change request. The issue generator is additive and keeps its state in
`scripts/issues.json`, so a hand-made issue is not at risk from a regeneration.

## 2026-09-09 - Bosta carrier status mapping, tested

P-012 re-run against the real integration: Bosta WooCommerce 4.5.7, the official plugin SHIP-08 names,
installed on the discovery runtime.

**The plugin never changes order status from a webhook.** In `handle_status_update` the call to
`map_bosta_state_to_wc_status` is commented out. Driving the handler with state 46 on a `processing`
order left it `processing` and wrote only `bosta_state_code` and `bosta_status` meta. So **SHIP-13 is
not delivered by the plugin as shipped**: carrier status is stored, not reflected in order status.

**The feared failure is refuted.** An unrecognised state falls back to `processing`, not `completed`.
Nothing is silently marked Delivered today.

**The real exposure is different.** The dormant table maps 45 to `completed` and **46 to `completed`
as well**, grouped as "Finished successfully", while only 45 sets a delivery date. Two distinct
terminal outcomes collapse into one Woo status, and whoever enables that mapping inherits it silently.

**The plugin ships no legend for the state codes anywhere in its source.** What 46 means cannot be
established from the integration and must come from Bosta documentation or an account. That is a
blocking input for SHIP-14: if 46 is a return-to-origin outcome, enabling the stock mapping records a
returned parcel as delivered, which SHIP-17 forbids. This absence is the argument for SHIP-14 existing.
Raised as [#234](https://github.com/MustafaShaaban/mizzey-platform/issues/234), in Stage 1 rather than
sprint 11 where the carrier stories sit: the answer costs an email and then waiting, so the lead time is
free, and Stage 4 runs at 64 points against a 55 average with no slack to absorb an unknown. It is
developer-side vendor documentation, deliberately not labelled `blocked:client-input`, because nothing
in it is owed by Mizzey.

**Direction for the build:** do not enable the plugin's mapping. Write the Mizzey mapping explicitly,
code by code, with a named legend visible in admin per SHIP-14, and an unmapped code must land
somewhere inert and visible rather than in any terminal state.

Recorded on the board at [#166](https://github.com/MustafaShaaban/mizzey-platform/issues/166) and
[#165](https://github.com/MustafaShaaban/mizzey-platform/issues/165). `bosta_delivery_date`, set only
on state 45, is a candidate source for the `delivered_at` column decided for ENT-10.
