# Feature Specification: the assistant connection for the Mizzey team

**Feature Branch**: `062-assistant-connection`

**Created**: 2026-11-05

**Status**: Draft, for the owner's review before any build (a new gift area)

**Work type**: requirement

**Input**: D-30 (the owner, 4 November 2026): "Run the store by asking Claude or ChatGPT is a gift", sections 1 to 4,
and the owner's instruction of the same day that anything done through the connection must be logged and tracked, so
the source of a problem can be found. Before go-live.

## Register trace [checked]

| ID | Scope | Stage | Register wording (short, verbatim) |
|---|---|---|---|
| ADM-130 | P1-L | S1 | Create staff users, roles and granular permissions |
| ADM-133 | P1 | S1 | The audit trail cannot be deleted from the interface by ordinary users |

ADM-130 is traced because the right to use the assistant is one more permission the owner gives a staff account.
ADM-133 is traced because the assistant's entries are written to the staff log and keep its protection. The
connection itself traces to no register row: it is D-30's gift, never the delivery of EX-06, HOME-13 or ROLE-10.

## Context rows (no obligation here)

| ID | Scope | Why it matters here |
|---|---|---|
| ADM-132, ADM-134 | P1-L, P1 | The staff log (specs/031) that the assistant's entries are written to |
| ADM-50 | P1-L | Price change history (specs/036): a price the assistant changes is recorded there as any other change |
| NFR-10 | P1-L | Alerts to the staff responsible for operations (specs/039): the assistant's alerts use the same route |
| ROLE-10 | DEF | The audit trail of sensitive operations done by hand in the admin: not delivered here, stays DEF (D-30 item 4) |
| HOME-13 | P3 | A personalised home page: excluded, not delivered and not owed because of this gift (D-30 item 2) |
| EX-06 | Exclusion | AI and personalised recommendations: excluded, not delivered and not owed because of this gift (D-30 item 2) |

## Open contract items

- **The ERP's field ownership** (PRE-09, the ERP Integration Specification, not yet written): which product fields
  the website owns, whether products are created on the website or in the ERP after go-live, and who owns the price.
  The assistant follows whatever it sets (FR-012). Nothing here decides it.
- **specs/031 FR-007** said only sign-ins and security events are written to the staff log (CX-03). D-30, the owner's
  later decision, adds the assistant's own entries, with source "assistant". This is the gift's record of what the
  assistant did, not the ROLE-10 audit of work done by hand, which stays DEF. specs/031 is amended to point here.
- **Telling the client.** The gift is told to the client in writing, as included at no charge beyond the contracted
  scope (D-30 item 2). Mizzey's own Claude or ChatGPT subscription is a third-party cost, not part of the gift.

## Contractual acceptance criteria [checked]

| # | Criterion | Traces | Status |
|---|---|---|---|
| AC-1 | The owner gives or removes a staff account's right to use the assistant, as one of its permissions | ADM-130 | provisional |
| AC-2 | No entry the assistant writes to the staff log can be changed or deleted from the interface | ADM-133 | provisional |

The gift's own behaviour (D-30 items 1, 3 and 4) is checked below as engineering requirements, not as contractual
criteria.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Ask how the store is doing (Priority: High)

1. **Given** a staff member with the right to use the assistant, connected from Claude, **When** they ask "how much
   did we sell last week, compared with the week before?", **Then** the assistant answers with the store's figures
   for both weeks, and the staff log records the task and the period asked for, not the figures.
2. **Given** the same staff member, **When** they ask in Arabic for the best sellers this month, **Then** the answer
   lists them with their names in Arabic.

### User Story 2 - Find an order and act on it (Priority: High)

1. **Given** a customer calling about order 5021, **When** the staff member asks the assistant to find it and mark it
   completed, **Then** the order is found, its status changes exactly as it would in the admin (with the same emails
   and stock effects), the order gets a note naming the assistant, the person and the reference, and the staff log
   records the status before and after.

### User Story 3 - Add a product in both languages (Priority: Medium)

1. **Given** a staff member who may edit products, **When** they ask the assistant to add a product with its English
   and Arabic name and description, its price, its category and a new SKU, **Then** the product is created as a draft
   in both languages, the SKU is checked as unused, and the product's change history names the assistant, the person
   and the reference.

### User Story 4 - Know what it did (Priority: High)

1. **Given** the assistant changed three orders and one product yesterday, **When** the owner reads the daily summary,
   **Then** it lists those four changes with their references, and the staff log filtered by source "assistant" shows
   the same four with the person, the app, the inputs and the values before and after.
2. **Given** a change that turns out wrong, **When** the owner looks up its reference, **Then** the value before the
   change is there, so it can be put back by hand.

### Edge Cases

- A request outside the task list (change the home page, edit a template, run code, change stock, issue a refund,
  change an SKU): refused, the assistant is told why, and the refusal is logged with outcome "refused".
- A staff member whose own permissions do not allow an action in the admin: refused through the assistant too, even
  when the task is on the list.
- A product field the website does not own once the ERP is live: refused, naming the field and saying where it is
  changed instead.
- A key that was revoked, a key made for another purpose, or any key while the connection is switched off: refused,
  and recorded as a failed sign-in in the staff log (specs/031).
- Many refusals in a short time, or an unusual number of changes: the staff responsible for operations are alerted.
- The same request sent twice by the app: each call has its own reference; a change already made is not made twice
  where the task can tell (for example, a status that is already the one asked for).
- A failure to write the log entry: the change is not made (no untracked change).

## Native coverage

