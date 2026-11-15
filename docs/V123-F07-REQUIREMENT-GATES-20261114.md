# File07 — V12.3 50-ID traceability and 20 acceptance journeys

**Date:** 2026-11-14. **Governing:** Master Plan V12.3 File07 charter, Chapter 11 IDs, Chapter 2 Doctors UI, Chapter 8/12 acceptance, CEN-REL-001. **Source main:** `8f9b567c08e9456daf31ebadf17b13bb307571f8` (v1.2.2). Source documentation states implemented; **NONE of these rows is verified in real staging or live** by this evidence review.

## Counting and evidence law

- 15 FR + 10 NFR + 1 CEN + 24 FUT = **50** unique source requirements. FUT is included in 50, not additional.
- Status vocabulary: `SOURCE-DOCUMENTED; STAGING-UNVERIFIED; LIVE-UNVERIFIED`. Detailed source path/test evidence must be independently linked on acceptance. Mere ID presence is never a runtime PASS.
- Each row shall ultimately record: owner, source SHA/path/function, positive/negative/user-role evidence, exact installed SHA, DB/migration state, test ID, reviewer and result.
- V12.3 cross-cutting M01–M12 and UX/QA requirements still apply; counted as acceptance dimensions, not invented F07 IDs.

## All 50 V12.3 requirement IDs

