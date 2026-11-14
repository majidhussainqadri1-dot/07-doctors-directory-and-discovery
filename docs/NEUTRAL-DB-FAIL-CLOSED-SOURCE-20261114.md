# File07 neutral All Verified fallback — PHP 8 DB error hardening (source-only)

Date: 2026-11-14. Governing V12.3 CEN-REL-001, File07-NFR-003/NFR-007, Master Plan Chapter 8/12. Issue #17.

## Frozen source root cause

At `main@8f9b567c08e9456daf31ebadf17b13bb307571f8`, `DDD_Central_Ranking::neutral()` uses `$wpdb->get_results(..., ARRAY_A)`, then unconditionally `count($rows)`. On a failed DB query, WordPress may return `null`, and PHP 8 `count(null)` throws a `TypeError`; this is a **reproducible PHP source failure**, not proof of a Hostinger production incident.

## Isolated narrow source correction

The new source branch checks `is_array($rows)` and `$wpdb->last_error` immediately after the query and before any `count()`. On failure it returns the generic privacy-safe `WP_Error('neutral_ranking_store_unavailable', ..., 503)`; it neither asserts no doctors exist nor fabricates ranked records. It clears pre-query stale `last_error` so successful reads are not falsely classified as broken. No File00/03/08/09/19/24/26 ownership semantics, DB schema, migration, user data or ranking merit policy changed.

## Executable isolated PHP regression

`tests/neutral-db-fail-closed.php` loads the real corrected `DDD_Central_Ranking` class and mocks WPDB to exercise:
- NULL + SQL error
- Empty array + SQL error
- NULL with unexpectedly empty SQL error string
- False + SQL error
- Legitimate empty success
- Single legitimate public doctor with rank zero and a no-merit explanation
- Previously stale `last_error` and bounded read count
- No raw database error string in public error response; HTTP 503 for failures

Both GitHub workflows execute this real PHP class unit test. Their synthetic results are **not** MySQL/WordPress staging success. Other existing tests and deterministic package validation remain mandatory.

## Version/package/release discipline

This is a **provisional source fix** on an isolated branch, not an authorized Live bug patch, not merged into `main`, and not deployed. The current plugin header is 1.2.2 pending exact installed File07 parity and release-version decision; do not distribute a changed package under the same public release identity. Any package fingerprint produced by CI is **candidate-only**; the old `RELEASE-CANDIDATE.sha256` is expected not to match after runtime bytes changed until a separate release engineering decision. Do not weaken the final checksum parity gate.

To close: authenticated deployed plugin/DB/migration freeze → reproduce symptom (if it is a Live incident) → parity audit → approval of unique release version → exact HEAD source QA → deterministic ZIP and manifest SHA → restored MySQL staging + negative tests + rollback → Founder approval → controlled deployment → Live re-test and five-field parity report.

## Mandatory five-field truth

Repository HEAD: `8f9b567c08e9456daf31ebadf17b13bb307571f8` is old source main; candidate head separately recorded by PR.
Deployed Version: UNVERIFIED.
DB Version: UNVERIFIED.
Migration State: UNVERIFIED.
Live Verification Status: NOT EXECUTED.

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**
