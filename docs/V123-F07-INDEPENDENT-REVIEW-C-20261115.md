# File 07 — V12.3 independent review C and executable release delta (2026-11-15)

**Scope:** File07 13-phase Execution Plan F07-EXEC-1.0 under Central Master Plan V12.3. This is repository and cross-owner source assessment, NOT an installed WordPress/Hostinger incident diagnosis.

## A. Fresh exact independent facts

| Subject | Current verified GitHub source fact | Real production fact |
|---|---|---|
| File07 main HEAD | `8f9b567c08e9456daf31ebadf17b13bb307571f8`, plugin 1.2.2 | deployed artifact UNVERIFIED |
| File07 PR15 | Draft `afdd751486835ff50cb7efc54b55813350402a08`, File26 adapter PROPOSED-only | not accepted |
| File07 PR16 | Draft doc/test source-map head separately tracked by GitHub | no deployed change |
| File07 PR18 | Draft `4c66bd9ece186caee042deec03c894a9d7714c5f`, fail-safe neutral DB read | not merged/deployed |
| File07 PR20 | Draft `e37c4bc38cb24048ebc3867ddf35965d6d26f972`, active privacy eraser read guards | not merged/deployed |
| File26 PR22 | Draft `c0dc5ac09fdf068f5e77dab0e7f0862a21f33cb1` | shadow/cutover unverified |
| Site | WordPress.com Jetpack status `site_disconnected`; WPVibe no registered sites and connect_site HTTP 530 | no authorized installed bytes, schema, options, logs or parity |
| GitHub branch protection | Main branch currently reports `protected: false` on GitHub branch resource | merge governance must be enforced separately |

## B. New source trace is an evidence improvement, not a completion certificate

The previous 50-ID inventory enumerated each V12.3 requirement but did not bind each ID to a specific PHP method and existing source test path. This was an **incomplete traceability artifact**, not a proven functional defect.

New `docs/V123-F07-EXACT-SOURCE-TRACE-20261115.tsv` maps every 15 FR / 10 NFR / 1 CEN / 24 FUT to a concrete **main-source method**, a related existing test file, and the critical external/real-world acceptance condition. New `tests/v123-source-symbol-trace.py` checks exactly 50 IDs, exact file existence, actual PHP function signatures, related test presence, nonempty owner gate and `STAGING=LIVE=UNVERIFIED`. Symbols are explicitly labeled `SYMBOL_ONLY`: merely finding a function proves neither complete code behavior nor test coverage. Both exact PR16 source workflows succeeded on `54d4e51ba9b5be0f552b8f4ef0bcfc3629534a52` (runs 43663869710 and 43663869714).

## C. Independent privacy callback reversal (high-impact source review)

Original PR20 changed `DDD_Privacy::erase` and tested that method directly, but source registration proves the effective WordPress privacy eraser is **`DDD_Privacy_Hardening::erase`**:
- `doctors-directory.php`: `DDD_Review_Hardening::register` at `plugins_loaded` 29, plugin bootstrap at 30.
- `DDD_Review_Hardening::replace_privacy_eraser`: `wp_privacy_personal_data_erasers` filter at 99 overrides the key installed at ordinary priority 10.
- Effective handler originally issued page-one mutations *before* SQL held-count and report reads; unchecked `get_var` failure could cast null to 0; `get_results` could report an empty array with `last_error`. Returning `done=true` and recording a success audit would be misleading.

**Correction status:** PR20 has now been updated to guard the **effective** handler with preflight queries, generic WordPress HTTP 503 errors, UPDATE failure detection and remaining-count validation. `tests/privacy-effective-callback.php` selects the real replacement callback and tests failure/success cases including 51 unheld reports, 51 held reports, and global holds. Source **Quality Gates SUCCESS** run `43664361238`. **Final Quality Gates FAILURE** run `43664361260` solely at the *old* release v1.2.2 ZIP checksum, deliberately retained as a blocking gate. The earlier 31-assertion source test alone was insufficient and must not be used as live acceptance. The separate active-handler test is source-level only.

The prior speculative 51-held-row starvation statement about `DDD_Privacy::erase` must **not** be represented as a proven effective-WordPress bug; the priority-99 handler already selected `retention_hold=0` and its 51-held path is distinct. This correction supersedes historical Issue19 review comments; history retained.

