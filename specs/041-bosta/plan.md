# Implementation Plan: shipping through Bosta

**Branch**: `041-bosta` | **Date**: 2026-11-02 | **Spec**: [spec.md](spec.md)

## Summary

A thin carrier layer over the official Bosta plugin. `Carrier` is the interface and `BostaCarrier` reads what the plugin
writes on the order (tracking number, state code, description). `CarrierStatus` applies each new code once through a
staff-kept mapping table, marks unmapped or refused moves for review, keeps returned to origin apart and fills the
order's shipment with the carrier's tracking. `CodReconciliation` keeps collected and remitted apart on cash orders.
`DeliveryEstimate` adds an English and an Arabic estimate to each rate. Zones, rates and free shipping are WooCommerce's.

## Technical Context

| Item | Value |
|---|---|
| Code | `mizzey-site/src/Shipping/Carrier.php`, `BostaCarrier.php`, `CarrierStatus.php`, `CodReconciliation.php`, `DeliveryEstimate.php` |
| Data | Order meta `bosta_tracking_number`, `bosta_state_code`, `bosta_status` (the plugin's); `_mizzey_carrier_code_applied`, `_mizzey_carrier_needs_review`, `_mizzey_cod_collected`, `_mizzey_cod_collected_at`, `_mizzey_cod_remitted`, `_mizzey_cod_remitted_at`, `_mizzey_cod_remittance_ref`; options `mizzey_carrier_status_map`, `mizzey_carrier_codes_seen` (not autoloaded); rate settings `mizzey_estimate_en`, `mizzey_estimate_ar` |
| Extension points | `woocommerce_after_order_object_save`, `mizzey_order_shipment`, `woocommerce_get_sections_shipping`, `woocommerce_settings_shipping`, `woocommerce_settings_save_shipping`, `woocommerce_admin_order_data_after_*`, `woocommerce_process_shop_order_meta`, the HPOS list filters, `woocommerce_shipping_instance_form_fields_*`, `woocommerce_package_rates`, `mizzey_carriers` |
| Testing | `t81-bosta`, driving the plugin's own webhook handler (`Bosta_Webhook_Handler::handle_status_update`) as Bosta's call would; mutation evidence |

## Constitution Check

| Rule | How it is met |
|---|---|
| M-4, D-24 | The official plugin does the carrier work; Mizzey adds only the mapping, the reconciliation and the estimate |
| ADR-0002 | The minimum shipment state record: mapping table, current mapped state, returned to origin, collected and remitted |
| M-10 | The missing account, legend and cycle are mocks or empty defaults, not blockers |
| M-7 | Each criterion has an automated check |
