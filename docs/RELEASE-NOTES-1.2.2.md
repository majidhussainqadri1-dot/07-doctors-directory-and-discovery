# File 07 — Release Candidate Notes 1.2.2

Date: 2026-10-08

## Purpose

v1.2.2 is the corrective result of a fresh twenty-round File 07 audit against the File 07 master plan, the central governing plan, and current companion repository contracts.

## Runtime changes

- Official merit-ranking freshness is aligned from 35 days to File 24's current 31-day fairness-evidence window.
- An explicit File 24 `blocked` fairness decision now withholds official merit ranking. File 07 does not silently publish merit results after the assurance owner has blocked them.
- Core, neutral-ranking and advanced-discovery fee filters now reject absent/non-numeric fee owner data for bounded fee searches; missing currency also cannot satisfy an explicit currency filter.
- Missing File 08 owner fields remain unknown rather than being fabricated.

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

SHA-256: `3607e59f9d080cfe97a1835346dda9c4c426d5a56f4e653b8adb63b02fe2e64b`.

Evidence: File 07 v1.2.2 Final Quality Gates, run 37924897538, corrective source HEAD `2bfe174b1af850a3825cedf2163608e39ab59121`. The package is assembled exclusively from `doctors-directory/`; these release-document and workflow changes do not modify the packaged plugin. The final quality workflow checks the declared checksum against the freshly built package. If packaged source changes, rebuild and refresh this checksum.

## Release truth

This document records repository-source work. It does not prove Hostinger staging, deployed database migration, real browser/device acceptance, Founder acceptance, live deployment or operational status.
