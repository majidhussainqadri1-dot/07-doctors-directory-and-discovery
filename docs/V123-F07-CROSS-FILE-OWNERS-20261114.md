# File 07 — V12.3 cross-file current-source matrix (2026-11-14)

**Repository SHA only; NOT installed source.** Re-query each owner HEAD and installed package immediately before staging acceptance.

| Owner | Main commit SHA observed | Canonical responsibility | Current acceptance |
|---|---|---|---|
| File 00 | `1fad051c86d530d558be3eb1890d3c6e67bce6e8` | membership, identity, suspension, scoped doctor-verification intake | Scoped File00→File09 provider source merged, staged/live unknown |
| File 03 | `bf317e5521ec79f9f16d21353f402571fcd4f84c` | public doctor UUID and professional profile | Must verify real public DTO/consent/tombstones |
| File 08 | `d086840f7e2ac0ed328006385f064e0e2fb58cb1` | clinic, fee, public locations, availability and services | Rich public data availability not fully verified |
| File 09 | `8f52f87a6d82fc89c6c78b7da80b474dff916d97` | professional verification, expiry, human review | Scoped File00 intake consumer source merged, staged/live unknown |
| File 19 | `3cd8f01a4614e96c0104d0ff82faa4ad3f02f4e4` | saved-search notification ingestion/delivery | Source 3.0.9 candidate; real File07 event acceptance missing |
| File 20 | `8a4dbcaf4fef8e926b9b834ecfde16c21a0f00ca` | global shell, menu, routes | Real theme/shell acceptance missing |
| File 24 | `a5b8d49968a7a5a7d6f3f4655bea541bf38a9acb` | independent fairness/security assurance | Verify File26 policy and assurance compatibility |
| File 25 | `347a4ff4d4c233c5ea6cd82c7786ee5398ea9d1e` | canonical visual tokens and components | Browser/AT acceptance missing |
| File 26 | `1b064c6ea9f81195611ed001d5457e8ced65460a` | official doctor merit ranking, search index, appeal | PR #22 remains DRAFT; no production connector acceptance |
| File 07 | `8f9b567c08e9456daf31ebadf17b13bb307571f8` | rebuildable public directory projection and UI | Main runtime source v1.2.2; package/CI only |

## High-impact owner state update

File00's purpose-scoped `smc.file09.doctor-intake@1.0.0` provider and File09 consumer are NOW **merged source** with exact-main CI green. This supersedes historical comments that the source fix is wholly absent, but original File00 Issue #57 rightly remains open for real staging/deployed acceptance. The contract grants only intake, never public verified badge, can_practice, chart, appointment or prescription authority. File07 must not bypass File09.

File07 PR #15 head `afdd751486835ff50cb7efc54b55813350402a08` is DRAFT, PROPOSED-only File07→File26 connector. File26 PR #22 head `c0dc5ac09fdf068f5e77dab0e7f0862a21f33cb1` is source-only DRAFT. No connector activation or merge is authorized by this document.

## Contract-by-contract staging matrix

1. File00 + 09: approved new doctor role with pending professional verification may enter application intake ONLY; suspended, stale, underage or unknown must fail closed.
2. File03: immutable public UUID, published consent, no hidden phones/identity evidence in search/detail/sitemaps.
3. File08: published country entries exact equality; unknown fee/currency/availability never match as known.
4. File19: authenticated saved-search producer, permitted data fields, idempotency, quiet-hours and honest delivery acknowledgment.
5. File20/25: single shell and File25 canonical tokens, no duplicate global navigation or invented design owner.
6. File24/26: Top10/100/1000 nested monthly tiers with File24 allowed assurance, transparent explanations, no paid/donor signal; in a blocked/unavailable state only neutral All Verified fallback.
7. File07→26: owner event/tombstone, indexed revocation, stale cursor/cache/page/SEO purge, current click-time authorization, no patient/credential/private data.

Source, staging and live contract columns must remain separate.
