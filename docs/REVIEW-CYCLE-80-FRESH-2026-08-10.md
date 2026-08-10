# File 07 — Fresh Exact-Main 80-Round Review and Correction Register

Date: 2026-08-10 (Asia/Karachi)

Starting repository truth: canonical `main` at `2e10640677c9e4a922d09a6f4efe2d62dbf4ff9e`, runtime 1.2.0 / DB schema 1.1.0 / contract 1.2.0 / projection schema 2.

## Governing method

This is a fresh review of the already merged File 07 release. The consolidated central plan, File 07 v1.2 FUT24 plan, canonical-owner boundaries, exact repository source, executable tests, security/privacy rules, release packaging and truthful status rules were re-applied. A defect discovered in a round is corrected before the review proceeds. Repository evidence does not establish Hostinger staging or live deployment.

## Fresh defect-bearing round

| Round | Defect / gap found | Immediate correction |
|---:|---|---|
| 29 | The public File 26 ranking REST endpoint (`/ranking`) was callable anonymously without its own bounded abuse/rate control, unlike other public File 07 discovery/search endpoints. This could permit avoidable provider/DB load amplification. | Added the persistent IP-hash rate limiter to `DDD_Central_Ranking::rest_ranking()` at 60 requests/minute with HTTP 429 fail-safe response, and added an executable central-ranking regression assertion proving the endpoint remains rate limited. |

## Fresh clean rounds

No additional repository-level defect was identified in fresh rounds:

`01–28, 30–80`.

The fresh cycle therefore contains **1 defect-bearing round** and **79 clean rounds** after the immediate correction above.

## Re-verification gates

The corrective branch must pass the existing exact-state gates before merge:

- PHP syntax and JavaScript syntax
- helper/source contracts
- central File 26 ranking contract and adversarial ranking suite
- FUT24 executable suite
- static architecture/security/privacy audit
- 40-round regression gate
- 80-round post-correction gate (exactly 80 assertions)
- stable pagination model and WCAG contrast calculations
- deterministic package A/B equality
- canonical release checksum and clean-extract package verification

Any failure introduced by the correction is itself a defect and must be fixed before merge.

## Historical distinction

This fresh cycle is separate from the earlier hardening register (`docs/REVIEW-CYCLE-80.md`), whose defect-bearing rounds were `01, 04, 06–29`. Those historical findings remain useful provenance; they are not counted again as fresh defects unless reproduced against this exact starting `main` state.

## Release truth

This document can prove repository/source review only after the corrective branch and final `main` CI are green. Exact deployed code, live DB/schema state, migration state, companion runtime behavior, browser/device staging, backup/restore and rollback rehearsal remain separate evidence requirements. No `Staging-Accepted`, `Live-Deployed`, `Operational` or live `Resolved` status is claimed here.
