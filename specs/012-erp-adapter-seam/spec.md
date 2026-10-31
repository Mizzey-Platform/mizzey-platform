# Feature Specification: ERP adapter seam

**Feature Branch**: `012-erp-adapter-seam`

**Created**: 2026-10-31

**Status**: Draft

**Work type**: requirement

**Input**: User description: "continue until you finish all waves" (the owner, 31 October 2026), wave 1 of the Stage 3
build plan under D-22. E-ERP-1 (#245). No design dependency; no ERP call (M-3).

## Register trace [checked]

| ID | Scope | Stage | Register wording (short, verbatim) |
|---|---|---|---|
| ERP-01 | P1 key | S1 | The ERP is the source of truth for stock; the store does not operate an independently maintained stock balance that can disagree with it |
| ERP-02 | P1 | S1 | The stock available to customers reflects your ERP inventory throughout the buying journey |
| ERP-10 | P1 | S1 | The integration is specified in the ERP Integration Specification (PRE-09), approved by you separately, and then built and tested against a test environment |
| INT-16 | P1 key | S1 | Every integration behind an adapter with retries, logging and webhook handling |

Story: Functional Specification MS-SPC-2026-032 v1.4, US-27-06 (Observability) for INT-16; ERP-01 and ERP-02 are
restated in the product, cart and checkout stories (US-04, US-05, US-07).

## Context rows (no obligation here)

| ID | Scope | Why it matters here |
|---|---|---|
| ERP-03, ERP-06, ERP-07, ERP-08 | P1-E | Separate PBIs; their mechanism waits for PRE-09 (M-3) |
| CART-10, CHK stock validation | P1 | #306 and #255 call this seam; the stock safeguard of D-19 item 5 is theirs |
| PRE-09 | Pre-build | The ERP Integration Specification: external key (M16), stock per product or per variant (M17), lookup, freshness, caching, reservation, decrement timing, reversal, idempotency, timeout lookup, concurrency, reconciliation |

## Open contract items

- PRE-09 is not approved. Every ERP mechanism waits for it; this slice chooses none.

## Contractual acceptance criteria [checked]

| AC | Criterion | Register | Status |
|---|---|---|---|
| AC-1 | Every stock question and every stock change the store makes passes through one boundary, behind an adapter that can be replaced without changing its callers | INT-16, ERP-01 | provisional |
| AC-2 | A product's English and Arabic records, and a variation's, resolve to one stock identity: the translation group's source-language record | ERP-01 | provisional |
| AC-3 | When the stock source cannot answer, the answer is "unknown", and unknown stock is never sellable | ERP-01, ERP-02 | provisional |
| AC-4 | A failed attempt is retried a bounded number of times; a refusal is not retried | INT-16 | provisional |
| AC-5 | A retried stock change carries the same operation key, so it is applied once | INT-16, ERP-01 | provisional |
| AC-6 | Every attempt and its outcome is logged | INT-16 | provisional |
| AC-7 | Webhook handling, and the real adapter, tested against an ERP test environment | INT-16, ERP-10 | pending PRE-09 |
| AC-8 | The stock available to customers reflects ERP inventory through product, cart and checkout | ERP-02 | pending PRE-09 |

AC-1 to AC-6 are provisional because they are verified against a mock and a contract, not a real ERP (M-3).

## User Scenarios & Testing *(mandatory)*

### User Story 1 - The store asks one place about stock (Priority: High)

The cart and checkout (#306, #255) need to know whether an item can be sold, and later to change stock. They ask one
gateway, never WooCommerce's stock field directly and never the ERP's API directly.

**Why this priority**: ERP-01 is key; every stock path in Stage 3 depends on it.

**Independent Test**: bind a mock adapter, ask for the stock of an English product and of its Arabic record; both
answers come from the same stock identity through the mock.

**Acceptance Scenarios**:

1. **Given** a product and its Arabic record, **When** either is asked about, **Then** the same stock identity is
   asked, and the same answer comes back.
2. **Given** a variation and its Arabic record, **When** either is asked about, **Then** they share one identity,
   distinct from the parent product's.
3. **Given** no adapter is configured, **When** stock is asked for, **Then** the answer is unknown and not sellable.

### User Story 2 - A failing stock source never sells blind (Priority: High)

**Independent Test**: a mock that fails, refuses, or fails after applying a change.

**Acceptance Scenarios**:

1. **Given** a source that fails twice and then answers, **When** stock is asked for, **Then** the third attempt's
   answer is used, and three attempts are logged.
2. **Given** a source that keeps failing, **When** the attempts are used up, **Then** the answer is unknown.
3. **Given** a source that refuses (for example an item it does not know), **When** stock is asked for, **Then** it
   is not retried and the answer is unknown.
4. **Given** a change the source applies and then times out on, **When** it is retried, **Then** the retry carries
   the same operation key and the source applies the change once.

### Edge Cases

- A post that is not a product, or a product that no longer exists: no identity; unknown.
- A product not yet registered with WPML while WPML is active: no identity, rather than a guess (see the cost sync's
  record of WPML answering with a neighbouring group for an unregistered post).
- WPML not active: each record is its own identity.
- A change of zero, or a negative quantity asked for: refused by the gateway before the source is called.
- The gateway changes no WooCommerce stock field itself; local stock writers are closed by #255 and #306 (D-19 item 5).

## Native coverage

| Need | Native mechanism | Verdict |
|---|---|---|
| Stock source of truth | WooCommerce's own stock field and holds | Gap: ERP-01 makes the ERP authoritative; WooCommerce has no external stock source (D-19, ADR-0003 consequences) |
| Translation identity | WPML translation group (`wpml_element_trid`, `wpml_get_element_translations`) | Native, used as is |
| Logging | WooCommerce's logger (`wc_get_logger()`, WooCommerce > Status > Logs) | Native, used as is (CoreX publishes no logging API for plugins, ADR-0003) |
| Retries | None for this purpose | Gap: a bounded retry in the gateway |

## Requirements

### Functional Requirements

- **FR-001**: One gateway MUST answer every stock question and carry every stock change; callers name a WooCommerce
  product or variation id in either language (AC-1).
- **FR-002**: The gateway MUST resolve the id to the translation group's source-language record and its kind
  (product or variation) before asking the adapter (AC-2).
- **FR-003**: The adapter MUST be supplied through one filter, and the default MUST be an unconfigured adapter that
  refuses, so nothing sells on unknown stock until PRE-09's adapter is bound (AC-1, AC-3).
- **FR-004**: An attempt that fails MUST be retried up to a bounded number of attempts (default 3); a refusal MUST
  NOT be retried; when no attempt succeeds the answer MUST be unknown (AC-3, AC-4).
- **FR-005**: A stock change MUST carry an operation key chosen by the caller and kept across the gateway's retries
  (AC-5). The adapter contract requires a key to be applied once.
- **FR-006**: Every attempt MUST be logged with the operation, identity, attempt number, outcome and adapter name;
  failures as warnings (AC-6).
- **FR-007**: The gateway MUST NOT write WooCommerce's stock field.

### Key Entities

- **Stock item**: the identity the adapter is asked about: the source-language record's id, its kind and its SKU.
- **Stock answer**: known with a quantity, or unknown with a reason.
- **Operation key**: the caller's name for one stock change, the same on every retry.

## Optional safeguards (not owed)

- None.

## Future or Option C items (not built)

- The real adapter, webhook handling and reconciliation: PRE-09 and the ERP PBIs.

## Success Criteria

- **SC-001**: No stock answer is ever "sellable" when the source did not answer.
- **SC-002**: The two language records of any product or variation never reach the adapter as two identities.
- **SC-003**: A retried change is never applied twice by a source that honours the contract.

## Delivery stage

Contractual stage: S1, delivered with Stage 3 (D-22). Engineering timing: wave 1 of the Stage 3 build plan, from 31
October 2026. Final acceptance waits for PRE-09 and the client's approval.

## Assumptions

- Stock is identified per product or per variation as WooCommerce models it; whether the ERP keys stock per product or
  per variant (M17) is the adapter's mapping, decided in PRE-09.
- The source-language record is the identity because it is the one the store maintains (A11, sources first).
