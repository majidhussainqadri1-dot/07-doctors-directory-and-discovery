# Changelog

## 1.2.0 — 2026-08-10

### Added — approved future Doctor Discovery expansion
- Implemented `F07-FUT-01..24` in one bounded discovery layer: Compare Doctors, Guided Finder, privacy-safe Near Me, Map + List, next availability, local-time availability, serves-country discovery, saved-search alerts, private shortlists, Why This Doctor, user-controlled personal ordering, Ranking Transparency Center, freshness indicators, advanced professional filters, knowledge footprint, communication accessibility, clinic accessibility, natural-language discovery, multilingual semantic expansion, zero-result recovery, anti-gaming integrity advisory, unmet-demand intelligence, emergency safety diversion and offline/low-bandwidth packs.
- Added owner adapters for File 03 professional public projections, File 08 public clinic/availability/location projections, File 19 notification handoff, File 24 assurance projection and File 26 ranking-policy transparency.
- Added privacy export/erasure for future discovery preferences. Precise user coordinates remain request-scoped and are explicitly removed from saved searches and aggregate demand telemetry.
- Added executable `future-discovery-24.py` acceptance contract and folded the new layer into source-contract and regression gates.

### Eighty-round corrective hardening
- Sensitive free-text, precise-location and personal-weight discovery now uses POST/private-no-store semantics; sensitive GET parameters are rejected.
- Near-Me coordinates are reduced before transport and cleared when typed location filters change.
- Saved searches persist only a strict structured-filter allowlist and no longer expose internal notification fingerprints.
- Shortlist update IDs and hard limits are enforced; File 19 saved-search handoff uses provider-presence gating and a stale-safe worker lock.
- File 24/File 26 public transparency payloads are field-minimized; public FUT endpoints have bounded rate limits.
- FUT controls meet the 44px interaction objective; client action links receive same-origin defense; reduced-motion behavior is honored.
- Cursor continuation prevents false terminal zero-result states; compare/personal fee DTOs and requester timezone handling are corrected.
- Saved-search matching now includes qualification, experience and fee/currency constraints; empty semantic needles fail closed; JS-generated strings use the localization dictionary.
- Added `tests/review-80.py` and `docs/REVIEW-CYCLE-80.md`; final post-correction local gate: 80/80 PASS.

### Preserved
- Database schema remains `1.1.0`; no new File-07 native table was introduced.
- Official global merit ranking remains File 26-owned. Personal ordering is labeled as a user preference and never exposed as official rank.
- Verification, profile, clinic/appointment, notification and assurance truths remain with their canonical owners.
- Staging/live/operational acceptance remains a separate release gate.

## 1.1.0 — 2026-08-06

### Corrected
- Closed all fail-open mandatory-owner contract paths.
- Added explicit verification consistency and positive-state eligibility checks.
- Enforced same-origin canonical destinations both during projection rebuild and public DTO delivery, including legacy rows.
- Replaced implicit discoverability with explicit privacy-first consent.
- Added persistent opaque public UUIDs and removed internal identifiers from public DTOs/privacy exports.
- Bound signed cursors to the exact filter set and relevance tuple.
- Added active feature-period handling, audited expiry and no hidden paid ranking.
- Added canonical taxonomy/aliases, transliteration-aware search, autocomplete and all required facets.
- Added InnoDB schema, activation locking, schema repair and resumable legacy migration.
- Added claimed outbox workers, durable inbox states, bounded retries, dead-letter state and reconciliation evidence.
- Added persistent bounded rate limiting, actor-scoped report idempotency and atomic moderation transitions.
- Added click-time owner eligibility rechecks for public detail and protected mutations.
- Added privacy export/erasure, legal holds, deletion pseudonymization and non-destructive uninstall.
- Added Safe Mode, System Check, bounded repair, redacted health evidence, SEO/sitemap/noindex/no-store controls.
- Added responsive, RTL, keyboard, focus, reduced-motion and contrast foundations.

### Verification
- PHP syntax: pass.
- JavaScript syntax: pass.
- Helper/business-rule tests: 17 pass.
- Architecture/security/privacy source contracts: pass.
- Stable relevance pagination model: 1,500 records, zero duplicates/omissions.
- WCAG contrast calculations: 7 pass.
- Deterministic package rebuild: byte-identical.

## 0.2.0
- Previous corrective candidate retained in repository history.
