# Implementation Plan: the store at a glance

**Branch**: `035-admin-dashboard` | **Date**: 2026-11-02 | **Spec**: [spec.md](spec.md)

## Summary

One dashboard widget, `StoreDashboard`, reads Analytics' revenue totals (so it agrees with RPT-01), sums top products
per physical item, counts the orders and refunds that wait for staff, and asks the stock seam for each physical item.

## Technical Context

| Item | Value |
|---|---|
| Code | `mizzey-site/src/Reporting/StoreDashboard.php` |
| Reads | `/wc-analytics/reports/revenue/stats` (internal request), `wc_order_product_lookup` with `wc_order_stats`, `CanonicalProduct`, `StockIdentity`, `StockGateway::quick()`, `PaymentRecord::ATTENTION` |
| Extension points | `wp_dashboard_setup` |
| Testing | `t75-admin-dashboard`, with a stand-in stock source; mutation evidence |

## Constitution Check

| Rule | How it is met |
|---|---|
| M-3 | Stock only through the seam; ADM-10, ADM-11 criteria pending PRE-09 |
| M-4 | Analytics' own figures and charts; gaps recorded |
| M-7 | Each criterion has an automated check |
