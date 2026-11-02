# Feature Specification: the staff log of sign-ins and security events

**Feature Branch**: `031-staff-activity-log`

**Created**: 2026-11-02

**Status**: Draft

**Work type**: requirement

**Input**: Slice E-ADM-9, issue #275 ("Staff accounts with two-factor sign-in and a searchable log"). ADM-131 (two-factor
sign-in) is specs/008, merged and on staging; this spec covers the log and the staff accounts. D-20 row A14: the log
is CoreX Activity behind a thin Mizzey adapter, with a CoreX task for any framework gap. The owner's instruction of
2 November 2026 puts it third in the work that needs no client input.

## Register trace [checked]

| ID | Scope | Stage | Register wording (short, verbatim) |
|---|---|---|---|
| ADM-130 | P1-L | S1 | Create staff users, roles and granular permissions |
| ADM-132 | P1-L | S1 | Searchable log of logins, security events and order notes. The custom audit trail of sensitive operations is ROLE-10, deferred |
| ADM-133 | P1 | S1 | The audit trail cannot be deleted from the interface by ordinary users |
| ADM-134 | P1 | S1 | Logins and significant security events recorded |

ADM-130 is P1-L: the standard commerce role set (ROLE-09, specs/013), with the Customer Service, Warehouse, Marketing
and Accountant profiles DEF. ADM-132 is P1-L: "Logins, security events and order notes, recorded and searchable in the
standard administration", not "a custom audit trail of refunds, price, stock and permission changes" (ROLE-10, DEF) and
not forensic reporting or log analytics (Feature Register section 1.2).

## Context rows (no obligation here)

| ID | Scope | Why it matters here |
|---|---|---|
| ADM-131 | P1 | Two-factor sign-in (specs/008); a wrong second factor is one of the events recorded here |
| ROLE-09 | P1 | The launch roles (specs/013) |
| ENT-17 | P1-L | The audit log entity; CX-03 (D-12) keeps it narrow: only rows that ask for a record write to it |
| NFR-05 | P1 | The sign-in protection and lockouts are CoreX's (specs/009) |

## Open contract items

- **CX-03** (resolved by the owner in D-12, not client-confirmed): the log records sign-ins and security events only;
  ROLE-10 stays DEF.
- **ROLE-06** (CX-01): the Accountant role waits for the client's acknowledgement; ADM-130 here uses the roles that
  exist.
- **Retention.** One year, a working default (D-10) the client may change.

## Contractual acceptance criteria [checked]

| # | Criterion | Traces | Status |
|---|---|---|---|
| AC-1 | The owner creates staff accounts and gives them a launch role and its permissions | ADM-130 | provisional |
| AC-2 | Staff sign-ins and sign-outs, failed sign-ins (a wrong password or a wrong second factor, for any account), lockouts and password resets are recorded with the account, the time and the address | ADM-134 | provisional |
| AC-3 | The log is searchable in the administration by account, event, outcome and dates, and order notes are searchable by their text | ADM-132 | provisional |
| AC-4 | No entry can be changed or deleted from the interface, and only the owner and administrators can see the log | ADM-133 | provisional |

## User Scenarios & Testing *(mandatory)*

### User Story 1 - What happened to this account? (Priority: High)

1. **Given** a staff account that was locked out, **When** the owner searches the log for it, **Then** the failed
   sign-ins, the lockout with its address and time, and the password reset that followed are listed.

### User Story 2 - Find what was said on an order (Priority: Medium)

1. **Given** order notes, **When** the owner searches their words, **Then** the matching notes are listed with their
   orders.

### Edge Cases

- A failed sign-in for an account that does not exist: recorded with what was typed.
- CoreX not booted: nothing is recorded and the sign-in is not affected.
- A shop manager opening the log's address: refused.

## Native coverage

| Need | Native mechanism | Verdict |
|---|---|---|
| Staff accounts, roles, permissions | WordPress users and roles; the launch roles (specs/013) | Native plus specs/013 |
| A store for the log, with retention | CoreX Activity (`activity_events`, retention and pruning) | CoreX (D-20 A14) |
| Recording sign-ins and security events | Neither WordPress nor CoreX records them in Activity | Gap: a thin adapter on WordPress's sign-in actions and CoreX's lockout event |
| Reading the log in the administration | CoreX's read-only REST routes; its admin screens are not shipped (D-20 A4, A5) | Gap: one read-only screen under Users |
| Searching order notes | Notes are shown per order only | Gap: a text search on the same screen |
| Free-text search inside CoreX Activity | Not offered by CoreX's query | CoreX gap, raised as a CoreX task; the account, event, outcome and date filters meet the row meanwhile |

## Requirements

### Functional Requirements

- **FR-001**: The adapter MUST record into CoreX Activity, area security: a staff sign-in and sign-out, every failed
  sign-in with the reason (password or second factor), CoreX's lockout, a password reset requested and completed
  (AC-2).
- **FR-002**: A customer's successful sign-in MUST NOT be recorded (AC-2).
- **FR-003**: Each entry MUST keep the account, what was typed, the time, the outcome and the visitor's address as
  CoreX's sign-in protection resolves it behind the trusted proxy (AC-2).
- **FR-004**: A failure to record MUST NOT affect the sign-in (AC-2).
- **FR-005**: The Users menu MUST have an Activity log screen, for `manage_options` only, with filters by account,
  event, outcome and dates, and a search of order notes (AC-3, AC-4).
- **FR-006**: The screen MUST only read; no route Mizzey adds may change or delete an entry (AC-4).
- **FR-007**: Nothing beyond sign-ins and security events MUST be written to the log (CX-03, ROLE-10 DEF).

## Optional safeguards (not owed)

- None.

## Future or Option C items (not built)

- The audit trail of refunds, prices, stock and permission changes (ROLE-10, DEF); forensic reporting, log analytics
  and exports; the deferred staff profiles (ROLE-03 to ROLE-05, ROLE-10).

## Success Criteria

- **SC-001**: On staging, a wrong password, a wrong second factor and a lockout on a test staff account are found in
  the log by searching the account.

## Delivery stage

Contractual stage: S1. Engineering timing: November 2026 (D-22, D-27). Delivered and offered for acceptance in
contract order.

## Assumptions

- "Staff" is an account that can edit posts, manage the store or edit orders; everyone else is a customer.
- The log is the owner's and the administrators' to read, as CoreX's own activity routes require `manage_options`.
