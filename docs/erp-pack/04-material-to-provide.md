# Mizzey online store and the ERP: stock integration

## Material to provide

* **From:** Mustafa Shaaban, developer of the Mizzey online store
* **For:** the ERP developers working with Mizzey
* **Date:** [date]

This is the one list of material asked for. Numbers in brackets point to the decisions and questions in the
technical appendix. Whatever exists today is useful, including drafts. If an item does not exist, please say so on
the reply sheet: that is an answer too.

Three rules apply to every item:

* **Stock and item data only.** No customer names, addresses, telephone numbers or order history. No prices or
  costs are needed either.
* **SKUs exactly as the ERP stores them.** Please export them and do not retype them. Send SKU columns as text, so
  that a spreadsheet does not strip leading zeros, change case or trim spaces.
* **No credentials in a document or a plain email.** Access details for the test environment are passed through a
  channel agreed with Mizzey: [channel].

## Before the meeting

Please send these by [date], through [name] at Mizzey.

| # | What | Why it is needed | Format, if it matters |
|---|---|---|---|
| B1 | Any existing documentation of the ERP's interface, in whatever state it is (2.1) | Much of the question list can then be answered by reading, which shortens the meeting | Any. A draft is welcome |
| B2 | A full list of products and variants as the ERP identifies them (3.15): SKU, the internal item id if one exists, item name, the parent of each variant, and whether the item is active | The SKUs on both sides are compared and corrected before the catalogue is loaded. This list is the ERP's side of that comparison | CSV or spreadsheet, UTF-8, one row per sellable item, SKU as text |
| B3 | A sample of real SKUs covering one simple product (no variants) and one product with variants, with all of its variants (3.4, 3.5) | Shows how each kind of product is represented, and at which level the stock balance sits (M4, M17) | The records as the ERP holds or returns them, not retyped |
| B4 | Examples of any SKU that was renamed, any that was retired or made inactive, and any that is or was duplicated, with the old and new values where there are any (3.9 to 3.12) | These cases drive the SKU questions (M5, M16, M18). One real example of each is worth more than a description | A short table: old SKU, new SKU, date, what happened to the stock balance |
| B5 | An example of the stock data the ERP holds for a product with variants (4.2) | Shows what the stock figure means: on hand, available, reserved, per location (M6) | The raw record or response for one product, with field names |
| B6 | A short description of how stock is shared between the website, Amazon and other sales channels today, if it is, and which figure is the website's (4.2, 4.3) | Decides which figure the store shows and sells against | A few lines of text |
| B7 | A first answer on connectivity and access: how the store would authenticate, and whether the interface can be reached from a server outside Mizzey's network and outside Egypt (M2; 1.1, 1.4, 1.5) | Can decide where the store is hosted, so it is needed early | A few lines of text. No credentials |
| B8 | Whether a test environment exists today, and if not, when one is expected (M3; 10.1) | The integration is built and tested against a test environment, never against live stock | Yes or no, and a date if no |

## At the meeting

Please bring these to the meeting, or give a named owner and a date for each. The meeting date is [date, to be
arranged].

| # | What | Why it is needed | Format, if it matters |
|---|---|---|---|
| A1 | Test environment details: its address, how access is issued, and confirmation that it is separate from live stock (10.1, 10.2) | The store connects to the test environment only. Nothing is built against the live ERP | Address and access method in writing. Credentials through the agreed channel only |
| A2 | Test data in that environment that uses the real SKUs and can safely be reduced, including the products in B3 | Tests must run against the SKUs the store will really use, and must be free to reduce stock without touching real units | A list of the SKUs loaded and their starting balances |
| A3 | Interface documentation for the test environment: the calls to resolve a SKU, read stock, reduce stock and reverse a reduction, with their inputs and outputs (3.1, 4.1, 6.1, 7.1) | The store's integration component is built against it | Written documentation, with one sample request and response for each call |
| A4 | Sample responses for a SKU that is unknown, inactive or retired, duplicated, and held without a stock balance (3.12, 3.13) | The store must tell these cases apart and never sell on a SKU the ERP does not recognise (M18) | One sample response for each case |
| A5 | The list of error responses, with which may be retried and which may not (9.1, 9.2) | The store retries only what is worth retrying and shows the customer the right message | A list of codes with meanings |
| A6 | Rate limits and expected response times (4.4, 9.3) | The store is built to stay inside them | Figures, per credential or per IP address |
| A7 | Planned maintenance windows, and how notice of them is given (9.5) | No sale is confirmed while the ERP cannot be reached, so customers meet every window | Days and times, and the notice route |
| A8 | The names of the people who will answer open items after the meeting, and who is on call once the store is live (10.5) | Every decision left open needs an owner and a date | Name, role and contact route |

## What is not being asked for

Access to the live ERP, any customer personal data, any price, cost or accounting data, and any work on your side
before the meeting.
