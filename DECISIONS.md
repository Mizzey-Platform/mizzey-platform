# Mizzey — Decision Log

Record each non-trivial decision (context · decision · why · status).

> **Entries dated before 21 September 2026 were made under Option C**, the Operations Platform, which was never
> signed. They are kept as engineering history and evidence. They are not Option B obligations, and where they
> cite register ids or scope values those are the Option C register's (MS-ANX-2026-001). The Option B register is
> MS-ANX-2026-006 v1.5. Architecture decisions from 22 September 2026 on are also recorded as ADRs in `docs/adr/`.

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

## 2026-09-09 - WPML is the translation plugin, and P-006 confirms the licence buys something

**No translation plugin is named in any client document.** The Technical Design section 11 and the
Statement of Work section 8 both say only "translation plugin", commercial, about 107 USD a year, and
R-14 has the client buying the licence in their own name. The choice was open. It is now WPML, on
Mustafa's judgement that it is the most stable and the most compatible with WooCommerce.

**CoreX does not constrain the choice, and its config implies otherwise.** `.env.example` carries
`MWP_I18N_DRIVER=polylang` and describes it as the "driver behind the I18nHandler abstraction, see
FRAMEWORK §16". There are **zero references to `I18nHandler` anywhere in `plugins/`, `addons/`,
`packages/` or `theme/`**. The abstraction does not exist. Unlike the WooCommerce kit, which the
Technical Design declares honestly as not built, this one reads as a working configuration key with a
default value.

**P-006, run against WPML 4.9.7 with WooCommerce Multilingual 5.5.7 and String Translation:
confirmed.** An English variable product with a global attribute and two variations produced a linked
Arabic product of type variable, carrying both variations and the attribute, with the pair sharing one
translation group and the attribute taxonomy registered in `icl_translations`.

**What it settles and what it does not.** It shows the data model supports translated product data, so
the recurring licence buys something real and the section 11 claim is sound. It does **not** measure
translation quality, and it does **not** test the free alternatives, so it justifies the line item
without proving it is the cheapest way to satisfy FIX-04. If that ever matters commercially, the
comparison is a second probe, not an argument.

**Environment finding.** The stack exhausts PHP's 128M default and dies mid-request. `WP_MEMORY_LIMIT`
and `WP_MAX_MEMORY_LIMIT` are now 512M in the runtime `wp-config.php`, and `run_probes.py` raises the
limit for every probe process. This bears on hosting sizing: the Statement of Work budgets 28 to 88 USD
a month, and a 128M PHP limit is not enough to run this plugin set.

## 2026-09-09 - Product reporting splits by language, and the licence does not fix it

P-019, run against WooCommerce 11.1.0 with WPML 4.9.7 and WooCommerce Multilingual 5.5.7 on the
discovery runtime.

WPML gives every product its own post per language and `wc_order_product_lookup` records the id that
was actually ordered, so one product reaches the analytics layer as two rows. Seeded: an English
product sold 3, its Arabic translation sold 5, and a single-language control sold 6. The lookup table
held two rows for the pair, 3 and 5. Aggregated the pair is the best seller at 8. Split, neither half
beats the control.

**WCML does ship a merge for this**, in `classes/Reports/Products/Query.php`, and it half works. On
one full page it recovered the pair as a single row carrying 8. Three things then went wrong.

**The ranking does not survive.** The ORDER BY runs in SQL before the merge runs in PHP, so the
8-unit row sat at position 2 of 2, behind the control's 6. A best sellers list ranks products on split
figures and then displays merged ones.

**A paginated page reports a wrong number under the right name.** At 2 per page the English original
fell to page 2, so the Arabic half found nothing to merge into, `translateProductTitles` relabelled it
with the original's title, and page 1 showed "P-019 probe product" at 5 units. That is not a missing
figure. It is a plausible wrong one, and nothing on the screen marks it.

**It fires only on `wc-analytics` REST requests**, gated on `WCML\Rest\Functions::isAnalyticsRestRequest`.
Any query Mizzey writes against the lookup tables gets none of it, and E24 is classified partial
precisely because Mizzey writes queries on top of the base.

**What this settles, and what it does not.** RPT-03 best sellers cannot be built on the analytics base
as it stands: the probe drove that exact report and it returned a wrong figure. RPT-01 is order-level and
is not threatened, so US-24-03 is untouched. RPT-10 is split: its dead stock and adjustment figures read
the same product-level path and inherit the same fault, but low stock and out of stock read product stock
rather than the analytics tables, and that query was **not** tested here. Whether WCML's stock
synchronisation makes a translated pair one stock row or two is a separate question and needs its own
probe before US-24-04 is accepted.

**Direction for the build.** Correct it in SQL rather than in PHP after the fact: resolve `product_id`
to its translation group before ordering and paginating, by joining `icl_translations` and grouping on
`trid`. Do it once, in whatever query helper the reporting layer ends up with, so it covers Mizzey's
own reports and not only the Woo screens.

**Lane one, not a change request.** RPT-03 and RPT-10 are contracted and FIX-04 is contracted. A
contracted report that counts one product twice is a defective contracted report, not an extra.

**Not the same trade as RPT-11.** The cost field had to be captured from day one or the history was
gone for good. This one is recoverable at any time, because `icl_translations` carries the language
mapping whenever the order was placed, so historical figures recompute. Nothing is lost by building
the reports in sprints 12 and 16 as planned. What must not happen is the query layer being written as
though the problem is not there.

