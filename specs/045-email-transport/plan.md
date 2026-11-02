# Implementation Plan: the transactional email service

**Branch**: `045-email-transport` | **Date**: 2026-11-02 | **Spec**: [spec.md](spec.md)

## Summary

`Emails/Transport` sets WordPress's mailer to the provider's SMTP relay when the server names one, gives every message
WooCommerce's sender, logs refused mail for the monitor, and offers a test message in Settings, Email delivery.

## Technical Context

| Item | Value |
|---|---|
| Code | `mizzey-site/src/Emails/Transport.php` |
| Configuration | Constants in the server's `wp-config.php`: `MIZZEY_SMTP_HOST`, `MIZZEY_SMTP_PORT`, `MIZZEY_SMTP_SECURE`, `MIZZEY_SMTP_USER`, `MIZZEY_SMTP_PASSWORD` |
| Extension points | `phpmailer_init`, `wp_mail_from`, `wp_mail_from_name`, `wp_mail_failed`, `admin_menu`, `admin_post_mizzey_email_test` |
| Testing | `t85-email-transport`; mutation evidence |

## Constitution Check

| Rule | How it is met |
|---|---|
| M-4, D-24 | WordPress's own mailer; no SMTP plugin |
| Secrets | The password lives in the server's configuration only |
| M-7 | Each criterion has an automated check |
