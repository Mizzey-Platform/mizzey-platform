# Contract: where product cost may and may not appear

This is the exposure contract behind AC-5 and AC-6. Test t08 checks every "never" row. t09 records the staff rows
without judging them.

| Surface | Audience | Cost | Criterion |
|---|---|---|---|
| Storefront product, shop, cart and checkout pages (HTML) | Visitor, customer | **Never** | AC-5 |
| Store API `/wp-json/wc/store/v1/products` and `/products/{id}` | Visitor, customer | **Never** | AC-5 |
| REST v3 `/wp-json/wc/v3/products` and variations | Visitor | **Never** (expect 401) | AC-5 |
| REST v3 `/wp-json/wc/v3/products` and variations | Customer | **Never** (expect 401 or 403) | AC-5 |
| Customer's own order view (My Account, order emails) | Customer | **Never** | AC-5 |
| Admin product edit screen, products list, CSV export | Staff | Recorded fact only (which roles see it) | AC-6, pending CX-01 |
| REST v3 with staff credentials | Staff | Recorded fact only | AC-6, pending CX-01 |
