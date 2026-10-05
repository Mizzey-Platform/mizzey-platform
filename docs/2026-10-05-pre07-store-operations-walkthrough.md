# PRE-07: store operations walkthrough, ready-to-run package

5 October 2026. Owner PBI: #267.

**Status: the walkthrough has not taken place.** No session has been scheduled, offered or held, and nothing in the
Section G10 list has been confirmed by the client. This package prepares the session. Every confirmation cell below
is blank on purpose.

## 1. What the register says about PRE-07

The row, register Part Seven, verbatim:

> **PRE-07** [key] | **Store operations walkthrough**: a live demonstration of the actual admin environment, with the Section G10 list reviewed together and confirmed, before development begins | - | **DLV** | S1

The note under the table, verbatim:

> **PRE-07 is strongly recommended.** Rather than describing how the store will be managed, we will show you a working environment and let you perform the tasks you do every day. If something you need is missing, we would far rather find out before development begins than after the build is complete.

### Timing and consequences stated in the contract set

| Point | Words | Where |
|---|---|---|
| Timing | "before development begins" | PRE-07 row; repeated in the Functional Specification, US-27-10 |
| Why it exists | "Finding a gap before development is cheaper for both of us than finding it after" | Register 2.5, "What we added beyond your SRS" |
| What G10 is | "**This section defines what you and your team can actually do, day to day, without contacting a developer.** It is the practical answer to OBJ-05, and the section we most recommend you review line by line before approving." | G10, opening |
| The list is exhaustive | "Your operational capability is defined exhaustively in Section G10. Anything not listed there is a Change Request" | EX-27 |
| Already acknowledged at signature | "**EX-27**: the admin is not a reproduction of any third-party platform; Sections G10 and G11 as classified in this register are the complete operational commitment" | Register, Approval, point 3 |
| A gap found now | "Moving one of these rows into the Launch Platform after signature is a Change Request." | Register 1.6 |
| The test the admin is held to | "**OBJ-05 is the acceptance test for the entire admin panel.** If you must contact the developer to change a price, add a product or run an offer, the admin has failed regardless of what the feature list says. Section G10 is written against this objective." | Register A2 |

**What is not stated anywhere:** a date for the walkthrough, a payment tied to it, or a consequence if it is not
held. It is not mentioned in the Statement of Work, the Project Plan, the Document Guide or What You Need to
Provide. The register calls it both a contractual deliverable (DLV, key) and "strongly recommended".

**The honest position on timing.** Development has begun: three features are merged in the repository (product
cost, the bilingual baseline, information architecture). None of them is a Section G10 row, and no G10 row has been
built. So "before development begins" can no longer be met literally. It can still be met in substance: the session
is held before any G10 row is developed. That should be said to the client plainly at the start of the session, not
left unsaid.

**What a confirmation can and cannot do.** The client has already signed the register, so the list is already the
contractual commitment. A confirmation records that the client has seen it working and recognises it. A gap found in
the session is recorded and goes to Change Control (MS-CHG-2026-028). It is not agreed in the room.

## 2. Section G10, reproduced verbatim

Register heading: "G10. [key] STORE OPERATIONS CONSOLE". Lines 1348 to 1416 of the source. 23 rows.

### The dedicated Operations Console: Operations Platform

| ID | Requirement | § | Scope | Stage |
|---|---|---|---|---|
| ADM-140 [add] | **Operations Console**: a dedicated admin landing page combining the day's outstanding work in one view | - | **DEF** | - |
| ADM-141 | **Orders requiring action queue** (new, unpaid, ready to dispatch, failed delivery) worked top to bottom | - | **DEF** | - |
| ADM-142 | **Stock requiring attention**: low, out of stock, missing image, unpublished | - | **DEF** | - |
| ADM-143 | **Today's summary**: orders, revenue, units, pending dispatch | - | **DEF** | - |
| ADM-144 | **Saved views**: save any filtered list and return to it in one click | - | **DEF** | - |
| ADM-145 | Quick actions from the console without navigating away | - | **DEF** | - |

### Product operations

