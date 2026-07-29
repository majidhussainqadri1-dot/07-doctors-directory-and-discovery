=== Doctors Directory and Discovery ===
Contributors: sabrihomeopathy
Tags: doctors, directory, profiles, discovery, homeopathy
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 0.2.0
License: GPLv2 or later

A secure, accessible, paginated verified-doctor directory and discovery layer for the Sabri Social Homeopathy Platform.

== Description ==

File 07 provides the public Doctors directory and professional discovery foundation.

Features:
* Public browsing without registration.
* Verified and discoverable doctors only in public search.
* Database-level filtering and deterministic pagination without a 250-doctor ceiling.
* Search by name, specialty, country, city, language, qualification, experience, consultation method, and patient availability.
* Founder presented separately and excluded from ordinary doctor sections.
* Featured, recently joined, and all-doctors sections.
* Professional cards with verification, profile completion, contributions, phone, WhatsApp, and optional internal messaging integration.
* Extended professional profiles including license, authority, professional address, fee, timings, time zone, clinic, appointment, and contact boundaries.
* Status-aware private preview for pending, under-review, rejected, or suspended doctors.
* Safe page ownership: no broad shortcode-based page-content replacement.
* Administrative pagination, visibility controls, report moderation, reviewer notes, and immutable transition audit records.
* WordPress privacy export and erasure callbacks covering directory settings and report data.
* ProfilePage, Person, and credential structured data for public verified profiles.
* Private-page no-cache, noindex, noarchive, and nosnippet controls.
* WCAG-oriented keyboard focus, touch targets, contrast, reduced-motion, responsive, and RTL-ready styling.
* File 20 shell compatibility: File 07 does not render a duplicate global navigation bar.

== Requirements ==

File 03 — Sabri Profiles and Doctors version 0.1.0 or later must be installed and active with its required public helper API. Other platform modules are integrated only when their published pages are genuinely available.

== Installation ==

1. Upload the ZIP through WordPress Admin > Plugins > Add New > Upload Plugin.
2. Activate Doctors Directory and Discovery after File 03.
3. Open the public Doctors page.
4. Review directory settings on a doctor account.
5. Use Doctors Management for featured placement, visibility, and reports.
6. Test fresh install, upgrade, rollback, File 20 shell integration, and all role/privacy cases on staging before production use.

== Safety and trust ==

Directory verification is not an endorsement, treatment guarantee, or substitute for emergency or locally licensed medical care. Users should independently confirm professional licensing and suitability. The plugin does not expose identity documents, create cure claims, or invent ratings.

== Changelog ==

= 0.2.0 =
* Prevented unsafe page-content overwrite and added reversible page ownership.
* Replaced the fixed 250-user directory with database-filtered deterministic pagination.
* Excluded the Founder from ordinary doctor sections.
* Removed duplicate global navigation output and added File 20 integration hooks.
* Added status-aware profile badges and private preview notices.
* Expanded the professional profile and directory settings contract.
* Added optional real-page integrations for Messages, Worldwide Clinic, and Appointments.
* Added report reviewer notes, transition audit records, write-failure handling, and administration pagination.
* Expanded privacy export and erasure coverage.
* Added canonical, structured-data, no-cache, and private-indexing protections.
* Corrected contrast, focus, touch-target, reduced-motion, responsive, and RTL foundations.
* Added dependency API/version validation and schema upgrade handling.

= 0.1.0 =
* Initial modular release.
