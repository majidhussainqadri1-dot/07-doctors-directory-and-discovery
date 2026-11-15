# File 07 V12.3 — Operator-only reality freeze probe and safety boundary

## What this does and DOES NOT establish

`ops/file07-readonly-reality-freeze.php` is a **staging-only diagnostic** kept OUTSIDE the installable plugin archive. It reads declared installed File07 plugin version, header SHA-256, a bounded deterministic plugin-tree SHA-256, selected non-secret WordPress option values, the 12 known File07 table names/column signatures and aggregate row counts, scheduled File07 cron hooks, and rough companion-plugin identifiers. It emits one JSON snapshot with no doctors, patient records, raw reports, user IDs, phone, email, token or log text.

**It cannot establish ZIP parity**: `tree_sha256` != `zip_sha256`. It never certifies plugin code and DB running behavior. It does not execute user journeys or a migration.

**WARNING — no guarantee of overall read-only WordPress startup:** WP-CLI `eval-file` bootstraps WP before running this script; File07 `plugins_loaded` can trigger `DDD_Activator::maybe_upgrade()`, which can mutate database and pages if loaded. `--skip-plugins` prevents normal plugins; must-use plugins can still run. Therefore DO NOT use `wp eval-file` as the initial Live Reality Freeze. Obtain separate Hostinger immutable filesystem snapshots, read-only database snapshots/queries and host logs first. Only execute this probe against a **disposable isolated restored staging copy** with owner-approved source and backups.

## Safe operator procedure — isolated copy ONLY

1. Verify restored clone's site URL, copies of files+DB and completely separated outbound email/notifications; ensure the clone cannot contact patients or active services.
2. Verify whether the clone has mu-plugins or other startup hooks capable of writes; use actual database read-only credentials if feasible. Never assume `--skip-plugins` is absolute.
3. Run from the **repo workspace** with an authorized shell and WP-CLI:
   ```sh
   wp --skip-plugins --skip-themes eval-file ops/file07-readonly-reality-freeze.php > private-file07-freeze.json
   ```
4. Store JSON privately in evidence register and label `environment`, exact capture date, platform and authorization. Do not paste sensitive host data into a public issue.
5. Compare declared source plugin `1.2.2`, schema `1.1.1`, projection `3` with actual clone results; identify missing/extra columns and unfinished cursor without making a code-bug root-cause claim from source alone.
6. Record full SHA/parity method for any actual installed source and compare against a **same-algorithm** baseline; use actual downloadable deployed ZIP hash only when the exact ZIP exists.
7. Only after an independent actual Live freeze and parity decision may an authorized maintainer consider staging migration or production actions.

## Output contract, privacy and failure modes

- Version fields come from plugin header and `wp_options`, not from GitHub HEAD.
- Table rows are aggregate `COUNT(*)` only. These SELECTs can be expensive on huge tables; operator must evaluate load on the isolated clone first.
- Symlink, file-size >20MiB, or >500 files marks `plugin_tree.complete=false`; never claim exact tree parity.
- A missing File07 install returns `file07_installed=false`; it does not by itself distinguish a Live parity incident because environment may be an isolated copy.
- The `selected_companions` mapping is a **heuristic name/slug hint**, not a definitive source-owner contract. Operator must inspect exact versions and API compatibility separately.
- `write_operations=0` would be an overclaim: the JSON instead states `script_write_operations=0` and `bootstrap_side_effects=NOT ATTESTED`.
- Logs and migration errors remain private; this script reports no log contents.

## Evidence status on 2026-11-14

Tool written and repository CI static/syntax tested in Draft PR #16 only. **Not executed against Hostinger or any staging database.** Jetpack is `site_disconnected` and WPVibe connection returned HTTP 530. Repository HEAD / Deployed Version / DB Version / Migration State / Live Verification Status must remain five separate facts.

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**