Recorded on the board at [#194](https://github.com/MustafaShaaban/mizzey-platform/issues/194)
US-24-04 and [#232](https://github.com/MustafaShaaban/mizzey-platform/issues/232) US-24-06.

**A second finding, in the toolchain.** `discovery/run_probes.py` defaults `MIZZEY_WP` to
`C:/wamp64/www/corex/wp`, where WooCommerce is not active and WPML is not installed. The first P-019
run against that default returned a valid JSON object with verdict `partial` and observed
"Probe error: WooCommerce is not active on this site", and the runner wrote it to the row. A wrong
runtime produces a recorded verdict instead of a failure, which is the exact thing this dataset exists
to prevent. Re-running with `MIZZEY_WP=C:/wamp64/www/mizzey/app/wp` gave the real verdict, refuted.
Correcting the default, and making a wrong runtime abort loudly, is booked separately.

## 2026-09-09 - The low stock list counts one physical item twice

P-020, the probe P-019 raised and did not answer. Same runtime: WooCommerce 11.1.0, WPML 4.9.7,
WooCommerce Multilingual 5.5.7. Verdict refuted.

**The root is in storage, not in the report.** `WCML\Synchronization\Component\Stock` copies `_stock`
and `_stock_status` onto every translation. The probe seeded one item with a managed stock of 1 and
`wc_product_meta_lookup` came back holding two rows, 68=1 and 69=1. Two stockable products describe
one physical item, each claiming the full quantity.

**The report then repeats it.** `GET /wc-analytics/reports/stock?type=lowstock`, dispatched as an
administrator, returned both halves of the pair as separate rows, with nothing marking them as one
item. A purchasing decision taken off that list is taken off a double count.

**What the probe could not reach.** The two language views returned identical lists, so no language
scoping ran on this query in an internal dispatch. That bounds the claim: the double count is proved
for the query as `Reports\Stock\Controller` builds it, and the opposite fault is not ruled out for a
browser admin request, where WPML's `posts_where` filter may scope the list to one language and hide
an Arabic-only product instead. WCML removes the All languages option from the analytics switcher, in
`classes/Reports/Hooks.php`, which suggests the admin normally is scoped.

**It does not matter which way it falls.** Both branches come off the same root, and the probe proved
the root. Either the list shows one item as two, or it shows one language and silently omits the rest.
Both are wrong for a report an operator restocks from, and a silent omission is the worse of the two.
One manual check in the browser before US-24-04 is accepted will say which failure the client would
actually have seen, and it changes the wording of the acceptance note, not the work.

**Direction for the build, unchanged from P-019 and now shared by both reports.** Resolve the product
to its translation group before the list is built, by joining `icl_translations` and grouping on
`trid`, so one physical item is one row whatever language the operator is in. This is the second
report to need the same resolution, which settles that it belongs in a shared query helper in the
reporting layer rather than being written twice.

**Lane one.** RPT-10 and FIX-04 are both contracted. Recorded on the board at
[#194](https://github.com/MustafaShaaban/mizzey-platform/issues/194).

## 2026-09-09 - Gutenberg is the mechanism for SSC, and the page-builder part is not contracted

Mustafa's instruction, 9 September 2026: the home page and the static pages are built in Gutenberg,
every component is a block, and the client controls the home page including header, footer and mega
menus.

**Most of that is already contracted, and it is contracted as an outcome, not as a mechanism.**
SSC-01 puts every storefront text, image and link under admin control with nothing a customer reads
locked in code. SSC-02 covers home page sections including their order, SSC-03 show and hide without
deleting, SSC-07 header navigation and mega menu structure, SSC-08 footer content links and columns,
SSC-10 static and policy pages, SSC-11 campaign landing pages built without a developer, SSC-12 all of
it independently in both languages, SSC-13 preview before live. CMS-01 to CMS-10 carry the static
pages and CMS-09 requires them editable without a developer.

**So choosing Gutenberg blocks to deliver those rows is a build decision, not a scope increase.** No
Annex A row names a mechanism, so the mechanism is the developer's to pick, and picking blocks costs
the client nothing extra. Post-Signature-Build-Notes item 1 keeps "a Gutenberg block editor for
content pages, with colour customisation" out of the register, and that still holds in the sense that
matters: no story, epic or ticket is opened called Gutenberg. The stories stay the SSC and CMS ids and
blocks are how they are satisfied. The two-lanes rule is about what is owed, not about how it is built.

**Three things in the instruction do exceed the register, and they are the expensive ones.**

- **Per-block presentation control**: style, rows, column count, limits on a product block. SSC-02
  contracts section content, images, calls to action and order. It does not contract layout controls.
- **Layout direction toggles**, for example swapping an image-right text-left section to the reverse.
  Nothing in Annex A contracts it.
- **Editor canvas parity with the front end**, "like Elementor and Bricks". This is the big one.
  SSC-13 contracts **preview before a change goes live**, which WordPress preview satisfies. A live
  WYSIWYG editing canvas that renders the theme's real output inside the editor is a different and far
  larger thing, and no row asks for it.

Together those three are a page builder. The register does not price one.

**Why this cannot be absorbed quietly.** Stages 4 and 5 already run at 68 and 92 points a week against
a 55 average, which is why nine stories were pulled forward into Stage 2. Block work of this grade
lands in E22, Self Service Storefront Control, which is one of the five custom epics where WooCommerce
contributes nothing. Absorbing an unpriced page builder into the two stages that are already the worst
loaded is how a fixed-price engagement loses its schedule.

**Recommendation, not a decision.** Build SSC and CMS in Gutenberg with well-made blocks: that is
free, it is better than option panels, and it is the right call. Treat the three items above as a
priced change under MS-CHG-2026-014, or as a deliberate goodwill extra decided once the contracted
work is under control, which is exactly the position Post-Signature-Build-Notes section 3 takes.
What must not happen is acceptance criteria quietly appearing for a page builder inside the same
290,000 EGP.

**One technical warning, recorded now because it is cheap now and expensive later.** A layout direction
toggle fights the Arabic mirroring. FIX-04 and NFR-04 put the storefront in RTL, where the whole layout
flips, so an image-right block in English is image-left in Arabic before any toggle is applied. A
reverse control has to mean "swap the logical order", not "put the image on the right", or every
toggled section breaks in one of the two languages. Any block built with a direction control needs
that decided in the block's data model, not in its CSS.

**Effect on the discovery pass.** The E22 verdict agent was told to classify against the register as it
stands and to record any story that appears to need a block editor in `open`, which will surface this
tension as evidence rather than as an assumption. That instruction stays.

## 2026-09-09 - The plugin register closes at 107 USD, and one need did not survive contact

All six plugin needs decided. Every version, date, install count and price below was read from the
WordPress.org plugin information API, the vendor's own pricing page, or the plugin installed on the
discovery runtime, on 9 September 2026. Nothing was recalled.

| Need | Decision | Annual |
|---|---|---|
| PL-01 translation | WPML Multilingual CMS | 107 USD |
| PL-02 Google sign in | Nextend Social Login and Register | nil |
| PL-03 redirects | Redirection | nil |
| PL-04 admin 2FA | WP 2FA | nil |
| PL-05 product feeds | Product Feed Manager for WooCommerce (CTX Feed) | nil |
| PL-06 campaign attribution | No plugin. WooCommerce is native | nil |

**Total 107 USD a year against the about 107 USD in Technical Design section 11.** The register does
not exceed what the client has been told, and nothing needs acknowledging as an overage.

**PL-06 did not survive being asked about.** P-021: WooCommerce 11.1.0 ships an order attribution
controller with the feature flag on by default, recording sixteen fields including utm_source,
utm_medium, utm_campaign, utm_content, utm_term and utm_id onto the order. The probe wrote them to a
real order, re-read them from storage and selected orders by campaign, so the data is queryable and
not merely captured. MKT-09 needs no plugin. Section 7 row 18 groups feeds and attribution and
classes the pair Plugin plus Extend: it is right about the feeds half and wrong about this one.
Section 11 never listed attribution as a cost, so nothing the client was told changes.

**The one commercial thing worth raising with the client. The 107 USD figure is a conversion, and the
obligation is in euros.** wpml.org lists Multilingual CMS at 99 EUR a year, and that is the lowest
tier including WooCommerce support, which this build needs. So what the client actually owes is
99 EUR annually, and the 107 USD printed in the Technical Design will drift with the exchange rate
in either direction. It is not wrong today and it was not wrong when written. It is simply not a
dollar obligation, and a client budgeting in EGP should be told the currency it is really in. The
renewal price is not stated on the vendor's purchase page and is deliberately left unverified rather
than guessed.

**Two risks recorded on the free rows rather than smoothed over.**

Nextend's own comparison chart marks WooCommerce integration as Pro only: the free version places
buttons on the WordPress login page and on forms using the wp_login_form action, while automatic
placement on WooCommerce forms sits in a Pro addon priced at 49 EUR as a one time payment, not an
annual licence. Mizzey builds the account templates under SSC-01 anyway, so placing the button by
hand is plausible, but it is unverified and it is the risk on PL-02. Section 11 promised the client
"nil, or low" here, and a 49 EUR one off is still inside that phrasing.

Neither two factor candidate declares support for WordPress 7.1, which is the runtime version.
WP 2FA is tested to 7.0.4 and Two Factor to 6.9.7. WP 2FA was chosen on maintenance recency, and
compatibility has to be verified before ADM-131 is accepted, which matters more than usual because
this one is a security control.

**One decision deliberately deferred.** PL-03 went to Redirection, which is single purpose, has two
million installs, is tested to 7.1 and states plainly that it has no premium version. Rank Math would
also satisfy MKT-13 and MKT-14 from its free tier, and would additionally answer the question E23
raised about which plugin owns the SEO fields the catalogue migration imports at US-23-12. That
question is unanswered, and adopting an SEO suite for its redirect feature is the tail wagging the
dog. If Rank Math is later chosen for SEO, PL-03 should be revisited and dropped rather than running
two plugins that both manage redirects.

**Test change, recorded because it was a test change and not a data change.**
`test_nothing_is_decided_yet` asserted that no plugin need had a decision. That could only pass
before this pass ran, so it was a seed guard rather than an invariant. It is replaced by two checks
that outlive the seed and that the gate does not make: a decision must name one of the candidates the
row actually weighed, and a decided row must have weighed at least two options, with PL-06 exempt
because researching a second plugin for a problem nobody has is not diligence. The PL-01 candidate
test was loosened from equality to membership; its intent was never "exactly one candidate", it was
that the figure already in the client's hands is on the table to be beaten.

## 2026-09-09 - Block architecture and the ERP position

Verified against the running install rather than assumed, 9 September 2026.

**The theme is already a block theme.** `wp_is_block_theme()` returns true on the active
`mizzey-theme`, which carries `theme.json` version 3, a `templates/` directory and a `parts/`
directory. Full Site Editing is therefore available now. That matters commercially: SSC-02, SSC-03,
SSC-07 and SSC-08, the home sections, show and hide, header and mega menu, and footer, can be
delivered through the Site Editor rather than through custom admin screens. The mechanism is free.
What is not free is the per-block presentation control and the editor canvas parity recorded in the
Gutenberg entry above.

**`theme.json` is empty.** Version 3, `appearanceTools` on, and then nothing: zero colour palette
entries, zero font families, no `styles` block. So the site-wide font, the unified colour palette and
the text colour control are all natively available and none of them is defined yet. Populating
`theme.json` is the whole job, and it aligns exactly with the contracted deliverable, which is
working HTML and CSS with design tokens. `theme.json` is the token file. This is cheap work with a
high return and it should happen before any block is written, because every block then inherits it.

**PHP or JavaScript blocks is not a choice, it is a split.** Every block needs a JavaScript `edit`
component, because that is the editor interface and there is no other way to provide one. The front
end should be server rendered PHP. Evidence: all 175 WooCommerce blocks registered on this install
are dynamic, every one of them carrying a `render_callback`. Server rendering is also what the rest
of this dataset demands, because saved static markup is not translatable by WPML and not queryable
for the reporting corrections P-019 and P-020 force.

**Cart and checkout are already blocks, and that is not the same as editable.** WooCommerce ships
`woocommerce/cart` and `woocommerce/checkout` with granular inner blocks: billing address, contact
information, fields, order note, order summary and its sub blocks, express payment, actions. They
extend through a narrow documented API rather than free editing. Checkout is the hardest surface in
WooCommerce to customise, not the easiest. Nothing should be said to the client that implies the
checkout is theirs to rearrange.

**ERP. INT-14 is P3 and is not in this release.** The Feature Register puts ERP and accounting
synchronisation at P3 with no stage, and the exclusions table carries advanced ERP synchronisation
at 12 and 16.4. Mustafa confirmed on 9 September that a client meeting raised it and that it is
outside the agreed scope. It does not become a story and it does not get a ticket.

**But the architecture that makes it possible later is contracted, at INT-16, P1 and marked key:**
every integration behind an adapter with retries, logging and webhook handling. So accounting for the
ERP now costs nothing extra if INT-16 is honoured properly rather than treated as boilerplate. Two
concrete build rules follow: keep every stock mutation on one path so a webhook can be attached to it
later without hunting call sites, and keep the payment and carrier adapters free of provider specific
assumptions leaking into checkout, which is US-08-01.

**Webhooks are native and broader than expected.** WooCommerce accepts webhook topics of the form
`action.woocommerce_*` and `action.wc_*`, so any WooCommerce action hook can fire a webhook, on top of
the resource topics for order, product, customer and coupon. Three hooks are explicitly blocked:
`woocommerce_login_credentials`, `woocommerce_product_csv_importer_check_import_file_path` and
`woocommerce_webhook_should_deliver`.

**The risk to record while it is cheap.** Woo webhook delivery is a log plus automatic deactivation
after repeated failures. It is not a guaranteed delivery queue. An ERP that must never miss a stock
change needs a durable queue with replay in front of it, and that is a design decision for whenever
INT-14 is actually commissioned, not something the P1 adapter work silently owes.

## 2026-09-22 - Option B engineering governance foundation

**Context.** The client chose Option B, the Launch Platform, on 21 September 2026. The repository still described
Option C. Its agent instructions pointed at a `develop` branch that does not exist, and its PR template promised an
audit trail that Option B excludes (ROLE-10). The repository was transferred to `Mizzey-Platform/mizzey-platform`
the same day, with history intact.

**Decided (approved by Mustafa as G-1, G-2 and G-3):**

- Spec Kit 0.10.1 is the only system for specs, plans and tasks. The constitution is at
  `.specify/memory/constitution.md` and inherits CoreX v1.2.2. Task-to-issue generation is disabled, and so are the
  agent-context hooks.
- Scope traceability: `docs/scope/register-ids.json` is generated from register v1.5 (754 ids), and its source hash
  is recorded. A register id is necessary but not sufficient.
- Work categories: `requirement`, or internal (`governance`, `ci`, `test-infrastructure`, `tooling`,
  `security-maintenance`, `documentation`). Internal work needs no register id and may not add client-facing
  behaviour.
- Contradictions are recorded in `docs/scope/open-items.json`, never resolved in code. CX-01 (the Accountant role)
  is open.
- Versions are recorded in `stack.lock.json`, with an upgrade regression path in `docs/stack-and-upgrades.md`.
- CI reports on every PR. On the free plan it cannot block a merge. The local pre-push hook is advisory.
- ADR-0001 (native Cost of Goods Sold) and ADR-0002 (minimum shipment record) are proposed, not accepted.
- The Option C board scripts are retired behind a guard. The 9 Sep entries above stay as history.

**Not decided here:** D-03 (removing `docs/engagement/`) is a separate PR. D-07 (the 232 legacy issues), the Option B
Project, the backlog and a paid plan are deferred.

## 2026-09-22 - PR #235 corrective pass: the governance exception is closed by path policy

**Context.** The PR #235 review package (REVIEW.md, findings F-1 to F-12) and ChatGPT's independent test of the
checker showed that internal work could add client-facing code outside `mizzey-site/` and `mizzey-theme/`. It could
also change build tooling or version locks, and use free-text security claims. The checker accepted an invented id
next to a real one in a criterion, and P1-E criteria marked `approved` or `finalized` before PRE-09. Approved as a
single corrective pass on the same branch.

**Decided:**

- Every added, modified, renamed and deleted path is classified by an explicit allow-list (`PATH_POLICY`), and
  each work category may touch only its path classes. Unlisted paths, including new top-level directories, fail
  for everyone until the policy is extended by an `internal:governance` PR.
- Governance-sensitive paths need `Sensitive changes:`. Delivery-affecting paths need `Delivery impact:`.
  `security-maintenance` needs `Security evidence:` (CVE, GHSA, advisory URL, or a local defect with a regression
  test) and `Security change:`. A declaration makes a change reviewable. It does not prove it safe.
- Every token in a criterion's Traces must be a traced register id. Statuses are exactly `final`, `provisional`,
  or `pending CX-nn / PRE-09 / OD-nn`. P1-E criteria are never `final` while PRE-09 is open.
- A duplicated PR field fails. Feature specs are never deleted or moved. A new spec's directory must match the
  branch name.
- `tools/run_trusted.py` runs the base branch's checker next to the PR's own. PR #235 is the bootstrap, because
  `main` has no checker yet. This does not prevent a PR from editing the workflow, and the free plan still enforces
  nothing server-side.
- Spec Kit 0.10.1 does not create git branches here. The branch comes first, from the dry-run name, and
  `SPECIFY_FEATURE_DIRECTORY` is passed explicitly. `.specify/feature.json` is ignored. The agent-context
  extension and skill are removed.
- The authority model follows Agreement MS-AGR-2026-023 section 2.1. The Agreement governs; Annex A (the Feature
  Register) defines contracted scope; specs and ADRs implement and cannot override.

**Unchanged:** ADR-0001 and ADR-0002 remain Proposed. CX-01 and PRE-09 remain open.

## 2026-09-22 - D-03: Option C contract copies removed from the tree

**Decided (approved by Mustafa):** the 35 files in `docs/engagement/` are removed from the current tree: 17
markdown sources and 18 PDFs of the unsigned Option C pack, about 14 MB. Git history is not rewritten. The files
stay in every commit before this one, and in the full-history bundle at
`C:\Mizzey-Backups\mizzey-platform\2026-09-22\`.

**Checked first:** no script, tool, test or CI step reads those files. The remaining mentions are historical text
(this log, `scripts/README.md`, and a string inside the retired `scripts/make_issues.py`) and are left as written.
The 232 Option C issues and Project #4 still name the old paths. They are not edited. `tools/house_rules.py`
still blocks anything new under `docs/engagement/`.

## 2026-10-04 - D-04: the product-cost pilot is closed, and the identity risk becomes a workstream

**Decided:** the pilot `specs/001-product-cost-capture` is complete and closed. PR #237 was squash-merged on
4 October 2026 as `93d647b`, from the reviewed head `4068b32`, whose tree it matches exactly. It is not
reopened. **ADM-27 and RPT-11 are technically verified by the pilot; contractual acceptance remains pending CX-01
and OD-12.** Neither is described as fully delivered anywhere while those criteria are open: merged code can be
complete while acceptance is not. No margin or profitability reporting is built under it (RPT-02 is P2), and no general catalogue
synchronisation infrastructure is added. Later work that touches product cost cites the closure record in
`specs/001-product-cost-capture/verification.md` instead of rediscovering it.

Three states, reported separately as the constitution requires: **workflow pilot completed**, **unblocked
technical functionality verified** (AC-1 to AC-5, clean baseline, 12 scenarios, 0 failed), **contractual
acceptance pending client decisions** (CX-01 for AC-6, OD-12 for AC-7).

**Two corrections recorded with the closure:**

- **MIG-13 carries a required migration precondition, not a note.** Product costs are imported before translations
  are created, unless the migration implementation explicitly resolves the canonical source-language product or
  variation when duplicate SKUs exist. A WPML duplicate is created with its original's SKU, so one SKU identifies
  two products and `wc_get_product_id_by_sku()` returns whichever the query reaches first. A cost import run after
  translations exist can therefore address the translation, where the ownership rule refuses the value: the import
  reports success and the cost does not change. It cannot corrupt data, because the pair agrees either way. It
  breaks the expectation that an import changes a cost. Carried into the migration runbook and a migration test,
  not left in a spec.
- **The Store API is not a cost write channel.** The WooCommerce REST API (`/wc/v3`) is the administrative product
  write channel and was tested as one. The Store API (`/wc/store/v1`) is the customer-facing read surface and was
  used only to verify that cost is not exposed. One sentence in `spec.md` had listed them together. Corrected, with
  an explicit table, so no later feature treats the two as interchangeable.

**Decided:** the multilingual identity risk the pilot exposed is handled as a cross-feature workstream rather than
as an extension of the cost feature. `docs/2026-10-04-multilingual-data-integrity-workstream.md` classifies six
areas (identity, stock, ERP identity, orders, reporting, returns) as verified working, verified defect, not yet
tested, requires ERP clarification, requires client clarification, or future and Option C only, and maps each item
to register ids and to one of five kinds: contractual functionality, developer-quality safeguard, verification or
probe, client or ERP decision, future capability. **A risk does not create a feature**: where the behaviour behind
a risk is outside Option B (RPT-02, RPT-03), the risk is recorded and nothing is built.

**The reason it is a workstream and not a second cost fix.** The cost defect had two causes, and neither is
specific to cost. First, `WC_Product_Data_Store_CPT::update()` skips `wp_update_post()` on a meta-only save, so
`save_post` never fires and WPML and WCML never copy the field; any copied field is exposed through the same
channels. Second, one commercial item is two WordPress posts with their own meta rows, which already splits a
product report (P-019) and double-counts a stock report (P-020). The highest-priority consequence is untested and
is recorded as a hypothesis, not a finding: `WCML\Synchronization\Component\Stock` copies `_stock` on `save_post`
while order stock reduction goes through `wc_update_product_stock()`, which issues arithmetic SQL. If that write
does not fire `save_post`, each language version holds its own quantity and one physical unit could be sold twice.
That probe (item B1) is the first work after the merge.

**Decided:** planning moves to Option B delivery, built from the register's 595 delivery rows (P1 435, P1-L 117,
P1-E 19, DLV 24) grouped into epics that follow the register's own sections, with vertical PBI slices.
`docs/2026-10-04-option-b-backlog-structure.md` is a proposal for review. Nothing is created from it: no Project,
no issues, no specs. DEF, P2, P3 and OUT are not delivery items. Option C material is historical reference.

**Decided:** the ERP identity constraint is settled by us, before the ERP meeting, because it follows from ERP-01
rather than from the ERP's interface: the Arabic and English versions of a product are **one** ERP stock item,
never two, and the mapping is held once against the source-language element and resolved through WPML identity.
What the meeting decides is which key to map to. The question set is
`docs/2026-10-04-erp-technical-meeting-questions.md`, covering authentication, API availability, identifiers,
lookup, availability, reservation, decrement, restoration, idempotency, errors, limits, downtime, environments,
logging, reconciliation ownership and acceptance criteria. The integration is not expanded beyond stock: a
proposal to add order, customer, price or accounting synchronisation is routed to Change Control, not agreed at
the table.

**Unchanged:** ADR-0001 and ADR-0002 remain Proposed. CX-01 and PRE-09 remain open. No P1-E row receives final
acceptance criteria while PRE-09 is unapproved.

## 2026-10-04 - D-05: correction pass on the Option B planning package

**Decided (from review of the three planning documents):** the package is corrected before it becomes the
authoritative delivery plan. Five corrections, all of them to records rather than to code.

**The delivery-row total is 595, and one hand count caused the contradiction.** The first draft of the backlog
gave a scope summary of 595 and an epic table totalling 594, with a DLV column of 23. Recomputed from
`docs/scope/register-ids.json`: P1 435, P1-L 117, P1-E 19, DLV 24, which is 595. The cause is a single series:
the pre-development deliverables are labelled PRE-01 to PRE-09, which reads as nine rows, but the register splits
PRE-03 into **PRE-03a** and **PRE-03b**, so the series holds ten ids. The epic table and the DLV composition
sentence both inherited the nine. Nothing else was wrong: the Part Three sections account for 573 delivery rows
and Part Seven for the remaining 22. The total was not chosen between 594 and 595; it was recomputed.

**A check now stops it recurring.** `tools/tests/test_backlog_totals.py` recomputes the per-scope delivery totals
from `register-ids.json` and fails if the scope summary, any epic row, the epic column sums or the Total row
disagree with it or with each other. Eight cases, including the exact drift that occurred, a row whose columns do
not sum, a wrong scope summary and a reissued register. It runs through the tooling unit-test step CI already has,
and it reproduced the 594-against-595 failure before the correction, which is how it was confirmed to work. It
lives in the test rather than in a `tools/` script because a `requirement` PR may touch `tools/tests/` and not
`tools/*.py`; that was verified against the checker, not assumed.

**Commercial pricing is out of the engineering backlog.** The sentence naming Option B's fee is removed and not
replaced with another figure. Fees, invoices and signatures are not needed to build the software, and the backlog
covers scope, stages, dependencies and acceptance only.

**Three wording corrections, each one a distinction worth keeping:**

- **Technically verified is not contractually accepted.** Every place the backlog called ADM-27, RPT-11 or the
  product-cost feature "delivered" now reads: technically verified by the product-cost pilot, contractual
  acceptance pending CX-01 and OD-12. Merged code can be complete while acceptance is open.
- **Stock integrity is partly measured, not wholly unverified.** The workstream had opened that area by saying
  nothing in it is verified, which was wrong in both directions. Corrected to state the three things separately:
  translated records hold separate stock rows (measured, P-020), the low-stock report double-counts one physical
  item (measured defect, B10), and reduction and restoration across translations are not yet verified (B1, B7 to
  B9, the largest unverified risk).
- **A10 is a measured anomaly in the path that was tested, not a contracted storefront defect.** The Arabic parent
  of a translated variable product was observed registered `simple` with no variations, with translations created
  from WP-CLI, which is the only path tested. The wp-admin translation workflow has not been tested (A11), and
  that is what decides the final classification. The finding is not softened or hidden; it is scoped to the path
  it was measured in.

**Decided:** the ERP identity rule separates an invariant from a proposal. The invariant is mandatory and follows
from ERP-01: the Arabic and English translations of one commercial item must resolve to the same single ERP stock
item, and must never become two ERP stock balances. The meeting determines the external key; it does not get to
redefine the invariant. The proposal, which is an architecture position and not a contractual requirement, is to
resolve the canonical commercial item through WPML translation identity, maintain one canonical ERP mapping per
commercial item, and have translations resolve to it. **Where that mapping is physically stored is an architecture
decision to finalise with PRE-09 and the implementation design**; holding it on the source-language record is a
candidate, and a mapping table keyed by translation group would satisfy the invariant equally. ERP-01 requires the
single balance, not a storage location, and the earlier draft overstated it.

**Decided:** ERP acceptance criterion 11.5 is conditional, because PRE-09 has not chosen the reservation and
decrement timing. If stock has already been reserved or decremented, a failed payment or cancellation releases or
reverses that effect exactly once, linked to the original so a repeat is refused. If no stock mutation has
occurred, no compensating mutation is sent. Either way the state is reconcilable per order. Writing it
unconditionally would have asserted a decision nobody has made.

**Decided:** the ERP question set gains a fifteen-item "Must answer in the meeting" section, ahead of the full
questionnaire, which stays as the appendix and working checklist. The fifteen are the decisions that can change
the architecture or the schedule: ERP-side development, authentication and connectivity, test environment, the
canonical item and variant key, key uniqueness and stability, stock value semantics, lookup mechanism and
freshness, reservation support, decrement mechanism and timing, partial success, idempotency, timeout outcome
lookup, concurrent last-unit behaviour, reversal mechanism, and ownership of reconciliation.

**Next technical work, in this order and no other:** probe **B1** (multilingual stock reduction), then **A12**
(which fields WPML and WCML synchronise on `save_post`). Both are specified in
`docs/2026-10-04-multilingual-data-integrity-workstream.md`. **No stock synchronisation fix is implemented until
B1 has measured the actual behaviour**, and no fix is created for a field A12 finds outside Option B.

**Unchanged:** ADR-0001 and ADR-0002 remain Proposed. CX-01, OD-12 and PRE-09 remain open. No P1-E row receives
final acceptance criteria while PRE-09 is unapproved. No GitHub Project, no bulk issues, no Option C issue
closures, no ERP implementation.

## 2026-10-04 - D-06: repository private again, and what B1 and A12 measured

**Decided:** the repository is **private** again. It had been made public for external review; that is complete,
and the visibility was reversed before any further delivery work. Confirmed by two independent reads. 0 forks,
0 stars, network count 0.

**Not decided, and deliberately not done: the history purge.** `docs/engagement/` was removed from the tree by
D-03 but remains in 57 of the repository's 60 commits, so the engagement pack was reachable while the repository
was public. Nothing has been rewritten, nothing force-pushed, and the backup bundle is intact. The assessment is
`docs/2026-10-04-public-exposure-assessment.md`, and it was measured rather than assumed:

- 35 files, 13.7 MB, of which 8 are commercial or signed.
- The two SIGNED PDFs each hold exactly one embedded image, the same signature graphic, and three counterparty
  form fields (`name`, `title`, `date`) that are **empty in both**. The client never signed; Option C was never
  executed.
- No national ID, passport number, phone number, email address, account number, IBAN or SWIFT reference appears in
  any text layer of the Agreement, Statement of Work or Invoice.
- No private key, certificate or credential was ever committed, in any commit.
- **The one genuine sensitivity is the developer's own signature image.** Everything else is a superseded,
  unsigned engagement's commercial terms and the client's specification material.

A purge would rewrite 57 commits, break the commit links in 232 historical Option C issues and in five merged PR
records, require every clone to be re-cloned, and still not un-publish what was already fetched. The D-03
"preserve history" position is therefore **reconsidered and left open for a separate explicit approval**, with a
narrow option (the two signed PDFs only) recorded beside the full one. **Recommended regardless of that decision:
rotate the signature asset**, since a publicly reachable signature graphic should be treated as compromised for
future use.

**Measured, probe B1 (`t17-multilingual-stock.php`): the hypothesis was refuted, and a different defect found.**

The pre-probe hypothesis was that stock shared the cost gap. It does not. `WCML\Synchronization\Hooks::syncProductStock`
is registered on `woocommerce_product_set_stock` and `woocommerce_variation_set_stock`, which fire on the
arithmetic stock write itself, so stock never depended on `save_post`. **Simple products hold one effective
balance**: either language's order reduces both records, a cancellation restores both, and the last unit cannot be
sold twice. **Variations behave the same while their translation group is intact**, verified in eight sequences.

What was found instead: **creating any new product after a WCML translation of a variable product, in the same
process, corrupts the Arabic variations.** Reproduced deterministically. WPML associates the new post with the
Arabic variations' group, WCML copies the new product's title onto them (visible Arabic content corruption), and
the first save of such a variation **rewrites its `icl_translations` row** into the neighbouring trid, read
directly from the database as `db=71` before and `db=70` after. From then on stock does not synchronise in either
direction, and **overselling becomes possible**, for variations only.

This is the same defect class the cost pilot found, and `CostTranslationSync::noteCreation()` is the guard that
protects cost from it. WCML's own synchronisation has no equivalent guard. **Production relevance:** the trigger
needs several product creations in one process, which a human in wp-admin does not do but **a catalogue migration
or CSV import does**. It therefore blocks **E-MIG** and is a second, more serious reason for the MIG-13
precondition than cost was.

**Measured, probe A12 (`t18-synced-fields.php`): the cost gap is not unique, and price is in it.**

One field at a time on the `crud-cli` channel, 14 fields, both product types. **Nine of fourteen do not reach the
Arabic record**: `regular_price`, `sale_price`, `sku`, `manage_stock`, `weight`, `length`, `catalog_visibility`, a
custom field, and `product_cat`. Three do: `cost` (only because `CostTranslationSync` exists), `stock_quantity`
(WCML hooks the write, not `save_post`), and `status` and `name` for products (a post field changed, so `save_post`
fired). For variations, `status` did **not** follow even though `save_post` fired. A SKU change on a translated
variation is **refused** by WooCommerce, because the duplicate holds the same SKU.

Thirteen of WCML's fourteen synchronisation components run from `save_post`; only Stock does not. That is the
shape of the problem, and it is now bounded rather than suspected.

**The highest-severity finding is `regular_price`.** It is the same mechanism as cost, on the field the customer
pays: an Arabic customer would be shown, and charged, a stale price after a code-level price change.

**Decided: no fix is implemented from either probe in this checkpoint**, and no general multilingual
synchronisation framework is built. Each finding attaches to the PBI that owns the behaviour: price to the pricing
slices, the group corruption to E-MIG and the translation workflow (US-16-03, A10, A11), dimensions to shipping,
visibility to the catalogue admin. `manage_stock` is recorded for PRE-09 because ERP-07 is P1-E. `product_cat`
needs interpretation before it is called anything, since WPML translates taxonomies as their own elements, and a
custom field not being copied is a configuration rule rather than a defect. Neither created work.

**Decided: three questions are added to the ERP meeting's must-answer list** (M16 to M18), from these results: how
a SKU change is handled at all if the SKU is the ERP key, given it is refused on a translated variation and does
not propagate on a simple product; whether the ERP holds stock per variant, which is the level where the
translation group was measured to detach; and what the ERP expects when `manage_stock` differs between language
versions. The invariant is unchanged: one commercial item resolves to one ERP stock item, and the meeting
determines the external key, not the invariant.

**Decided: the delivery board stays a proposal.** `docs/2026-10-04-github-project-proposal.md` sets out the
fields, views, two issue templates and the first fifteen PBIs with every field filled, and records what B1 and A12
changed in the backlog: a new first PBI for the price gap, a data-integrity dependency and a new PBI on E-MIG,
A11 promoted ahead of the migration, and the removal of anticipated work on the stock mechanism, which needs no
fix. No Project, no fields, no views, no templates and no issues are created. Project #4 and the 232 historical
Option C issues are untouched.

**Unchanged:** ADR-0001 and ADR-0002 remain Proposed. CX-01, OD-12 and PRE-09 remain open. ADM-27 and RPT-11 are
technically verified and not contractually accepted. No P1-E row receives final acceptance criteria. No ERP
implementation.

## 2026-10-04 - D-07: the exposure decision, A11, the price verdict, and the delivery board

**Decided (approved by Mustafa): the Git history is not rewritten.** No force-push, no purge of historical blobs.
The backup bundle stays intact and the repository stays private. The reasoning is the assessment in
`docs/2026-10-04-public-exposure-assessment.md`: no client signature, no client personal identity data, no banking
data, no credentials, no private keys and no certificates were in the historical engagement material. A rewrite
would change most of the repository's commits, break historical links and clones, and could not undo any copy
already fetched during the public window. **The purge stays documented as a future explicit decision only**, to be
revisited if legal or business requirements change.

**Decided: the old embedded developer signature image is exposed and deprecated.** It is not reused in any future
generated signing pack. No replacement signature is generated or imitated; when a new signature asset is needed,
Mustafa supplies it separately. The brand-render signing step must not reuse the deprecated asset.

**Measured, A11 (`t19-translation-group-integrity.php`): a same-process sequencing defect, and sequencing alone
prevents it.** Seven workflows compared, with the translation group read from `icl_translations` directly:

| Workflow | Result |
|---|---|
| WCML editor translation, then a product created in the same process | **CORRUPTED** |
| The same with nothing created afterwards | intact |
| A migration-shaped batch of ten products afterwards | **CORRUPTED** |
| Interleaved create and translate | **CORRUPTED** |
| **All sources created first, translations second** | **intact** |
| **WPML duplicate** instead of the WCML editor | **intact** |
| **The native CSV importer** creating the later products | **intact** |

Four conclusions. It is **not** a general wp-admin defect, because the admin is one request per save and the
nothing-afterwards case is intact; the classification stays **Partial / workflow-dependent** and is not
generalised. It needs the **WCML translation editor** specifically. The **native importer does not reproduce it**,
so the contracted import path is not the danger; a scripted migration using WooCommerce CRUD is. And **sequencing
alone prevents it**, so the correction is a **migration runbook invariant, not runtime code**.

The damage is wider than B1 could see: the Arabic parent loses its `icl_translations` registration entirely, the
Arabic variations' titles are overwritten with the later product's name, **their attributes are wiped** so the
variation can no longer be selected, variations detach from the English parent, and stock stops synchronising in
both directions. That is a direct failure of **MIG-02** ("Arabic content is not corrupted" by the import) and
**SSC-21** ("product content maintained separately in English and Arabic"), which is why the outcome owns a
requirement PBI (#247) while the probe itself stays `internal:test-infrastructure`.

**Measured, the price matrix (`t20-price-integrity.php`): every launch-supported path is already correct, so no
custom code is justified.** Six channels, both price fields, both product types, including the amount an Arabic
order actually charges:

- **wp-admin product form (ADM-25, ADM-26), wp-admin variations AJAX (ADM-33), the native CSV importer
  (MIG-13): all FOLLOWED**, both regular and sale price, and the Arabic order charges the new price.
- **The scheduled-sales cron: no divergence.** Tested specifically, because it is the one price write a store
  makes without anybody scripting anything. A sale and both dates were set in one wp-admin submission, the end
  date was then moved into the past by a raw meta write on both records so the cron was the only product-object
  save, and afterwards both agreed. The expiry is evaluated per record at read time from each record's own
  `_sale_price_dates_to`, which WCML copies on the admin save.
- **REST `/wc/v3`, WP-CLI CRUD and front-end or cron code: stale Arabic price, and the Arabic order charges the
  old amount.** None of these is a contracted price-maintenance path, and **ADM-28, scheduled price changes, is
  P2**, so no contracted feature performs programmatic bulk price updates either.

**Decided: no price synchronisation is built, and no price requirement PBI is created.** The native-first rule
applies as written. The residual risk is recorded as a development rule instead: any code that writes a product
price must go through a path that fires `save_post` or synchronise the translation explicitly. That attaches to
whichever slice ever writes a price programmatically; it is not a PBI and not a class built for a caller that does
not exist. `CostTranslationSync` is not reused, and no general multilingual metadata synchronisation framework is
created.

**Decided: a risk or a probe does not create a contractual PBI.** Applied in both directions this round. A11 the
probe is verification, attached to the catalogue and migration work; the outcome it protects is a requirement PBI
because MIG-02 and SSC-21 independently oblige it. The price gap creates **no** PBI, because no contracted path is
defective and inventing one would be inventing scope.

**Decided: the Option B delivery board is created**, organisation-owned and private, at
https://github.com/orgs/Mizzey-Platform/projects/1, titled **Mizzey Option B Delivery**. The suggested title
carried an em dash; `tools/house_rules.py` forbids one in added markdown, and CI caught it on the first push, so
the board is named plainly rather than having the document describe one name and the board use another. Eleven custom fields and six views. **Status separates
Verified from Accepted** in its own option descriptions, and #252 (product cost) is the reference case: Verified,
not Accepted, carrying CX-01 and OD-12 to the Stage 1 gate. The first **15** PBIs are seeded, #241 to #255, and no
more; the remaining 45 to 55 wait for review. Project #4 and the 232 Option C issues are untouched, verified after
creation.

**A governance lesson worth keeping: cite exact register ids, never a range.** The first seeding used ranges, and
a check against `docs/scope/register-ids.json` caught **IA-36 (P2)** and **CHK-13 (P2)** inside them. Expanding
the ranges exposed three more leaks: ORD-09 to ORD-11 (DEF), ROLE-03 to ROLE-06 and ROLE-10 (DEF) with ROLE-06a
(P2) and ROLE-11 (P3), and PDP-20 with PDP-23 (P2). All corrected; each issue now names its excluded neighbours
and their scope. 131 cited ids were re-validated: all real, all delivery scope, no ranges left. A range is how
non-delivery scope gets into a delivery board.

**Unchanged:** ADR-0001 and ADR-0002 remain Proposed. CX-01, OD-12 and PRE-09 remain open. ADM-27 and RPT-11 are
technically verified and not contractually accepted. No P1-E row receives final acceptance criteria. OD-41, OD-42
and OD-11 stay settled and are not reopened. No ERP implementation.