| Need | Native mechanism | Verdict |
|---|---|---|
| A declared, fixed list of tasks with their inputs, checks and permissions | WordPress's Abilities API (core since 6.9) | Native, used: each task is one ability; nothing else is exposed |
| A personal key per person and per app, revocable on its own | WordPress's application passwords | Native, used, limited to keys made for the assistant |
| The connection an assistant app speaks (the Model Context Protocol) | None in WordPress or WooCommerce core | Gap: a thin MCP endpoint over the abilities (our own code, D-24); the plan names the transport and how each app signs in |
| Orders, products, reports | WooCommerce's own objects and reports | Native, used: the tasks call them, so every rule the admin applies applies here |
| Translations of a product | WPML and WooCommerce Multilingual, as the admin uses them | Native, used |
| The record of what was done | The staff log (specs/031, CoreX Activity), order notes, price history (specs/036) | Native plus the existing log, with a new source; a product change-history entry for other fields is a gap |
| Alerts | Monitoring's route to the staff responsible for operations (specs/039) | Existing, used |

## Requirements

### Functional Requirements

**The tasks (D-30 item 1).** The connection MUST offer these tasks and nothing else:

- **FR-001**: Reports, read only: sales for a period (totals, by day, against an earlier period); best sellers; orders
  by status; customers (new and returning, with their order counts). Figures as the store's own reports give them.
- **FR-002**: Find orders by number, customer, status or dates, and read one order.
- **FR-003**: Update an order: change its status as the admin allows, and add a note (private, or to the customer).
  Refunds, payment captures and changes to an order's items or totals are not on the list.
- **FR-004**: Add a product, created as a draft unless the staff member asks to publish it, with its English and
  Arabic texts, and edit a product, both only on the fields FR-012 allows.
- **FR-005**: A daily summary: yesterday's orders and sales, and every change the assistant made the day before, with
  references.
- **FR-006**: An answer to "what can you do?": the task list in plain words, in the language asked.
- **FR-007**: Anything else MUST be refused, including design, layout, blocks, templates, settings, code, users,
  stock and SKUs (D-30 item 1).

**How it is built (D-30 item 3).**

- **FR-008**: Each staff member MUST connect with a personal key tied to their own staff account; there is no shared
  key; each key can be revoked on its own, and a key made for the assistant works only for the assistant.
- **FR-009**: A staff account MUST have the assistant permission, given by the owner (AC-1), and each task MUST also
  need the permission the same action needs in the admin (for example, editing orders).
- **FR-010**: Each task MUST go through the same code paths and checks as the admin (WooCommerce's order and product
  objects), so emails, stock effects, price history, translations and validation are the admin's.
- **FR-011**: The assistant MUST never change stock or an SKU.
- **FR-012**: Product edits MUST be limited to the fields the website owns as the ERP Integration Specification sets
  them once the ERP is live; until then, the product fields staff may edit in the admin, the SKU and stock excepted.
  Adding a product MUST be refused if, after go-live, products are created in the ERP.
- **FR-013**: An admin switch MUST turn the connection off, and every key with it, at once.

**Every action traceable (D-30 item 4).**

- **FR-014**: Every call MUST get one reference (for example `A-20261204-0012`), and everything it touches carries it.
- **FR-015**: Every call MUST be written to the staff log with source "assistant": the time, the staff account, the
  assistant app (Claude or ChatGPT), the task, its inputs, the products and orders touched, the value before and
  after for every field changed, and the outcome (done, refused as outside its tasks, refused for permission, or
  failed). A read is recorded as the task and its filters, never the data returned.
- **FR-016**: The log MUST be searchable by source, person, task, product, order, outcome and date, and keep the
  staff log's protection (AC-2) and retention (one year).
- **FR-017**: A change to an order MUST add an order note, and a change to a product an entry in that product's change
  history, each naming the assistant, the person and the reference. An update this causes towards the ERP MUST carry
  the same reference.
- **FR-018**: Repeated refusals, or an unusual number of changes, MUST alert the staff responsible for operations
  (working defaults below, changeable in the admin).
- **FR-019**: The value before every change MUST be kept, so any change can be reversed by hand.
- **FR-020**: If the log entry cannot be written, the change MUST NOT be made.
- **FR-021**: Every message the assistant receives from the store MUST be available in English and Arabic (FIX-04).

### Key Entities

- **Assistant key**: the staff account, the app it was made for, when it was made and last used; revocable.
- **Assistant entry** (in the staff log): reference, time, account, app, task, inputs, objects touched, before and
  after values, outcome.
- **Product change-history entry**: reference, time, account, app, the fields changed with their before and after.

## Optional safeguards (not owed)

- None beyond D-30's own items.

## Future or Option C items (not built)

- New tasks or extensions of these (D-30 item 2: not owed); AI or personalised recommendations and a personalised
  home page (EX-06, HOME-13); the audit trail of work done by hand in the admin (ROLE-10, DEF); undoing a change
  automatically.

## Success Criteria

- **SC-001**: On staging, every change made through the assistant is found in the staff log by its reference, with
  the person, the app and the values before and after, and on the order or product it touched.
- **SC-002**: Every request outside the list, tried on staging, is refused and logged.

## Delivery stage

A gift with no register stage, delivered before go-live (D-30 item 3). The traced rows ADM-130 and ADM-133 are S1 and
are unchanged by it.

## Assumptions

- Working defaults (D-10), changeable in the admin: an alert after 5 refusals for one person within 10 minutes, or
  more than 50 changes within an hour.
- The daily summary is read through the assistant ("what happened yesterday?"); it is not emailed.
- Answers carry only what the task needs. Card details never pass through (the store does not keep them). What the
  assistant app does with an answer is under Mizzey's own subscription terms.
- Product images are added by link to an image the staff member gives; uploading files through the assistant is not
  on the list.
- Which apps connect, and how each signs in (a key in the app's settings, or signing in through the store to get
  one), is set in the plan; either way it ends in a personal key for one staff account and one app (FR-008).
