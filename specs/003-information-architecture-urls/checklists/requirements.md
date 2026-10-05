# Checklist: requirements quality, before tasks are executed

Feature `003-information-architecture-urls`, PBI #242. Run against `spec.md`, `research.md`, `plan.md` and
`tasks.md` on 4 October 2026, before any implementation task.

The point is not to agree with the spec. It is to try to break it.

## Scope discipline (M-1)

- [x] **All thirty ids are obligation rows.** 28 P1, two P1-L, verified against `docs/scope/register-ids.json`.
- [x] **Scope and stage match the register exactly.** Verified by `tools/scope_trace.py`, 0 problems.
- [x] **Scope class and Stage computed, not inferred.** `mixed` and `S1`, from the thirty ids, matching the board.
- [x] **No range is used as a citation.** Thirty exact ids. `IA-01 to IA-36` appears nowhere as scope.
- [x] **The excluded rows are named with their reason.** IA-12, IA-13, IA-34, IA-36 (P2), IA-22 (DEF), and
      IA-23, IA-33 (P1-L staged S2, owned by E-FND-2b and **not removed from scope**).
- [x] **No criterion traces an id outside the thirty.**
- [x] **Rows owned by other slices are named as boundaries, not built.** MKT-12, MKT-15, MKT-16, MKT-18, ADM-41,
      SSC-27, ADM-159, the NAV rows, PRE-07 and PRE-08.
- [x] **Does any criterion quietly expand a row?** The hardest case is **NFR-03**. Its eight words became nine
      criteria, AC-242-08 to G-1, and each is checked against the wording: clean URLs, meta, sitemap, robots,
      canonical, breadcrumbs, structured data, alt text, plus G-1 for the duplicate-URL risk that "clean" implies
      and that is the failure mode most likely to appear. **G-1 is the one to challenge**, and it is kept
      because a URL that is clean and duplicated fails the obligation in substance.
- [x] **Is "information architecture" read correctly?** Checked against the **SRS**, not inferred from the PBI
      title. SRS §5 is a page inventory with no URL content, so the page rows oblige existence and reachability
      and #242 owns the runtime IA, URL and SEO output assigned to its registered rows, while separately registered SEO and admin-control rows keep their own ownership. **This is the reading most likely to be wrong, and it is sourced.**

## Clarity and testability

- [x] **Every criterion is checkable by someone else.** All sixteen by served HTTP from a scripted baseline.
- [x] **No criterion depends on a judgement call.** The weakest wording is AC-242-12's "available on the storefront
      pages whose position in the hierarchy they describe", bounded by naming the pages in T-12.
- [x] **Each functional requirement names its criterion.** FR-001 to FR-012.
- [x] **Statuses use only the permitted vocabulary.** Sixteen `final`, confirmed by the checker.
- [x] **The success criteria are measurable.** SC-001 to SC-007 each name what is asserted and how.
- [x] **No criterion is "URL structure" in the abstract.** Each names a behaviour. This was the specific risk the
      brief called out.

## Honesty about evidence (M-7)

- [x] **Nothing claims to be verified that is not.** Structured data is `PREVIOUSLY TESTED`, not `VERIFIED`,
      because the class exists but was not observed in output: the runtime had no products.
- [x] **The three states are kept apart** in the spec and in T-14.
- [x] **No staging claim is manufactured, and none is dodged.** C-4 separates the obligations: all eight are
      verifiable locally by served HTTP, so **#244 is not required to start**. **DOD-04 still requires manual testing on staging for closure**, which is named as the remaining dependency rather than dismissed. The issue's phrase "on staging"
      was **not** converted wholesale into a blocker, which was the explicit instruction.
- [x] **No browser matrix is invented.** None of these thirty rows obliges browser rendering. #241 needed one
      because NFR-14 named browsers; #242 does not, and T-12 says so.
- [x] **Every VERIFIED row in Native coverage names its evidence**, including two that cite WordPress core's own
      source rather than an observation.

## Native first (M-4)

- [x] **Each custom component is justified by a recorded gap.** Three, two of them proved by reading core's
      source, one of them **deferred pending T-01's measurement** rather than assumed to need code.
- [x] **Nothing is built that already works.** Five of eight NFR-03 obligations are native and untouched.
- [x] **hreflang is doubly excluded**: already emitted by WPML, and owned by MKT-18.
- [x] **The endpoint finding prevented eight unnecessary pages**, which is the largest single piece of work this
      audit removed.
- [x] **CoreX was inspected only where relevant**, and provides nothing. No framework patch is proposed.
- [x] **An SEO plugin was considered and rejected with a reason**, not ignored: it would deliver four other
      slices' admin surfaces along with the output.

## Boundaries

- [x] **Design dependency stays `none`.** No layout, component, styling or token. A page may have an IA identity
      and no design, and the spec says so.
- [x] **No content invented.** Three classifications instead: placeholder-capable, client-input-required, later
      slice. Eight pages get placeholder bodies; four rows get recorded decisions.
- [x] **No navigation or language switcher.** Owned by #253.
- [x] **#241 is not duplicated or reopened**, and its open AC-242-09 is explicitly not a blocker here.
- [x] **Multilingual invariants reused, not reinvented.** Translation identity, never slug or SKU guesses;
      language set before insert; same templates; no synchronisation framework; no Arabic admin.
- [x] **No stock, price, ERP or migration work.**

## The things most likely to go wrong

- [x] **Creating pages for the endpoint rows.** The single most likely error, and the one the audit exists to
      prevent. T-04 says create no pages; AC-242-04 and G-1 assert no duplicate route; T-11 asserts it negatively.
- [x] **An Arabic URL silently serving the English record.** A 200 in the right place with the wrong content.
      AC-242-02 and S-2 assert against it specifically.
- [x] **A suffixed slug and a 301.** #241 measured the cause; T-03 applies the invariant; T-11 would catch it.
- [x] **Attributing the sitemap gap to the wrong cause.** T-01 measures before T-09 decides configuration or code.
- [x] **Building an admin control while emitting output.** The line is drawn in C-3 and restated in T-08, T-09,
      T-10 and FR-011.
- [x] **Inventing a collection model.** Avoided by reading the register rather than deciding: ADM-57 makes manually curated collections P1/S1 and ADM-58 defers rules-based membership to P2, so the shape is given. Only the names are content input, and the tests use fixture terms.
- [x] **A path this work type may not touch.** All planned paths are feature-spec, site-code or site-tests.

## Open, and not blocking the feature

- [ ] **D-242-1, the Home page.** Static page or posts index. Gates IA-01 only.
- [ ] **D-242-2, the final collection names, copy and imagery.** **Content input, not a blocker**: the model follows ADM-57, and the capability, routing and tests are built with fixture terms.
- [ ] **D-242-3, the brand list.** Gates IA-05's completion; the translatability setting is not blocked.
- [ ] **D-242-4, the tracking screen's presentation detail.** **Design and content input, not a blocker**: the route and the data source follow ORD-05, SHIP-03 and ADR-0002.
- [ ] **G-1's reading of NFR-03**, noted under Scope discipline, surfaced for disagreement on the record.

## Result

**Pass, with four content inputs recorded and two scope readings surfaced.** **No row is blocked.** All thirty are implementable now, and four carry an open content or presentation input, named rather than invented. Closure still owes DOD-04's staging step, which #244 must deliver.
