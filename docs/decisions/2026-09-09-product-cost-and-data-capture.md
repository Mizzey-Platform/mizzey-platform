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

## 3. Cost at sale: tested, and the answer is better than feared

**Superseded by evidence on 9 September 2026.** This section originally said cost was not frozen onto
the order and that Mizzey had to build the snapshot. P-016 and P-017 were run against WooCommerce
11.1.0 and that is wrong.

### What the probes found

| Probe | Verdict | Finding |
|---|---|---|
| P-016 | partial | WooCommerce 11.1.0 ships a Cost of Goods Sold feature. `WC_Product::get_cogs_value` and `set_cogs_value` exist and a cost survives save and reload. **The feature is off by default** (`woocommerce_feature_cost_of_goods_sold_enabled = no`) |
| P-017 | refuted | An order placed with 2 units at cost 100 recorded 200. The product cost was then changed to 999 and the same order, re-read from storage, still read 200. **WooCommerce freezes cost onto the order during `calculate_totals`** |

So ADM-27's "accurate history" holds natively. Mizzey does not need to build a snapshot mechanism.

### What survives, and it still matters

The unrecoverable risk did not disappear, it moved:

> **The feature is off by default, and an order placed while it is off carries no cost at all.**
> Enabling it later does not backfill. Those orders have no margin, permanently.

So the action is no longer "build a snapshot". It is **"enable `cost_of_goods_sold` before the first
order, and prove it is on"**. Far cheaper, and far easier to get wrong by omission, because nothing
about a working store tells you the flag is off.

Treat it as a go-live gate item, not a build task.

### What this changes in the register

- **US-16-05 `native` is defensible.** Woo does carry the field. The classification is not wrong.
- **Technical Design section 9 is wrong** where it calls ADM-27 a "New field on product and
  variation". It is a native field behind a flag. That document has been sent to the client, so the
  correction goes through the normal route rather than a quiet edit.
- The flag itself is a real build step that no document currently names.

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

## 5. Shipment status timestamps: decided

**Tested, and the gap is real.** P-018 walked an order through pending, processing, on-hold and
completed on WooCommerce 11.1.0 and asked what record survives.

| What exists | Value |
|---|---|
| Structured transition table | **None.** Checked `wc_order_status_history`, `wc_order_status_transitions`, `wc_order_history` |
| Queryable milestones | `date_created`, `date_modified`, `date_paid`, `date_completed` only |
| Per-transition record | An English sentence in an order note: "Order status changed from On hold to Completed." Timestamped, not structured |

So `date_paid` and `date_completed` are the only queryable milestones, and **neither is dispatch or
delivery**. A delivery-time or RTO-rate figure would have to parse note prose.

**US-18-02 `native` is right for displaying a timeline and wrong for measuring one.** The admin
timeline renders those notes perfectly well. ADM-86 is satisfied. Nothing measurable comes out of it.

### The decision

**Record dispatched, delivered and returned as timestamp columns on ENT-10 when the table is built.**

Not a transition log. Three columns. The shipment lifecycle is short and its states are known, so a
general history table would be more machinery than the problem needs, and the three moments that carry
meaning are the ones anyone will ever ask about.

### Which parts are contracted, honestly

This is a judgment call, and the parts of it rest on different ground:

| Column | Ground |
|---|---|
| `delivered_at` | **Contractual.** SHIP-16 records collected versus remitted per order. Outstanding remittance cannot be aged, chased or reconciled without knowing when collection happened, so the pair is not usable without it |
| `dispatched_at` | **Prudence.** Nothing contracted needs it. It is half of every delivery-time figure |
| `returned_at` | **Prudence, with a contractual neighbour.** SHIP-17 makes returned-to-origin a distinct state. The state is contracted, the moment is not |

Only `delivered_at` is defensible as delivering a contracted row. The other two are the same trade as
the cost flag: **near-zero to add while the table is being designed, and permanently unrecoverable for
every shipment closed before anyone notices.**

Three nullable columns on a table already in the build. Recommend adding all three and recording that
two of them were a deliberate choice rather than a contracted requirement, so nobody later mistakes
them for scope that was quietly assumed.

The reports that consume them (**C-RPT-15**, carrier performance, average delivery time, RTO rate)
stay lane two and are charged.

## 6. Lane one and lane two

| Item | Lane | Grounds |
|---|---|---|
| Cost field on product and variation | One | ADM-27, RPT-11, both P1 key |
| Enabling `cost_of_goods_sold` before the first order | One | Native per P-017, but off by default per P-016. Orders placed with it off carry no cost, permanently |
| Basis label on the cost value | One | Costs nothing, and protects the ADM-27 history if OD-12 is ever revisited |
| Search term logging | One | SRCH-08, P1 |
| Order timeline | One | ADM-86, P1 |
| `delivered_at` on ENT-10 | One | SHIP-16 collected versus remitted cannot be aged or reconciled without it |
| `dispatched_at`, `returned_at` on ENT-10 | One by prudence, not by contract | Nothing contracts them. Near zero to add now, unrecoverable later. Recorded as a deliberate choice |
| Carrier cost per shipment | **Two** | Nothing contracts it. Needed only for contribution margin, which the client marked optional |
| RPT-02 profitability and margin reports | **Two** | P2. Priced through MS-CHG-2026-014 |
| RPT-04, RPT-05, RPT-06 customer, promotion, returns reports | **Two** | P2 |

The line to hold: **capture is lane one where a contracted requirement depends on it. Reports are lane
two and are charged.** That is also the strongest commercial position, because it lets us say the data
is being kept at no extra cost and the reports will have full history from launch whenever they are
bought.

## 7. Evidence

Both probes have been run against the Mizzey runtime. WordPress 7.1, WooCommerce 11.1.0, CoreX 0.42.0.

| Probe | Question | Verdict |
|---|---|---|
| P-016 | Does WooCommerce provide a native product cost field? | partial. Yes, and off by default |
| P-017 | Is cost frozen onto the order at sale? | refuted. Yes it is, natively |
| P-018 | Does Woo keep a structured record of status transitions? | refuted. Prose order notes only, no transition table |

Both scripts create their fixtures, assert, and delete them, and P-017 restores the feature flag to
the state it found. The site was checked afterwards and carries no probe product, no probe order, and
the flag reads `no` again.

Recorded in `discovery/data/probes.json` with the environment, so re-running after a WooCommerce
update turns them into a regression check rather than a one-off.

## 8. What to do next

1. Put OD-12 to the client: **purchase cost, single field, entered by roles with financial permission
   through the product screen and the import mapping.** Ask them to confirm, and record the written
   definition their reporting document asks for
2. **Add "cost_of_goods_sold is enabled" to the go-live gate**, and to the Stage 1 environment
   checklist. This is the whole of the cost-history risk now, and nothing about a working store
   reveals that the flag is off
3. Correct Technical Design section 9: ADM-27 is a native field behind a feature flag, not a new
   field. The document is with the client, so route the correction properly
4. Add `dispatched_at`, `delivered_at` and `returned_at` to the ENT-10 design before the table is built

The basis label in section 2 is still worth keeping. It costs nothing and it is what makes the
history unambiguous if the client ever revisits OD-12.
