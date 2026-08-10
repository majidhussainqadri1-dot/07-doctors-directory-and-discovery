# File 07 — Release Candidate Notes 1.2.0

Date: 2026-08-10

## Scope

Release candidate 1.2.0 adds the Founder-approved `F07-FUT-01..24` future Doctor Discovery expansion on top of the exact File 07 candidate that implements the rewritten Central Plan ranking reconciliation. A subsequent eighty-round source review hardened the same 1.2.0 candidate before repository integration.

## Architectural rule

File 07 remains a **discovery consumer/projection owner**, not a duplicate owner of other domains. File 03 supplies public professional projections, File 08 supplies public clinic/location/availability truth, File 19 owns notification delivery/preferences, File 24 may supply independent ranking assurance, and File 26 remains the canonical official global merit-ranking orchestrator.

## Eighty-round corrective review

The post-FUT24 review corrected privacy, request-transport, saved-search, shortlist, concurrency, companion-payload minimization, public rate-limit, accessibility, reduced-motion, cursor-continuation, DTO/timezone and localization defects. The executable post-correction gate `tests/review-80.py` contains exactly 80 assertions and passes 80/80. Full chronology is in `docs/REVIEW-CYCLE-80.md`.

## Data/migration impact

- Runtime version: `1.2.0`
- Contract version: `1.2.0`
- Database schema: `1.1.0` — unchanged
- Projection schema: `2` — unchanged
- New File-07 database table: none
- Private saved searches/shortlists: bounded WordPress user-meta records, covered by privacy export/erasure
- Unmet-demand intelligence: bounded aggregate structured facets only; no free-text query, IP, precise user coordinates or patient data
- Canonical post-hardening package SHA-256: `8b79c87395406dfcb96489f1a006c81a21f8839128b010bccb9916db0cef3427`

## Release truth

This release note records repository/package intent only. Hostinger staging, exact companion-provider integration, browser/device/accessibility acceptance, backup/restore, rollback rehearsal, Founder staging acceptance, live deployment and operational acceptance remain separate gates.
