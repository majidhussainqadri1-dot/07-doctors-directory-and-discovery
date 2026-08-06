# File 07 — Requirements Traceability Matrix — Version 1.0.0

Governing specification: `SSH-F07-PLAN-2026-v1.0`.

| Requirement | Implementation owner | Primary evidence |
|---|---|---|
| F07-FR-001 Eligibility projection | `DDD_Contracts::eligibility`, `DDD_Repository::rebuild_doctor` | dedicated `ddd_projection`, fail-closed `eligible=1` public queries, reconciliation |
| F07-FR-002 Founder section | `DDD_Contracts::founder`, `DDD_Directory::founder_section` | `founder_separate` eligibility reason; Founder excluded from projection |
| F07-FR-003 Featured doctors | `DDD_Repository::set_feature`, `DDD_Admin` | explicit label, reason, approver, start/end, optimistic version and outbox event |
| F07-FR-004 Recently verified | `DDD_Repository::search` | `verified_at` from authoritative verification contract; Founder absent |
| F07-FR-005 All doctors | `DDD_Repository::search` | signed stable cursor; bounded `LIMIT`; duplicate-free primary key |
| F07-FR-006 Directory search | `DDD_Helpers::normalize_token`, taxonomy aliases, search query | name/title/specialty/location/language/qualification search; transliteration where PHP intl is available |
| F07-FR-007 Faceted filters | `DDD_Directory::filters`, REST arguments | country, city, specialty, language, qualification, experience, mode, accepting, currency, fee |
| F07-FR-008 Doctor cards | `DDD_Directory::card` | avatar, name/title, badge, specialty, location, languages, modes, fee, clinic/appointment actions, accessible labels |
| F07-FR-009 Ranking policy | `DDD_Repository::quality_score`, deterministic ORDER BY | bounded featured effect, completeness, availability, verification freshness; public explanation labels |
| F07-FR-010 Taxonomy | `ddd_taxonomy`, `taxonomy_normalize` | canonical key and alias registry; normalized public projection |
| F07-FR-011 Index synchronization | inbox/outbox, `consume_event`, cron reconciliation | versioned events, dedupe, retries, dead-letter, full reconciliation event |
| F07-FR-012 Directory saves | `ddd_saved_refs`, `save_reference` | account-owned reference, unique user/doctor key, no profile duplication |
| F07-FR-013 SEO/structured data | `DDD_SEO` | public-only CollectionPage/Person data, canonical directory, filtered noindex, sitemap |
| F07-FR-014 Doctor preview/status | `DDD_Profile::status`, `get_status` | private no-cache/noindex page, exact eligibility reasons and canonical owner links |
| F07-FR-015 Abuse/reporting | `DDD_Repository::report`, moderation transition | rate limit, idempotency, evidence URL, reviewer note, atomic audit record |
| F07-NFR-001 Authorization | REST/admin permission callbacks, nonce and object resolution | no client-supplied badges; opaque public IDs; capability gates; version conflicts |
| F07-NFR-002 Privacy lifecycle | `DDD_Privacy` | export/erase, legal-hold filter, anonymized report retention, projection withdrawal |
| F07-NFR-003 Reliability | inbox/outbox and reconciliation | idempotent event IDs, exponential retry, dead-letter, safe mode |
| F07-NFR-004 Performance | projection indexes, bounded cursor query | no live four-owner fan-out on public reads; 60 maximum limit |
| F07-NFR-005 Accessibility | `directory.css`, semantic markup | focus-visible, 44px controls, RTL, reflow, reduced motion, status semantics |
| F07-NFR-006 Observability | `DDD_Observability` | redacted structured logs, health log, System Check, trace IDs |
| F07-NFR-007 Migration/rollback | `DDD_Activator`, legacy report migration | activation lock, dbDelta, legacy import, deterministic package, non-destructive uninstall |
| F07-NFR-008 Operability | System Check, Safe Mode, bounded repair | contract/table/route/cron/outbox/projection checks and operator controls |
| F07-NFR-009 Compatibility | bootstrap and legacy aliases | WordPress 7.0+, PHP 8.0+, existing `SDD_*` class/constant and shortcode adapters |
| F07-NFR-010 Localization | American English base and CSS logical properties | Urdu/Arabic/RTL readiness; time/currency fields normalized |

## Automated evidence

- PHP syntax: every plugin PHP file.
- JavaScript syntax: every plugin JavaScript file.
- Helper contract tests: list, decimal, normalization, signed cursor and tamper detection.
- Static architecture/policy audit: tables, eligibility, cursor, events, privacy, SEO, Safe Mode, accessibility and green identity.
- Programmatic WCAG contrast calculations.
- Two deterministic builds compared byte-for-byte.
- Clean-extract PHP syntax in GitHub Actions.

## External acceptance boundary

Real WordPress/MySQL, exact File 00/03/08/09/20/24/25 packages, Hostinger/LiteSpeed behavior, real browsers/devices, backup restoration and Founder acceptance require the external staging environment. They are enumerated without being falsely marked as executed in `STAGING-ACCEPTANCE.md`.