WordPress Core original source consulted: `wp-admin/includes/ajax-actions.php` / `wp_ajax_wp_privacy_erase_personal_data()` validates `WP_Error` from eraser callbacks before processing a success array; `wp-admin/includes/privacy-tools.php` / `wp_privacy_process_personal_data_erasure_page()` requires valid done status.

## D. Updated defect accounting — do not combine distinct types

| Type | Count | Actual outcome |
|---|---:|---|
| Previously confirmed stale version docs on main | 2 | corrected on unmerged PR16, exact source CI green |
| 50-ID explicit PHP method/test trace gap | 1 | fixed as source documentation/QA on unmerged PR16, exact CI green |
| Neutral directory DB-read error family (Issue17) | 1 | isolated source fix PR18 + PHP tests, Quality green, release checksum blocked |
| Privacy DB-read/false-success family (Issue19) | 1 | active callback correction PR20 + PHP tests, Quality green, release checksum blocked |
| Previously untested effective handler (false source test coverage) | 1 | independently identified; tested corrected priority-99 path in PR20 |
| Cross-owner capability/contract gaps | 2 | File08 Issue52 and File26 Issue40 open |
| Independently verified production Live defects fixed | **0** | no deployed parity or Live journey evidence |

These counts are classification groups, not a comprehensive security audit. Older CI failed attempts remain part of audit history. Current repository quality success is not a release authorization.

## E. 13-phase executable gates, source vs staging vs production

| Phase | Source/QA today | Staging/Live gate |
|---|---|---|
| 00 Live Reality Freeze | GitHub truth frozen; WP access attempt failed | **BLOCKED** Hostinger plugin bytes/schema/options/logs |
| 01 Requirements | 50-ID inventory + exact symbol/test trace | every clause real functional evidence pending |
| 02 File00/09 | purpose-scoped intake sources merged elsewhere | actual applicant/privileges MySQL acceptance pending |
| 03 File03/08/09 | owner source inspected | rich File08 fields and revocation pending |
| 04 File26/24 ranking | proposed connector Draft, policy syntax gap | **NO-GO** index cutover/assurance/tombstone |
| 05 FUT24 journeys | source functions and related test files mapped | all 24 real journeys unverified |
| 06 Modern UI/RTL | source style/contrast tests | real 320–1440, AT/zoom/RTL unverified |
| 07 Security/privacy | real source risks and mitigations isolated | real WP erasure, IDOR, owner tombstone unverified |
| 08 Performance | bounded synthetic tests exist | real 1000 fixture doctors, p75/p95 unverified |
| 09 Two independent reviews | source review A/B/C evidence exists | independent deploy-specific review still required |
| 10 Build/migrate/restore | unchanged main package CI green; runtime draft release SHA blocks | no unique versioned combined ZIP, staging restore/rollback |
| 11 Founder signoff/deploy | none requested or given for a production release | **NO-GO** |
| 12 Live re-test/handoff | none executed | **NO-GO** |

## F. Required next controlled handoff

1. Hostinger operator provides **authorized immutable live bytes and DB/options/log snapshot** and identifies the real origin/runtime build. If parity differs, STOP for Deployment-Parity Audit.
2. File24 independent reviewer validates the original and corrected priority-99 privacy callback with **actual WordPress/MySQL**: read failure before writes, UPDATE/remaining query failures after some writes, retry idempotence, mixed 50/51/1001 held cases, File03/09/26 privacy revocation and actual backups/restore.
3. File08 owner completes rich public clinic contract without fabricated fee, coordinates or slots. File26/24 owners resolve policy identity, connector tombstones, shadow index + cutover/rollback.
4. Only after exact-source isolated PR approvals, create one **new uniquely versioned** integration release candidate, deterministic ZIP + verified SHA; never reuse unchanged 1.2.2 release identity with changed PHP.
5. Staging green → Founder approval → monitored deploy → exact installed parity → live user and negative journey retest. Until then CEN-REL-001 remains NO-GO.

**Five mandatory truths:** Repository HEAD main `8f9b567c08e9456daf31ebadf17b13bb307571f8`; Deployed Version `UNVERIFIED`; DB Version `UNVERIFIED`; Migration State `UNVERIFIED`; Live Verification Status `NOT EXECUTED`.

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**
