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

## Current companion read/action contracts — 2026-10-06
- File 00 identity: `smc_membership_assertions(user_id)`; missing/incompatible current claims deny public eligibility.
- File 03 profile: provider registration action `sabri_file07_register_profile_provider`; File 03 public UUID is canonical and File 07 does not mint a second profile identity.
- File 08 clinic: `sabri_file08_public_clinic_projection_v1`; File 07 consumes only the owner-published public DTO.
- File 09 verification: `GDO_Integration_Contracts::projection(user_id, 'file07')`.
- File 19 saved-search alert: producer `file07-doctor-discovery`, event `DoctorDiscovery.SavedSearchMatched`, ingested through `sun_ingest_domain_event()`.
- File 20 shell: `sabri_shell_verified_doctor_user_ids` receives current eligible File 07 doctor IDs only.
- File 26 ranking: current provider registry `sabri_file25_search_provider` → `file26.doctor_ranking` and `ranking_constitution`; ranking appeals are handed to File 26's owner service.
- File 24 assurance: optional public allowlist only; absence never becomes a positive assurance claim.
