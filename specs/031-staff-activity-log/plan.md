# Implementation Plan: the staff log of sign-ins and security events

**Branch**: `031-staff-activity-log` | **Date**: 2026-11-02 | **Spec**: [spec.md](spec.md)

## Summary

CoreX Activity stores the log (D-20 A14); WordPress and CoreX already raise the events; nothing writes them to the
log or shows them. `StaffActivityLog` writes them through CoreX's `ActivityService`; `StaffActivityScreen` reads them
back under Users, with the order-note search beside them.

## Technical Context

| Item | Value |
|---|---|
| Stack | WordPress 7.1.4, WooCommerce 11.1.0, CoreX v0.47.0 (Activity in corex-core and corex-config), WP 2FA 4.2.1 |
| Code | `mizzey-site/src/Security/StaffActivityLog.php` (the adapter), `StaffActivityScreen.php` (the screen) |
| Extension points | `wp_login`, `wp_login_failed` (WP 2FA reports a wrong code there as `authentication_failed`), `wp_logout`, `retrieve_password`, `after_password_reset`; CoreX's `ListenerProvider` for `LoginLockoutEvent`, reached on `corex_booted`; `admin_menu` |
| Data | CoreX's `activity_events` rows, area `security`, kinds `mizzey.*`, sensitivity `security`, retention 365 days |
| Address | CoreX's `ClientIpResolver`, the same the lockouts use behind the trusted proxy |
| Testing | `t71-staff-activity-log`, in process and one real failed sign-in on wp-login.php; mutation evidence |

## Constitution Check

| Rule | How it is met |
|---|---|
| ADR-0003, D-20 A14 | One authority: CoreX holds the record; Mizzey writes through its service and reads through its query |
| M-1, CX-03 | Only ADM-130, ADM-132 to ADM-134; no ROLE-10 audit trail |
| M-7 | Each criterion has an automated check; deliberate defects are caught |
| ADM-159 | The screen is staff only, in English |
