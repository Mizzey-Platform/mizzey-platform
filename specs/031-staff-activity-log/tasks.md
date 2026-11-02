# Tasks: the staff log of sign-ins and security events

**Spec**: [spec.md](spec.md) | **Plan**: [plan.md](plan.md)

- [x] T001 `StaffActivityLog`: sign-in, sign-out, failed sign-in and second factor, lockout, password reset, written to CoreX Activity with the account and address; customers' sign-ins left out; a write failure never stops a sign-in (AC-2)
- [x] T002 `StaffActivityScreen`: Users, Activity log, `manage_options` only, filters by account, event, outcome and dates, order-note search, read only (AC-3, AC-4)
- [x] T003 `t71-staff-activity-log` and mutation evidence (AC-1 to AC-4)
- [ ] T004 CoreX task: free-text search in Activity's query (a framework gap, D-20 A14)
- [ ] T005 Staging: a wrong password, a wrong second factor and a lockout on a test staff account found in the log through the browser (DOD-04)
