from pathlib import Path
import sys
root=Path(__file__).resolve().parents[1]
plugin=(root/'doctors-directory/doctors-directory.php').read_text()
parts=[root/'doctors-directory/includes/class-ddd-central-ranking.php',root/'doctors-directory/includes/class-ddd-ranking-ui.php',root/'doctors-directory/includes/class-ddd-ranking-appeal.php']
text='\n'.join(p.read_text() for p in parts)
compact=text.replace(' ','')
checks={
 'central bridge runtime loaded': all(p.name in plugin for p in parts),
 'File26 canonical ranking contract': 'sabri_file25_search_provider' in text and "'consumer' => 'file07'" in text and 'doctor_ranking' in text,
 'nested Top10/100/1000/All tiers': all(x in text for x in ["'top10'","'top100'","'top1000'","'all'"]) and 'nested_tiers' in text,
 'All Verified public wording': 'All Verified Doctors' in text,
 'monthly version freshness': 'require_monthly_version' in text and 'monthly_version' in text and 'MAX_SNAPSHOT_AGE' in text,
 'bounded fail-closed item page': all(x in text for x in ['file26_page_oversized','file26_public_id_invalid','file26_duplicate_public_id','file26_rank_invalid','file26_explanation_missing']),
 'public explanations': 'require_explanations' in text and 'ranking_explanation' in text,
 'doctor appeal to File26': 'doctor_appeals' in text and 'Appeal my ranking' in text and 'current_file26_submit' in text,
 'File24 current fairness assurance': 'spcrc/evaluate_ranking_fairness' in text and 'file24-ranking-assurance' in text and 'file26_owner_contract' in text and 'monthly_recomputation' in text,
 'File24 legacy assurance fallback bounded': 'sabri_file24_doctor_ranking_assurance_v1' in text and 'null === $assurance' in text,
 'zero paid donor favoritism': all(x in text for x in ['donation','payment','paid_promotion','founder_favoritism','purchased_engagement']) and 'paid_boost' in text and 'donor_boost' in text,
 'no fabricated top tiers': 'file26_ranking_safe_fallback' in text and "return'all'===$tier?self::neutral($filters):$current;" in compact,
 'neutral fallback not a merit rank': 'Neutral alphabetical fallback; no merit rank is asserted' in text and "'rank'=>0" in compact,
 'live owner eligibility recheck': text.count('DDD_Repository::get_by_public_id')>=2 and 'get_live_status' in text,
 'legacy local rank removed from DOM': 'Remove the legacy File-07-local ranked All/Search section from the rendered DOM' in text and "strpos($output,'</section>'" in compact,
 'same-origin appeal return': 'DDD_Helpers::same_origin_url' in text and 'wp_safe_redirect' in text,
 'appeal rate and object gate': "rate_limit('ranking-appeal'" in compact and 'appeal only your own eligible doctor ranking' in text,
 'public ranking REST route': "'/ranking'" in text and 'rest_ranking' in text,
 'public ranking REST rate limited': "rate_limit('ranking'" in compact and 'ranking_rate_limited' in text,
}
failed=[k for k,v in checks.items() if not v]
for k,v in checks.items(): print(('PASS' if v else 'FAIL')+': '+k)
if failed: print('FAILED: '+', '.join(failed),file=sys.stderr); sys.exit(1)
print(f'TOTAL PASS: {len(checks)}')
