# File07 privacy export/erasure — failed DB read guards (source-only)

Date: 2026-11-14. V12.3 M07 / F07-NFR-002 / CEN-REL-001. Issue #19.
Exact baseline repository `main 8f9b567c08e9456daf31ebadf17b13bb307571f8`, runtime 1.2.2, schema 1.1.1; these are **source declarations**, not deployed facts.

## Reproducible source root cause

`DDD_Privacy::export()` used two unguarded `$wpdb->get_results()` outcomes, iterated and `count()`ed returned values. WordPress may return `null` when SQL fails; PHP 8 `count(null)` is TypeError. `erase()` originally changed directory metadata, saved references and projection before its report `get_results()` with no error/type guard; if that read failed, partial mutation might already have occurred and an audit success entry could precede a later TypeError.

## Minimal source change

1. Export: reset `$wpdb->last_error` and require array+no-error for *both* reports and saved doctors reads; otherwise return a generic `WP_Error(ddd_privacy_export_*_unavailable, ..., HTTP 503)`. Do not report empty successful output.
2. Erase: preflight report read **before** page-one mutation, returning redacted `WP_Error(ddd_privacy_erasure_reports_unavailable, ..., HTTP 503)` on failure. This prevents mutation for this specific failed-read path.
3. The success flow retains existing WordPress Core exporter/eraser response signatures; WordPress privacy Ajax handlers recognize WP_Error and do not falsely mark the request complete.
4. No new database table, field, general consent logic, retention policy or File00/03/09/26 ownership semantics.

## Explicit limitations and further risk

This correction **does not prove fully atomic privacy erasure** if a later mutation, cache or downstream File26 delete operation fails. File07 Issue #19 remains OPEN for staged transactional/retry and over-50-row legal hold scenarios. Do not claim all privacy/erasure defects resolved from an isolated mock.

`tests/privacy-db-read-guard.php` loads the actual File07 privacy class with bounded WordPress and WPDB fakes; checks invalid report/saved data responses, prior stale errors, no raw error leakage, legitimate zero/single data, and erasure preflight zero mutations. Both QA workflows run it, source-only.

## No-go release truth

One source correction candidate on an **independent branch** (not stacked on neutral PR #18, V12.3 docs PR #16 or File26 connector PR #15). Runtime bytes changed but package version and old checksum remain 1.2.2; final candidate checksum gate must stay strict. No unversioned changed ZIP may be released under the old identity. Real WordPress/MySQL staging, 50+ hold, owner tombstone, export/erasure request UI, backup/rollback, Founder approval, exact installed parity and Live re-test are mandatory.

Repository HEAD: baseline `8f9b567c08e9456daf31ebadf17b13bb307571f8`; candidate branch HEAD separately by PR.
Deployed Version: UNVERIFIED. DB Version: UNVERIFIED. Migration State: UNVERIFIED. Live Verification Status: NOT EXECUTED.

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**
