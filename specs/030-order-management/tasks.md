# Tasks: order management in admin

**Spec**: [spec.md](spec.md) | **Plan**: [plan.md](plan.md)

- [x] T001 `OrderFilters`: payment method, carrier and date range on the orders list, combining with WooCommerce's search and filters (AC-1)
- [x] T002 `ShipmentBox`: carrier, tracking number and address on the order screen, saved with the nonce, every change noted with who made it; shown on the customer's order (AC-3)
- [x] T003 `PackingSlip`: the per-order slip in the order's language, without prices, staff only, from the order screen and the list; `InvoicePdf::render` shared (AC-6)
- [x] T004 Arabic for the slip and the customer's shipment lines (FIX-04)
- [x] T005 `t70-order-management`: AC-1 to AC-6; mutation evidence
- [ ] T006 Staging: find by phone, filter, attach a shipment, print a slip, through the browser, both languages (DOD-04)
