# File 07 — authorized read-only Hostinger/WordPress reality freeze (2026-11-15)

**Governing:** Sabri Website Master Plan V12.3 §8/§12, File07 13-phase execution plan Phase 00, CEN-REL-001. **NO LIVE CHANGES OR WRITE OPERATIONS AUTHORIZED BY THIS DOCUMENT.** This is a collection procedure for a separately authorized hosting operator, not proof that any command has been run.

## 0. Keep three environments distinct

Record the URL and instance ID for **LIVE**, **STAGING**, and **GITHUB main** separately. GitHub `main` currently at `8f9b567c08e9456daf31ebadf17b13bb307571f8` describes repository source, **not** installed PHP.

A 2026-07-14 Jetpack plugin snapshot is historical only. On 2026-11-15 WP.com reports `site_disconnected`, WPVibe has no registered site and its connection attempt produced HTTP 530. HTTP 530 alone proves neither host fault nor successful plugin access. An unverified live error root cause must not be inferred from these symptoms.

## 1. Authorized File Manager/SFTP read-only capture (no WordPress bootstrap)

Use Hostinger File Manager or an **authorized read-only SFTP** session on the correct site root. Capture the **actual installed** `wp-content/plugins/doctors-directory-and-discovery/doctors-directory.php` header and all File07 included PHP, JS/CSS, readme and uninstall files. Check whether the installed plugin directory name differs and record that fact.

Optional POSIX commands on the correct host, when a read-only shell is authorized:

```sh
# Replace /ABSOLUTE/SITE/ROOT with the operator's verified document root.
# No redirects to web-accessible files. No WordPress or plugin bootstrap.
site=/ABSOLUTE/SITE/ROOT
test -f "$site/wp-content/plugins/doctors-directory-and-discovery/doctors-directory.php"
sed -n '1,45p' "$site/wp-content/plugins/doctors-directory-and-discovery/doctors-directory.php"
sha256sum "$site/wp-content/plugins/doctors-directory-and-discovery/doctors-directory.php"
find "$site/wp-content/plugins/doctors-directory-and-discovery" -type f -print0 | sort -z | xargs -0 sha256sum
```

**Hash meaning:** exact file SHA-256 checks installed bytes; a collection of file hashes is **not** an installed ZIP SHA or Git tree SHA. Compare only identical algorithms and path normalization. Never include upload directories, private patient files or `wp-config.php` contents in public evidence. Do not expose config/database credentials.

Record active File 00,03,07,08,09,19,20,24,25,26 plugin *installed* version and main plugin file hash independently from repository versions.

## 2. Read-only SQL on the VERIFIED correct database

Use a DB account with **SELECT on minimal WordPress metadata and SELECT on information_schema**. An application admin or the browser alone is not necessarily a DB read-only account. Replace `wp_` below with the verified actual WordPress table prefix. Never run the queries against a guessed database. Do not use `SELECT *` on personal/clinical tables.

```sql
-- Installed file version and DB version are independent facts.
SELECT option_name, LEFT(option_value, 120) AS value_excerpt
FROM wp_options
WHERE option_name IN (
 'ddd_version', 'ddd_db_version', 'ddd_contract_version',
 'ddd_projection_schema', 'ddd_legacy_reports_migrated',
 'ddd_legacy_reports_cursor', 'ddd_reconcile_cursor',
 'ddd_safe_mode'
)
ORDER BY option_name;

-- NEVER display the ddd_activation_lock token; only whether the key exists.
SELECT COUNT(*) AS activation_lock_option_present
FROM wp_options WHERE option_name='ddd_activation_lock';

-- Installed table existence, storage engine and APPROXIMATE InnoDB row estimates.
SELECT table_name, engine, table_rows
FROM information_schema.tables
WHERE table_schema=DATABASE() AND table_name LIKE 'wp\\_ddd\\_%'
ORDER BY table_name;

-- Column-level schema signature only: no personal row data.
SELECT table_name, column_name, column_type, is_nullable
FROM information_schema.columns
WHERE table_schema=DATABASE() AND table_name LIKE 'wp\\_ddd\\_%'
ORDER BY table_name, ordinal_position;
```

`information_schema.tables.table_rows` can be **approximate** for InnoDB. Do not treat it as a certified exact personal-data count. Exact row-count queries should be planned separately, bounded by DB owner approval and incident load limits.

Do not output `active_plugins` values, authentication cookies, salts, nonce/token options, identity-document locations, patient records, doctors' sensitive fields, private reports, or raw error log contents into public issues.

## 3. WordPress runtime, logs, migration and extension truth

From Hostinger environment controls capture platform versions: WordPress, PHP, MySQL/MariaDB, active theme, LiteSpeed/object/CDN cache, PHP error log **redacted** excerpts around exact failing timestamps, and background cron health. Observe actual File07 events `ddd_reconcile_tick`, `ddd_expire_features_tick`, `ddd_process_outbox_tick` and `ddd_continue_legacy_migration` read-only; no cron invocation.

Confirm whether WP upgrade had completed, been deferred, or partially run. A stored `ddd_db_version` value does not prove all DB columns/indices actually exist; compare real schema. Avoid running `wp eval-file` or `wp plugin list` as an initial Live freeze because WP-CLI WordPress bootstrap can trigger installed plugin migration hooks. PR16's diagnostic is restricted to an **isolated restored staging copy**, not the first Live probe.

## 4. Parity decision

| Evidence | Live | Staging | GitHub |
|---|---|---|---|
| Runtime / plugin version | UNKNOWN until captured | UNKNOWN | File07 source 1.2.2 |
| Exact installed File07 file hashes | UNKNOWN | UNKNOWN | GitHub main alone is NOT an installed hash |
| DB schema / migration / cursor | UNKNOWN | UNKNOWN | Repo declares 1.1.1 projection 3 |
| WP/PHP/MySQL runtime logs | UNKNOWN | UNKNOWN | None from GitHub |
| Feature flags / active plugin load order | UNKNOWN | UNKNOWN | Source hooks only |
| Functional user-journey retest | NOT EXECUTED | NOT EXECUTED | Source tests only |

If any actual installed hashes or active schema differ from the chosen source candidate, open **Deployment-Parity Audit** before doing application-code debugging. Do not patch until a root cause is established for the **exact deployed build**. No "Resolved" before original symptom is re-tested on LIVE.

## 5. Secure handoff form

Store in the private incident register: capture UTC timestamp, authorized operator, site and environment ID, installation paths, declared plugin version, per-file SHA-256 manifest identity, actual schema tables/column names, safe options above, migration state, redacted error code/timestamp, platform versions, reviewer and proof of parity method. Never place tokens, raw logs or personal data in public GitHub.

**Repository HEAD:** `8f9b567c08e9456daf31ebadf17b13bb307571f8` (main, source only).
**Deployed Version:** UNVERIFIED.
**DB Version:** UNVERIFIED.
**Migration State:** UNVERIFIED.
**Live Verification Status:** NOT EXECUTED.

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**
