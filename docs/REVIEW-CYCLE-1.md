# Fresh Review and Correction Cycle 1 — 2026-08-06

Focus: final architecture, privacy, release identity, compatibility and failure-path audit after the v1.1.0 coding set.

Defects found and corrected:
1. Public DTO could return historical foreign profile/clinic/appointment URLs already stored before the new rebuild gate. Delivery now revalidates every destination through the same-origin allowlist.
2. Adding `display_name_norm` to an existing populated projection could be fragile under strict SQL modes. The schema now supplies a safe empty default and reconciliation fills authoritative values.
3. Final reconciliation emitted the completion event without surfacing an outbox write failure. Event persistence failure is now included in reconciliation errors.
4. Automatic profile/role rebuild failure was silent. Redacted health evidence is now recorded without exposing doctor identity.

Regression: PHP/JS syntax, helper tests, source contracts, pagination, contrast and static audit all passed after correction.
