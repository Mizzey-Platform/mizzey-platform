# Implementation Plan: launch staff roles, the part Stage 3 needs

**Branch**: `013-launch-roles` | **Date**: 2026-10-31 | **Spec**: [spec.md](spec.md)

## Summary

The standard commerce role set stands: WordPress's Administrator is the Owner / Super Admin, WooCommerce's Shop
manager is the Admin, the Customer role and visitors are as WooCommerce makes them. One Mizzey capability,
`mizzey_refund_orders`, keeps refunding separate (D-10, D-11 item 7): given once to both staff roles, checked before
WooCommerce on the order screen's refund and delete-refund actions and on the REST refunds routes. The Accountant
role (ROLE-06, CX-01) is not built.

## Technical Context

| Item | Value |
|---|---|
| Stack | WordPress 7.1.4, WooCommerce 11.1.0 (`stack.lock.json`) |
| Code | `mizzey-site/src/Roles/RefundCapability.php` |
| Testing | `t54-launch-roles`: in-process, role capabilities, editable roles, the default role, REST refunds as a shop manager with and without the capability, the AJAX guard, a refund by code |

## Constitution Check

| Rule | How it is met |
|---|---|
| M-2 | CX-01 is not resolved in code: no Accountant role; nothing traces ROLE-06 |
| M-4 | Every role is native; the one gap (refunds share `edit_shop_orders`) is recorded in Native coverage |
| M-7 | Refund permission has automated tests and mutation checks |
| M-9 | The refusal message is admin-facing, in English (ADM-159) |

## Design

| Piece | What it does |
|---|---|
| `grant()` on `init` | Adds the capability to Administrator and Shop manager once, marked by the `mizzey_refund_capability_granted` option, so an owner's later removal is kept |
| `guardAjax()` | At priority 0 on `wp_ajax_woocommerce_refund_line_items` and `wp_ajax_woocommerce_delete_refund`: refuses with 403 without the capability, before WooCommerce's handler |
| `guardRest()` | On `rest_pre_dispatch`: a signed-in user's POST, PUT, PATCH or DELETE on `/wc/vN/orders/{id}/refunds` or `.../refunds/{id}` without the capability gets a 403 `WP_Error`; reads and the preview pass |

## Criterion to test map

| AC | `t54-launch-roles` asserts |
|---|---|
| AC-1 | Administrator's capabilities |
| AC-2 | Shop manager's store capabilities and the refund capability |
| AC-3 | No owner-level capability on Shop manager; editable roles are Customer only; the default role is not staff |
| AC-4 | REST create and delete refused without the capability, made with it; reads and preview not refused; AJAX guard at priority 0, 403 without, through with; a refund by code made |
| AC-5 | A visitor and a customer hold no staff capability |

## Risks

| Risk | Handling |
|---|---|
| A later WooCommerce adds a refund route | The REST pattern covers every `wc/vN` refunds route; t54 lists the routes it checks |
| The owner removes the capability and a role reset restores it | `grant()` runs once; a reset of roles is not part of the store's tooling |
