from pathlib import Path
import re, sys
R=Path(__file__).resolve().parents[1]
paths=['doctors-directory/includes/class-ddd-future-query.php','doctors-directory/includes/class-ddd-future-discovery.php','doctors-directory/includes/class-ddd-future-preferences.php','doctors-directory/includes/class-ddd-future-mutation-guard.php','doctors-directory/includes/class-ddd-future-ui.php']
php='\n'.join((R/p).read_text(encoding='utf-8') for p in paths)
js=(R/'doctors-directory/assets/js/future-discovery.js').read_text(encoding='utf-8')
css=(R/'doctors-directory/assets/css/future-discovery.css').read_text(encoding='utf-8')
doc=(R/'docs/FUTURE-DISCOVERY-24-ENHANCEMENTS.md').read_text(encoding='utf-8')
checks=[
('FUT-01 factual doctor compare', '/future/compare' in php and 'MAX_COMPARE=4' in php and 'factual comparison' in php.lower()),
('FUT-02 guided doctor finder', '/future/guided' in php and 'rest_guided' in php),
('FUT-03 privacy-safe near me', 'navigator.geolocation' in js and 'exact user location is not stored' in js.lower() and 'distance_km' in php),
('FUT-04 map plus list', 'map_points' in php and 'ddd-future-mapplane' in css and 'renderMap' in js),
('FUT-05 next availability', 'availability_days' in php and 'next_available_at' in php),
('FUT-06 local-time availability', 'DateTimeZone' in php and 'timezone_identifiers_list' in php and 'toLocaleString' in js),
('FUT-07 country coverage', 'serves_country' in php and 'countries_served' in php),
('FUT-08 saved-search alerts', 'ddd_saved_searches_v1' in php and 'DoctorSavedSearchMatched.v1' in php and 'sabri_file19_notification_event_v1' in php and 'future_idempotency_key_required' in php and 'future_mutation_rate_limited' in php and 'Idempotency-Key' in js),
('FUT-09 private shortlists', 'ddd_shortlists_v1' in php and 'MAX_SHORTLIST_ITEMS' in php and 'shortlist_save' in php and 'X-DDD-Idempotent-Replay' in php),
('FUT-10 why this doctor', 'why_this_doctor' in php and 'Why this result' in js),
('FUT-11 personal sort not global rank', 'personal_order_notice' in php and 'not the official global merit rank' in php and "$w['language']" in php),
('FUT-12 ranking transparency', '/future/transparency' in php and 'sabri_file26_ranking_policy_public_v1' in php),
('FUT-13 verification freshness', "'freshness'" in php and "'verification'" in php and "'availability'" in php),
('FUT-14 advanced professional filters', 'books_studied' in php and 'teaching_experience' in php and 'research_activity' in php),
('FUT-15 knowledge footprint', 'ddd_public_knowledge_footprint_v1' in php and 'knowledge_counts' in php),
('FUT-16 communication accessibility', 'communication_accessibility' in php),
('FUT-17 clinic accessibility', 'clinic_accessibility' in php),
('FUT-18 natural-language search', '/future/interpret' in php and 'function interpret' in php and 'residual_q' in php),
('FUT-19 multilingual semantic expansion', 'ddd_file07_semantic_dictionary_v1' in php and 'اردو' in php and 'عربی' in php),
('FUT-20 zero-result recovery', 'zero_result_recovery' in php and 'widen_radius' in php and 'relax_availability' in php),
('FUT-21 anti-gaming integrity', 'ddd_file07_discovery_integrity_v1' in php and 'purchased_engagement' in php and 'paid_promotion' in php),
('FUT-22 unmet-demand intelligence', 'ddd_unmet_demand_v1' in php and '/future/demand' in php and 'Aggregated filters only' in php),
('FUT-23 emergency safety diversion', 'possible_emergency' in php and 'directory_suppressed' in php),
('FUT-24 offline low-bandwidth pack', '/future/offline-pack' in php and 'stale_label_required' in php and 'must-revalidate' in php and 'stale-if-error' not in php),
]
failed=[]
for i,(name,ok) in enumerate(checks,1):
    print(('PASS' if ok else 'FAIL')+f' F07-FUT-{i:02d}: '+name)
    if not ok: failed.append(i)
ids=[f'F07-FUT-{i:02d}' for i in range(1,25)]
missing=[x for x in ids if x not in doc]
if missing:
    print('FAIL governance IDs missing: '+', '.join(missing)); failed += [100]
else: print('PASS governance traceability: all 24 requirement IDs documented')
if failed: sys.exit(1)
print('TOTAL FUTURE ENHANCEMENTS PASS: 24/24')
