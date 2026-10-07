# Migration Guide

Current source target: runtime `1.2.1`, database schema `1.1.1`, projection schema `3`. The 1.1.1 upgrade is additive: `dbDelta` adds the public-safe `avatar_url` field to the rebuildable doctor projection; it introduces no new File-07 table and does not transfer media ownership from File 03.


1. Record exact current version, schema, package and active companion contract versions.
2. Verify files+database backup by isolated restore before mutation.
3. Install on staging; activation lock prevents concurrent schema owners.
4. Run idempotent table upgrade and InnoDB verification.
5. Legacy reports migrate in bounded resumable batches with a stored cursor.
6. Managed pages preserve prior content/status ownership and track created/adopted pages.
7. Run reconciliation until `next_cursor=0`; inspect errors/dead letters.
8. Compare source counts, eligible counts, duplicate public IDs, routes, cache and search output.
9. Execute real upgrade paths from every supported deployed version.
10. Proceed only after the staging matrix and rollback rehearsal pass.