| ID | Requirement | § | Scope | Stage |
|---|---|---|---|---|
| ADM-146 | Add a simple product (title, price, stock, image, category) in under three minutes. The stock figure follows ERP-01 | - | P1 | S1 |
| ADM-147 | **Inline edit grid**: change price and stock across many products on one screen, without opening each | - | **DEF** | - |
| ADM-148 | Bulk price change by amount or percentage across a selection, using the standard commerce bulk-edit capability | - | **P1-L** | S1 |
| ADM-149 | Bulk stock change across a selection | - | P1-E | Per PRE-09 |
| ADM-150 | Bulk publish, unpublish or archive, using the standard commerce bulk-edit capability | - | **P1-L** | S1 |
| ADM-151 | Bulk category assignment, using the standard commerce bulk-edit capability | - | **P1-L** | S1 |
| ADM-152 | Find any product within seconds by SKU, title or barcode | - | P1 | S1 |
| ADM-153 | Duplicate an existing product as a starting point | - | P1 | S1 |

### Order operations

| ID | Requirement | § | Scope | Stage |
|---|---|---|---|---|
| ADM-154 | Bulk order status change, using the standard commerce bulk-action capability | - | **P1-L** | S1 |
| ADM-155 | Bulk dispatch and airway-bill printing from the dedicated Operations Console | - | **DEF** | - |
| ADM-156 | Bulk packing slip printing | - | **DEF** | - |
| ADM-157 | **Order search by customer phone number** | - | **P1** [key] | S1 |
| ADM-158 | Order export to spreadsheet using the standard supported commerce export | - | **P1-L** | S1 |

### Language and access

| ID | Requirement | § | Scope | Stage |
|---|---|---|---|---|
| ADM-159 | **Administrative interface in English**, presented consistently across the standard store screens and the screens built in this engagement | - | P1 | S1 |
| ADM-159a | Arabic administrative interface | - | P2 | - |
| ADM-160 | Admin usable on a mobile device for order status checks and stock updates, the stock part per PRE-09 | - | P1-L | S1 |
| ADM-161 | Product import and export: see Section U | - | P1 | S1 |

Count by scope: 13 delivery rows (P1 6, P1-L 6, P1-E 1; ADM-160 is one of the P1-L rows and has an ERP-dependent
part), 9 DEF, 1 P2.

## 3. The environment, as it stands today

Read from the local runtime on 5 October 2026 with read only commands. Nothing was written.

| Item | State |
|---|---|
| WordPress / WooCommerce | 7.1.2 / 11.1.0 |
| Translation | WPML 4.9.7, WooCommerce Multilingual 5.5.7, String Translation 3.5.4. Default language English, admin default English |
| Order storage | The current WooCommerce order tables are enabled |
| Analytics | Enabled |
| Product cost | The native Cost of Goods Sold feature is enabled |
| Carrier plugin | Bosta 4.5.7 active. No carrier account or API key, so no shipment can be created |
| Payment plugin | None installed |
| Products, variations, orders | **0, 0, 0** |
| Users | One, `pilot_admin`, Administrator |
| Roles | The standard set only: Administrator, Editor, Author, Contributor, Subscriber, Customer, Shop manager. **No Accountant role exists** |

**Nothing can be demonstrated on it today, because it holds no products and no orders.** The classification in
section 5 says which screen each item lives on; it does not claim the item has been exercised. Class (a) below means
"standard WooCommerce administration, expected to work on this runtime", and each one needs the dry run in section 4
before it is shown to a client.

## 4. Before the session: preparation checklist

