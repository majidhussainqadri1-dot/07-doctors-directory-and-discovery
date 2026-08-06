# File 07 — Mandatory Hostinger Staging Acceptance — Version 1.0.0

No production-complete declaration or main-branch release is permitted until every line below has dated evidence for the exact release checksum.

## Candidate identity

- Release: `07-doctors-directory-and-discovery-1.0.0.zip`
- Expected SHA-256: `96a770f28ef9cceb2401204ef716d3503ebd1ebecc005d5ea1d2a8c2b36ee375`
- Canonical ZIP top folder: `doctors-directory-and-discovery/`
- Source branch: `codex/file-07-complete-v1.0.0`

## 1. Preflight and restore proof

- [ ] Record WordPress, PHP, MySQL/MariaDB, LiteSpeed and companion versions.
- [ ] Take database, uploads, plugin, configuration and encryption-key backup as one consistent set.
- [ ] Restore that set in an isolated staging clone; record counts/checksums and login proof.
- [ ] Confirm production remains unchanged.

## 2. Dependency contracts

- [ ] Exact File 00 identity/active/suspension claims.
- [ ] Exact File 09 doctor decision/effective/expiry claims.
- [ ] Exact File 03 public-profile and consent projection.
- [ ] File 08 clinic/fee/availability and destination links when installed.
- [ ] Missing, incompatible, malformed and stale providers fail closed with safe health reasons.

## 3. Installation and migration

- [ ] Fresh activation creates all eight canonical tables and both managed routes.
- [ ] Upgrade from 0.2.0 imports reports without duplicate IDs or data loss.
- [ ] Repeated activation/upgrade is idempotent.
- [ ] Two concurrent activation requests do not duplicate schema/pages.
- [ ] Non-destructive uninstall retains data; reinstallation restores behavior.

## 4. Eligibility and state matrix

Test Founder, Administrator, verified, pending, under-review, more-information, rejected, expired, suspended, patient, student, minor/guardian-restricted and logged-out contexts.

- [ ] Only active, verified, public-consented and policy-eligible doctors appear.
- [ ] Suspension/private/deletion propagates to projection, search, cache and sitemap.
- [ ] Founder appears exactly once in the Founder section and never in recent/all groups.
- [ ] Doctor status page shows accurate owner-sourced reasons without hidden-object leakage.

## 5. Scale, search and cursor stability

- [ ] Seed at least 1,000 doctors, including 300+ eligible doctors.
- [ ] Test every filter alone and in combinations.
- [ ] Test Urdu/Arabic/English spelling, aliases and transliteration.
- [ ] Insert/update/suspend doctors between cursor requests; no duplicates or skipped stable records.
- [ ] Verify query plans use projection indexes and every request remains bounded.

## 6. Featured governance

- [ ] Only authorized curator/Founder can change feature state.
- [ ] Required reason, visible label, effective time and expiry work.
- [ ] Concurrent stale form receives version conflict.
- [ ] Expired feature is removed by request-time query and cron.
- [ ] No fee/payment/donation creates hidden ranking influence.

## 7. Public journeys and integrations

- [ ] Guest can browse/search without registration.
- [ ] Save/report/appointment actions enforce login and return to intended destination.
- [ ] Clinic and appointment links appear only for genuine published destinations.
- [ ] File 20 renders the only global shell/navigation; File 07 creates no duplicate shell.
- [ ] File 25 cards/tokens can enhance presentation without owning directory data.
- [ ] Global search receives only the public-safe index document.

## 8. Reports and privacy

- [ ] Report rate limit, idempotency, invalid target and failed insert behavior.
- [ ] Reviewer transition requires capability, nonce, expected version and reason.
- [ ] Report plus audit transition is atomic under database failure injection.
- [ ] Export covers projection status, saves and both report relationships.
- [ ] Erasure withdraws discoverability, deletes saves and anonymizes report relationships in repeated batches.
- [ ] Legal-hold behavior is approved and evidence is access-restricted.

## 9. Security/adversarial

- [ ] CSRF, IDOR, guessed internal IDs, privilege escalation and replay tests.
- [ ] Signed event timestamp, signature, replay mismatch and secret rotation tests.
- [ ] Injection payloads in search, taxonomy, report, label, reason and URLs.
- [ ] Scraping amplification and REST rate limits.
- [ ] No private identity, patient, risk, phone/address or stack/SQL/path data in HTML, JSON, logs, cache or sitemap.
- [ ] Safe Mode blocks high-risk mutations while public reading remains safe.

## 10. Accessibility and visual acceptance

- [ ] 320, 360, 390, 480, 768, 900, 1024, 1100, 1280, 1366, 1440, 1600 and 1920px.
- [ ] 200% and 400% zoom/reflow; no horizontal page overflow.
- [ ] Full keyboard sequence, visible focus, no trap, semantic headings/landmarks.
- [ ] Screen-reader name/role/value/status and form error associations.
- [ ] 44px touch targets, contrast, reduced motion and forced-colors.
- [ ] Urdu and Arabic RTL with long labels and long doctor names.
- [ ] Current Chrome, Edge, Firefox, Safari, Android Chrome and iOS Safari.

## 11. Performance and resilience

- [ ] Representative mobile Core Web Vitals meet “good” objective or have approved measured exception.
- [ ] Search API p75/p95 and database query budgets recorded under 1,000+ dataset.
- [ ] Provider timeout/malformed response/queue failure/cache failure behavior.
- [ ] Outbox retries, dead-letter alert and recovery drill.
- [ ] Slow/offline interface shows honest state and no fabricated results.

## 12. Rollback and release

- [ ] Roll back plugin source and database snapshot without losing post-cutover data outside the approved recovery point.
- [ ] Purge LiteSpeed/object cache and rebuild directory projection/sitemap.
- [ ] Complete critical real-role journeys after restore.
- [ ] Two fresh review-and-fix rounds against the exact staging candidate.
- [ ] Security/privacy/medical/Sharīʿah approvals where applicable.
- [ ] Founder reviews real profiles, search, status, moderation and mobile screenshots and signs dated acceptance.
- [ ] Controlled production deployment, smoke tests, monitoring and rollback window.
