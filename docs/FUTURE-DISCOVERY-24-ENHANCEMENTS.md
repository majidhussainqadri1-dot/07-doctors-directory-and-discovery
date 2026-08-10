# File 07 — Future Doctor Discovery 24 Enhancements

Status: implemented repository candidate for File 07 v1.2.0. These are File-07 discovery capabilities only; canonical truth remains with the owning modules. Official global merit ranking remains File 26-owned. Clinic/availability/location truth remains File 08-owned; professional-profile truth File 03-owned; verification File 09/00-owned; notifications File 19-owned; assurance File 24-owned.

| ID | Enhancement | Canonical implementation rule | Acceptance evidence |
|---|---|---|---|
| F07-FUT-01 | Compare Doctors | Compare 2–4 currently eligible public doctors using factual public projections; never label a winner. | `/future/compare`; eligibility re-read; factual-comparison notice. |
| F07-FUT-02 | Guided Doctor Finder | Convert a bounded questionnaire into approved directory filters; no diagnosis. | `/future/guided`; accepting-patient default; same discovery pipeline. |
| F07-FUT-03 | Privacy-Safe Near Me | Browser geolocation only after user action; exact user location not persisted by File 07. | Haversine distance; private/no-store response when coordinates used. |
| F07-FUT-04 | Map + List View | Render public clinic coordinate projections and accessible list together; no external map-tracker dependency required. | `map_points`; accessible interactive approximate map pins. |
| F07-FUT-05 | Next Available Appointment | Filter by published next availability supplied by File 08. | `availability_days`; `next_available_at` owner adapter. |
| F07-FUT-06 | Local-Time Availability | Convert public clinic availability into the user-selected IANA timezone. | `DateTimeZone`; local availability DTO; browser locale rendering. |
| F07-FUT-07 | Serves My Country | Filter online doctors by File 08 public country-coverage contract. | `countries_served` + `serves_country`. |
| F07-FUT-08 | Saved Searches + Smart Alerts | Store bounded private saved filters and emit matching-doctor notification facts to File 19. | user-meta limits; resumable batched matcher; `DoctorSavedSearchMatched.v1`. |
| F07-FUT-09 | Private Doctor Shortlists | Private named collections of public doctor IDs; no clinical notes. | bounded shortlists and item limits; live eligibility check on save. |
| F07-FUT-10 | Why This Doctor | Explain filter matches such as language, location, mode, distance and published availability. | `why_this_doctor`; max six public-safe reasons. |
| F07-FUT-11 | User-Controlled Personal Order | User weights may reorder discovery presentation but must never be represented as official global rank. | `personal_order_notice`; separate personal score. |
| F07-FUT-12 | Ranking Transparency Center | Surface File 26 policy/monthly version and File 24 assurance when provided. | `/future/transparency`; prohibited-signal register. |
| F07-FUT-13 | Verification & Freshness Indicators | Public freshness timestamps only; no private evidence. | verification/profile/availability freshness DTO. |
| F07-FUT-14 | Advanced Professional Filters | Consume public books-studied, teaching, research/practice projections from File 03 or registered owners. | File03 discovery adapter; extended filter pipeline. |
| F07-FUT-15 | Educational & Knowledge Footprint | Consume public aggregate knowledge counts/links; quantity is not automatically a merit rank. | `ddd_public_knowledge_footprint_v1`. |
| F07-FUT-16 | Communication Accessibility Filters | Filter public declared accessibility/language-support capabilities. | `communication_accessibility` owner projection. |
| F07-FUT-17 | Clinic Accessibility Filters | Filter public declared clinic accessibility; File 08 remains source of truth. | `clinic_accessibility` owner projection. |
| F07-FUT-18 | Natural-Language Search | Parse bounded discovery intent into approved filters; no medical diagnosis/prescription. | `/future/interpret`; safe structured parser. |
| F07-FUT-19 | Multilingual Semantic/Phonetic Expansion | Expand Urdu/English/Arabic discovery intent and allow owner-approved synonym dictionaries. | multilingual dictionary + `ddd_file07_semantic_dictionary_v1`. |
| F07-FUT-20 | Zero-Result Recovery | Never fabricate doctors; propose safe relaxation actions. | remove city, allow online, widen radius, relax availability/fee/language. |
| F07-FUT-21 | Anti-Gaming & Manipulation Guard | File 07 exposes discovery-integrity contract; payment/donation/purchased engagement cannot be accepted as merit signals. | `ddd_file07_discovery_integrity_v1`; blocked-signal list. |
| F07-FUT-22 | Unmet Demand Intelligence | Aggregate zero-result structured facets only; never store free-text query or precise user location. | bounded `ddd_unmet_demand_v1`; admin health endpoint. |
| F07-FUT-23 | Red-Flag Safety Diversion | Emergency-type query phrases suppress directory recommendation and display urgent-care diversion. | `possible_emergency`; no diagnosis. |
| F07-FUT-24 | Offline / Low-Bandwidth Directory Pack | Provide bounded public-safe text-first directory snapshot with explicit stale semantics. | `/future/offline-pack`; 6-hour cache; stale label + stale-if-error. |

## Shared contracts

- `ddd_file08_public_discovery_v1`: public clinic coordinates, timezone, availability, countries served and clinic accessibility.
- `ddd_file03_public_professional_discovery_v1`: public books studied, teaching/research/practice and communication accessibility.
- `ddd_public_knowledge_footprint_v1`: public aggregate knowledge contribution projection.
- `sabri_file19_notification_event_v1`: notification event handoff only; File 19 owns delivery/preferences.
- `sabri_file26_ranking_policy_public_v1`: public ranking-policy transparency only; File 26 owns ranking.
- `sabri_file24_doctor_ranking_assurance_public_v1`: optional independent assurance projection.
- `ddd_file07_discovery_integrity_v1`: File 07 anti-manipulation advisory contract for ranking/discovery consumers.

## Privacy and safety invariants

Precise user coordinates are request-scoped and never written to user meta, options, demand analytics or logs by this feature set. Saved searches explicitly discard coordinates. Shortlists contain only public doctor IDs and labels, not symptoms, diagnoses, prescriptions or patient notes. Unmet-demand analytics contain only bounded structured facets. Emergency-type natural-language queries do not produce doctor recommendations.

## Release boundary

Repository implementation and automated QA do not equal staging or live acceptance. Real File 03/08/19/24/26 providers, WordPress/MySQL behavior, browser/device/accessibility, backup/restore, rollback and Founder acceptance remain staging gates.
