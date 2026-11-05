# Feature Specification: the agency's restricted access

**Feature Branch**: `064-agency-access`

**Created**: 2026-11-05

**Status**: Draft

**Work type**: requirement

**Input**: #298. An agency the client engages adds tracking and changes content without being able to install
software, edit templates or files, or reach customer data beyond campaign needs; its actions are recorded; it tests on
staging first; and the client has a written protocol to give it. CX-02 is resolved by the owner (D-12): a restricted
Marketing access profile exists at launch only as far as these rows need; the deferred ROLE-05 profile is not built.

## Register trace [checked]

| ID | Scope | Stage | Register wording (short, verbatim) |
|---|---|---|---|
| MKT-21 | P1 | S1 | The Marketing role cannot install software, edit templates, or edit files |
| MKT-22 | P1-L | S1 | The Marketing role cannot access customer personal data beyond campaign needs |
| MKT-23 | P1 | S1 | Google Tag Manager is the sanctioned route for all tracking scripts. No third party edits site code directly |
| MKT-24 | P1-L | S1 | Third parties receive staging access for testing before any production change |
| MKT-25 | P1-L | S1 | All third-party administrative actions recorded in the audit log |
| MKT-26 | DLV | S1 | Third-Party Access Protocol: a short written document you give to any agency you engage |

## Context rows (no obligation here)

| ID | Scope | Why it matters here |
|---|---|---|
| ROLE-05 | DEF | The full Marketing profile: not built (CX-02, D-12) |
| ROLE-10 | DEF | Not built |
| MKT-11 | P3 | Not built |
| MKT-01, INT-09 | P1 | The Tag Manager container (specs/043), whose setting this role may change |
| ADM-132, ADM-134 | P1 | The staff log (specs/031) the agency's actions are recorded in |

## Open contract items

- CX-02: resolved by the owner (D-12), not client-confirmed. This slice builds only the minimal access these rows need.
- MKT-26 is a document for the client: its acceptance follows the client's feedback (CR-13).

## Contractual acceptance criteria [checked]

| # | Criterion | Traces | Status |
|---|---|---|---|
| AC-1 | An account of the Marketing (agency) role holds no capability to install, activate, update or edit plugins or themes, edit templates, menus or files, import, export or change site settings, and its plugin, theme, editor and settings screens refuse it | MKT-21 | provisional |
| AC-2 | It holds no user, order, customer, report or product capability; the users, orders, reports and store settings screens refuse it, and the REST API gives it no customer, order, analytics or user record | MKT-22 | provisional |
| AC-3 | It names the Tag Manager container in Settings, Marketing tags; a script it puts into content is taken out when saved, so tracking goes through Tag Manager only | MKT-23 | provisional |
| AC-4 | It publishes and edits pages and posts, edits promotional banners and uploads media | MKT-23 | provisional |
| AC-5 | Each content, media and tracking change it makes is in the staff log under its account, beside its sign-ins | MKT-25 | provisional |
| AC-6 | The owner and admin roles keep the Tag Manager setting | MKT-23 | provisional |
| AC-7 | The role ships with every release, so the same restricted account can be made on staging and tested there before production; the protocol tells the agency to | MKT-24 | provisional |
| AC-8 | The Third-Party Access Protocol is written for the client to give to an agency | MKT-26 | provisional |

Provisional: verified by automated tests (AC-1 to AC-6) and by the release and document (AC-7, AC-8); acceptance
follows manual testing on staging and the client's approval.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - The agency does its work and nothing else (Priority: High)

1. **Given** an agency account, **When** it opens Settings, Marketing tags, **Then** it names the container; **When**
   it publishes a campaign page or edits a banner, **Then** it can (AC-3, AC-4).
2. **Given** the same account, **When** it opens Plugins, the theme or site editor, Users or Orders, **Then** each
   screen refuses it; **When** it asks the REST API for customers or orders, **Then** it is refused (AC-1, AC-2).
3. **Given** a script pasted into a page, **When** the page is saved, **Then** the script is gone (AC-3).

### User Story 2 - The owner sees what the agency did (Priority: High)

1. **Given** the agency changed a page and the container, **When** the owner opens the Activity log, **Then** both are
   there under the agency's account (AC-5).

### Edge Cases

- WooCommerce's analytics screen is a shell whose figures come from its routes: the routes refuse the account.
- WordPress's public author list answers anyone; it holds no customer data.
- An admin's edit of a page the agency made is not recorded as the agency's.

## Native coverage

| Need | Native mechanism | Verdict |
|---|---|---|
| A role with chosen capabilities | WordPress roles and capabilities | Native, used |
| Screens and routes refusing it | WordPress's and WooCommerce's own capability checks | Native, checked |
| Scripts kept out of content | WordPress's kses for accounts without `unfiltered_html` | Native, checked |
| The tracking setting for this role | The setting checked `manage_woocommerce`, which brings orders and customers | Gap: a capability of its own |
| Recording content and setting changes | The staff log records sign-ins only (CX-03) | Gap: the agency's actions recorded |

## Requirements

### Functional Requirements

- **FR-001**: The Marketing (agency) role MUST hold only content, media and tracking capabilities (AC-1 to AC-4).
- **FR-002**: The Tag Manager setting MUST be checked against its own capability, held by this role, the owner and the
  admin (AC-3, AC-6).
- **FR-003**: Every content, media and tracking change by an account of this role MUST be recorded in the staff log
  (AC-5).
- **FR-004**: The role MUST be written by the release, so it exists wherever the release runs (AC-7).

### Key Entities

- **Marketing (agency) role**: `mizzey_marketing`; capability `mizzey_manage_tracking`.

## Optional safeguards (not owed)

| Id | Safeguard | Why | Custom code? |
|---|---|---|---|
| S-1 | Two-factor sign-in required for the agency role too: **approved by the owner on 5 November 2026** | ADM-131 names administrators and shop managers only; an outside account is a likely target | No: the role is added to the security baseline's two-factor roles (`BaselineReport::TWO_FACTOR_ROLES`); t104 checks it |

## Future or Option C items (not built)

| Item | Where it sits |
|---|---|
| The full Marketing profile (campaign reports, customer segments) | ROLE-05, DEF |

## Success Criteria

- **SC-001**: An agency can be given access in minutes, and cannot reach software, files or customers.

## Delivery stage

Contractual stage: S1, with #298. Engineering timing: 5 November 2026.

## Assumptions

- Product content stays with the store's staff; the agency's content is pages, posts, banners and media.
