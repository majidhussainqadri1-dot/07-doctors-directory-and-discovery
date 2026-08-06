# Threat Model

## Protected assets
Public-listing integrity, private identity/profile/clinic facts, doctor eligibility state, moderation reports, operator capabilities, event integrity, cache/index correctness and release evidence.

## Principal threats and controls
- **Unverified/private/suspended exposure:** mandatory owner contracts fail closed; click-time recheck; short-lived/invalidation-aware projection; scheduled reconciliation.
- **IDOR/enumeration:** opaque persistent UUIDs; object/state checks; restricted status endpoint.
- **Ranking manipulation/paid concealment:** documented bounded ranking; feature label/reason/period/audit; no payment signal.
- **SQL/filter abuse and scraping amplification:** allowlisted normalized filters, prepared SQL, bounded limits/cursors, persistent rate limits.
- **Replay/duplicate mutation:** idempotency keys, unique constraints, expected versions, signed event timestamps, inbox dedupe.
- **Queue concurrency/loss:** claimed workers, stale-claim recovery, bounded exponential retry, dead-letter state and reconciliation.
- **CSRF/XSS/open redirect:** WordPress nonces/capabilities, output escaping, same-origin route validation.
- **Privacy leakage:** explicit DTO allowlists, no internal IDs, redacted logs, no-store/noindex private surfaces, legal-hold-aware erasure.
- **Schema/migration corruption:** activation lock, InnoDB, idempotent schema, resumable migration, repair preview and external backup/rollback gate.
- **Single-point security failure:** File 07 native enforcement remains active; File 24 consumes assurance evidence but does not replace authorization.

External penetration, real provider failure injection and Hostinger restore/rollback remain mandatory staging evidence.
