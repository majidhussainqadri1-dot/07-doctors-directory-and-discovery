# File 07 — Fresh Review and Correction Cycle 1

## Scope

Architecture, canonical ownership, database design, concurrency, security, privacy, search scale, SEO, accessibility, migration and operability were reviewed after the 1.0.0 rewrite.

## Defects found and corrected

1. **Expired feature update used an invalid object placeholder in `wpdb::update`.** Replaced with a bounded prepared arithmetic update and expiry event.
2. **Event inbox processing wrapped a projection method that opened its own transaction.** Removed unsupported nested transaction behavior; event IDs are reserved, projection rebuilt, then inbox marked processed; failed rebuild releases reservation.
3. **Deleted users could generate a new orphan public ID.** Rebuild now preserves the existing projection public ID when owner profile data is absent.
4. **Privacy erasure reset report version to 1.** It now increments the existing optimistic-concurrency version.
5. **`account/directory-status` insertion did not create the parent route.** Added safe parent-path creation and child-page ownership.
6. **Public actions initially depended on internal numeric doctor IDs.** Front-end and REST actions now use opaque public IDs and resolve internal IDs server-side.

## Retest

PHP syntax, JavaScript syntax, helper tests, static audit, contrast tests and deterministic package build passed after correction.
