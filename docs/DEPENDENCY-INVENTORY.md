# Dependency and Supply-Chain Inventory

## Runtime dependencies
- WordPress core APIs only.
- PHP extensions/functions used by WordPress/PHP baseline; no Composer packages.
- Browser JavaScript without external npm runtime libraries.

## Build/test tools
- PHP CLI
- Node.js syntax checker
- Python 3 standard library
- POSIX shell, `zip`, `unzip`, `sha256sum`, `cmp`
- GitHub Actions `actions/checkout@v5`

No bundled third-party binary, remote script, tracking SDK, analytics SDK or payment library is included.

## Current cross-file contracts reviewed 2026-10-06
- File 00: `SMC_CONTRACT_VERSION >= 1.2.3`; identity via `smc_membership_assertions()`.
- File 03: `SPD_VERSION >= 1.2.0-rc2`, `SPD_CONTRACT_VERSION >= 1.4.0`; public profile truth is consumed from the canonical public-profile API, with `sabri_file07_register_profile_provider` accepted when the owner publishes that optional registration path.
- File 08: doctor-scoped public clinic projection through `sabri_file08_public_clinic_projection_v1`; richer FUT24 geo/availability data remains an optional owner extension and is never fabricated.
- File 09: `GDO_Integration_Contracts::VERSION >= 1.1.0`; verification projection via `GDO_Integration_Contracts::projection(..., 'file07')`.
- File 19: `sun_register_notification_producer()` + `sun_ingest_domain_event()` using `sun.event.v1`.
- File 20: File 07 supplies a bounded current `sabri_shell_verified_doctor_user_ids` projection; File 20 remains shell owner.
- File 24: current ranking-fairness assurance is consumed through `spcrc/evaluate_ranking_fairness`; File 07 forwards bounded File 26 evidence and preserves `blocked`/`unverified` instead of fabricating `pass`.
- File 25: canonical visual ownership is honored through the `--sabri-*` token bridge; File 07 retains accessible fallback values only for owner absence/degraded rendering.
- File 26: current ranking provider is discovered through `sabri_file25_search_provider`; ranking constitution and appeal service remain File 26-owned.
