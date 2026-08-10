# File 07 ↔ File 26 Doctor Ranking Contract v1

## Governing decision

File 07 owns the public-safe verified-doctor directory projection, filters, cards, routes and directory-specific operations. File 26 owns global ranking orchestration and ranking explanations. File 24 may attest fairness/security assurance. File 07 must not fabricate Top 10, Top 100 or Top 1000 when File 26 is unavailable.

## Read contract

WordPress filter: `sabri_file26_doctor_ranking_v1`

File 07 sends a versioned request containing `tier`, `limit`, cursor, active directory filters, nested-tier requirement, monthly-version requirement, explanation requirement, appeal requirement, bias-audit requirement and the prohibited ranking signals.

A valid File 26 response must provide:

- `ready=true` and compatible `contract_version`;
- non-empty `policy_version`;
- `monthly_version` in a year-month form and a snapshot no older than 35 days;
- `nested_tiers=true`;
- `bias_audit.status=pass` with explicit prohibition of donation, payment, paid promotion, Founder favoritism and purchased engagement;
- unique privacy-safe doctor `public_id` values, positive global `rank`, and non-empty public explanations;
- an optional stable `next_cursor`.

File 07 rechecks every ranked `public_id` through current owner eligibility before rendering. A stale, private, suspended or otherwise ineligible result is excluded from public output.

## Tier law

`Top 10 ⊂ Top 100 ⊂ Top 1000 ⊂ All Verified Doctors`.

The public UI uses “All Verified Doctors”; it does not label the remaining verified practitioners as “ordinary doctors.”

## Degraded behavior

If File 26 is missing, incompatible, stale, or fails its bias guard:

- Top 10 / Top 100 / Top 1000 fail closed with an explicit unavailable state;
- File 07 never invents a score or substitutes its historical local quality score as a global rank;
- All Verified Doctors remains browsable through a neutral alphabetical, signed-cursor fallback that applies the same public eligibility and filters and explicitly states that no merit rank is asserted.

## Appeal contract

Filter: `sabri_file26_doctor_ranking_appeal_v1`.

Only a logged-in, currently eligible doctor may appeal their own public identifier. File 07 enforces nonce, object ownership, current eligibility, bounded reason/details and rate limiting, then hands the appeal to File 26. File 07 stores only a redacted handoff audit record, not the appeal narrative.

## Fairness assurance

Optional filter: `sabri_file24_doctor_ranking_assurance_v1`.

Absence of File 24 assurance is shown as unverified; File 07 does not turn a File 26 self-attestation into an independent-assurance claim.

## Release boundary

This contract is repository/source behavior. It does not establish Hostinger staging acceptance, live deployment or operational acceptance. Real File 26 and File 24 provider contracts, real-role journeys, browser/accessibility tests, migration/rollback and Founder acceptance remain external release gates.
