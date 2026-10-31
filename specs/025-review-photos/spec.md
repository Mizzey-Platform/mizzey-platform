# Feature Specification: photos in reviews

**Feature Branch**: `025-review-photos`

**Created**: 2026-10-31

**Status**: Draft

**Work type**: requirement

**Input**: User description: "continue until you finish all waves" (the owner, 31 October 2026), wave 3 of the Stage 3
build plan under D-22: the second part of #309 (specs/020 recorded it), D-23: "Photos in reviews as a recorded gift:
up to 5 images per review, no video, moderated with the review. Record it as a gift, not REV-06" (the owner, 30
October 2026). The function layer; the designed upload form and gallery wait for PRE-03b.

## Register trace [checked]

| ID | Scope | Stage | Register wording (short, verbatim) |
|---|---|---|---|
| REV-03 | P1 | S1 | Moderation before publication |

REV-03 is traced because the photos are moderated with their review. The photos themselves trace to no register row:
they are D-23's gift, never REV-06's delivery.

## Context rows (no obligation here)

| ID | Scope | Why it matters here |
|---|---|---|
| REV-06 | P2 | Photo and video reviews: not delivered by this gift, and not owed because of it (D-23) |
| REV-01, REV-02 | P1 | Who may review (D-17, specs/020): only they have a review to attach photos to |

## Open contract items

- None. The gift is told to the client in writing when Stage 3 is presented (D-23 item 2).

## Contractual acceptance criteria [checked]

| # | Criterion | Traces | Status |
|---|---|---|---|
| AC-1 | A review's photos are shown only once the review is approved, and leave view when it is not | REV-03 | provisional |

The gift's own behaviour (D-23 items 1 and 3) is checked below as engineering requirements, not as contractual
criteria.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Show it (Priority: Medium)

1. **Given** a customer whose review is held for moderation, **When** they attach up to 5 photos, **Then** they are
   kept with the review, and shown under it once a moderator approves it.

### Edge Cases

- A file that is not a JPEG, PNG or WebP picture, whatever its name says, is refused; so is one over 5 MB or 6000
  pixels a side, a sixth photo, another customer, or a review already published.
- A review sent back to moderation, or rejected, takes its photos out of view; a review deleted deletes them.

## Native coverage

| Need | Native mechanism | Verdict |
|---|---|---|
| Images on a review | None in WooCommerce | Gap: `ReviewPhotos` (our own code, D-24) |
| Moderation | WordPress's comment status | Native, used: a photo is served only while its review is approved |

## Requirements

### Functional Requirements

- **FR-001**: Only the reviewer MUST be able to attach photos, only while the review is held, up to 5 (D-23 item 1).
- **FR-002**: Each photo MUST be checked by its content (JPEG, PNG or WebP), at most 5 MB and 6000 pixels a side,
  then decoded and encoded again at most 2000 pixels a side, which removes its location and camera data (D-23 item 3).
- **FR-003**: Photos MUST be stored under random names in a folder the web server is told to refuse, and served only
  through the site, only while the review is approved; staff who moderate see held ones (AC-1).
- **FR-004**: Each photo MUST have alternative text: the reviewer's, or one naming the product (D-23 item 3).
- **FR-005**: Deleting a review MUST delete its photos.
- **FR-006**: Every message MUST be translated (FIX-04).

### Key Entities

- **Review photo**: file name, alternative text, width and height, kept on the review (comment meta).

## Optional safeguards (not owed)

- None.

## Future or Option C items (not built)

- Video, galleries, more than 5 photos, and the verified-purchase badge (REV-06, REV-07, P2).

## Success Criteria

- **SC-001**: No photo is ever public before its review is approved.

## Delivery stage

Contractual stage: S1 for REV-03, delivered with Stage 3 (D-22). The photos are a gift with no stage.

## Assumptions

- The caps (5 MB, 6000 pixels a side, 12 million pixels, kept at 2000 pixels) are set here, as D-23 item 3 asks the spec to set them.
- On a web server that does not read `.htaccess` (nginx), the folder is refused by a hosting rule; the random names
  and the site's own serving keep it closed meanwhile.
