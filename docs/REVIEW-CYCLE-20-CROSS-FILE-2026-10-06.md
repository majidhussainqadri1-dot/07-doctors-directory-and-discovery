# File 07 — Twenty-Round Plan, Central-Plan and Cross-File Review Register

Review window: 2026-10-06 to 2026-10-07  
Starting audited branch head: `c6039ff32d9a612aefda3fa782d51ff3b380d1df`  
Main baseline: `67c32ec4af45a7de6e3d9c1dbf0f8614d6b5a844`

## Method

Each round was completed as a separate concern against the File 07 master plan, consolidated governing plan, the current File 07 source tree, and the current repository contracts of Files 00, 03, 08, 09, 19, 20, 24, 25 and 26. A defect was corrected only after the round was completed, before treating the next round as clean. Repository/source evidence is not staging or live evidence.

## Round register

| Round | Concern | Initial result | Correction / evidence |
|---:|---|---|---|
| 01 | Source/evidence manifest integrity | **Defect** | Manifest referenced the 20-round register and 1.2.1 release note before those files existed. Both evidence files were created. |
| 02 | Runtime/release/schema identity | **Defect** | Runtime was 1.2.1 / DB 1.1.1 / projection 3 while README/readme/changelog still described DB 1.1.0 / projection 2. Documentation was reconciled to executable constants. |
| 03 | Runtime require graph | **Clean** | Every required PHP runtime file exists; no dangling include was found. |
| 04 | Source-integrity inventory | **Defect** | Root source checksum evidence did not include the new cross-file contract layer and later corrective files. A deterministic tracked-source generator and CI verification gate were added, and the manifest is regenerated after the final source changes. |
| 05 | File 00 identity/membership boundary | **Clean** | Current File 00 assertions are consumed through the canonical contract; stale membership meta is not accepted as owner truth. |
| 06 | File 03 public profile/public ID boundary | **Clean** | File 03 remains canonical public-profile/public-ID owner; File 07 does not mint a competing public doctor ID. |
| 07 | File 09 verification boundary | **Clean** | File 09 current integration projection is consumed; stale verification display/meta is not accepted as canonical truth. |
| 08 | File 08 clinic/appointment boundary | **Clean** | File 08 public clinic projection is consumed and absent richer owner data is not fabricated. |
| 09 | File 19 notification handoff | **Clean** | Saved-search alerts use registered File 19 producer + domain-event ingestion; notification state advances only after accepted owner ingestion. |
| 10 | File 20 shell relationship | **Clean** | File 07 publishes bounded verified-doctor IDs; File 20 remains shell/navigation/layout owner. |
| 11 | File 24 fairness assurance | **Defect** | File 07 still listened only for a legacy assurance filter. Added current `spcrc/evaluate_ranking_fairness` consumption with bounded File 26 evidence and honest pass/blocked/unverified mapping; no pass is fabricated. |
| 12 | File 25 visual ownership | **Defect** | Native File 07 CSS used a competing hard-coded palette. Directory and FUT24 fallback styles now consume File 25 `--sabri-*` tokens with accessible local fallback values only. |
| 13 | File 26 ranking and appeals | **Clean** | Current provider registry, ranking constitution, opaque-key URL mapping and canonical appeal service are consumed; File 07 does not own global merit ranking. |
| 14 | F07-FUT-01..24 coverage | **Clean** | All 24 approved future-discovery capabilities remain traceable and covered by the dedicated executable contract. |
| 15 | Canonical routes/page lifecycle | **Clean** | `/doctors/`, search rewrite, public-doctor redirect and `/account/directory-status/` managed-page lifecycle are implemented. |
| 16 | Security/privacy/abuse controls | **Clean** | Nonces, rate limits, privacy exporter/eraser, same-origin controls, fail-closed owner checks and bounded mutation behavior remain present. |
| 17 | Migration/rollback truth | **Defect** | Migration/rollback docs did not describe DB 1.1.1 / projection 3. They now record the additive `avatar_url` projection migration and database-aware rollback requirement. |
| 18 | Accessibility/RTL/reduced motion | **Clean** | 44px targets, focus visibility, RTL handling, reduced motion and contrast regression coverage remain present. |
| 19 | Regression-gate parity | **Defect** | Tests asserted the legacy File 24 surface and did not enforce File 25 token ownership. Central-ranking and source-contract gates now cover the current File 24 evaluator and File 25 token bridge. |
| 20 | Package/release checksum truth | **Defect** | The stale 1.2.0 checksum was replaced only after exact-head deterministic build/clean-extract verification. Canonical 1.2.1 SHA-256: `f06032e1ca39501f0f540e1336011335f0f0ec9ed4a787c376412cb79ba11dfc`. |

## Final twenty-round count

- Total rounds: **20**
- Defect-bearing rounds: **8** — 01, 02, 04, 11, 12, 17, 19, 20
- Clean rounds: **12** — 03, 05–10, 13–16, 18
- Defect-bearing rounds corrected: **8 of 8**
- Post-correction package evidence: deterministic build A/B match, clean-extract verification, **80/80** regression pass, and canonical package SHA-256 recorded above.
- Exact tracked-source checksum generation/verification is part of the final CI gate; `CHECKSUMS.sha256` excludes itself to avoid self-reference.

## Truth boundary

This register is repository/source evidence. It does not prove Hostinger staging installation, deployed package parity, database migration completion, browser/device acceptance, rollback rehearsal, Founder acceptance, live deployment, or operational status.
