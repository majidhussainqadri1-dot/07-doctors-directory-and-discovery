#!/usr/bin/env python3
"""Check exact File07 V12.3 requirement→source symbol map, not runtime acceptance."""
from pathlib import Path
import csv
import re
import sys

root = Path(__file__).resolve().parents[1]
name = "docs/V123-F07-EXACT-SOURCE-TRACE-20261115.tsv"
source = root / name
with source.open(encoding="utf-8", newline="") as fh:
    rows = list(csv.DictReader((line for line in fh if not line.startswith("#")), delimiter="\t"))

expected = (
    [f"F07-FR-{n:03d}" for n in range(1, 16)] +
    [f"F07-NFR-{n:03d}" for n in range(1, 11)] +
    ["F07-CEN-01"] +
    [f"F07-FUT-{n:02d}" for n in range(1, 25)]
)
required = {
    "Requirement_ID", "Short_Requirement", "Source_File", "Method",
    "Related_Source_Test", "Critical_Unverified_Contract", "Source_Anchor",
    "Staging", "Live",
}
problems = []
ids = [r.get("Requirement_ID", "") for r in rows]
if len(rows) != 50 or len(set(ids)) != 50 or set(ids) != set(expected):
    problems.append("50 exact unique IDs not present")
for r in rows:
    rid = r.get("Requirement_ID", "[UNKNOWN]")
    if set(r) != required or any(not str(r.get(x, "")).strip() for x in required):
        problems.append(f"{rid}: incomplete or unexpected columns")
        continue
    path = r["Source_File"]
    test = r["Related_Source_Test"]
    method = r["Method"]
    if not re.fullmatch(r"doctors-directory/includes/class-[a-z0-9-]+\.php", path):
        problems.append(f"{rid}: noncanonical source path")
        continue
    file = root / path
    if not file.is_file():
        problems.append(f"{rid}: source file not found: {path}")
        continue
    if not re.search(r"\bfunction\s+" + re.escape(method) + r"\s*\(", file.read_text(encoding="utf-8")):
        problems.append(f"{rid}: no function symbol {method} in {path}")
    if not re.fullmatch(r"tests/[a-z0-9-]+\.(?:php|py|sh)", test) or not (root / test).is_file():
        problems.append(f"{rid}: related test file missing: {test}")
    if r["Source_Anchor"] != "SYMBOL_ONLY":
        problems.append(f"{rid}: source symbol falsely claimed as functional acceptance")
    if r["Staging"] != "UNVERIFIED" or r["Live"] != "UNVERIFIED":
        problems.append(f"{rid}: untested environment incorrectly certified")
    if len(r["Critical_Unverified_Contract"]) < 15:
        problems.append(f"{rid}: external/data evidence gate not specified")
# The new map must be an addition to, not a replacement for, the existing 50-row register.
previous = (root / "docs/V123-F07-REQUIREMENT-GATES-20261114.md").read_text(encoding="utf-8")
if len(re.findall(r"^\| F07-(?:FR-\d{3}|NFR-\d{3}|CEN-01|FUT-\d{2}) \|", previous, re.M)) != 50:
    problems.append("Existing V12.3 requirement inventory is incomplete")
print(f"V12.3 exact source anchors: {len(rows)} IDs; {len(set(r['Source_File'] for r in rows))} source files; {len(set(r['Related_Source_Test'] for r in rows))} test files")
for problem in problems:
    print("FAIL:", problem, file=sys.stderr)
if problems:
    sys.exit(1)
print("PASS: All 50 IDs have a present PHP symbol and related existing test file")
print("NOTICE: This is a source mapping only, not proof of functional coverage, real WordPress/MySQL, staging or Live")
