#!/usr/bin/env python3
"""V12.3 File07 read-only operator-probe safety and scope check. Source-only, not Live QA."""
from pathlib import Path
import re
import sys

ROOT = Path(__file__).resolve().parents[1]
tool = ROOT / "ops/file07-readonly-reality-freeze.php"
text = tool.read_text(encoding="utf-8")
build = (ROOT / "tests/build-release.sh").read_text(encoding="utf-8")
expected_tables = (
    "projection", "taxonomy", "saved_refs", "reports", "report_audit", "feature_audit",
    "admin_audit", "outbox", "inbox", "search_metrics", "rate_limits", "health_log",
)
expected_cron = (
    "ddd_reconcile_tick", "ddd_expire_features_tick", "ddd_process_outbox_tick",
    "ddd_continue_legacy_migration",
)
checks = {
    "Operator-only WP-CLI and no HTTP guard": all(
        piece in text for piece in ("defined( 'ABSPATH' )", "defined( 'WP_CLI' )", "WP_CLI", "PHP_SAPI", "exit( 1 )")
    ),
    "No HTTP route or REST registration": not re.search(r"register_rest_route|add_action\s*\(\s*['\"](?:init|rest_api_init|wp_ajax)", text),
    "No option/meta/post writes or destructive SQL": not re.search(
        r"\b(?:update_option|add_option|delete_option|update_user_meta|add_user_meta|delete_user_meta|"
        r"wp_insert_post|wp_update_post|wp_delete_post|wp_schedule_event|wp_clear_scheduled_hook|"
        r"dbDelta)\s*\(|\b(?:INSERT|UPDATE|DELETE|REPLACE|ALTER|DROP|TRUNCATE)\s+(?:INTO|TABLE|FROM)\b",
        text, re.I
    ),
    "No raw lock token emission": "ddd_activation_lock_present" in text and "'ddd_activation_lock' ]" not in text,
    "Records 12 fixed table suffixes": all(f"'{name}'" in text for name in expected_tables),
    "Captures 4 File07 cron schedules": all(f"'{name}'" in text for name in expected_cron),
    "No personally identifiable row payload query": "SELECT COUNT(*)" in text and not re.search(
        r"SELECT\s+\*\s+FROM|SELECT\s+(?:doctor_id|user_id|phone|email|patient_id|reporter_id)\s+FROM",
        text, re.I
    ),
    "No raw error log or secret output": all(x not in text for x in ("file_get_contents( WP_CONTENT_DIR", "AUTH_KEY", "DB_PASSWORD", "WP_DEBUG_LOG_PATH")),
    "Clear installed tree vs ZIP parity distinction": "tree_sha256" in text and "zip_sha256" in text and "UNVERIFIED: installed tree hash is not a ZIP hash" in text,
    "Non-installable ops file excluded from ZIP build": 'cp -a "$ROOT/doctors-directory/." "$STAGE/"' in build,
    "Isolated-only safety due to WP-CLI bootstrap effects": "--skip-plugins --skip-themes" in text and "ISOLATED RESTORED STAGING COPY" in text and "bootstrap_side_effects" in text,
    "Runtime remains untouched": "File07 V12.3" in text and not (ROOT / "doctors-directory/ops").exists(),
}

for name, ok in checks.items():
    print(("PASS" if ok else "FAIL") + ": " + name)
failures = [name for name, ok in checks.items() if not ok]
print(f"V12.3 READONLY PROBE SAFETY: {len(checks)-len(failures)}/{len(checks)}")
print("NO WORDPRESS/DB/LIVE RUNTIME CHECKS EXECUTED BY THIS UNIT TEST")
if failures:
    print("FAILED: " + ", ".join(failures), file=sys.stderr)
    sys.exit(1)
