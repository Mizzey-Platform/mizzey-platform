# Implementation Plan: the second release, built behind its switch

**Branch**: `047-second-release` | **Date**: 2026-11-02 | **Spec**: [spec.md](spec.md)

## Summary

`SecondRelease/ReleaseSwitch` holds the switch. `Reorder` opens WooCommerce's "Order again" for delivered orders.
`PrivacyRequests` adds the customer's two requests and handles confirmed ones in the background with WordPress's and
WooCommerce's exporters and erasers, and the staff export. `NotificationPreferences` adds the email choices.
`CustomerMetrics` adds the lifetime figures and the new and returning count. Three hook points are added to the
customer record, the dashboard and the review invitation.

## Technical Context

| Item | Value |
|---|---|
| Code | `mizzey-site/src/SecondRelease/*.php`; hooks in `Account/CustomerRecords`, `Reporting/StoreDashboard`, `Reviews/ReviewInvitation` |
| Data | Option `mizzey_second_release`; user meta `_mizzey_no_review_invitations`; WordPress's `user_request` posts |
| Testing | `t87-second-release`; mutation evidence |

## Constitution Check

| Rule | How it is met |
|---|---|
| M-4 | WooCommerce's order again, WordPress's privacy requests, WooCommerce's erasers and email settings |
| SOW | Built now, off until the second release |
| M-7 | Each criterion has an automated check |
