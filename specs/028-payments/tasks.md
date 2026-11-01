# Tasks: payments through Paymob

**Spec**: [spec.md](spec.md) | **Plan**: [plan.md](plan.md)

- [x] T001 `PaymentProvider`, `CallbackResult`, `PaymentEvent`: the seam and its values (AC-8)
- [x] T002 `PaymobProvider`: Paymob's signature for the transaction notice and the return, the order from the merchant reference, everything else refused (AC-4)
- [x] T003 `PaymentRecord`: events on the order, once per reference and kind; attention items; the staff panel (AC-6, AC-10)
- [x] T004 `PaymentGate`: refuse unverified, lock the order, stop repeats, pending and mismatches, flag a second payment or a payment of a closed order, then let the plugin act (AC-1 to AC-4)
- [x] T005 `RefundLedger`: the order lock around a refund, the refund's Paymob id captured, its notice acknowledged once (AC-2, AC-7)
- [x] T006 `CallbackAddress`: Paymob told the path form of the callback address
- [x] T007 Register in `mizzey-site.php`; the customer message in Arabic (FIX-04; staff text is English, ADM-159)
- [x] T008 `t68-payments` in process and over the real request; mutation evidence (`evidence/mutation.txt`, 7 of 7 caught)
- [x] T008b The same callbacks through the plugin itself, over HTTP to the local runtime (plugin installed on the owner's approval, 1 November 2026): forged return, signed success, repeat, unsigned repeat, other amount, a refund from the order screen with Paymob's reply stood in for, its notice, a refused refund, a refund above what is left, a dashboard refund and its repeat
- [x] T009 `stack.lock.json` paymob entry (installed-untested); `docs/payments-settlement.md` (PAY-19)
- [x] T011 A second payment of a paid order refunded automatically through the provider (D-25): `PaymentProvider::refundTransaction`, `PaymobProvider` through Paymob's API, `RefundLedger::record`; t68 AC-1
- [x] T012 Refunds reconciled with Paymob's refunded total (the shape the staging sandbox showed): equal adds nothing, more is a dashboard refund recorded for the difference, less is flagged; a refund's own notice never reaches the plugin
- [ ] T010 Staging: release with the plugin (after the tooling PR), test keys, sandbox run of AC-1 to AC-7 by hand
