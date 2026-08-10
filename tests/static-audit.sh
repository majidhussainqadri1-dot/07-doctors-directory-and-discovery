#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
PLUGIN="$ROOT/doctors-directory"
fail(){ echo "FAIL: $1" >&2; exit 1; }
pass(){ echo "PASS: $1"; }
! grep -RInE '\$wpdb->replace|REPLACE[[:space:]]+INTO' "$PLUGIN" --include='*.php' || fail 'forbidden replace primitive'
pass 'no destructive REPLACE primitive'
python3 - "$ROOT" <<'PY'
from pathlib import Path
import re,sys
root=Path(sys.argv[1])
hits=[]
pattern=re.compile(r'''(?i)(?:password|secret|api[_-]?key|access[_-]?token)\s*[:=]\s*['\"]([^'\"]{16,})['\"]''')
for p in root.rglob('*'):
    if p.is_file() and p.suffix in {'.php','.js','.json','.yml','.yaml','.md','.txt'}:
        text=p.read_text(errors='ignore')
        for m in pattern.finditer(text):
            value=m.group(1)
            if not any(x in value for x in ('test-salt','example','placeholder','REDACTED')): hits.append((p,m.group(0)))
if hits:
    print('Potential hard-coded secrets:',hits,file=sys.stderr); raise SystemExit(1)
print('PASS: no obvious hard-coded secret literals')
PY
grep -q "identity_contract_unavailable" "$PLUGIN/includes/class-sdd-helpers.php" || fail 'missing fail-closed identity path'
grep -q "profile_contract_unavailable" "$PLUGIN/includes/class-sdd-helpers.php" || fail 'missing fail-closed profile path'
grep -q "verification_contract_unavailable" "$PLUGIN/includes/class-sdd-helpers.php" || fail 'missing fail-closed verification path'
pass 'mandatory contracts fail closed'
grep -q "same_origin_url" "$PLUGIN/includes/class-sdd-helpers.php" || fail 'same-origin canonical URL gate missing'
grep -q "reporter:' . \$reporter_id" "$PLUGIN/includes/class-sdd-directory.php" || fail 'actor-scoped idempotency missing'
grep -q "status='processing' AND locked_at<" "$PLUGIN/includes/class-sdd-directory.php" || fail 'stale outbox recovery missing'
grep -q "DoctorDirectoryTaxonomyChanged.v1" "$PLUGIN/includes/class-sdd-directory.php" || fail 'taxonomy reindex event missing'
pass 'security and reliability invariants present'
grep -q "private, no-store" "$PLUGIN/includes/class-sdd-plugin.php" || fail 'private status cache policy missing'
grep -q "noindex" "$PLUGIN/includes/class-sdd-seo.php" || fail 'noindex policy missing'
pass 'privacy-safe cache/index controls present'
python3 "$ROOT/tests/source-contracts.py"