| ID | Scope | Required evidence family | Acceptance state |
|---|---|---|---|
| F07-FR-001 | Current-owner eligibility projection | Owner/role/public privacy and regression | STAGING-UNVERIFIED / LIVE-UNVERIFIED |
| F07-FR-002 | Founder appears only in official section | Owner/role/public privacy and regression | STAGING-UNVERIFIED / LIVE-UNVERIFIED |
| F07-FR-003 | Featured doctor state/label/reason/audit | Owner/role/public privacy and regression | STAGING-UNVERIFIED / LIVE-UNVERIFIED |
| F07-FR-004 | Recently verified, correct timestamp/order | Owner/role/public privacy and regression | STAGING-UNVERIFIED / LIVE-UNVERIFIED |
| F07-FR-005 | All verified, deterministic cursor/pagination | Owner/role/public privacy and regression | STAGING-UNVERIFIED / LIVE-UNVERIFIED |
| F07-FR-006 | Multilingual name and professional search | Owner/role/public privacy and regression | STAGING-UNVERIFIED / LIVE-UNVERIFIED |
| F07-FR-007 | Country/city/specialty/language/fee and other facets | Owner/role/public privacy and regression | STAGING-UNVERIFIED / LIVE-UNVERIFIED |
| F07-FR-008 | Public-safe cards and canonical destinations | Owner/role/public privacy and regression | STAGING-UNVERIFIED / LIVE-UNVERIFIED |
| F07-FR-009 | Directory ordering without paid manipulation | Owner/role/public privacy and regression | STAGING-UNVERIFIED / LIVE-UNVERIFIED |
| F07-FR-010 | Taxonomy canonical aliases/versioning | Owner/role/public privacy and regression | STAGING-UNVERIFIED / LIVE-UNVERIFIED |
| F07-FR-011 | Owner event, reconciliation and revocation | Owner/role/public privacy and regression | STAGING-UNVERIFIED / LIVE-UNVERIFIED |
| F07-FR-012 | Private saved references | Owner/role/public privacy and regression | STAGING-UNVERIFIED / LIVE-UNVERIFIED |
| F07-FR-013 | Canonical URL/sitemap/noindex/cache safety | Owner/role/public privacy and regression | STAGING-UNVERIFIED / LIVE-UNVERIFIED |
| F07-FR-014 | Private current doctor status | Owner/role/public privacy and regression | STAGING-UNVERIFIED / LIVE-UNVERIFIED |
| F07-FR-015 | Reporting, abuse-review audit | Owner/role/public privacy and regression | STAGING-UNVERIFIED / LIVE-UNVERIFIED |
| F07-NFR-001 | Authorization and object/field isolation | Security/performance/a11y/migration as applicable | STAGING-UNVERIFIED / LIVE-UNVERIFIED |
| F07-NFR-002 | Consent and personal data lifecycle | Security/performance/a11y/migration as applicable | STAGING-UNVERIFIED / LIVE-UNVERIFIED |
| F07-NFR-003 | Reliability, idempotency, retry/dead letters | Security/performance/a11y/migration as applicable | STAGING-UNVERIFIED / LIVE-UNVERIFIED |
| F07-NFR-004 | Performance, load, p75/p95 | Security/performance/a11y/migration as applicable | STAGING-UNVERIFIED / LIVE-UNVERIFIED |
| F07-NFR-005 | WCAG 2.2 AA, RTL and focus | Security/performance/a11y/migration as applicable | STAGING-UNVERIFIED / LIVE-UNVERIFIED |
| F07-NFR-006 | Redacted observability and alerts | Security/performance/a11y/migration as applicable | STAGING-UNVERIFIED / LIVE-UNVERIFIED |
| F07-NFR-007 | Migration, backup/restore/rollback | Security/performance/a11y/migration as applicable | STAGING-UNVERIFIED / LIVE-UNVERIFIED |
| F07-NFR-008 | System check/safe mode/recovery | Security/performance/a11y/migration as applicable | STAGING-UNVERIFIED / LIVE-UNVERIFIED |
| F07-NFR-009 | Runtime and companion version compatibility | Security/performance/a11y/migration as applicable | STAGING-UNVERIFIED / LIVE-UNVERIFIED |
| F07-NFR-010 | Localization, timezones, currencies | Security/performance/a11y/migration as applicable | STAGING-UNVERIFIED / LIVE-UNVERIFIED |
| F07-CEN-01 | Monthly Top 10/100/1000/All Verified tiers, policy, appeal, unbiased explanations | Current File26+File24 real-owner evidence | STAGING-UNVERIFIED / LIVE-UNVERIFIED |
| F07-FUT-01 | Compare 2–4 doctors factually | Real owner presence or approved isolated fallback; relevant negative tests | STAGING-UNVERIFIED / LIVE-UNVERIFIED |
| F07-FUT-02 | Guided doctor finder, non-diagnostic | Real owner presence or approved isolated fallback; relevant negative tests | STAGING-UNVERIFIED / LIVE-UNVERIFIED |
| F07-FUT-03 | Consent-based ephemeral Near Me | Real owner presence or approved isolated fallback; relevant negative tests | STAGING-UNVERIFIED / LIVE-UNVERIFIED |
| F07-FUT-04 | Map and accessible list fallback | Real owner presence or approved isolated fallback; relevant negative tests | STAGING-UNVERIFIED / LIVE-UNVERIFIED |
| F07-FUT-05 | Next public available appointment | Real owner presence or approved isolated fallback; relevant negative tests | STAGING-UNVERIFIED / LIVE-UNVERIFIED |
| F07-FUT-06 | Local IANA time display | Real owner presence or approved isolated fallback; relevant negative tests | STAGING-UNVERIFIED / LIVE-UNVERIFIED |
| F07-FUT-07 | Serves-country exact match | Real owner presence or approved isolated fallback; relevant negative tests | STAGING-UNVERIFIED / LIVE-UNVERIFIED |
| F07-FUT-08 | Saved searches and File19 alerts | Real owner presence or approved isolated fallback; relevant negative tests | STAGING-UNVERIFIED / LIVE-UNVERIFIED |
| F07-FUT-09 | Private shortlists | Real owner presence or approved isolated fallback; relevant negative tests | STAGING-UNVERIFIED / LIVE-UNVERIFIED |
| F07-FUT-10 | Why this doctor explanation | Real owner presence or approved isolated fallback; relevant negative tests | STAGING-UNVERIFIED / LIVE-UNVERIFIED |
| F07-FUT-11 | Personal ordering not official ranking | Real owner presence or approved isolated fallback; relevant negative tests | STAGING-UNVERIFIED / LIVE-UNVERIFIED |
| F07-FUT-12 | Ranking transparency | Real owner presence or approved isolated fallback; relevant negative tests | STAGING-UNVERIFIED / LIVE-UNVERIFIED |
| F07-FUT-13 | Verification/profile freshness | Real owner presence or approved isolated fallback; relevant negative tests | STAGING-UNVERIFIED / LIVE-UNVERIFIED |
| F07-FUT-14 | Professional filters from File03 | Real owner presence or approved isolated fallback; relevant negative tests | STAGING-UNVERIFIED / LIVE-UNVERIFIED |
| F07-FUT-15 | Public knowledge footprint | Real owner presence or approved isolated fallback; relevant negative tests | STAGING-UNVERIFIED / LIVE-UNVERIFIED |
| F07-FUT-16 | Communication accessibility | Real owner presence or approved isolated fallback; relevant negative tests | STAGING-UNVERIFIED / LIVE-UNVERIFIED |
| F07-FUT-17 | Clinic accessibility from File08 | Real owner presence or approved isolated fallback; relevant negative tests | STAGING-UNVERIFIED / LIVE-UNVERIFIED |
| F07-FUT-18 | Natural-language bounded intent | Real owner presence or approved isolated fallback; relevant negative tests | STAGING-UNVERIFIED / LIVE-UNVERIFIED |
| F07-FUT-19 | Urdu/English/Arabic synonyms | Real owner presence or approved isolated fallback; relevant negative tests | STAGING-UNVERIFIED / LIVE-UNVERIFIED |
| F07-FUT-20 | Zero-result honest recovery | Real owner presence or approved isolated fallback; relevant negative tests | STAGING-UNVERIFIED / LIVE-UNVERIFIED |
| F07-FUT-21 | Anti-gaming signals | Real owner presence or approved isolated fallback; relevant negative tests | STAGING-UNVERIFIED / LIVE-UNVERIFIED |
| F07-FUT-22 | Aggregated privacy-safe unmet demand | Real owner presence or approved isolated fallback; relevant negative tests | STAGING-UNVERIFIED / LIVE-UNVERIFIED |
| F07-FUT-23 | Emergency query diversion | Real owner presence or approved isolated fallback; relevant negative tests | STAGING-UNVERIFIED / LIVE-UNVERIFIED |
| F07-FUT-24 | Offline/public low-data pack | Real owner presence or approved isolated fallback; relevant negative tests | STAGING-UNVERIFIED / LIVE-UNVERIFIED |

