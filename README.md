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

The approved `F07-FUT-01..24` expansion is implemented through a new public-safe discovery layer:

- factual 2–4 doctor comparison and guided finder;
- user-consented, request-scoped nearby discovery and map/list projection;
- next availability, local-time conversion and serves-country filtering through File 08;
- private saved searches, File 19 matching-alert handoff and private shortlists;
- why-this-doctor explanations and user-controlled personal ordering explicitly separated from official merit rank;
- File 26 ranking-transparency and optional File 24 assurance projection;
- freshness, professional, knowledge and accessibility projections through canonical owner adapters;
- natural-language multilingual discovery, zero-result recovery, anti-gaming advisory and privacy-safe unmet-demand intelligence;
- emergency-query directory suppression and text-first low-bandwidth/offline-pack endpoint.

Full traceability: `docs/FUTURE-DISCOVERY-24-ENHANCEMENTS.md`.

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
python3 tests/test-pagination.py
python3 tests/test-contrast.py
bash tests/static-audit.sh
bash tests/build-release.sh
```

## Release truth

Source, deterministic package and automated repository QA are separate from Hostinger staging, real companion-package integration, browser/device acceptance, restore/rollback rehearsal, Founder acceptance, live deployment and operational acceptance. See `docs/STAGING-ACCEPTANCE.md`.
