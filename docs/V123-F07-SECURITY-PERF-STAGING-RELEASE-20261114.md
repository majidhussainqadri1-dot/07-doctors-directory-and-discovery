# File 07 — V12.3 security, performance, staging and production acceptance

Status **CHECKLIST ONLY — NO PHYSICAL TEST PASS CERTIFIED**. File07 source `main@8f9b567c08e9456daf31ebadf17b13bb307571f8`. Scope under V12.3 M01–M12, QA15–QA24, CEN-REL-001.

## Security and privacy negative-test suite

- Guest/member/private/suspended/verified doctor, Founder, curator, admin. Check public and private object/field access, IDOR, nonce/CSRF, tampered cursor, replay/idempotency, role escalation and forged owner/event.
- Doctor withdrawal, verification expiry, suspension, consent revocation, privacy erasure and legal hold: public search/detail/featured/top-tier/cursor/HTML/REST/SEO/sitemaps/share preview/File26 index must all revalidate.
- No private phone, medical case, identity scans, credentials, internal user ID or diagnosis in public DTO, browser HTML, JS hydration, structured data, logs, caches, analytics or saved-search notifications.
- Check File19 registration/ingestion boundaries, receipt semantics, quiet hours, duplicate events, provider outage and retry.
- SQL/filter fuzz, API rate abuse, path traversal in ZIP, same-origin links, false language/country/fee matches, denial on expired owner and invalid schemas.
- Emergency terms: no recommendation returned in emergency-diversion flow. No clinical efficacy claims inferred from popularity.

**Acceptance:** independently replay negative tests on real WordPress/MySQL and all actual owner packages. Static file scanning is not a substitute.

## UX/accessibility/performance suite

- 320, 390, 768, 1024 and 1440 CSS px, 200% text, 400% zoom and reduced-motion; RTL Urdu/Arabic and LTR English; NVDA/TalkBack/VoiceOver with actual browser/OS versions recorded.
- File20 one global header/menu; File25 canonical green #087A4E and semantic tokens; no extra duplicate shell. Use actual mobile, tablet and desktop user journeys.
- Page and REST states: loading, empty, zero-result, permission expired, owner unavailable, stale, offline, failed, retry, revoked and success. Keyboard focus preserved.
- Test at least 1000 synthetic practitioners, >250 eligible, concurrent updates, stable pagination, owner version changes and slow cache/DB/outbox jobs.
- Lab: weak Android link, Windows and iOS Safari, cold/warm cache with at least three repeatable runs.
- Field p75: LCP ≤2.5 s, INP ≤200 ms, CLS ≤0.10 with provenance and sufficient sample; otherwise `INSUFFICIENT-FIELD-DATA`. Proposed pilot p95 API/search targets need measured region/load/window before approval.

## Staging package, migration and rollback

1. Refreeze exact source and installed versions; deterministic ZIP builds A/B, identical bytes, package SHA and clean-extract validation.
2. Get verified files+DB backup by **actual isolated restore** before mutation; keep owner data outside File07 migrations.
3. Fresh install, all supported upgrade paths, activation/deactivation/reactivation, interrupted migration/resume, idempotence and non-destructive uninstall.
4. Reconcile real File00/03/08/09/19/20/24/25/26 owner packages/versions, MySQL schema, WordPress REST and LiteSpeed cache.
5. Run each AT-01..AT-20 and applicable QA, M and UX criteria, including blocked/unavailable fallbacks.
6. Roll back in isolated staging with preserved owner records, user rights, index/cursor/cache validity, manifest/hash and smoke checks.
7. Obtain independent review and explicit Founder approval before any protected staged→live change. A green CI check is not Founder or staging acceptance.

## Live and operating handoff

Use approved package SHA and rollback plan; limit cohort, monitor, then verify actual installed bytes, DB/options/migration, redacted logs, indexed doctor revocation and live user journeys. Record Repository HEAD / Deployed Version / DB Version / Migration State / Live Verification Status. A live issue is not `RESOLVED` before the original symptom is retested on that installed build.

## Current gate decisions

| Gate | Outcome |
|---|---|
| Source main automated CI | Historical GitHub main CI success on HEAD 8f9b567c; this V12.3 branch needs its own CI |
| File00/File09 source intake | Merged and CI green; staging/live not verified |
| File07/File26 connector | PR15 PROPOSED-only; NO-GO activation |
| File26 index cutover | PR22 DRAFT; NO-GO |
| Current Hostinger plugin/DB data | UNVERIFIED |
| Actual restore and rollback rehearsal | NOT TESTED |
| Founder release acceptance | NOT RECORDED |
| Production deploy and live retest | NOT EXECUTED |
