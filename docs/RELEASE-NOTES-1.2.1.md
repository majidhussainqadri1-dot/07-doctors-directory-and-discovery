# File 07 — Release Candidate Notes 1.2.1

Date: 2026-10-07

## Scope

Release candidate 1.2.1 reconciles File 07 with the current repository contracts and ownership boundaries of Files 00, 03, 08, 09, 19, 20, 24, 25 and 26 after a fresh twenty-round plan/central-plan/cross-file audit.

## Corrective highlights

- File 00 remains identity/membership owner; File 07 consumes canonical assertions and fails closed on unavailable/incompatible owner truth.
- File 03 remains public professional-profile and public-ID owner; File 07 no longer creates a competing public doctor identity.
- File 09 remains doctor-verification owner; current verification projection and authorization state drive eligibility.
- File 08 remains clinic/availability/appointment owner; File 07 consumes public-safe projections and does not fabricate absent owner data.
- File 19 saved-search alerts use producer registration and canonical event ingestion.
- File 20 receives only a bounded verified-doctor ID projection; shell ownership remains File 20.
- File 24 current ranking-fairness evaluator is consumed with bounded File 26 evidence. Unsupported, incomplete or incompatible assurance remains blocked/unverified rather than being promoted to pass.
- File 25 remains visual-token owner; File 07 fallback UI now consumes the platform `--sabri-*` token bridge with local accessible fallbacks only.
- File 26 remains official global merit-ranking and ranking-appeal owner; File 07 consumes its current provider registry, constitution, ranking pages and appeal service.

## Data/migration impact

- Runtime version: `1.2.1`
- Contract version: `1.2.1`
- Database schema: `1.1.1`
- Projection schema: `3`
- Migration: additive `dbDelta` update adding public-safe `avatar_url` to the rebuildable projection
- New File-07 database table: none
- Companion ownership transferred: none

## Review evidence

See `docs/REVIEW-CYCLE-20-CROSS-FILE-2026-10-06.md`. The twenty-round register records 8 defect-bearing rounds and 12 initially clean rounds. Seven source/evidence defects were corrected before the final deterministic package checksum closure.

## Package truth

The canonical 1.2.1 package checksum must come from the deterministic exact-head final workflow. It is intentionally not guessed or copied from 1.2.0. `RELEASE-CANDIDATE.sha256` is valid only after it is updated from that exact artifact and the final workflow is re-verified.

## Release truth

Repository/source completion, packaging, automated QA, Hostinger staging acceptance, live deployment and operational acceptance are separate states. This note does not assert staging or production deployment.
