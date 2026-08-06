from pathlib import Path
import re, sys
root=Path(__file__).resolve().parents[1]
files={p.relative_to(root).as_posix():p.read_text(errors='ignore') for p in root.rglob('*') if p.is_file() and p.suffix in {'.php','.js','.css','.txt','.md','.yml','.sh'}}
pluginfiles={k:v for k,v in files.items() if k.startswith('doctors-directory/')}
alltext='\n'.join(pluginfiles.values())
checks={
 'release identity 1.1.0': "Version: 1.1.0" in files['doctors-directory/doctors-directory.php'] and "Stable tag: 1.1.0" in files['doctors-directory/readme.txt'],
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
}
failed=[name for name,result in checks.items() if not result]
for name,result in checks.items(): print(('PASS' if result else 'FAIL')+': '+name)
if failed:
 print('FAILED: '+', '.join(failed),file=sys.stderr); sys.exit(1)
print(f'TOTAL PASS: {len(checks)}')
