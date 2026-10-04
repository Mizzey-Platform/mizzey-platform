# Checklist: requirements quality, before tasks are executed

Feature `002-bilingual-platform-baseline`, PBI #241. Run against `spec.md`, `plan.md` and `research.md` on
4 October 2026, before any implementation task.

The point of this checklist is not to agree with the spec. It is to try to break it.

## Scope discipline (M-1)

- [x] **Every register id is an obligation scope.** FIX-04, FIX-04a, NFR-04, NFR-04a, NFR-14 are all P1 in
      `docs/scope/register-ids.json`.
- [x] **Every scope and stage matches the register exactly.** Verified by `tools/scope_trace.py`, which fails on a
      mismatch and reports 0 problems.
- [x] **Every criterion is supported by the wording of the row it cites.** Checked one by one. AC-7 failed this
      test on the first draft: it asserted "a repeatable check proves it", which NFR-04a does not say. Corrected
      under clarification C-4, and the check moved to FR-007.
- [x] **No criterion traces an id outside the five.** Nine criteria, all tracing within the trace table.
- [x] **No row outside the five is built.** ADM-159, NFR-04b and NFR-03 are named as Context rows with their
      owning slice, and no criterion or task touches them.
- [x] **Does any criterion quietly expand a row? One did, and it is now labelled rather than implied.** AC-5
      requires a valid translation relationship with the English record as source. FIX-04 says "Multilingual
      storefront: English and Arabic with full LTR/RTL support" and does not mention translation identity.
      **Decided 4 October 2026**: AC-5 is kept, and is classified in the spec as **technical acceptance needed to
      deliver FIX-04 safely, explicitly not a restatement of the row**. It is not a client deliverable and adds no
      behaviour beyond FIX-04. The need is measured: a WPML duplicate shares its original's SKU, and A11 showed a
      translation group can detach an Arabic record from its English source.

## Clarity and testability

- [x] **Every criterion is checkable by someone else.** Eight by a scripted served request, one by a browser
      matrix.
- [x] **No criterion depends on a judgement call.** "Renders correctly" in AC-9 is the weakest, and it is NFR-14's
      own word. The browser matrix in C-5 bounds it to six browsers and two directions.
- [x] **Every functional requirement names its criterion.** FR-001 to FR-009 each cite one.
- [x] **Statuses use only the permitted vocabulary.** Nine `final`. Confirmed by the checker, which rejects any
      other wording.
- [x] **The success criteria are measurable.** SC-001 to SC-005 each name what is asserted and how.

## Honesty about evidence (M-7)

- [x] **Nothing claims to be verified that is not.** AC-9 is marked final as a criterion and unverified as a fact,
      with the reason.
- [x] **The three states are kept apart.** Workflow complete, technically verified and contractually accepted are
      separate, and this feature can reach the second at most.
- [x] **No staging claim is made from a command-line runtime.** The opposite problem was found and fixed: the
      first draft deferred the `/ar/` rendered-URL check to staging when the runtime can in fact serve it. The
      requirement was not weakened to suit the runtime; the runtime was configured to meet it, and a served
      request then returned `<html dir="rtl" lang="ar">`. **Only AC-9 needs staging.**
- [x] **Every "VERIFIED" row in Native coverage names its evidence.** Each cites the option, filter, table, file or
      request that produced it.
- [x] **Every measurement that is not yet a conclusion is marked as such.** The in-process `lang` attribute lag
      was recorded as a measurement to repeat, then resolved by a served request rather than by reasoning.

## Native first (M-4)

- [x] **Each custom component is justified by a recorded gap.** One production component, text domain loading,
      justified by two gaps: nothing in this engagement loads a domain, and CoreX loads only its own.
- [x] **Nothing is built that already works.** AC-3, AC-4 and AC-6 are met natively and receive tests only.
- [x] **The framework was inspected, not assumed.** CoreX v0.42.0 has no i18n layer, and the four load-bearing
      absences were each confirmed by a second independent search.
- [x] **No framework patch is proposed.** The client site supplies what CoreX lacks.

## Boundaries that the pilot and the probes bought

- [x] **No general multilingual metadata synchronisation.** Not planned, and listed under what is not done.
- [x] **No stock synchronisation.**
- [x] **No price synchronisation**, and no programmatic product price write. This feature writes no product data
      at all.
- [x] **SKU is never used as multilingual identity.** AC-5 requires translation identity explicitly, for the
      reason the pilot measured: a WPML duplicate shares its original's SKU.
- [x] **Product Cost is not reopened.** No file from `specs/001-product-cost-capture` is touched.
- [x] **No migration logic.** Migration sequencing is owned by another PBI.

## The things most likely to go wrong

- [x] **Could the default language drift to Arabic?** The risk is real: a stored preference or session value would
      do it. AC-1 requires resolution from the URL, its scenario tests a second request after an Arabic one, and
      S-2 proposes keeping that assertion in every suite.
- [x] **Could the admin become Arabic?** Not by anything here. CoreX does not touch admin locale, and this feature
      adds no locale filter. FR-008 asserts the boundary.
- [x] **Could the baseline script produce an environment that looks configured and is not?** Yes, and it nearly
      did. `wp rewrite flush --hard` reports success and writes no `.htaccess` in this runtime, because
      `got_mod_rewrite()` is false under CGI PHP. The script must write the file and the scenario must assert a
      served request, not an option value.
- [x] **Could a path be changed that this work type may not touch?** Checked against `PATH_POLICY` before
      planning. The string check moved from `tools/` to `tools/tests/` for exactly this reason.
- [x] **Could the check pass by doing nothing?** Its own tests include a deliberately hard-coded string, so a
      check that never fires fails its own suite.

## Decided since the first pass

- [x] **C-5, the mobile browser list.** **Approved 4 October 2026**: current Chrome, Safari, Edge and Firefox on
      desktop, current Chrome on Android, current Safari on iOS, both reading directions on every row. Internet
      Explorer, Opera Mini, in-app browsers and named device models are excluded by the same decision.
- [x] **AC-5's classification.** Decided, and recorded under Scope discipline above.
- [x] **S-1 and S-2.** **Approved**, and kept classified as engineering safeguards and evidence rather than
      client deliverables.

## Still open, and not blocking

- [ ] **AC-9 cannot be completed in this feature.** Staging does not exist, and it is owned by #244. The browser
      matrix is written with every row unexercised, and the feature reports AC-9 as not verified rather than
      quietly dropping it. **This is the only item this feature cannot close.**

## Result

**Pass.** Everything that was open at the first pass is decided. One item remains, a dependency on #244 that is
recorded rather than worked around.
