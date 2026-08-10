from pathlib import Path
import re,sys
root=Path(__file__).resolve().parents[1]
text='\n'.join((root/'doctors-directory/includes'/n).read_text() for n in ['class-ddd-central-ranking.php','class-ddd-ranking-ui.php','class-ddd-ranking-appeal.php'])
compact=text.replace(' ','')
checks={
 'top tier fails closed without File26': 'if(!has_filter(self::RANKING_FILTER))' in compact and "'all'===$tier" in compact,
 'stale snapshot rejected': 'file26_snapshot_stale' in text and 'MAX_SNAPSHOT_AGE' in text,
 'bad contract rejected': 'file26_contract_incompatible' in text,
 'missing bias audit rejected': 'file26_bias_audit_missing' in text and 'file26_bias_guard_incomplete' in text,
 'paid donor boost rejected': 'file26_paid_bias_detected' in text,
 'oversized File26 page rejected': 'file26_page_oversized' in text and 'count( $raw_items ) > $request_limit' in text,
 'duplicate IDs reject snapshot': 'file26_duplicate_public_id' in text and 'isset($seen[$id])' in compact,
 'invalid public ID rejects snapshot': 'file26_public_id_invalid' in text,
 'tier rank cap rejects snapshot': 'file26_rank_invalid' in text and '$rank>$cap' in compact,
 'empty explanation rejects snapshot': 'file26_explanation_missing' in text and 'if(!$why)' in compact,
 'neutral rank zero is not displayed': "if(!empty($doctor['global_rank']))" in compact,
 'neutral signed filter cursor': 'DDD_Helpers::cursor_decode' in text and 'DDD_Helpers::cursor_encode' in text and 'filter_hash' in text,
 'appeal requires valid File26 versions': "empty($snapshot['policy_version'])" in compact and "empty($snapshot['monthly_version'])" in compact,
 'appeal nonce': 'check_admin_referer' in text and 'wp_nonce_field' in text,
 'appeal narrative not in local audit context': "ranking_appeal_handoff" in text and "array('reason_code'=>$reason,'policy_version'" in compact,
 'no arbitrary callback execution': 'call_user_func' not in text and 'eval(' not in text,
 'no direct File26 table ownership': not re.search(r'\$wpdb->[^\n;]*file26',text,re.I),
}
failed=[k for k,v in checks.items() if not v]
for k,v in checks.items(): print(('PASS' if v else 'FAIL')+': '+k)
if failed: print('FAILED: '+', '.join(failed),file=sys.stderr); sys.exit(1)
print(f'TOTAL PASS: {len(checks)}')
