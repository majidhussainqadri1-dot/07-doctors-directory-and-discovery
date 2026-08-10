# Operations Runbook

## Routine checks
- Run **Doctors Directory → System Check**.
- Monitor contract health, schema versions, managed routes, cron schedules, projection counts, stale outbox claims and dead letters.
- Run bounded reconciliation until complete after owner changes, migration or cache/index incident.
- Review feature expiry, moderation queues and taxonomy changes under least privilege.

## Safe Mode
Safe Mode disables nonessential saves/reports/reconciliation while preserving public-safe reading from current eligible projection. Enable with a reason and audit; do not use it to bypass owner authorization.

## Repair
Repair defaults to preview. Execute only after backup/restore verification and operator review. Repair is bounded to File 07 tables/options/pages/capabilities/schedules; it does not rewrite companion truth.

## Incident priorities
1. Hide stale/private/suspended records immediately through native eligibility/event handling or Safe Mode.
2. Preserve audit/event evidence.
3. Reconcile source owners, projection, cache and global search consumers.
4. Record redacted trace IDs and timestamps.
5. Escalate security/privacy assurance evidence to File 24.
