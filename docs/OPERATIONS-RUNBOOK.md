# File 07 — Operations, Safe Mode, Repair and Rollback Runbook

## System Check

Open **Doctors Directory → System Check**. The check reports only privacy-safe evidence for contracts, schema, managed routes, cron, outbox/dead letters, projection counts and Safe Mode.

## Safe Mode

Safe Mode is a containment control, not a permanent product change. It blocks feature mutations, reports, saves, event consumption and reconciliation while retaining safe reading of already eligible public projections where possible. Every enable/disable action requires an operator reason and actor audit context.

## Reconciliation

1. Confirm File 00/09/03 contracts are healthy.
2. Run bounded reconciliation from cursor `0`.
3. Continue with returned `next_cursor` until zero.
4. Review errors by safe machine code; correct the canonical owner rather than editing projection truth manually.
5. Confirm `DoctorDirectoryIndexReconciled.v1` outbox event and eligible counts.

## Dead letters

Outbox delivery uses exponential backoff and moves an event to `dead` after eight failed attempts. Investigate destination/provider health, correct the cause, preserve the original event ID/payload, then requeue through a controlled repair command. Never edit secret/provider data into repository documentation or logs.

## Feature expiry

Public queries ignore expired feature state immediately. Hourly cron clears expired state, increments projection version, emits `DoctorDirectoryFeatured.v1` and invalidates cache.

## Incident sequence

1. Contain with Safe Mode when integrity/privacy is at risk.
2. Record trace ID, affected route, time window, source versions and redacted symptoms.
3. Preserve backup and logs under File 24 assurance rules.
4. Diagnose owner contract versus projection versus cache/index versus delivery.
5. Dry-run repair and record expected create/update/quarantine counts.
6. Execute bounded repair, reconcile, purge cache and retest roles.
7. Complete incident review and ratify any permanent change through change control.

## Rollback

- Source rollback alone is insufficient when schema/projection changed.
- Restore the approved consistent database/files/config/key set.
- Protect any post-cutover new data within the accepted RPO.
- Reinstall the prior approved package, purge caches, rebuild rewrites and reconcile.
- Verify public exclusion of suspended/private/deleted doctors before reopening writes.
