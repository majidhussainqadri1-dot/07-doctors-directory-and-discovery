# File 07 — 2026-11-13 exact-source companion audit and release gates

Evidence boundary: read-only GitHub/source audit, NOT Hostinger staging, Live deployed artifact or DB proof. This is newer source evidence than the Oct 09 snapshot, which remains an immutable historical record.

## Frozen repository/source state
- File07 main: 2f4a89707724fd2b9946600afe10ddab27ec3c2d — runtime 1.2.1 / declared DB 1.1.1 / projection 3.
- File07 PR11: 491665ddcec7cc8a8e1df6567cdf423461bb63d2, open 1.2.2.
- File07 PR13: newest source on the present branch. Verify exact HEAD from CI, not from this prose. Includes corrected language and country search plus current-owner public disclosure guard.
- File07→File26 PR12: 77bd1f8b2efff02251158432830b01b573d25ee2; DRAFT proposed-only. Source revocation/tombstone and full provider acceptance not complete.
- File26 shadow/cutover PR22: open DRAFT; no approved production doctor connector.

## Companion repository main HEADs as inspected

| Owner | Main SHA | Source-of-truth responsibility |
|---|---|---|
| File00 | 2fa7c022ee9cd1b65432e900579512f304532442 | Identity, age/guardian and suspension |
| File03 | bf317e5521ec79f9f16d21353f402571fcd4f84c | Public doctor UUID/profile |
| File08 | d086840f7e2ac0ed328006385f064e0e2fb58cb1 | Clinic, fee, availability and public clinic versions |
| File09 | c07d122d3d802cfbc64ac94e3522ab9cc374ac73 | Current professional verification/expiry |
| File17 | 8ae656e51796d1f05865d8be5dca2480443d79ca | Communications; not File07 owned |
| File19 | 66a4de6077e32c4c6cd692ae3d906afc4a5f3eb2 | Producer registration and notification ingestion |
| File20 | 8a4dbcaf4fef8e926b9b834ecfde16c21a0f00ca | Unified shell/navigation |
| File24 | a5b8d49968a7a5a7d6f3f4655bea541bf38a9acb | Fairness/security assurance |
| File25 | 347a4ff4d4c233c5ea6cd82c7786ee5398ea9d1e | Canonical design tokens |
| File26 | 1b064c6ea9f81195611ed001d5457e8ced65460a | Search index, merit rankings and appeals |

## Source review A — governing plan, contracts and safe read
- V12.1 lists 15 FR + 10 NFR + 1 CEN + 24 FUT = 50 requirements. A source traceability table is not real staging acceptance.
- The 2026-10-09 companion snapshot did not reflect later File03/08/09/19/26 commits. Revalidate executable APIs against exact installed owner versions before production.
- File07 current-source correction now rechecks owner eligibility, canonical public UUID and source versions on directory search/detail/sitemap; missing DB result fails closed; REST search/detail disable stale public caching.
- File08 optional richer discovery projection remains unproven for some fields. Unknown fees/availability cannot be fabricated.
- File26 doctor-global-1.0 policy identifier and File24 semver-only validator may disagree; prohibited/blocked merit must never be shown as verified.

## Source review B — adversarial and deployment blockers
- File00 GitHub issue #57 is a currently OPEN P0 cross-file blocker: a circular File00↔File09 preverification eligibility contract may prevent an otherwise approved new doctor from applying for professional verification. File07 must NEVER bypass the verification authority.
- File07 PR12 + File26 PR22 are proposed/DRAFT, lacking independently accepted real owner revocation/cutover, MySQL and end-to-end lifecycle evidence.
- Rechecking File00/File03/File09/File08 per public result raises latency/owner-call costs; measure p75/p95 under realistic load, do not assert performance based on source CI.
- WordPress page cache, CDN cache, sitemaps, cursor leakage, facet stale counts, RTL/accessibility, real role and negative provider tests remain staging gates.
- Clinical data, private phones and credential evidence must never enter public DTOs or File26 derivative index.

## Five required production facts
| Field | Current fact |
|---|---|
| Repository main HEAD | 2f4a89707724fd2b9946600afe10ddab27ec3c2d at this source freeze |
| Deployed Version | UNVERIFIED |
| DB Version | UNVERIFIED |
| Migration State | UNVERIFIED |
| Live Verification Status | NOT TESTED |

WPVibe returns HTTP 530; connected WordPress.com/Jetpack reports site_disconnected. Neither observation proves the failing network hop or a particular WordPress origin cause.

Verdict: source corrections and exact-HEAD tests may proceed. Installation/activation, official File26 connector cutover, staging acceptance and claims of Live-Operational or Resolved remain NO-GO without genuine installed code/schema/restore and Live re-test evidence.
