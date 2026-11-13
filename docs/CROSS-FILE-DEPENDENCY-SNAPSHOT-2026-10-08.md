# File 07 — Fresh Cross-File Dependency Snapshot — 2026-10-08

This is repository-source evidence captured for the fresh File 07 audit. It is not staging or live deployment evidence.

| File | Repository | Audited main HEAD | File 07 relationship |
|---|---|---|---|
| 00 | majidhussainqadri1-dot/00-sabri-membership-core | `2fa7c022ee9cd1b65432e900579512f304532442` | identity, membership, age/guardian and institutional assertions |
| 03 | majidhussainqadri1-dot/03-sabri-profiles-and-doctors | `636e3ef965423887f810718abec3cd1c11c3659d` | canonical public professional profile/public ID |
| 08 | majidhussainqadri1-dot/08-worldwide-clinic-and-appointments-foundation | `70541974ce0ffb16aebef557c3016eb7447662f4` | clinic and appointment owner/public clinic projection |
| 09 | majidhussainqadri1-dot/09-global-doctor-onboarding-and-verification-completion | `cfc5f781a766330314dc98c42abeca0eb7786eba` | doctor verification/onboarding decision projection |
| 17 | majidhussainqadri1-dot/17-sabri-network | `8ae656e51796d1f05865d8be5dca2480443d79ca` | communications owner; no File 07 duplicate ownership |
| 19 | majidhussainqadri1-dot/19-sabri-unified-notifications | `04078025b643ab7696e4cb4e37826bf152defa18` | saved-search notification producer/ingestion |
| 20 | majidhussainqadri1-dot/20-sabri-unified-application-shell | `8a4dbcaf4fef8e926b9b834ecfde16c21a0f00ca` | shell/page integration and verified-doctor projection consumer |
| 21 | majidhussainqadri1-dot/sabri-complete-home-news-feed | `f2eb7e95ddea327af36ea725ffb923b029f885e6` | Home/News consumer; no feed ownership in File 07 |
| 22 | majidhussainqadri1-dot/sabri-universal-post-composer | `b7a7f2e69411cbd32f0574fd12d766fb70c01b7a` | composer owner; File 07 does not duplicate create/publish |
| 23 | majidhussainqadri1-dot/23-sabri-doctor-founder-publishing-dashboard | `dcae138e6073f4d0ff596623deb05b9940b8271b` | publishing-dashboard owner; directory remains projection-only |
| 24 | majidhussainqadri1-dot/24-sabri-platform-security-privacy-compliance-and-resilience-center | `a5b8d49968a7a5a7d6f3f4655bea541bf38a9acb` | fairness/security assurance |
| 25 | majidhussainqadri1-dot/25-sabri-public-ui-profile-timeline-visual-experience | `e35563b7f3d8ebf0acbbc80982b7bcf2e1b78c0a` | visual/design-token owner |
| 26 | majidhussainqadri1-dot/26-search-discovery-recommendations-knowledge-graph-classification | `bbea3aad466792a4a6a62b53532bbd45c7c592de` | official global merit ranking/search/appeal owner |

## Fresh compatibility findings

File 08's current public clinic projection is safe and owner-controlled but does not currently publish every richer field File 07 can consume for consultation-mode, accepting-patient, fee/currency and availability filtering. File 07 therefore treats missing owner fields as unknown and does not fabricate them. The v1.2.2 correction also prevents unknown fee values from satisfying bounded fee filters.

File 24 requires fairness evidence freshness within 31 days and can explicitly return a blocked state. File 07 v1.2.2 aligns its ranking freshness window to 31 days and withholds official merit tiers when File 24 explicitly blocks the current File 26 evidence.

The current File 26 fallback policy identifier may be `doctor-global-1.0`, while File 24's current policy-version validator accepts numeric semantic-version syntax. File 07 does not rewrite either owner's truth. If this combination causes File 24 to block assurance, File 07 fails closed for merit ranking and can expose only the neutral, clearly non-merit All Verified fallback.

No companion repository was modified in this File 07 correction set.
