# File 07 — Eighty-Round Review, Correction and Re-Verification Register

Date: 2026-08-10 (Asia/Karachi)

Scope: File 07 — Doctors Directory and Discovery, runtime 1.2.0 / DB 1.1.0 / contract 1.2.0 / projection schema 2. Starting repository candidate head: `c8cac2609f1703aebaabab3232cdebcd1e74dbcd`.

## Governing method

Each review round was completed against the consolidated central plan, the File 07 v1.2 FUT24 plan, canonical-owner boundaries, current candidate source, tests, packaging and release-truth rules. When a defect was found, it was corrected before the next round. Repository completion is not Hostinger staging or live acceptance.

## Rounds in which defects were found and corrected

| Round | Defect / gap found | Correction applied |
|---:|---|---|
| 01 | Default `main` was still the historical minimal baseline while the complete candidate lived on a stacked branch. | Final integration is required through an exact-head PR after this 80-round gate; no live-deployment claim follows from repository merge. |
| 04 | The v1.2 FUT24 Word plan still carried v1.0 document identity/version text on its opening metadata. | Corrected in the Founder-facing v1.2 80-round reviewed plan artifact. |
| 06 | Sensitive free-text / precise-location / personal-weight discovery could travel in GET URLs. | Added POST discovery, rejected sensitive GET parameters, and made sensitive responses private/no-store. |
| 07 | Browser geolocation was transported at unnecessary precision. | Reduced request coordinates to three decimals before transport. |
| 08 | Previous Near-Me coordinates could survive later country/city edits. | Typed country/city changes clear stale latitude/longitude before the next search. |
| 09 | Saved searches could persist raw natural-language query text. | Saved searches now persist a strict structured-filter allowlist; unstructured residual text is rejected. |
| 10 | Saved-search reads could expose internal notification fingerprints and lacked explicit private cache controls. | Added public-safe saved-search DTO and private/no-store REST responses. |
| 11 | A caller-supplied invented shortlist ID could bypass the bounded-list policy. | Enforced canonical 32-hex IDs, existing-list lookup for updates, and hard MAX_LISTS on creation. |
| 12 | Saved-search matching could advance notification state when File 19 was not present. | Added File 19 provider-presence gate; no fingerprint is advanced when the provider is absent. |
| 13 | Concurrent saved-search workers could race and duplicate handoffs. | Added stale-safe per-doctor/cursor persistent locking with TTL and guaranteed release. |
| 14 | Future transparency could forward excessive File 24 assurance fields. | Added explicit public assurance allowlist and same-origin report URL validation. |
| 15 | Central ranking assurance could similarly expose unbounded companion payload fields. | Added public-safe assurance minimization in the File 26 ranking bridge. |
| 16 | Several public future-discovery endpoints lacked endpoint-specific bounded rate limits. | Added rate limits for compare, interpret, transparency and offline-pack, while retaining discovery limits. |
| 17 | FUT UI used `.ddd-future__buttons` while CSS targeted a different action class. | Unified DOM/CSS class naming so button layout rules actually apply. |
| 18 | Map pins and compare selectors were below the 44px touch-target objective. | Increased interactive targets to 44×44px and retained keyboard focus indication. |
| 19 | Client-rendered profile/appointment action URLs relied solely on provider strings. | Added client same-origin validation as defense in depth; server canonical URL validation remains authoritative. |
| 20 | Programmatic smooth scrolling ignored reduced-motion preference. | Added reduced-motion detection and automatic non-smooth scrolling. |
| 21 | Post-enrichment filtering could incorrectly show a terminal zero-result state after only one base cursor page. | Terminal recovery/demand now waits for the final cursor; added signed-cursor continuation / Load More. |
| 22 | Compare UI read flat fee fields that do not match the canonical nested fee DTO. | Corrected compare fee rendering to the canonical nested `fee` object. |
| 23 | Compare availability was converted as UTC rather than the requester’s timezone. | Added validated timezone argument and browser timezone handoff. |
| 24 | Personal fee preference used the obsolete flat fee shape. | Corrected personal-order fee scoring to the canonical nested fee DTO. |
| 25 | Saved-search alert matching omitted qualification, experience and fee/currency constraints. | Added all three constraint families to saved-search matching. |
| 26 | An empty normalized semantic needle could match every value. | Empty normalized needles now fail closed instead of matching all strings. |
| 27 | Several JS-created future-discovery labels/messages bypassed localization. | Added localized server dictionary and a safe JS message resolver. |
| 28 | File 26 transparency nested fields were not sufficiently minimized. | Added strict scalar/signal/url allowlisting for the public policy projection. |
| 29 | The new 80-round QA script assumed the package-internal `MANIFEST.sha256` also existed in the repository source tree, causing the exact GitHub CI gate to fail before assertions ran. | Removed the unused source-tree manifest read; package manifest verification remains in the deterministic clean-extract workflow. |

## Clean rounds

No new repository-level defect was found after the preceding corrections in rounds:

`02, 03, 05, 30–80`.

That is 54 clean rounds. The 26 defect-bearing rounds above were corrected before proceeding.

## Post-correction executable 80-gate

`tests/review-80.py` contains exactly 80 independent post-correction assertions covering release identity, schema/contracts, runtime inventory, secrets, SQL/uninstall, transactional schema, locks/migrations, same-origin URLs, signed cursors, rate limits, DTO minimization, canonical owner boundaries, ranking/fairness, privacy, saved searches, shortlists, worker concurrency, compare/personal-order correctness, accessibility, reduced motion, continuation semantics, demand minimization, localization, emergency diversion, offline-pack safety and privileged admin gates.

Expected terminal result:

- `TOTAL ROUNDS: 80`
- `PASS AFTER CORRECTION: 80`
- `FAIL AFTER CORRECTION: 0`

## Release truth

This register proves repository/source-level review and automated checks only. Exact Hostinger deployed source, database state, migration state, companion runtime behavior, browser/device staging, backup/restore and rollback rehearsal must still be verified before any `Staging-Accepted`, `Live-Deployed` or `Operational` claim.
