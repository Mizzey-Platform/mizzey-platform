# Tasks: refunds with their payment reference

**Spec**: [spec.md](spec.md) | **Plan**: [plan.md](plan.md)

- [x] T001 `RefundReference::stamp`: the payment's reference on every refund; the staff's typed reference for a refund made by hand, from WooCommerce's refund request only, with the nonce and the refund capability; the dashboard mark (AC-1, AC-2, AC-3, AC-5)
- [x] T002 `RefundReference::fromGateway`, called by `RefundLedger::captureReference`: the provider's refund id on the refund (AC-2)
- [x] T003 `PaymentGate::reconcileRefunds` marks the refund it records from Paymob's report (AC-3)
- [x] T004 The refund line on the order screen and the reference field on the refund panel (`admin-refund-reference.js`) (AC-2, FR-004)
- [x] T005 The reference in the refunded-order email, full and partial, with its Arabic (AC-4, FIX-04)
- [x] T006 `t69-refunds` and the mutation evidence (`evidence/mutation.txt`)
- [ ] T007 Staging: a manual and a gateway refund on the order screen through the browser, the email captured, in both languages (DOD-04)
