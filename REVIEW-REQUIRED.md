# Mandatory Staging Review — File 07 Version 0.2.0

The corrective source and automated static gates are complete. The following runtime acceptance remains mandatory before production approval.

1. **Dependency contract:** activate, deactivate, and upgrade File 07 with the exact File 03 package; confirm incompatible/missing dependency behavior fails safely.
2. **Safe page ownership:** test existing blank managed pages, exact legacy shortcodes, mixed-content pages, same-slug unrelated pages, deactivation restoration, and reactivation.
3. **Database migration:** upgrade a copy of the 0.1.0 reports table; verify new columns, report-audit table, existing timestamps, rollback, and no data loss.
4. **Directory scale:** seed more than 250 verified doctors; verify complete searchability, deterministic ordering, counts, Founder exclusion, and pagination under every filter.
5. **Roles and states:** test Founder, Administrator, verified doctor, pending doctor, under-review doctor, more-information, rejected, suspended, patient, student, and logged-out visitor.
6. **Visibility and contacts:** verify hidden/discoverable states, required professional-contact policy, Phone, WhatsApp, optional Network Message, Clinic, and Appointment links only when their genuine published destinations exist.
7. **Profile contract:** verify license, authority, address, fee/currency, timings/time zone, specialty, qualification, clinic, methods, languages, studied books, owner actions, and safe empty states.
8. **Report moderation:** verify rate limit, failed insert behavior, reviewer-note requirement, atomic status/audit transition, pagination, anonymized users, and administrator permissions.
9. **Privacy:** verify export and erasure batches for directory settings, reports submitted by the user, reports about a doctor profile, retained non-identifying audit data, and repeated pages without skipped rows.
10. **SEO/cache:** verify canonical URLs, public-doctor sitemap, structured credentials, noindex/noarchive/nosnippet, private preview no-cache headers, and absence of duplicate canonicals.
11. **File 20 integration:** confirm no duplicate header/navigation and correct rendering inside the Unified Application Shell.
12. **Accessibility:** keyboard-only operation, visible focus, 44px targets, screen-reader labels, contrast, reduced motion, semantic headings, zoom, and no keyboard traps.
13. **Responsive/RTL:** accept 320, 360, 390, 480, 768, 900, 1024, 1100, 1280, 1366, 1440, 1600, and 1920px; verify no horizontal page overflow and test Urdu/Arabic RTL.
14. **Cross-browser:** current Chrome, Edge, Firefox, Safari, Android Chrome, and iOS Safari.
15. **Operations:** backup, fresh install, upgrade, deactivation, reactivation, rollback restore, cache purge, uninstall-retention policy, and post-deployment smoke test.
16. **Founder acceptance:** final screenshots, real sample profiles, search results, profile settings, report moderation, and mobile views require explicit acceptance.

Any failure reopens correction work and blocks progression.
