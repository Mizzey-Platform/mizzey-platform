# Implementation Plan: the Egyptian address in full

**Branch**: `050-egyptian-address` | **Date**: 2026-11-03 | **Spec**: [spec.md](spec.md)

## Summary

The checkout already has Egypt's locale and the district and landmark fields (`019-checkout`). This slice adds the
kind of place, building, floor and apartment as WooCommerce address fields, puts the form in Egyptian reading order,
requires the mobile number, sets one address, and writes the building, floor and apartment as the address's second
line so every existing reader (order screens, emails, Bosta, packing slip, invoice) carries them. The address book
keeps the same parts. The designed form is PRE-03b's.

## Technical Context

| Item | Value |
|---|---|
| Stack | WordPress 7.1.4, WooCommerce 11.1.0 (`stack.lock.json`) |
| Code | `mizzey-site/src/Checkout/EgyptianAddress.php`; `Account/AddressBook.php`, `Account/CustomerRecords.php`; `Orders/PackingSlip.php`; `Invoices/InvoiceDocument.php` |
| Extension points | `woocommerce_get_country_locale` (labels, order, phone); `woocommerce_register_additional_checkout_field` (location `address`, conditional `required` and `hidden` on `customer.address`); `pre_option_woocommerce_ship_to_destination`; `woocommerce_before_order_object_save` |
| Testing | `t90-egyptian-address`: in-process and over HTTP through the Store API checkout; `t60`, `t47`, `t63`, `t73` bodies carry the new parts |

## Constitution Check

| Rule | How it is met |
|---|---|
| M-4 | Native first: WooCommerce's one-address setting, its additional fields and conditional rules, its second address line |
| M-7 | Every criterion has an automated check; deliberate defects are caught (`evidence/mutations.txt`) |
| Contract | Every part is in CHK-01's "Egyptian structure"; the map (CHK-13, P2) is not built |
| FIX-04 | Every new label and the composed line have their Arabic |
| Third-party code | The Bosta plugin is not changed: it already sends the second line |

## Design

| Piece | What it does |
|---|---|
| `EgyptianAddress::locale` | Labels "City or area" and "Street or compound"; governorate, city, street in reading order; phone required |
| `EgyptianAddress::fields` | District, kind of place (select), building (required), floor and apartment (required and shown only for an apartment or an office: a rule on the address object, as a rule's top-level keys are the document's properties), landmark; each with its index |
| `EgyptianAddress::oneAddress` | `woocommerce_ship_to_destination` is `billing_only` |
| `EgyptianAddress::composeOrder` | On every save of an order with an Egyptian address and these parts, the second line is "Building 5, floor 4, apartment 18" (or "Villa 12"), composed in the order's language (`OrderLanguage::during`) |
| `AddressBook` | The same parts and rules; the default copies them and the composed line to the customer |
| Readers | The packing slip adds the landmark; the invoice and the customer record add the second line |

## Criterion to test map

| AC | `t90-egyptian-address` asserts |
|---|---|
| AC-1 | The indices in reading order; phone required; the kind's three options; the street label |
| AC-2 | [HTTP] An apartment without floor and apartment refused, an address without a building refused, a villa placed without them |
| AC-3 | `wc_ship_to_billing_address_only()`; [HTTP] the placed order needs no separate shipping address |
| AC-4 | The composed line for each kind, and in Arabic; [HTTP] the order's second line; Bosta's `secondLine` where its plugin is active; the packing slip's line and landmark; a staff edit carried; an Arabic order's line in Arabic |
| AC-5 | The book refuses a flat without its parts, keeps no floor for a villa, keeps each part; the default prefills every part |

## Risks

| Risk | Handling |
|---|---|
| A saved address from before has no kind or building | The checkout asks for them; nothing is lost |
| Bosta refuses a first line under 10 characters | Unchanged behaviour; noted in the spec |
