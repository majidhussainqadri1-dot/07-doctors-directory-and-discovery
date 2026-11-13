# File 07 — Release Candidate Notes 1.2.2

Date: 2026-10-08

## Purpose

v1.2.2 is the corrective result of a fresh twenty-round File 07 audit against the File 07 master plan, the central governing plan, and current companion repository contracts.

## Runtime changes

- Official merit-ranking freshness is aligned from 35 days to File 24's current 31-day fairness-evidence window.
- An explicit File 24 `blocked` fairness decision now withholds official merit ranking. File 07 does not silently publish merit results after the assurance owner has blocked them.
- Core, neutral-ranking and advanced-discovery fee filters now reject absent/non-numeric fee owner data for bounded fee searches; missing currency also cannot satisfy an explicit currency filter.
- Missing File 08 owner fields remain unknown rather than being fabricated.
- Follow-up language-intent correction (2026-11-13): Latin language tokens (`Urdu`, `English`, `Arabic`) are recognized as language facets and removed from free-text residual search; 3 positive residual assertions and 3 language assertions are added to unit tests.
- Follow-up country-scope correction (2026-11-13): File 08 published coverage is now matched as an exact normalized country entry rather than substring; negative `US`/`Russia` and `FR`/`South Africa` plus positive `Pakistan` regression cases were added. An intermediate CI exposed that the generic transliteration-token helper is unsuitable for exact equality (capitalized `Pakistan` becomes a two-variant token). The match now uses a dedicated accent-, case- and whitespace-normalized exact comparison, leaving general search tokenization unchanged. The original failed CI is retained as corrective evidence.

- Follow-up current-owner disclosure correction (2026-11-13): search/detail/sitemap no longer trust the database's stale eligible bit. Before disclosing doctor rows, File07 rechecks File00/03/09 public eligibility, File03 canonical UUID and File00/03/09/08 projection owner versions. Missing owner truth, revocation, version drift and source database read failure return no public doctor data or a safe error. Search and detail REST responses now explicitly disable public/stale caches.
- Added `tests/current-owner-visibility.php` as a real PHP source-class regression for rechecks, consent/revocation behavior, canonical-ID and four owner-version changes, malformed projection, DB failure, REST and sitemap safeguards; included in both exact-head workflows.

## Version/data impact

- Runtime: `1.2.2`
- Contract: `1.2.2`
- Database schema: `1.1.1` unchanged
- Projection schema: `3` unchanged
- New tables/columns: none

## Cross-file truth

See `docs/CROSS-FILE-DEPENDENCY-SNAPSHOT-2026-10-08.md` for the exact companion repository HEADs used in this audit and the remaining external File 08 / File 24↔26 compatibility caveats.

## Current cross-file assurance status (2026-11-13)

The latest source-head matrix and dual-lens review are recorded in `docs/FILE07-2026-11-13-CURRENT-OWNER-AND-RELEASE-GATES.md`. File 09 now has a newer source HEAD than the previous October audit. File 00 issue #57 remains an OPEN P0 onboarding/verification contract blocker. File07 PR #12/File26 PR #22 remain proposed-only and cannot be marked operational. Source fixes do not bypass any of these mandatory owner gates.

## Package evidence

Deterministic package: `07-doctors-directory-and-discovery-1.2.2.zip`.

SHA-256 (File26 proposed-only draft branch candidate, pending exact-HEAD CI): `8be28f861b389511ac5bbbeda95e2ee3da2b5c2a49f184fbf6e42939d63ac342`.

Evidence: PR #13 HEAD `446701d2a839f5a07b4a8ce70c98229afa1d1adb` previously passed exact-source CI and two deterministic builds for the language/country scope corrections. The subsequent current-owner disclosure guard modifies three plugin PHP files and adds an executable source regression; the new candidate above was locally built and smoke tested. Its own exact-head CI / release artifact parity and separate current-owner staging acceptance are mandatory before release. Neither prior branch success nor this reconstructed package proves Hostinger staging/live deployment.

## File26 proposed-only source extension (separate draft branch, 2026-11-13)

The historical File07 PR #12 connector was ported without activation onto the exact merged File07 v1.2.2 source. This introduces a File07 -> File26 doctor_directory_projection PROPOSED manifest and executable no-owner fail-closed tests, but does not authorize production indexing or official ranking. See `docs/FILE07-FILE26-PROPOSED-INTEGRATION-2026-11-13.md`. File26 PR #22, end-to-end revocation and Hostinger staging remain outstanding. The main v1.2.2 package remains identified by SHA-256 `6661a0c87e0ecc80f4a13ea78c982209206f64516b515afeb30524cd36cc88e0`; this draft branch has a different binary and is NOT the current main package.

## Release truth

This document records repository-source work. It does not prove Hostinger staging, deployed database migration, real browser/device acceptance, Founder acceptance, live deployment or operational status.
