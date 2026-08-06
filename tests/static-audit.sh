#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PLUGIN="$ROOT/doctors-directory"
fail(){ echo "FAIL: $*" >&2; exit 1; }
pass(){ echo "PASS: $*"; }

grep -q "Version: 1.0.0" "$PLUGIN/doctors-directory.php" || fail "version header"
grep -q "define( 'DDD_VERSION', '1.0.0' )" "$PLUGIN/doctors-directory.php" || fail "runtime version"
grep -q "define( 'DDD_CONTRACT_VERSION', '1.0.0' )" "$PLUGIN/doctors-directory.php" || fail "contract version"
pass "version and contract identity"

for table in ddd_projection ddd_taxonomy ddd_saved_refs ddd_reports ddd_report_audit ddd_outbox ddd_inbox ddd_health_log; do
grep -Rqs "$table" "$PLUGIN/includes" || fail "missing table $table"
done
pass "canonical data architecture"

grep -Rqs "eligible=1" "$PLUGIN/includes" || fail "fail-closed public query"
grep -Rqs "founder_separate" "$PLUGIN/includes" || fail "Founder separation"
grep -Rqs "feature_end" "$PLUGIN/includes" || fail "feature expiry"
grep -Rqs "cursor_encode" "$PLUGIN/includes" || fail "signed cursor"
grep -Rqs "DoctorDirectoryIndexReconciled.v1" "$PLUGIN/includes" || fail "reconciliation event"
pass "eligibility, feature and cursor rules"

if grep -R "ORDER BY.*\$_\|LIMIT.*\$_\|meta_query.*\$_" "$PLUGIN/includes"; then fail "untrusted query interpolation"; fi
if grep -R "doctor_id.*=>.*public_dto" "$PLUGIN/includes"; then fail "internal doctor ID exposed in public DTO"; fi
grep -Rqs "Idempotency-Key" "$PLUGIN/includes" || fail "idempotency contract"
grep -Rqs "X-DDD-Signature" "$PLUGIN/includes" || fail "signed event input"
grep -Rqs "START TRANSACTION" "$PLUGIN/includes" || fail "atomic transitions"
pass "security and mutation contracts"

grep -q "wp_privacy_personal_data_exporters" "$PLUGIN/includes/class-sdd-privacy.php" || fail "privacy exporter"
grep -q "wp_privacy_personal_data_erasers" "$PLUGIN/includes/class-sdd-privacy.php" || fail "privacy eraser"
grep -q "noarchive" "$PLUGIN/includes/class-sdd-seo.php" || fail "private noarchive"
grep -Rqs "nocache_headers" "$PLUGIN/includes" || fail "private no-cache"
pass "privacy and indexing boundaries"

grep -q -- "--ddd-green" "$PLUGIN/assets/css/directory.css" || fail "green identity token"
if grep -q -- "--sdd-orange\|#ff8a1f" "$PLUGIN/assets/css/directory.css"; then fail "legacy orange primary remains"; fi
grep -q ":focus-visible" "$PLUGIN/assets/css/directory.css" || fail "visible focus"
grep -q "min-height:44px\|min-height: 44px" "$PLUGIN/assets/css/directory.css" || fail "touch target"
grep -q "prefers-reduced-motion" "$PLUGIN/assets/css/directory.css" || fail "reduced motion"
grep -q 'html\[dir="rtl"\]' "$PLUGIN/assets/css/directory.css" || fail "RTL"
pass "visual, accessibility and localization contracts"

grep -Rqs "ddd_safe_mode" "$PLUGIN/includes" || fail "safe mode"
grep -Rqs "system_check" "$PLUGIN/includes" || fail "system check"
grep -Rqs "ddd_reconcile_tick" "$PLUGIN/includes" || fail "reconciliation cron"
grep -Rqs "dead" "$PLUGIN/includes" || fail "dead-letter handling"
pass "operability and resilience"

echo "All File 07 static audit gates passed."
