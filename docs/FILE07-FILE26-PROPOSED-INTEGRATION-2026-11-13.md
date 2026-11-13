# File07 -> File26 proposed connector — 2026-11-13 source integration candidate

Status: DRAFT, SOURCE-ONLY. This connector is registered as PROPOSED and must never be treated as a production-ready search owner simply because the PHP class or manifest exists.

## Rebase and provenance
- Historical File07 PR12 HEAD 77bd1f8b2efff02251158432830b01b573d25ee2 was based on main v1.2.1. The same native connector PHP code was ported onto the newly source-verified File07 v1.2.2 main HEAD 8f9b567c08e9456daf31ebadf17b13bb307571f8.
- File07 v1.2.2 main source already contains current owner eligibility, canonical public ID, owner-version checks and no-store public REST safety guards.
- This new branch is an isolated compatibility candidate. It does NOT merge or activate the File26 global index, official merit scores or practitioner/clinic authority.

## Explicit source interface and safety
- Manifest slug: file07-verified-doctors, entity_type doctor_directory_projection, status proposed.
- Canonical source ownership: File00 identity, File03 public profile UUID, File09 human-reviewed professional verification, File08 clinic truth, File07 rebuildable safe directory projection.
- list_batch, fetch_object and can_view refuse absent dependency/schema or mismatched owner-projection versions. Never export phone, email, patient information, private verification evidence, clinical chart, unpublished notes or direct medical advice.
- Registration alone grants no File26 index eligibility. Use File26's own independent consent, policy, owner registry and cutover gates.

## Remaining mandatory release blockers
1. File07 PR12's explicit revocation semantics remain pending: owner suspension, expiry, erasure, consent/profile-private, current source event -> File26 tombstone/deletion, cache purge and click/query denial must be demonstrated end to end.
2. File26 PR22 remains DRAFT with broader shadow generation/atomic cutover and MySQL owner-lifecycle evidence still open.
3. File00 Issue #57 remains OPEN P0: circular doctor onboarding pre-verification ownership with File09. File07 must not bypass this.
4. Native File00/03/08/09/19/24/26 real multi-owner WordPress/MySQL integration, exact installed versions, migration, rollback, role/privacy acceptance and Founder approval remain UNVERIFIED.
5. WPVibe site access returned HTTP 530; WordPress/Jetpack account connection is currently marked disconnected. Exact deployed code and schema are unverified.

## Proposed-only test gates
- tests/file26-proposed-connector.php verifies manifest ownership, proposed state, missing prerequisite failures, no fabricated document, denied click-time visibility, and duplicate manifest suppression.
- Both File07 quality workflows run this test alongside current owner and other source regressions.
- Final exact branch HEAD must produce deterministic ZIP twice, exact declared SHA, checksum manifest and full clean-extract safety validation.

## Independent acceptance checklist
- [ ] File26 team verifies proposed manifest on real File26 exact source and no implicit indexing.
- [ ] Implement and independently test authorized owner revocation/deletion/tombstones/events; no overwrite of File26 native policy.
- [ ] Test simultaneous File03+09+07 changes and user privacy withdrawal in disposable WordPress+MySQL.
- [ ] Verify zero exposed retired/private records through API, index, HTML, sitemap, cache, autocomplete and click.
- [ ] Approve activation only after isolated staging backup/restore, rollback rehearsal and signed Founder release decision.

Production truth: Repository main/deployed version/DB/migration/Live acceptance are distinct. No Live fix or connector activation claimed.
