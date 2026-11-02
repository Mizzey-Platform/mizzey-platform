# Feature Specification: observability

**Feature Branch**: `039-observability`

**Created**: 2026-11-02

**Status**: Draft

**Work type**: requirement

**Input**: Slice E-NFR-2, issue #270 ("Application logs, error monitoring, integration logs"). No predecessor. D-21
(monitoring, serving NFR-10): lockouts and failed scheduled actions alerted. D-20 A12: CoreX's notifications reach
only the staff responsible for operational incidents. The owner's instruction of 2 November 2026 puts it in the set
that needs no client input.

## Register trace [checked]

| ID | Scope | Stage | Register wording (short, verbatim) |
|---|---|---|---|
| NFR-10 | P1-L | S1 | Observability: Application logs, error monitoring, integration logs, admin audit logs |

P1-L with no reduction written in Part One 1.2; Part One 1.6 at ROLE-10 sets the boundary: standard platform and
hosting logs, no custom operational audit trail.

## Context rows (no obligation here)

| ID | Scope | Why it matters here |
|---|---|---|
| INT-16 | P1 | Integration attempts logged by the stock seam (specs/012) |
| ADM-132, ADM-134 | P1-L, P1 | The staff log of sign-ins and security events (specs/031): the admin audit log |
| ROLE-10 | DEF | The sensitive-operation audit: not built |
| NFR-05 | P1 | Lockouts are raised by CoreX's own producer |

## Open contract items

- **OD-27.** The hosting logs (web server, PHP) are the production host's; staging's are the developer's server.

## Contractual acceptance criteria [checked]

| # | Criterion | Traces | Status |
|---|---|---|---|
| AC-1 | A fatal application error is written to the application log and raised to the staff responsible for operations | NFR-10 | provisional |
| AC-2 | A failed scheduled task is logged and raised | NFR-10 | provisional |
| AC-3 | An integration's failure is in its own log and raised | NFR-10 | provisional |
| AC-4 | Administrative actions are logged: sign-ins and security events (specs/031) and order notes | NFR-10 | provisional |

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Something broke at night (Priority: High)

1. **Given** the stock source stops answering, **When** an order asks for stock, **Then** the stock log has the failed
   attempts and the operations staff get one notification pointing at the logs, not one per request.

### Edge Cases

- The same failure many times an hour: one notification.
- A message containing a card-like number: masked before it is logged or raised.
- A warning that does not end the request: not raised.
- Another plugin's error: left to its own log.

## Native coverage

| Need | Native mechanism | Verdict |
|---|---|---|
| Application and integration logs, retention | WooCommerce's logger (Status, Logs) | Native |
| Notifications to operations staff | CoreX notifications (D-20 A12) | CoreX |
| Lockout alerts | CoreX's lockout producer | CoreX |
| Fatal errors, failed Action Scheduler tasks, integration errors raised | None | Gap: one monitor |

## Requirements

### Functional Requirements

- **FR-001**: A fatal error MUST be logged with its place inside the site and raised (AC-1).
- **FR-002**: A failed or timed-out scheduled action MUST be logged and raised (AC-2).
- **FR-003**: An error logged by a Mizzey integration source MUST be raised (AC-3).
- **FR-004**: One kind of failure at one place MUST be raised at most once an hour.
- **FR-005**: No card-like number and no absolute server path MUST be logged or raised by the monitor.
- **FR-006**: A failure of the monitor itself MUST NOT affect the request.

## Optional safeguards (not owed)

- None.

## Future or Option C items (not built)

- An external error-tracking service; a custom operational audit trail (ROLE-10).

## Success Criteria

- **SC-001**: On staging, a forced error and a failed integration call each appear in their log and as a
  notification.

## Delivery stage

Contractual stage: S1. Engineering timing: November 2026 (D-22, D-27).

## Assumptions

- The operations staff are the holders of CoreX's operations ability (the owner and administrators).
