# Implementation Plan: the agency's restricted access

**Branch**: `064-agency-access` | **Date**: 2026-11-05 | **Spec**: [spec.md](spec.md)

## Summary

`MizzeySite\Roles\MarketingRole` writes the role `mizzey_marketing` (content, media and `mizzey_manage_tracking`, once
per version, as `RefundCapability` does) and gives the tracking capability to the owner and admin roles. `TagManager`
checks that capability instead of `manage_woocommerce`. The role's content, media and tracking changes are recorded
through `StaffActivityLog::recordAction()`, with four new kinds the Activity log screen lists. The Third-Party Access
Protocol is drafted for the client (MKT-26).

## Constitution Check

| Rule | How it is met |
|---|---|
| M-1 | MKT-21 to MKT-26's wording; CX-02 as D-12 resolved it, ROLE-05 not built |
| M-4 | WordPress roles, capability checks and kses; only the tracking capability and the recording are ours |
| M-7 | Every criterion has a t104 check; deliberate defects are caught (`evidence/mutations.txt`) |

## Criterion to test map

| AC | `t104-agency-access` asserts |
|---|---|
| AC-1 | No plugin, theme, template, file, update, import, export, settings or `unfiltered_html` capability; eight admin screens refuse it over HTTP |
| AC-2 | No user, order, customer, report or product capability; four screens refuse it; REST customers, orders, analytics and users in edit context refuse it |
| AC-3 | Settings, Marketing tags opens and saves a container over HTTP; a script in a page is taken out |
| AC-4 | A page published and edited over REST; a banner editable; media allowed |
| AC-5 | The staff log holds its page saves, media and tracking change; an admin's edit is not recorded as the agency's |
| AC-6 | The owner and admin roles hold the tracking capability |
