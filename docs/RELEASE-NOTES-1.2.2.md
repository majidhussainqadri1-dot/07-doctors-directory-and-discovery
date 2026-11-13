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
- Follow-up country-scope correction (2026-11-13): File 08 published coverage is now matched as an exact normalized country entry rather than substring; negative `US`/`Russia` and `FR`/`South Africa` plus positive `Pakistan` regression cases were added.

## Version/data impact

- Runtime: `1.2.2`
- Contract: `1.2.2`
- Database schema: `1.1.1` unchanged
- Projection schema: `3` unchanged
- New tables/columns: none

## Cross-file truth

See `docs/CROSS-FILE-DEPENDENCY-SNAPSHOT-2026-10-08.md` for the exact companion repository HEADs used in this audit and the remaining external File 08 / File 24↔26 compatibility caveats.

## Package evidence

Deterministic package: `07-doctors-directory-and-discovery-1.2.2.zip`.

SHA-256 (locally reproduced candidate, pending exact-HEAD GitHub CI): `d85fe45e059f893d2bab2398ccf1b2205b13729436eab875c6e78458b92511ff`.

Evidence: the 2026-10-09 PR #11 GitHub Actions package (`bef255...`) was independently reproduced byte-for-byte from its extracted plugin source. The single PHP source correction was applied and the documented deterministic build procedure produced the new candidate SHA-256 above. Local focused functional smoke was 18/18 PASS; exact updated branch CI, full regression suite and final clean-extract acceptance are still mandatory. Neither this reconstructed package nor the prior PR artifact proves staging or live deployment.

## Release truth

This document records repository-source work. It does not prove Hostinger staging, deployed database migration, real browser/device acceptance, Founder acceptance, live deployment or operational status.
