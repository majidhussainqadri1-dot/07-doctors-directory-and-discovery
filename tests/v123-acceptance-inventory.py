#!/usr/bin/env python3
"""V12.3 File07 documentary acceptance INVENTORY only: NOT runtime/staging/live QA."""
from pathlib import Path
import re
import sys

ROOT = Path(__file__).resolve().parents[1]
gates = (ROOT / "docs/V123-F07-REQUIREMENT-GATES-20261114.md").read_text(encoding="utf-8")
freeze = (ROOT / "docs/V123-F07-ENV-REALITY-FREEZE-20261114.md").read_text(encoding="utf-8")
owners = (ROOT / "docs/V123-F07-CROSS-FILE-OWNERS-20261114.md").read_text(encoding="utf-8")
defects = (ROOT / "docs/V123-F07-DEFECTS-AND-BLOCKERS-20261114.md").read_text(encoding="utf-8")
reviews = (ROOT / "docs/V123-F07-REVIEWS-A-B-20261114.md").read_text(encoding="utf-8")
release = (ROOT / "docs/V123-F07-SECURITY-PERF-STAGING-RELEASE-20261114.md").read_text(encoding="utf-8")
main = (ROOT / "doctors-directory/doctors-directory.php").read_text(encoding="utf-8")
migration = (ROOT / "docs/MIGRATION.md").read_text(encoding="utf-8")
rollback = (ROOT / "docs/ROLLBACK.md").read_text(encoding="utf-8")

conditions = []
def check(name, ok):
    conditions.append((name, bool(ok)))

ids = re.findall(r"^\| (F07-(?:FR-\d{3}|NFR-\d{3}|CEN-01|FUT-\d{2})) \|", gates, re.M)
expected = (
    [f"F07-FR-{i:03d}" for i in range(1, 16)]
    + [f"F07-NFR-{i:03d}" for i in range(1, 11)]
    + ["F07-CEN-01"]
    + [f"F07-FUT-{i:02d}" for i in range(1, 25)]
)
ats = re.findall(r"^\| (AT-\d{2}) \|", gates, re.M)
check("Exactly 50 distinct F07 requirement IDs", len(ids) == 50 and len(set(ids)) == 50 and set(ids) == set(expected))
check("Exactly 20 acceptance journey IDs", len(ats) == 20 and len(set(ats)) == 20 and set(ats) == {f"AT-{i:02d}" for i in range(1, 21)})
row_lines = [ln for ln in gates.splitlines() if re.match(r"^\| F07-(?:FR|NFR|CEN|FUT)-", ln)]
check("Each requirement explicitly unverified staging/live", len(row_lines) == 50 and all("STAGING-UNVERIFIED / LIVE-UNVERIFIED" in ln for ln in row_lines))
check("AT journeys never presented as accepted", gates.count("NOT-EXECUTED-IN-STAGING/LIVE") == 20)
check("Version and declared schema match current source", "Version: 1.2.2" in main and "DDD_DB_VERSION', '1.1.1" in main and "DDD_PROJECTION_SCHEMA', 3" in main)
check("Migration documentation matches 1.2.2 source", "runtime `1.2.2`" in migration and "source target: runtime `1.2.1`" not in migration)
check("Rollback documentation matches 1.2.2 source", "1.2.2 source candidate" in rollback and "1.2.1 source candidate" not in rollback)
check("All five live-reality facts separately disclosed", all(term in freeze for term in ["Repository HEAD", "Deployed Version", "DB Version", "Migration State", "Live Verification Status"]))
check("Exact installed code and DB explicitly unverified", "Deployed Version | **UNVERIFIED**" in freeze and "DB Version | **UNVERIFIED**" in freeze)
check("Historical plugin snapshot is explicitly not live", "2026-07-14T01:33:09+00:00" in freeze and "NOT current proof" in freeze)
check("File00/File09 source correction not misreported as live", "source merged" in owners.lower() and "staged/live unknown" in owners.lower())
check("File07/File26 proposed-only integration kept blocked", "PR #15" in owners and "PROPOSED-only" in owners and "PR #22" in owners)
check("No fabricated resolved-production claims", "0 (live unverified" in defects and "New File07 runtime code patches" in defects)
check("Security and real restore/rollback remain evidence gates", all(x in release for x in ["IDOR", "actual isolated restore", "rollback", "WCAG 2.2 AA"]))
check("Two-pass review expressly not runtime E2E acceptance", "not a complete independent security code audit" in reviews and "not sufficient for real" in reviews)

for name, ok in conditions:
    print(("PASS" if ok else "FAIL") + ": " + name)
failures = [name for name, ok in conditions if not ok]
print(f"V12.3 DOCUMENT INVENTORY: {len(conditions)-len(failures)}/{len(conditions)} checks passed")
print("STAGING/LIVE ACCEPTANCE: NOT VERIFIED (this test must never claim otherwise)")
if failures:
    print("FAILED: " + ", ".join(failures), file=sys.stderr)
    sys.exit(1)
