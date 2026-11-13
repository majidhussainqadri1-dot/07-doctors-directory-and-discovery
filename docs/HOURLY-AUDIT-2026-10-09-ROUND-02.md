# File 07 — Hourly Audit Round 02 — 2026-10-09

## Exact source checkpoint
- Main: `2f4a89707724fd2b9946600afe10ddab27ec3c2d`
- Corrective PR #11 source before this correction: `a287c7b39e4214f3cb04f2f406cb4015a95f3129`
- Related companion main refs (00, 03, 08, 09, 17, 19, 20, 21, 22, 23, 24, 25, 26): individually refetched; all 13 unchanged from `docs/CROSS-FILE-DEPENDENCY-SNAPSHOT-2026-10-09.md`.
- Source-only review; no staging/live/DB parity inference.

## Frozen read-only defect ledger (before changes)
1. `DDD_Future_Query::interpret` removed stop words via `str_replace` anywhere inside a word, corrupting country names (Finland, Singapore, Indonesia) whenever an expansion was active.
2. The same parser detected language/mode dictionary terms by substring, falsely recognizing a `clinic` embedded in `teleclinic` and `urdu` embedded in longer terms.
3. `DDD_Future_Query::merge` ignored a valid residual query unless a dictionary expansion was found, leaving filler terms in ordinary searches such as `doctor in Finland`.

## Root-cause correction
- Apply Unicode-aware letter/number token boundaries to stop-word removal and dictionary detection.
- Apply the parsed residual query even without a language/mode expansion.
- Add real PHP regression assertions for country names, Urdu intent, embedded-word false positives, and residual merge behavior.
- Refresh tracked-source SHA-256 manifest.
- These source changes alter the release ZIP. A new exact-head package SHA-256 must be regenerated from CI before replacing `RELEASE-CANDIDATE.sha256` and release evidence; do not reuse the prior ZIP digest.

## Review status
- This is one focused sequential review/correction round, **not** proof of 20 independent rounds.
- A round is accepted only after exact corrective-HEAD CI and release-package parity pass.
- Full governing master-plan independent source access remains unverified; repository docs are not a substitute.
- Deployed version, DB version, migrations and live verification are unknown.
