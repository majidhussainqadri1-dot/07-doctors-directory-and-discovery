# File 07 — Doctors Directory and Discovery

Official repository for **File 07** of the **Sabri Social Homeopathy Platform**.

## Current corrective release candidate

- Plugin: `Doctors Directory and Discovery`
- Corrected version: `0.2.0`
- Required WordPress: `6.0+`
- Required PHP: `7.4+`
- Required dependency: **File 03 — Sabri Profiles and Doctors 0.1.0+**
- Plugin directory: `doctors-directory/`
- Corrective branch: `fix/file-07-audit-remediation`

## What version 0.2.0 repairs

Version 0.2.0 corrects the post-upload audit blockers without altering the preserved baseline branch. It introduces safe and reversible page ownership, a database-filtered paginated directory, Founder exclusion, File 20 shell boundaries, status-aware profiles, the expanded professional profile contract, privacy/report audit completion, private-page cache and indexing protection, a public-doctor sitemap provider, and WCAG-oriented interaction styling.

## Evidence and governance

- [`SOURCE-PROVENANCE.md`](SOURCE-PROVENANCE.md) — identity of the original supplied archive.
- [`CORRECTIVE-REPAIR.md`](CORRECTIVE-REPAIR.md) — defect-to-repair traceability.
- [`TEST-EVIDENCE.md`](TEST-EVIDENCE.md) — automated checks and current limits.
- [`STATUS.md`](STATUS.md) — exact lifecycle classification.
- [`MANIFEST.md`](MANIFEST.md) — repository and source inventory.
- [`CHECKSUMS.sha256`](CHECKSUMS.sha256) — corrected source and QA file integrity.
- [`REVIEW-REQUIRED.md`](REVIEW-REQUIRED.md) — mandatory staging acceptance still required.

## Branch policy

The branch `baseline/file-07-original-import` remains the immutable evidentiary import of version 0.1.0. Corrective development belongs only in later branches and pull requests. The repaired source must not be called production-complete until File 03 integration, database migration, role/privacy workflows, File 20 rendering, rollback, responsive behavior, accessibility, and Hostinger staging acceptance all pass.
