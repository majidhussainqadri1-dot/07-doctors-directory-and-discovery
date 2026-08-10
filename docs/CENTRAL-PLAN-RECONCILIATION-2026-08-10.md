# File 07 Central-Plan Reconciliation — 2026-08-10

## Objective

Close the newly identified gap between the existing File 07 v1.1.0 candidate and the rewritten governing Central Plan + File 07 plan, chiefly F07-CEN-01 / AJ-05 and the File 26 canonical ranking boundary.

## Implemented delta

- Added explicit Top 10 / Top 100 / Top 1000 / All Verified Doctors experience.
- Delegated global ranking orchestration and public ranking explanations to a versioned File 26 provider contract.
- Preserved File 07 ownership of public eligibility projection, filters, cards and public-safe rendering.
- Enforced monthly ranking version freshness, nested tiers, bias-audit attestation and zero paid/donor/Founder-favoritism/purchased-engagement advantage.
- Added doctor-owned ranking appeal handoff to File 26 with nonce, current-eligibility recheck, rate limiting and redacted audit.
- Added optional File 24 independent fairness-assurance boundary without overstated claims.
- Removed the legacy File-07-local All/Search ranked section from rendered output when the central-plan bridge is active.
- Added neutral alphabetical All Verified fallback only when File 26 is unavailable; no Top tier is fabricated and no merit rank is asserted.
- Added public ranking REST projection and traceability tests.

## Fresh review-and-fix round 1

Adversarial review found three defects in the initial implementation and they were corrected before repository write:

1. Neutral fallback would have displayed `Global rank #0`; corrected so neutral results have no global-rank badge.
2. Ranking appeal UI could have been rendered during neutral fallback without policy/monthly version fields; corrected so appeals are offered only for a valid File 26 snapshot.
3. The historical local All/Search ranking remained in the rendered DOM and was only visually hidden; corrected by removing that section server-side before mounting the File 26/neutral surface.

## Fresh review-and-fix round 2

The corrected final state was re-read against the central ownership, bias, nested-tier, fallback, appeal, privacy and eligibility invariants. No additional repository-level defect was identified in this delta. Automated central-plan and adversarial gates were added so these invariants remain executable.

## Status boundary

Repository coding/automated checks can become complete for this delta after exact-head CI passes. Hostinger staging, real File 26/File 24 provider integration, load/browser/accessibility journeys, backup/restore, rollback rehearsal, Founder acceptance and live re-test remain separate evidence gates.
