# Rollback Guide

- Do not delete companion-module truth.
- Put File 07 in Safe Mode and stop nonessential mutations.
- Preserve current package, database snapshot, page map, queue state and logs.
- Restore the verified pre-install files/database snapshot or deploy the approved previous package.
- Restore adopted page content/status; draft pages created solely by File 07 where policy requires.
- Purge LiteSpeed/object/page caches and rebuild canonical search/directory projections.
- Verify File 00/09/03/08 owner data remains unchanged.
- Smoke test public directory, private status, saves/reports denial rules and cron health.
- Document recovery time, affected records and post-rollback reconciliation.

A successful backup message is not rollback evidence; an actual restore and smoke test are required.
