# File 07 — Second Fresh Exact-Main 80-Round Review and Correction Register

Date: 2026-08-10 (Asia/Karachi)

Starting repository truth: canonical `main` at `8f48100c26dd7720fc8966cfcd1475e72bba94c1`, runtime 1.2.0 / DB schema 1.1.0 / contract 1.2.0 / projection schema 2.

## Governing method

This is a second fresh review of the already merged File 07 source. The consolidated governing master plan, File 07 v1.2 FUT24 plan, canonical-owner boundaries, exact repository source, executable tests, security/privacy laws, deterministic packaging and truthful release-state rules were re-applied. A defect found in a review round was corrected before proceeding. Repository evidence does not establish Hostinger staging or live deployment.

## Fresh defect-bearing rounds

| Round | Defect / gap found | Immediate correction |
|---:|---|---|
| 30 | The File 26 ranking bridge bounded the outgoing request but did not fail closed when a provider returned an oversized page. Invalid public IDs, duplicate IDs, out-of-tier ranks, or missing explanations were silently discarded, allowing a malformed authoritative page to be partially salvaged and presented as valid. | The ranking validator now rejects oversized pages and rejects the whole provider page for invalid IDs, duplicate IDs, out-of-tier ranks or missing explanations. Contract docs and central/adversarial regression tests were strengthened. |
| 45 | F07-FUT-08/09 private saved-search and shortlist POST/DELETE routes were login-gated and no-store but did not independently satisfy the File 07 mutating-API constitution for bounded rate limiting, idempotency/replay protection, serialized read-modify-write behavior and mutation audit. Concurrent user-meta mutations could also race. | Added a dedicated private mutation guard: per-user rate limiting, required `Idempotency-Key`, HMAC-keyed 24-hour replay receipts, conflicting-key rejection, per-user serialization lock, redacted audit, bounded receipt retention/cleanup and privacy erasure. Browser writes now send idempotency keys. No new DB table/schema was introduced. |
| 79 | F07-FUT-24 declared an offline-pack `expires_at` of six hours but its HTTP cache policy allowed `stale-if-error=86400`, permitting a cache to serve the snapshot after its declared lifetime. | Replaced stale serving with `public, max-age=21600, must-revalidate`; documentation and executable FUT24/40/80-round assertions now forbid `stale-if-error` for this bounded offline pack. |

## Fresh clean rounds

No additional original repository-level defect was identified in fresh rounds:

`01–29, 31–44, 46–78, 80`.

The second fresh cycle therefore contains **3 defect-bearing rounds** and **77 clean rounds** after immediate correction.

## Correction-introduced QA event

While implementing the Round 30 validator hardening, one intermediate edit introduced a PHP parenthesis/syntax error in the File 24 assurance call. GitHub CI stopped at PHP syntax before downstream gates. The syntax was corrected immediately and re-run; this is recorded as a corrective-development QA event, not as an original defect discovered in the starting `main` state and therefore is not assigned a fresh review-round number.

## Required final re-verification gates

Before merge, the final corrective head must pass:

- PHP syntax and JavaScript syntax;
- helper/source contracts;
- central File 26 ranking contract and adversarial ranking suite;
- all 24 FUT24 executable requirements;
- static architecture/security/privacy audit;
- 40-round regression gate;
- second strengthened 80-round post-correction gate (exactly 80 assertions);
- stable pagination model and WCAG contrast calculations;
- root source SHA-256 integrity;
- deterministic package A/B equality;
- canonical release checksum and clean-extract package verification.

Any regression introduced by a correction must be repaired before merge.

## Historical distinction

This register is separate from both prior hardening histories. The immediately preceding fresh cycle started from `2e10640677c9e4a922d09a6f4efe2d62dbf4ff9e` and found its new endpoint-rate-limit defect in fresh Round 29; that correction was merged into `main` as `8f48100c26dd7720fc8966cfcd1475e72bba94c1`. Historical findings are not re-counted here unless reproduced against this second cycle's exact starting state.

## Release truth

This document can establish repository/source review only after final branch and merged-`main` CI are green. Exact deployed code, live DB/schema version, migration state, real companion-provider behavior, Hostinger staging browser/device acceptance, backup/restore, rollback rehearsal and live operational verification remain separate evidence requirements. No `Staging-Accepted`, `Live-Deployed`, `Operational` or live `Resolved` status is claimed here.
