# Changelog

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
- PHP syntax: 10/10 files pass.
- JavaScript syntax: pass.
- Helper/business-rule tests: 17 pass.
- Architecture/security/privacy source contracts: 27 pass.
- Stable relevance pagination model: 1,500 records, zero duplicates/omissions.
- WCAG contrast calculations: 7 pass.
- Deterministic package rebuild: byte-identical.

## 0.2.0
- Previous corrective candidate retained in repository history.
