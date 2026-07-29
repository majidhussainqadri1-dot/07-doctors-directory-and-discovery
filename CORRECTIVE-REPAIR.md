# File 07 — Corrective Audit and Repair Record

## Defect-to-repair traceability

| Audited defect | Corrective implementation in 0.2.0 |
|---|---|
| Broad shortcode detection could overwrite page content | Page replacement now requires an exact approved shortcode or an empty managed page; prior content is backed up and deactivation is reversible. Same-slug unrelated pages are not overwritten. |
| Public directory stopped at 250 doctors | Public discovery now uses database-level conditions, bounded queries, total counts, deterministic ordering, and 24-result pagination. Featured and recent sections use independent bounded queries. |
| Founder could appear in normal doctor groups | Founder ID is excluded in public directory, statistics, and sitemap SQL and remains in a separate Founder section. |
| File 07 rendered duplicate global navigation and speculative URLs | Global navigation output was removed. File 07 exposes integration hooks and only renders optional service actions when genuine published destination pages exist. |
| Private pending/rejected profile showed “Verified Doctor” | Badge and notice now use the real File 03 verification status; only public verified doctors receive the verified public presentation. |
| Professional profile contract incomplete | Added license, licensing authority, professional address, specialty, fee/currency, timings/time zone, clinic, consultation modes, languages, studied books, and optional Clinic/Appointment/Message integration. |
| Privacy export/erasure omitted report data and reported-doctor cases | Export includes directory settings and report records; erasure handles both reporter and reported-doctor relationships in non-skipping batches. |
| Report moderation lacked reviewer/audit history and write checks | Added reviewer ID, reviewer note, updated timestamp, audit table, required reason, administration pagination, insert/update failure handling, and atomic status/audit transitions. |
| Private pages had incomplete indexing/cache protection | Added noindex, noarchive, nosnippet, no-cache headers, profile-aware canonical handling, structured credentials, and a verified-doctor sitemap provider. |
| Orange/white controls failed contrast and focus/touch rules were incomplete | Primary orange controls now use dark text at 7.46:1; WhatsApp and Message controls exceed 6:1; focus-visible, 44px targets, reduced motion, logical properties, responsive and RTL foundations were added. |
| File 03 dependency check was class-only | Startup and activation now validate the minimum version and required public helper methods, with a safe administrative failure notice. |
| No schema upgrade path | Added `SDD_DB_VERSION`, idempotent `dbDelta` upgrades, page repair, and version tracking. |
| Hard-coded or dead owner action links | Owner and service actions render only for real published pages; no `#` placeholder links are produced. |
| Non-Latin initials were byte-oriented | Initial generation now uses Unicode-aware splitting and multibyte functions where available. |

## Remaining boundary

This record proves code-level remediation and automated static QA. It does not substitute for WordPress runtime, real database migration, File 03/File 20 integration, staging accessibility, rollback, or production acceptance.