| # | Preparation | Done |
|---|---|---|
| 1 | Choose the environment. Either the local runtime by screen share, or the isolated staging and demonstration copy (#244), which is not built yet. D-10 requires synthetic data only and no real customer data | |
| 2 | Load synthetic data: at least 12 simple products and 2 variable products across 3 categories and 2 brands, each with its Arabic translation, a SKU, and a barcode on some; at least 10 orders in different statuses, with Egyptian phone numbers written in more than one format | |
| 3 | Create two accounts: one Administrator for the Developer, one Shop manager for the client to drive | |
| 4 | Dry run every class (a) line in section 5 and note anything that does not behave as described | |
| 5 | Dry run the five items in section 6 specifically | |
| 6 | Have a phone on the same environment for ADM-160 | |
| 7 | Print the checklist in section 5 and the sign-off block in section 8 | |
| 8 | Send the client the Section G10 list from the register two working days ahead, and ask them to bring the tasks their team does every day | |

## 5. Walkthrough checklist, one line per G10 item

Classes:

* **(a)** Standard administration, demonstrable on the runtime once data is loaded.
* **(b)** Contracted, still to be built or still to be defined. Shown as a description, not a demonstration.
* **(c)** Not in the Launch Platform. Shown as what the client has instead, in the register's own words.

| ID | Class | Where it is shown, or how it is described | Seen | Confirmed by client | Gap or comment |
|---|---|---|---|---|---|
| ADM-140 | (c) DEF | Not built. Instead, register 1.6: "The standard commerce administration, with product, order, customer, stock and content management". Show WooCommerce > Home | | | |
| ADM-141 | (c) DEF | Not built. Instead: "The standard order list, filterable and sortable by status". Show WooCommerce > Orders and its status tabs | | | |
| ADM-142 | (c) DEF | Not built. Instead: "The out-of-stock and unmatched SKU reporting in RPT-10, from your ERP figures". Describe only: the figures come from the ERP, which is not connected. Analytics > Stock exists natively and is being corrected under #246 | | | |
| ADM-143 | (c) DEF | Not built. Instead: "The standard admin dashboard and sales reporting (RPT-01)". Show Analytics > Overview and Analytics > Revenue | | | |
| ADM-144 | (c) DEF | Not built. Instead: "Standard filtering, applied per session". Show the filters on WooCommerce > Orders and Products > All Products | | | |
| ADM-145 | (c) DEF | Not built. Instead: "Actions performed from the standard order and product screens" | | | |
| ADM-146 | (a), stock part (b) | Products > Add new product. Time it against "under three minutes". Say that the stock field shown is the standard one and that the stock figure will come from the ERP (ERP-01), method per PRE-09 | | | |
| ADM-147 | (c) DEF | Not built. Instead: "Standard bulk price editing across a selection (ADM-148), and bulk stock change as settled for ADM-149" | | | |
| ADM-148 | (a) | Products > All Products, tick a selection, Bulk actions > Edit > Apply, then Price: increase or decrease by a fixed amount or a percentage. Check the Arabic versions afterwards | | | |
| ADM-149 | (b) P1-E | **Describe, do not demonstrate.** Contracted and included; whether it is done in the ERP or in the store writing to the ERP is set in the ERP Integration Specification (PRE-09). The standard bulk edit panel has a stock field; it must not be presented as the agreed behaviour | | | |
| ADM-150 | (a), one word to agree | Same bulk edit panel, Status field (Published, Draft, Private), and Bulk actions > Move to Trash. See section 6 on "archive" | | | |
| ADM-151 | (a) | Same bulk edit panel, Product categories | | | |
| ADM-152 | (a) for SKU and title; barcode to verify | Products > All Products, search box. See section 6 on barcode | | | |
| ADM-153 | (a) | Products > All Products, hover a row, Duplicate. Or, on the product screen, "Copy to a new draft" | | | |
| ADM-154 | (a) | WooCommerce > Orders, tick a selection, Bulk actions > Change status. The two order statuses the design adds are not built yet (#248) and are described | | | |
| ADM-155 | (c) DEF | Not built. Instead: "Orders dispatched individually through the Bosta integration, with tracking codes and status updates (SHIP-08, SHIP-09, SHIP-13)". Describe only: no carrier account exists on the runtime | | | |
| ADM-156 | (c) DEF | Not built. Instead: "Packing slips printed per order" (ADM-93, P1-L). Describe only: nothing on the runtime prints a packing slip today | | | |
| ADM-157 | (a), to verify. Key row | WooCommerce > Orders, search box, by phone number. See section 6 | | | |
| ADM-158 | (a), to agree which export | Analytics > Orders > Download. See section 6 | | | |
| ADM-159 | (a) | Every screen shown. The admin default language is English | | | |
| ADM-159a | (c) P2 | Not contracted. "An Arabic admin interface can be added later as a separate phase if it is ever needed." The administrator guide and training are delivered in Arabic (HND-03, HND-09) | | | |
| ADM-160 | (a) for order status; stock part (b) | On a phone: WooCommerce > Orders, open an order, change its status. The stock update part is per PRE-09 and is described | | | |
| ADM-161 | (a) for export; import described | Products > All Products > Export, which is MIG-25, "the standard supported commerce export format". For import, describe Section U: one Developer-performed migration, "not a reusable self-service import application that you operate afterwards". See section 6 | | | |

## 6. Five items to settle in the dry run, before the client sees them

These are the places where the standard administration may not match the register's words exactly. Each needs a
fact, not an assumption, before the session.

| Item | The question | Why it matters |
|---|---|---|
| ADM-157, order search by phone | Does the order search find an order by phone when the number is typed in a different format from the one stored, for example with and without the country code? | It is a key row, and What We Are Building (MS-DOC-2026-007) calls it "how most support calls actually start". If the standard search falls short, the difference is Stage 1 work under ADM-157 and is described, not demonstrated |
| ADM-152, barcode | Does the product search match the barcode field as well as the SKU and the title? | The row promises all three |
| ADM-150, "archive" | The standard administration has Draft, Private and Trash. It has no status called archive. Which of the three does the client mean? | So the word in the register maps to one agreed action |
| ADM-158, export | The register says "the standard supported commerce export" and does not name it. The standard order list has no export button; the Analytics orders report has a download. Are its columns enough for the client? | So the reduced specification is seen, not imagined |
| ADM-161, import | The standard product screen has an Import button. The register contracts a Developer-performed migration and defers the reusable import tooling (MIG-05, MIG-07, MIG-08, MIG-18) | So the Import button is not taken for a contracted self-service tool |

## 7. Agenda

Ninety minutes. One screen shared, the client's operator driving wherever possible.

| Time | Item | Rows |
|---|---|---|
| 0 to 5 | Purpose. What PRE-07 is, that the list is the one already signed, that gaps are written down and go to Change Control, and that work on other parts of the store has started while no G10 row has | |
| 5 to 10 | The environment. A demonstration copy with invented data. English administration | ADM-159 |
| 10 to 35 | Product operations. Add a product against the clock, duplicate it, find it, bulk price, bulk status, bulk category, export | ADM-146, 153, 152, 148, 150, 151, 161 |
| 35 to 40 | Stock, described. What the ERP changes and what is not decided yet | ADM-149, ERP-01, PRE-09 |
| 40 to 60 | Order operations. Find by phone, open an order, bulk status change, export | ADM-157, 154, 158 |
| 60 to 65 | On a phone. Check and change an order status | ADM-160 |
| 65 to 75 | What belongs to the Operations Platform, and what the client has instead for each | ADM-140 to 145, 147, 155, 156, 159a |
| 75 to 85 | The client's own daily tasks, performed by the client. Anything they cannot do is written in the gap column | all |
| 85 to 90 | Read the list back line by line. Sign, or list what is outstanding | section 8 |

Not on the agenda, deliberately: staff role profiles (the Accountant question is open, see the ROLE-06
clarification), the returns workflow (deferred), and Section G11 storefront editing, which PRE-07 does not name and
which depends on the interface design.

## 8. Record and sign-off

To be completed at the session. Left blank.

| Field | Entry |
|---|---|
| Date of the session | |
| Environment shown | |
| Present for the Client | |
| Present for the Developer | |
| Rows demonstrated | |
| Rows described only | |

Gaps and requests raised. Each is assessed under MS-CHG-2026-028 and none is agreed by being listed here.

| # | Row, if any | What was asked | Defect in the list, clarification, or Change Request |
|---|---|---|---|
| | | | |
| | | | |
| | | | |

Confirmation. By signing, the Client confirms that the Section G10 list in the Feature Register MS-ANX-2026-006
version 1.5 was reviewed together with the Developer on the date above, on the environment named above, and that
the rows marked as confirmed in section 5 are confirmed. This changes no scope, fee or date.

| | Client | Developer |
|---|---|---|
| Name | | Mustafa Mohamed Shaaban |
| Title | | Software Engineering Lead |
| Signature | | |
| Date | | |

## 9. What I could not establish

1. Whether each class (a) screen behaves as described on this runtime. The runtime has no data and I was limited to read only commands, so none was exercised. The menu paths are the standard WooCommerce ones.
2. The answers to the five questions in section 6.
3. Whether the client has been told the walkthrough exists as a step. It is in the register and the specification only.
4. Who on the client side operates the store day to day, which decides who should drive.
