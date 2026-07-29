#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PLUGIN="$ROOT/doctors-directory"

fail() { echo "FAIL: $*" >&2; exit 1; }
pass() { echo "PASS: $*"; }

[[ -f "$PLUGIN/doctors-directory.php" ]] || fail "plugin bootstrap missing"
grep -q "Version: 0.2.0" "$PLUGIN/doctors-directory.php" || fail "plugin header version is not 0.2.0"
grep -q "define( 'SDD_VERSION', '0.2.0' )" "$PLUGIN/doctors-directory.php" || fail "runtime version is not 0.2.0"
pass "version contract"

if grep -R "'number'[[:space:]]*=>[[:space:]]*250\|LIMIT 250" "$PLUGIN/includes"; then
  fail "fixed 250-record ceiling remains"
fi
grep -q "LIMIT %d OFFSET %d" "$PLUGIN/includes/class-sdd-directory.php" || fail "bounded public pagination query missing"
grep -q "doctor_page" "$PLUGIN/includes/class-sdd-directory.php" || fail "public pagination parameter missing"
pass "unbounded ceiling removed and pagination added"

if grep -R "SDD_Helpers::navigation" "$PLUGIN"; then
  fail "duplicate File 07 global navigation output remains"
fi
grep -q "sdd_before_directory" "$PLUGIN/includes/class-sdd-directory.php" || fail "File 20 integration hook missing"
pass "File 20 shell boundary"

if grep -n "strpos(.*\[sabri_\|strpos(.*\[sdd_" "$PLUGIN/includes/class-sdd-activator.php"; then
  fail "broad shortcode page overwrite logic remains"
fi
grep -q "in_array( \$content, \$replaceable, true )" "$PLUGIN/includes/class-sdd-activator.php" || fail "exact shortcode replacement guard missing"
grep -q "_sdd_previous_content" "$PLUGIN/includes/class-sdd-activator.php" || fail "reversible page backup missing"
pass "safe and reversible page ownership"

grep -q "u.ID <> %d" "$PLUGIN/includes/class-sdd-directory.php" || fail "Founder exclusion missing"
grep -q "is_founder" "$PLUGIN/includes/class-sdd-helpers.php" || fail "Founder identity helper missing"
pass "Founder exclusion"

grep -q "verification_status" "$PLUGIN/includes/class-sdd-profile.php" || fail "status-aware profile rendering missing"
grep -q "Private preview" "$PLUGIN/includes/class-sdd-profile.php" || fail "private status preview notice missing"
pass "verification-state rendering"

grep -q "sdd_report_audit" "$PLUGIN/includes/class-sdd-activator.php" || fail "report audit table missing"
grep -q "START TRANSACTION" "$PLUGIN/includes/class-sdd-admin.php" || fail "atomic report transition missing"
grep -q "review_note" "$PLUGIN/includes/class-sdd-admin.php" || fail "review reason missing"
pass "moderation audit trail"

grep -q "noarchive" "$PLUGIN/includes/class-sdd-seo.php" || fail "noarchive protection missing"
grep -q "nocache_headers" "$PLUGIN/includes/class-sdd-plugin.php" || fail "private no-cache headers missing"
grep -q "SDD_Doctor_Sitemap_Provider" "$PLUGIN/includes/class-sdd-seo.php" || fail "doctor sitemap provider missing"
pass "SEO and private-cache boundaries"

grep -q "Doctors Directory Reports" "$PLUGIN/includes/class-sdd-privacy.php" || fail "report export missing"
grep -q "doctor_id.*0\|\['doctor_id'\] = 0" "$PLUGIN/includes/class-sdd-privacy.php" || fail "reported-doctor erasure path missing"
pass "privacy export and erasure coverage"

if grep -q "background:var(--sdd-orange);color:#fff" "$PLUGIN/assets/css/directory.css"; then
  fail "known low-contrast orange/white button pair remains"
fi
grep -q ":focus-visible" "$PLUGIN/assets/css/directory.css" || fail "keyboard focus style missing"
grep -q "prefers-reduced-motion" "$PLUGIN/assets/css/directory.css" || fail "reduced-motion style missing"
grep -q "min-height: 44px" "$PLUGIN/assets/css/directory.css" || fail "touch target contract missing"
pass "accessibility static contract"

echo "All File 07 static audit gates passed."
