# Implementation Plan: observability

**Branch**: `039-observability` | **Date**: 2026-11-02 | **Spec**: [spec.md](spec.md)

## Summary

One class, `Security/Monitoring`, watches the end of each request for a fatal error, Action Scheduler for failed and timed-out actions, and WooCommerce's logger for errors from Mizzey's integration sources; it writes to the `mizzey-errors` log and publishes a CoreX notification to operations, once per kind and place an hour.

## Technical Context

| Item | Value |
|---|---|
| Code | `mizzey-site/src/Security/Monitoring.php` |
| Extension points | `register_shutdown_function`, `wp_php_error_message` (WordPress's fatal error handler ends the request before later shutdown functions run), `action_scheduler_failed_execution`, `action_scheduler_failed_action`, `woocommerce_logger_log_message`; CoreX `NotificationService::publish` |
| Testing | `t79-observability`; on staging a forced error in the PHP container and a stock source left unbound |

## Constitution Check

| Rule | How it is met |
|---|---|
| ADR-0003, D-20 A12 | Logs are WooCommerce's, notifications CoreX's |
| M-7 | Each criterion has an automated check |
