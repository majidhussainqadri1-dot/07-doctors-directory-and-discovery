# File 07 — V12.3 defects, corrections and blockers (2026-11-14)

Scope: read-only assessment of File07 `main` HEAD `8f9b567c08e9456daf31ebadf17b13bb307571f8` plus current owner repositories. **No new runtime defect has been reproduced on the installed site.**

## Evidence-qualified counts

| Category | Count / interpretation |
|---|---|
| New independently confirmed production runtime defects | 0 (live unverified, not a clean bill of health) |
| New confirmed repository documentation discrepancies | 2: `docs/MIGRATION.md` and `docs/ROLLBACK.md` still call v1.2.1 the current source while plugin main declares v1.2.2 |
| Previous source fixes in File07 PR #11/#13 | 5 corrections merged; exact-main GitHub CI green, staging/live separately unverified |
| New File07 runtime code patches in this V12.3 evidence branch | 0 (no deployed source parity; no speculative live fix) |
| Current unresolved integration/release gates | 3 primary owner groups plus unverified live/staging acceptance |
| Live verified fixes | 0 |

## Blocker register

| ID | Severity | Owner | Confirmed evidence | Action / gate |
|---|---|---|---|---|
| BL-01 | P0 operational | File00/09 | Issue #57 remains open; source scoped intake fix now merged to both mains | Real role/guard/WordPress/MySQL intake integration; never grant clinical/public verified role for intake |
| BL-02 | P0 integration | File07/26 | PR #15 proposed only, no approved deletion semantics | Owner-approved registration, event tombstone, cache/cursor/search revocation and staging negatives before merge/enable |
| BL-03 | P0 ranking | File26/24 | File26 PR #22 DRAFT shadow/cutover; owner-indexing validation pending | Real MySQL source→shadow count, orphan/cursor checks, audit, rollback and Founder signoff |
| BL-04 | P0 release evidence | Hostinger operator / File24 | Jetpack disconnected; WPVibe HTTP 530; current plugin and DB unverified | Restore read-only access and freeze current installed bytes/schema |
| BL-05 | High | File08 | Rich clinic/fee/availability provider contract not proven live | Safe unknown/fail-empty; do not fabricate fees or slots |
| BL-06 | High | File19 | No File07→File19 real delivery acceptance | Outage/quiet hours/idempotency/erase tests |
| BL-07 | High | File20/25/24 | Real responsive RTL/AT and p75/p95 absent | Device, contrast and browser acceptance evidence |
| BL-08 | Medium documentation | File07 | Migration and rollback docs refer to v1.2.1 while main runtime 1.2.2 | Correct on isolated docs-only branch and CI-retest; do not claim deployment |
| BL-09 | Medium documentation | File07 | Issue #14 source HEAD/PR statuses represent earlier snapshot | Append dated correction retaining historical issue evidence |
| BL-10 | High process | File07/File24 | Static and synthetic tests cannot certify restored live WordPress/MySQL | Full staged E2E and actual restore/rollback test |

## No-go conditions

No production patch, migration, File26 index activation, paid merit signal, fabricated availability, or resolved-live claim before exact deployed code/schema/parity and source-specific root cause. Protected user data must never enter derivative index.

Final incident report must separately state: Repository HEAD / Deployed Version / DB Version / Migration State / Live Verification Status; counts: found/fixed/CI-retested/staging-retested/live-verified/open/blocked.