## Twenty end-to-end journeys (AT-01 to AT-20)

| Test ID | Scenario | Status |
|---|---|---|
| AT-01 | Guest doctor list, search, detail and Home/Back | NOT-EXECUTED-IN-STAGING/LIVE |
| AT-02 | Founder one section, featured label and expiry | NOT-EXECUTED-IN-STAGING/LIVE |
| AT-03 | Doctor suspension/revocation across every public surface | NOT-EXECUTED-IN-STAGING/LIVE |
| AT-04 | File00/09 applicant preverification intake only | NOT-EXECUTED-IN-STAGING/LIVE |
| AT-05 | File08 fee/currency unknown handling | NOT-EXECUTED-IN-STAGING/LIVE |
| AT-06 | US/Russia and FR/South Africa exact country matching | NOT-EXECUTED-IN-STAGING/LIVE |
| AT-07 | Urdu/English/Arabic and Roman Urdu search | NOT-EXECUTED-IN-STAGING/LIVE |
| AT-08 | File26 monthly Top10/100/1000/All verified ranking | NOT-EXECUTED-IN-STAGING/LIVE |
| AT-09 | File24 blocked/unavailable ranking → neutral fallback | NOT-EXECUTED-IN-STAGING/LIVE |
| AT-10 | Donor/non-donor identical valid ranking signals | NOT-EXECUTED-IN-STAGING/LIVE |
| AT-11 | Duplicate/delayed/out-of-order events and tombstones | NOT-EXECUTED-IN-STAGING/LIVE |
| AT-12 | Near Me permission and no GPS persistence | NOT-EXECUTED-IN-STAGING/LIVE |
| AT-13 | Timezone/DST/next availability without false slot | NOT-EXECUTED-IN-STAGING/LIVE |
| AT-14 | Saved searches/shortlists, File19 notifications and erasure | NOT-EXECUTED-IN-STAGING/LIVE |
| AT-15 | Emergency query diversion with no diagnosis | NOT-EXECUTED-IN-STAGING/LIVE |
| AT-16 | Offline pack expiry at six hours and revalidation | NOT-EXECUTED-IN-STAGING/LIVE |
| AT-17 | 320–1440 px, 200/400% zoom, screen readers, RTL | NOT-EXECUTED-IN-STAGING/LIVE |
| AT-18 | 1000 doctors, >250 eligible, p75/p95 and concurrency | NOT-EXECUTED-IN-STAGING/LIVE |
| AT-19 | Database+files restore, migration idempotency, rollback | NOT-EXECUTED-IN-STAGING/LIVE |
| AT-20 | Post-deploy exact artifact/schema parity and live journeys | NOT-EXECUTED-IN-STAGING/LIVE |

## Release gates and no-fabrication rules

1. Public doctor shown only after File00/03/09 owner assertions; disabled or revoked doctor absent from search, detail, ranking, sitemap, snippet, cache, cursor and index.
2. Optional rich File08 File03 fields remain unknown/disabled until public contract evidenced. Never manufacture fee, slots, availability, phone or public qualifications.
3. Current File26 missing/blocked/invalid/expired gives NO official Top tiers. Only valid neutral `All Verified` alphabetical fallback may be offered with explicit no-merit statement.
4. File19 `accepted` event is not delivered-to-device proof. Never show false success or sensitive preview.
5. All real tests must state build, WP/PHP/MySQL versions, owner package hashes, role, locale, device/AT pairing, exact timestamp, DB state and redacted output.
6. WCAG 2.2 AA applicable criteria, 44px Sabri target, File25 token ownership, RTL/LTR, 320/390/768/1024/1440 breakpoints, 200% text/400% zoom.
7. Field CWV p75: LCP ≤2.5s, INP ≤200ms, CLS ≤0.10, with adequate sample; insufficient sample stays unverified.
8. CEN-REL-001 requires zero known defects in active scope and effective dependencies. Optional disabled capabilities remain openly tracked with truthful fallback and independent isolation proof.

## Five-field current truth

Repository HEAD: `8f9b567c08e9456daf31ebadf17b13bb307571f8`.
Deployed Version: **UNVERIFIED**.
DB Version: **UNVERIFIED**.
Migration State: **UNVERIFIED**.
Live Verification Status: **NOT EXECUTED**.
