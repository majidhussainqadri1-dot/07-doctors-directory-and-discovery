# API and Event Contracts

## REST namespace
`doctors-directory-discovery/v1`

## Public queries
- `GET /doctors` — allowlisted filters, bounded limit, signed cursor, public DTO only.
- `GET /doctors/{public_id}` — opaque UUID; click-time owner eligibility recheck.
- `GET /facets/{type}` — bounded/rate-limited autocomplete for approved taxonomy families.
- `GET /ranking` — public File 26 read bridge; endpoint rate-limited, provider page bounded to the requested limit, and malformed/duplicate/out-of-tier/explanation-less provider items fail closed.
- `GET /future/offline-pack` — public-safe explicit field allowlist, six-hour declared lifetime, `must-revalidate`; no stale-if-error serving beyond `expires_at`.

## Authenticated queries/mutations
- `GET /status` — current doctor only; private/no-store.
- `POST /reports` — current user, nonce/REST authentication, rate limit and idempotency.
- `PUT|DELETE /saves/{public_id}` — current account-owned reference.
- `POST /events` — HMAC-signed timestamped envelope and replay-resistant event ID.
- `GET /future/saved-searches` and `GET /future/shortlists` — current user only; private/no-store.
- `POST|DELETE /future/saved-searches[...]` and `POST|DELETE /future/shortlists[...]` — current user only; WordPress REST authentication/nonce where cookie-authenticated, required `Idempotency-Key`, per-user bounded rate limit, per-user serialization lock, 24-hour replay receipt, conflicting key reuse rejection, private/no-store response and redacted mutation audit. The browser client generates a fresh idempotency key for each new mutation.

## Operator routes
- Reconcile, System Check and Repair require current server-side directory capabilities. Safe Mode blocks nonessential mutation.

## Consumed owner facts
- `DoctorVerified.v1`
- `DoctorSuspended.v1`
- `PublicProfileUpdated.v1`
- `ClinicAvailabilityChanged.v1`
- `DoctorDeleted.v1`

## Published facts
- `DoctorDirectoryEligibilityChanged.v1`
- `DoctorDirectoryFeatured.v1`
- `DoctorDirectoryProjectionDeleted.v1`
- `DoctorDirectoryIndexReconciled.v1`
- taxonomy/change events used by registered consumers.

Events are past-tense facts, delivered at least once. Consumers must deduplicate. An event never grants authorization.
