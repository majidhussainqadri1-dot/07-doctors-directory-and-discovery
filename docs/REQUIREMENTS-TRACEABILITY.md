# Requirements Traceability — SSH-F07-PLAN-2026-v1.0

Status vocabulary: **Implemented** means present in source and covered by repository checks; **External gate** means source support exists but real-environment evidence remains mandatory.

| Requirement | Implementation | Automated evidence | Status |
|---|---|---|---|
| F07-FR-001 eligibility projection | `DDD_Contracts::eligibility`, `DDD_Repository::rebuild_doctor` | unit fail-closed tests; source contracts | Implemented |
| F07-FR-002 Founder section | Founder contract and separate rendering; ordinary searches exclude Founder | unit + source contract | Implemented |
| F07-FR-003 featured doctors | versioned feature state, label/reason/period/approver, atomic audit and expiry | source contracts | Implemented |
| F07-FR-004 recently verified | authoritative `verified_at`, bounded order, Founder exclusion | source contracts/pagination model | Implemented |
| F07-FR-005 all doctors | stable relevance tuple and signed filter-bound cursor | cursor unit tests; 1,500-row model | Implemented |
| F07-FR-006 directory search | normalized name/specialty/location/language, aliases/transliteration | source contracts | Implemented |
| F07-FR-007 faceted filters | country/city/specialty/language/qualification/experience/mode/accepting/currency/fee | source contracts | Implemented |
| F07-FR-008 doctor cards | public allowlisted DTO, accessible cards and canonical CTAs | static audit/contrast | Implemented |
| F07-FR-009 ranking policy | relevance, completeness, authoritative freshness, availability, bounded signals; no paid boost | source contracts | Implemented |
| F07-FR-010 taxonomy | canonical keys/labels/aliases, version/audit/reindex | source contracts | Implemented |
| F07-FR-011 synchronization | owner events, inbox/outbox, reconciliation, stale-worker recovery/dead letters | source contracts | Implemented |
| F07-FR-012 saves | account-owned references; no duplicate doctor entity | source contracts/privacy tests | Implemented |
| F07-FR-013 SEO | canonical/noindex/no-store/sitemap/structured data | source contracts | Implemented |
| F07-FR-014 doctor status | private owner+projection status, exact reasons and owner versions | source contracts | Implemented |
| F07-FR-015 abuse/reporting | rate-limited reports, evidence URL, actor idempotency, state law and atomic audit | source contracts | Implemented |
| F07-NFR-001 authorization | capability/object/state checks, click-time eligibility, IDOR-resistant public UUID | source contracts | Implemented |
| F07-NFR-002 privacy | public allowlists, consent, export/erasure/legal hold/deletion propagation | source contracts | Implemented |
| F07-NFR-003 reliability | idempotent delivery, retry/dead-letter, reconciliation and safe degraded paths | source contracts | Implemented |
| F07-NFR-004 performance | dedicated indexed projection, bounded search/facets/cursors/background work | pagination model | Implemented; load gate external |
| F07-NFR-005 accessibility | semantic UI, keyboard/focus, contrast, zoom/reflow foundations, RTL/reduced motion | contrast/static checks | Implemented; browser/AT gate external |
| F07-NFR-006 observability | redacted structured logs, health ledger, System Check and alertable states | source contracts | Implemented; monitoring gate external |
| F07-NFR-007 migration/rollback | idempotent schema, activation lock, resumable migration, restoration metadata | source contracts | Implemented; real upgrade/rollback external |
| F07-NFR-008 operability | System Check, Safe Mode, repair preview/execute, queue inspection and runbook | source contracts | Implemented |
| F07-NFR-009 compatibility | WP 7.0.1/PHP 8.3 baseline and versioned companion contracts | CI syntax/tests | Implemented; active-theme staging external |
| F07-NFR-010 localization | American-English source, gettext, RTL/logical CSS, timezone/currency normalization | static checks | Implemented; linguistic QA external |

## Release linkage

- Release: `1.2.2`
- Package: `07-doctors-directory-and-discovery-1.2.2.zip`
- Deterministic package SHA-256: `3607e59f9d080cfe97a1835346dda9c4c426d5a56f4e653b8adb63b02fe2e64b` (verified by File 07 v1.2.2 Final Quality Gates run 37924897538 on corrective HEAD `2bfe174b1af850a3825cedf2163608e39ab59121`).
- The exact source commit and package SHA-256 are separately checked by the final GitHub Actions candidate. A subsequent plugin-content change requires rebuilding the package and updating this evidence.
- Staging, Founder, live and operational evidence are intentionally not fabricated; they remain the gates in `STAGING-ACCEPTANCE.md`.
