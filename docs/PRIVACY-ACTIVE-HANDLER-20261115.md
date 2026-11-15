# File07 V12.3 independent privacy review: the *effective* WordPress eraser (2026-11-15)

## Evidence hierarchy and corrected callback ownership

Original File07 main source: `8f9b567c08e9456daf31ebadf17b13bb307571f8`. This is repository truth only, NOT installed code.

1. `doctors-directory/doctors-directory.php` registers `DDD_Review_Hardening::register()` on `plugins_loaded` priority 29, before `ddd_start_plugin()` at 30.
2. `DDD_Privacy::hooks()` registers `DDD_Privacy::erasers` at ordinary `wp_privacy_personal_data_erasers` filter priority 10.
3. `DDD_Review_Hardening::replace_privacy_eraser` replaces the identical `doctors-directory-discovery` exporter/eraser **key** at priority 99, setting `DDD_Privacy_Hardening::erase` as the **effective canonical privacy eraser callback**.
4. Previous PR20 source tests invoked `DDD_Privacy::erase` directly and missed the effective callback. Therefore prior PR20 success was limited, and no actual WordPress erasure acceptance was established.

WordPress Core independent source: `wp-admin/includes/ajax-actions.php`, `wp_ajax_wp_privacy_erase_personal_data()` calls the registered eraser callback and handles `WP_Error` with `wp_send_json_error` before requiring the successful array shape; `wp-admin/includes/privacy-tools.php`, `wp_privacy_process_personal_data_erasure_page()` marks requests complete only on a valid last-erased response. This is Core source semantics, not evidence of this plugin running live.

## Proven code-path root cause

In effective `DDD_Privacy_Hardening::erase`, page-one meta changes/deletions/projection deletion originally happened **before** the held-count and unheld-report SQL reads. `get_var()` may return null on query error; cast `(int) null` converts it to 0 and is indistinguishable from genuine no-remaining-records. `get_results(ARRAY_A)` may return `[]` with `last_error` set or null when connection is not ready. Either situation could produce a false `done=true` and a misleading success audit after preceding mutations.

This was independent of the stale plain `DDD_Privacy::erase` count(null) defect. Plain handler remains present in source but is superseded in normal WP privacy hook ordering. Do not count its 51-held-row pagination starvation as an incident on the effective path. Actual effective handler selects unheld rows excluding `retention_hold=1`, so rows continue without starvation in the 51-held example.

## Bounded source fix in Draft PR20

- Before **any** page-one mutation, read held-count and (if not under global hold) bounded unheld rows, explicitly reject null, invalid type, or `$wpdb->last_error` with redacted `WP_Error(..., 503)`.
- On report UPDATE failure, stop with a redacted error and **do not emit a success audit**.
- After per-row processing, verify the remaining-count result is numeric and free of query errors before setting `done`.
- Preserve the original `retention_hold=0` work selection, authorized global-hold retention, 50-row bounded pagination, no parallel identity owner, and privacy-safe public erasure notice.
- This source-only patch **does not guarantee transactional atomicity** across WordPress metadata, reports, projection deletion, caches and File26; if an operation fails after previous mutations, the response is error and the next run must be tested for idempotence. A real MySQL atomicity, held-policy and cross-file erasure acceptance remains mandatory.

## New executable source tests

`tests/privacy-effective-callback.php` obtains the real priority-99 replacement callback, invokes the actual `DDD_Privacy_Hardening::erase` class with a stateful fake WPDB and checks: failed held COUNT; null/empty+error unheld read; failed report UPDATE; failed remaining COUNT; no false success audit; no preflight mutations on failed reads; proper 503 generic errors; empty normal completion; 51 unheld rows in two pages; 51 held rows retained without starvation; global-hold behavior.

The earlier `tests/privacy-db-read-guard.php` remains a separate test of export and the overridden plain eraser; it is **not** sufficient for the effective WordPress eraser.

## Unfinished acceptance — all release blocking

1. Real WordPress AJAX export/erase callback registration at actual load order on an isolated WordPress/MySQL restoration.
2. Real transactional/retry handling with failures during meta, saved refs, owner projection deletion, report update, after-update count, audit, and File26 derivative tombstone.
3. File00/03/09 consent and legal hold policies, especially when held and unheld rows mix at 50/51/1001 records.
4. Authorized staging backup **actually restored**, rollback, schema & migration parity and real privacy browser journey.
5. Separate versioned deterministic release ZIP and SHA; old v1.2.2 release checksum is not valid after runtime code changes.
6. Exact deployed byte + database version/migration freeze, Founder release approval, controlled deploy and live re-test.

Five-field truth: Repository main `8f9b567c08e9456daf31ebadf17b13bb307571f8`; Deployed Version **UNVERIFIED**; DB Version **UNVERIFIED**; Migration State **UNVERIFIED**; Live Verification Status **NOT EXECUTED**.

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**
