# Mandatory Hostinger Staging Acceptance

The exact GitHub commit and exact package SHA-256 must be recorded before testing.

## Environment and lifecycle
- Verified files+database backup and isolated restore proof.
- Fresh install, activation, deactivation/reactivation and non-destructive uninstall.
- Real upgrade from every supported deployed File 07 version.
- WordPress 7.0.1, PHP 8.3, real MySQL and LiteSpeed/object-cache behavior.
- Exact File 00, 09, 03, 08, 20, 24, 25 and File 26-compatible contracts where installed.

## Real-role journeys
- Guest, member/patient, verified doctor, private doctor, suspended doctor, Founder, directory curator and administrator.
- File 00/09/03 unavailable, incompatible, stale or malformed: public eligibility fails closed.
- Founder appears once in the official section and never in recent/all.
- Featured labels/reason/period/expiry/audit; no hidden paid boost.
- Saves, reports, moderation transition, privacy export/erasure and legal hold.
- Doctor status shows live owner eligibility versus projection state without leaking private data.

## Scale/failure
- At least 1,000 doctor records and more than 250 eligible records.
- Cursor traversal under concurrent updates: no duplicate/omission beyond documented snapshot semantics.
- Search/facets for Urdu/English aliases and all filters.
- Queue retry/dead-letter, stale-worker recovery, cron delay, DB/cache/provider outage and reconciliation.
- Measured p75/p95 page/API budgets, queue lag and error alerts.

## Security/privacy
- IDOR, CSRF, replay, rate abuse, SQL/filter fuzzing, forged role/provider/event, stale-cache/private-index scans.
- No private phone/address/evidence/patient data/internal ID in HTML, REST, schema, sitemap, cache, logs or exports.
- Same-origin redirects/CTAs; external legacy URL rejected.

## Accessibility/browser/device
- 320–1920px, 200–400% zoom, keyboard, visible focus, screen reader, RTL, reduced motion, long Urdu/English labels.
- Current Chrome/Edge/Firefox/Safari and Android/iOS real-device smoke.
- No overlap, clipping, dead control or page-level horizontal scrollbar.

## Recovery and approval
- Cache/index rebuild and verified rollback/restore drill preserving companion data.
- Founder reviews real public, doctor and operator journeys.
- Written Founder acceptance, production plan, rollback package and monitored deployment window.

Until every applicable item passes, status remains **source/package/automated-QA candidate**, not staging-accepted or production-complete.
