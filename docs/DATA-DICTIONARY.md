# Data Dictionary

All tables use the WordPress prefix plus `ddd_` and InnoDB.

| Table | Purpose | Data class |
|---|---|---|
| `ddd_projection` | Rebuildable public-safe doctor directory projection and index fields | Public/Internal versions |
| `ddd_taxonomy` | Canonical directory taxonomy, aliases and versions | Public/Internal audit |
| `ddd_saved_refs` | Account-owned saved doctor references | Private |
| `ddd_reports` | Listing concern intake and reviewed state | Restricted |
| `ddd_report_audit` | Immutable moderation transitions | Restricted audit |
| `ddd_feature_audit` | Featured-state history | Restricted audit/public label projection |
| `ddd_admin_audit` | Directory operator actions | Restricted audit |
| `ddd_outbox` | Reliable published event queue | Internal |
| `ddd_inbox` | Dedupe, claim, retry and dead-letter state for consumed events | Internal |
| `ddd_search_metrics` | Minimized aggregate search counts | Internal aggregate |
| `ddd_rate_limits` | Hashed scope/bucket counters | Security operational |
| `ddd_health_log` | Redacted operational evidence | Internal |

Public output uses opaque `public_id`; raw WordPress user IDs, identity evidence, private phone/address and internal risk details are not public contract fields.
