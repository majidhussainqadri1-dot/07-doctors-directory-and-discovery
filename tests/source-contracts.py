from pathlib import Path
import re, sys
root=Path(__file__).resolve().parents[1]
files={p.relative_to(root).as_posix():p.read_text(errors='ignore') for p in root.rglob('*') if p.is_file() and p.suffix in {'.php','.js','.css','.txt','.md','.yml','.sh'}}
pluginfiles={k:v for k,v in files.items() if k.startswith('doctors-directory/')}
alltext='\n'.join(pluginfiles.values())
future='\n'.join(files.get(x,'') for x in ['doctors-directory/includes/class-ddd-future-query.php','doctors-directory/includes/class-ddd-future-discovery.php','doctors-directory/includes/class-ddd-future-preferences.php','doctors-directory/includes/class-ddd-future-ui.php'])
futurejs=files.get('doctors-directory/assets/js/future-discovery.js','')
futurecss=files.get('doctors-directory/assets/css/future-discovery.css','')
checks={
 'release identity 1.2.0': "Version: 1.2.0" in files['doctors-directory/doctors-directory.php'] and "Stable tag: 1.2.0" in files['doctors-directory/readme.txt'],
 'canonical DDD namespace': "define( 'DDD_VERSION'" in alltext and 'final class DDD_Repository' in alltext,
 'mandatory contracts fail closed': "mandatory_contract_missing" in alltext and "identity_contract_unavailable" in alltext,
 'Founder separation': "founder_separate" in alltext and "never mixed" in alltext,
 'all required filters': all(x in alltext for x in ['country','city','specialty','language','qualification','min_experience','mode','accepting','currency','fee_min','fee_max']),
 'filter-bound signed cursor': "filter_hash" in alltext and "cursor_decode" in alltext and "hash_hmac" in alltext and "relevance_score" in alltext,
 'opaque public UUID': "wp_generate_uuid4" in alltext and "_ddd_public_id" in alltext,
 'same-origin destinations': alltext.count('same_origin_url') >= 5 and "hash_equals( $home_host, $target_host )" in alltext,
 'no paid boost': 'no paid boost' in alltext.lower(),
 'taxonomy governance': "taxonomy_upsert" in alltext and "DoctorDirectoryTaxonomyChanged.v1" in alltext,
 'event reliability': all(x in alltext for x in ['ddd_outbox','ddd_inbox','dead','locked_by','event_replay_mismatch']),
 'bounded persistent rate limits': 'ddd_rate_limits' in alltext and 'request_count<%d' in alltext and 'expires_at<%s' in alltext,
 'stale worker recovery': "status='processing' AND locked_at<" in alltext,
 'report actor-scoped idempotency': "'reporter:' . $reporter_id" in alltext,
 'click-time owner recheck': alltext.count('DDD_Contracts::eligibility') >= 4 and 'get_live_status' in alltext,
 'moderation state law': 'report_transition_forbidden' in alltext and "'resolved' => array( 'open' )" in alltext,
 'atomic feature expiry': "automatic_expiry" in alltext and "self::set_feature( absint( $row['doctor_id'] ), 0, false" in alltext,
 'privacy legal hold': 'retention_hold' in alltext and 'export' in alltext.lower() and 'eras' in alltext.lower(),
 'safe mode/system check/repair': all(x in alltext for x in ['DDD_SAFE_MODE_OPTION','system_check','repair']),
 'SEO and sitemap': 'wp_sitemaps' in alltext and 'canonical' in alltext,
 'responsive RTL accessibility': all(x in alltext for x in ['prefers-reduced-motion','html[dir="rtl"]','focus-visible','min-height:44px']),
 'non-destructive uninstall': 'DROP TABLE' not in files['doctors-directory/uninstall.php'] and 'wp_delete_post' not in files['doctors-directory/uninstall.php'],
 'InnoDB schema': "$engine = ' ENGINE=InnoDB '" in alltext and alltext.count('{$engine}{$charset}') >= 8,
 'resumable migration': 'LEGACY_CURSOR_OPTION' in alltext and 'ddd_legacy_reports_cursor' in alltext and 'continue_legacy_migration' in alltext,
 'canonical search redirect': "ddd_directory_search" in alltext and "add_query_arg" in files['doctors-directory/includes/class-sdd-directory.php'],
 'no SQL REPLACE primitive': not re.search(r'\$wpdb->replace|\bREPLACE\s+INTO\b', alltext, re.I),
 'no public internal avatar ID': "'avatar_id' =>" not in re.search(r'public static function public_dto.*?\n\t}',files['doctors-directory/includes/class-sdd-directory.php'],re.S).group(0),
 'future discovery runtime loaded': 'DDD_Future_Discovery::register()' in alltext and 'class-ddd-future-discovery.php' in files['doctors-directory/doctors-directory.php'],
 'future canonical owner boundaries': all(x in future for x in ['ddd_file08_public_discovery_v1','ddd_file03_public_professional_discovery_v1','ddd_public_knowledge_footprint_v1','sabri_file19_notification_event_v1','sabri_file26_ranking_policy_public_v1']),
 'future privacy safe location': "unset($f['lat'],$f['lng']" in future and 'precise user location' in future.lower(),
 'future emergency diversion': 'possible_emergency' in future and 'directory_suppressed' in future,
 'future personal order not merit rank': 'personal_order_notice' in future and 'not the official global merit rank' in future,
 'future saved searches private': 'META_SEARCHES' in future and 'get_user_meta' in future and 'update_user_meta' in future,
 'future shortlists private': 'META_SHORTLISTS' in future and 'MAX_SHORTLIST_ITEMS' in future,
 'future ranking integrity': all(x in future for x in ['donation','payment','paid_promotion','founder_favoritism','purchased_engagement','engagement_requires_manipulation_screen']),
 'future UI safe DOM': 'innerHTML = data' not in futurejs and 'textContent' in futurejs,
 'future accessible responsive UI': all(x in futurecss for x in ['focus-visible','min-height:44px','prefers-reduced-motion','html[dir="rtl"]']),
}
failed=[name for name,result in checks.items() if not result]
for name,result in checks.items(): print(('PASS' if result else 'FAIL')+': '+name)
if failed:
 print('FAILED: '+', '.join(failed),file=sys.stderr); sys.exit(1)
print(f'TOTAL PASS: {len(checks)}')
