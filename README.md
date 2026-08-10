# File 07 — Doctors Directory and Discovery

Canonical repository source for **Sabri Social Homeopathy Platform File 07**.

## Release candidate

- Runtime: `1.2.0`
- Database schema: `1.1.0` (unchanged; the future-discovery expansion adds no File-07 database tables)
- Contract: `1.2.0`
- Projection schema: `2`
- WordPress baseline: `7.0.1`
- PHP baseline: `8.3`
- Text domain: `doctors-directory-discovery`
- PHP prefix: `DDD_`
- Package folder: `doctors-directory-and-discovery`

## Canonical ownership

File 07 owns the rebuildable public discovery projection of verified-eligible doctors, directory search/filter/pagination, directory presentation orchestration, File-26 ranking consumption, public-safe comparison and personal discovery preferences, Founder/featured/recent/all sections, SEO, saved references, listing reports, reconciliation and directory operations.

It does **not** own membership/identity (File 00), doctor verification decisions/evidence (File 09), professional profile truth (File 03), clinic/location/availability/appointment truth (File 08), notification delivery/preferences (File 19), global shell (File 20), visual design-system ownership (File 25), global merit-ranking orchestration (File 26), or native security enforcement/assurance owned by companion modules.

## v1.2.0 — 24 future doctor-discovery enhancements

The approved `F07-FUT-01..24` expansion is implemented through a bounded public-safe discovery layer: factual comparison, guided discovery, consented Near Me, map/list, availability/time-zone/country coverage, private saved searches and shortlists, File 19 alert handoff, explanations, personal ordering distinct from merit rank, transparency, freshness, professional/knowledge/accessibility projections, multilingual discovery, zero-result recovery, anti-gaming advisory, aggregate unmet demand, emergency diversion and low-bandwidth packs.

Full traceability: `docs/FUTURE-DISCOVERY-24-ENHANCEMENTS.md`.

## 80-round hardening — 2026-08-10

A fresh sequential 80-round review was completed after FUT24. Defects found in 26 rounds were corrected before the next round; the final executable post-correction gate is **80/80 PASS**. The hardening includes POST/no-store handling for sensitive discovery, coarse request-scoped Near-Me coordinates, strict saved-search persistence, shortlist limit integrity, File 19 worker locking/provider gates, public assurance/policy minimization, endpoint rate limits, 44px interaction targets, same-origin client defense, reduced-motion handling, signed-cursor continuation, canonical fee/timezone matching and localization coverage.

See `docs/REVIEW-CYCLE-80.md` and `tests/review-80.py`.

## Local verification

```bash
find doctors-directory -type f -name '*.php' -print0 | xargs -0 -n1 php -l
find doctors-directory -type f -name '*.js' -print0 | xargs -0 -n1 node --check
php tests/unit-helpers.php
python3 tests/source-contracts.py
python3 tests/central-plan-ranking.py
python3 tests/central-plan-adversarial.py
python3 tests/future-discovery-24.py
python3 tests/review-40.py
python3 tests/review-80.py
python3 tests/test-pagination.py
python3 tests/test-contrast.py
bash tests/static-audit.sh
bash tests/build-release.sh
```

## Release truth

Source, deterministic package and automated repository QA are separate from Hostinger staging, real companion-package integration, browser/device acceptance, restore/rollback rehearsal, Founder acceptance, live deployment and operational acceptance. See `docs/STAGING-ACCEPTANCE.md`.
