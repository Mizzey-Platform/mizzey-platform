# Implementation Plan: the sales report

**Branch**: `034-sales-report` | **Date**: 2026-11-02 | **Spec**: [spec.md](spec.md)

## Summary

RPT-01's seven figures are WooCommerce Analytics' Revenue and Orders reports. The work is the proof: known English and
Arabic orders on one day, with discounts, shipping and a refund, compared with a hand calculation, in process and
through the Analytics screen's own request in both admin languages.

## Technical Context

| Item | Value |
|---|---|
| Stack | WordPress 7.1.4, WooCommerce 11.1.0 Analytics, WPML 4.9.7, WCML 5.5.7 |
| Code | None, unless a check fails |
| Testing | `t74-sales-report` |

## Constitution Check

| Rule | How it is met |
|---|---|
| M-4 | Native reports; no parallel calculation |
| M-7 | Each criterion has an automated check |
