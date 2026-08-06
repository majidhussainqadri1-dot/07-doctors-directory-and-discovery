# File 07 — Doctors Directory and Discovery

Canonical repository source for **Sabri Social Homeopathy Platform File 07**.

## Release candidate

- Runtime: `1.1.0`
- Database schema: `1.1.0`
- Contract: `1.1.0`
- Projection schema: `2`
- WordPress baseline: `7.0.1`
- PHP baseline: `8.3`
- Text domain: `doctors-directory-discovery`
- PHP prefix: `DDD_`
- Package folder: `doctors-directory-and-discovery`

## Canonical ownership

File 07 owns the rebuildable public discovery projection of verified-eligible doctors, directory search, filters, ranking, cursor pagination, Founder/featured/recent/all sections, safe SEO, saved references, listing reports, reconciliation and directory operations.

It does **not** own membership/identity (File 00), doctor verification decisions/evidence (File 09), professional profile truth (File 03), clinic/appointment truth (File 08), global shell (File 20), visual design-system ownership (File 25), global discovery/recommendation orchestration (File 26), or security enforcement owned natively by companion modules.

## Local verification

```bash
find doctors-directory -type f -name '*.php' -print0 | xargs -0 -n1 php -l
find doctors-directory -type f -name '*.js' -print0 | xargs -0 -n1 node --check
php tests/unit-helpers.php
python3 tests/source-contracts.py
python3 tests/test-pagination.py
python3 tests/test-contrast.py
bash tests/static-audit.sh
bash tests/build-release.sh
```

## Release truth

Source, deterministic package and automated repository QA are separate from Hostinger staging, real companion-package integration, browser/device acceptance, restore/rollback rehearsal, Founder acceptance, live deployment and operational acceptance. See `docs/STAGING-ACCEPTANCE.md`.
