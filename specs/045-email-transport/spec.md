# Feature Specification: the transactional email service

**Feature Branch**: `045-email-transport`

**Created**: 2026-11-02

**Status**: Draft

**Work type**: requirement

**Input**: Issue #284 ("Transactional email service, Google Analytics with Tag Manager, Meta Pixel and the Merchant
Center feed connected"), its email row. Its other three rows (INT-09, INT-10, INT-12) are the same integrations as
#288 and are traced in specs/043. The owner's instruction of 2 November 2026 puts it in the work built with a
stand-in: the provider account and the domain's records are the client's (I-10, OD-18), and staging keeps capturing
all mail.

## Register trace [checked]

| ID | Scope | Stage | Register wording (short, verbatim) |
|---|---|---|---|
| INT-05 | P1 | S1 | Transactional email service |

## Context rows (no obligation here)

| ID | Scope | Why it matters here |
|---|---|---|
| NOTF-01 to NOTF-08 | P1 | The emails the service carries (email-only notifications at launch) |
| NFR-10 | P1-L | Failures raised to operations (specs/039) |
| INT-09, INT-10, INT-12 | P1, P1-L | #284's other rows, built in specs/043 |
| INT-03, INT-06 to INT-08 | P2 | SMS and other channels: not built |

## Open contract items

- **I-10.** The provider account (any provider with an SMTP relay) and its credentials, set on the server.
- **OD-18.** The sending address and the domain's SPF, DKIM and DMARC records, set at the provider and in DNS.

## Contractual acceptance criteria [checked]

| # | Criterion | Traces | Status |
|---|---|---|---|
| AC-1 | Every email the store sends leaves through the provider's relay, behind an adapter, with the store's sender | INT-05 | pending I-10 |
| AC-2 | A message the relay refuses is logged and raised to operations | INT-05 | provisional |
| AC-3 | Staff can send a test message to check delivery | INT-05 | provisional |

## User Scenarios & Testing *(mandatory)*

### User Story 1 - The provider is connected (Priority: High)

1. **Given** the relay's host, port, user and password in the server's configuration, **When** the store sends an
   order email, **Then** it leaves through the relay from the store's address; **When** the relay refuses it,
   **Then** operations hear of it.

### Edge Cases

- No relay set: WordPress sends as before (development, and staging, which captures mail first).
- The password: never in the database, the repository, a screen or a log.
- A recipient's address in an error: masked in the log.

## Native coverage

| Need | Native mechanism | Verdict |
|---|---|---|
| Sending | WordPress's `wp_mail` (PHPMailer), which WooCommerce uses | Native; the relay is set on it |
| The sender | WooCommerce, Settings, Emails | Native; used for every email |
| The relay | None (no SMTP plugin, D-24) | Gap: `Transport` |

## Requirements

### Functional Requirements

- **FR-001**: When the server names a relay (`MIZZEY_SMTP_HOST`, with `_PORT`, `_SECURE`, `_USER`, `_PASSWORD`), every
  message MUST be sent through it, TLS by default; otherwise nothing MUST change (AC-1).
- **FR-002**: WordPress's own messages MUST take WooCommerce's sender address and name; a sender set on purpose MUST be
  kept (AC-1).
- **FR-003**: A failed message MUST be logged under `mizzey-email` with addresses masked (AC-2).
- **FR-004**: Settings, Email delivery MUST show the relay and the sender, never the password, and send a test message
  to the person asking, behind `manage_options` and a nonce (AC-3).

## Optional safeguards (not owed)

- None.

## Future or Option C items (not built)

- SMS and WhatsApp (INT-03, INT-06 to INT-08, P2); a provider's bounce webhooks.

## Success Criteria

- **SC-001**: On staging, with a sandbox relay and capture switched off for one test, the test message arrives and an
  order email leaves through the relay; refused mail appears in the log and as a notification.

## Delivery stage

Contractual stage: S1. Engineering timing: November 2026 (D-22).

## Assumptions

- Every transactional provider the client may choose (Amazon SES, Postmark, Brevo, Mailgun and others) offers an SMTP
  relay, so the adapter does not depend on the choice.
