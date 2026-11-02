# Tasks: shipping through Bosta

**Spec**: [spec.md](spec.md) | **Plan**: [plan.md](plan.md)

- [x] T001 `Carrier`, `BostaCarrier`: the carrier layer over the official plugin (AC-3)
- [x] T002 `CarrierStatus`: the mapping table, review, returned to origin, tracking into the shipment (AC-3 to AC-5)
- [x] T003 `CodReconciliation`: collected, remitted, the unremitted list (AC-6)
- [x] T004 `DeliveryEstimate`: an estimate per rate and language (AC-2)
- [x] T005 `t81-bosta` and mutation evidence (AC-1 to AC-6)
- [ ] T006 Staging: the plugin installed, a mock webhook for each case, both languages (DOD-04)
