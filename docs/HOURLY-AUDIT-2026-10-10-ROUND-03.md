# File 07 — Hourly Audit Round 03 — 2026-10-10

## Exact source checkpoint
- Main HEAD: `2f4a89707724fd2b9946600afe10ddab27ec3c2d`.
- Corrective PR #11 before correction: `67176911e6cecdd9defc7f2889d55f35f31129ff`.
- Exact-head CI at checkpoint: four successful workflows, none covering the expiry edge case.
- Companion main HEADs 00,03,08,09,17,19,20,21,22,23,24,25,26 refetched and unchanged from the 2026-10-09 dependency snapshot.
- Governing plans located: SSH-F07-PLAN-2026-v1.0 and SSH-PMP-2026-v3.0; complete plan parity remains unproven.

## Frozen read-only defect ledger (before correction)
1. The optional File 08 `next_available_at` could be in the past yet satisfy File 07 `availability_days`, because `matches()` checked only the upper bound.
2. `local()` projected an expired slot as upcoming availability; this could produce misleading search explanations.
3. `strtotime()` in the filter and `DateTimeImmutable` in the display did not consistently apply `clinic_timezone` to offset-less values.

## Proven common root cause and owner
File 07 lacked a single timezone-aware future-only timestamp validation function for optional File 08 public enrichment. File 08 owns the underlying clinic/appointment facts; File 07 owns filtering and display. File 08's canonical public clinic contract v1.1.0 does not promise `next_available_at`; unknown remains unknown, never fabricated.

## Correction and regression plan
One shared parser enforces strictly-future timestamps, handles invalid values by failing closed, and feeds both filtering and local projection. Regression checks cover expired, upcoming, out-of-window, invalid and local display cases. No companion writes or deployment implied.

## Verification boundary
Round accepted only after exact corrective HEAD tests, checksum manifest, deterministic ZIP, package digest parity, and CI all pass. Twenty independent audit rounds are not implied by the twenty static assertions.
