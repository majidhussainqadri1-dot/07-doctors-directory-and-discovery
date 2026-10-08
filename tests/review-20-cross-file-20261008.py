from pathlib import Path
import re, sys
R=Path(__file__).resolve().parents[1]
rd=lambda p:(R/p).read_text(encoding='utf-8',errors='ignore')
plug=rd('doctors-directory/doctors-directory.php')
xc=rd('doctors-directory/includes/class-ddd-cross-file-contracts.php')
h=rd('doctors-directory/includes/class-sdd-helpers.php')
d=rd('doctors-directory/includes/class-sdd-directory.php')
rank=rd('doctors-directory/includes/class-ddd-central-ranking.php')
appeal=rd('doctors-directory/includes/class-ddd-ranking-appeal.php')
fq=rd('doctors-directory/includes/class-ddd-future-query.php')
fp=rd('doctors-directory/includes/class-ddd-future-preferences.php')
privacy=rd('doctors-directory/includes/class-sdd-privacy.php')
css=rd('doctors-directory/assets/css/directory.css')+'\n'+rd('doctors-directory/assets/css/future-discovery.css')
act=rd('doctors-directory/includes/class-sdd-activator.php')
wf=rd('.github/workflows/final-quality-gates.yml')
snap=rd('docs/CROSS-FILE-DEPENDENCY-SNAPSHOT-2026-10-08.md')
readme=rd('README.md')
manifest=rd('MANIFEST.md')
allphp='\n'.join(p.read_text(encoding='utf-8',errors='ignore') for p in (R/'doctors-directory').rglob('*.php'))
C=[]
def add(n,v): C.append((n,bool(v)))

add('Exact File07 release/schema identity',
    all(x in plug for x in ["Version: 1.2.2","DDD_VERSION', '1.2.2","DDD_CONTRACT_VERSION', '1.2.2","DDD_DB_VERSION', '1.1.1","DDD_PROJECTION_SCHEMA', 3"]))
add('Canonical File07 scope has no verification/profile/appointment ownership',
    'public discovery projection' in readme.lower() and all(x in readme for x in ['File 00','File 03','File 08','File 09','File 26']))
add('File00 membership identity consumed canonically',
    'smc_membership_assertions' in xc and 'DDD_MIN_FILE00_CONTRACT_VERSION' in plug and not any(x in xc+h for x in ['_smc_membership_status','_smc_suspension_status']))
add('File03 public profile and public ID remain owner truth',
    'spd_get_personal_site_profile' in xc and 'canonical_public_id_missing' in h and 'uuid_from_user' not in allphp)
add('File08 clinic boundary is owner-only and bounded fee filters exclude unknowns',
    'sabri_file08_public_clinic_projection_v1' in xc and 'fee_maxISNOTNULLANDfee_max>=%f' in d.replace(' ','') and 'fee_minISNOTNULLANDfee_min<=%f' in d.replace(' ',''))
add('File09 verification projection is current and fail-closed',
    'GDO_Integration_Contracts::projection' in xc and 'authorization_rechecked' in xc and 'verification_contract_unavailable' in h)
add('File17 communications ownership is not duplicated',
    not re.search(r'CREATE TABLE[^;]*(message|conversation|voice|call)',allphp,re.I))
add('File19 notification producer and ingestion are canonical',
    all(x in xc for x in ['sun_register_notification_producer','sun_ingest_domain_event','schema_versions','allowed_data_fields']))
add('File20 shell compatibility projection and page map are present',
    'sabri_shell_verified_doctor_user_ids' in xc and "PAGE_MAP_OPTION = 'ddd_page_map'" in act and "update_option( 'sdd_page_map'" in act)
add('File21 Home/News ownership is not duplicated',
    not re.search(r'CREATE TABLE[^;]*(news_feed|home_feed|post_feed)',allphp,re.I))
add('File22 Composer ownership is not duplicated',
    not re.search(r'(create_post_composer|universal_post_composer|composer_publish)',allphp,re.I))
add('File23 publishing-dashboard ownership is not duplicated',
    not re.search(r'CREATE TABLE[^;]*(publishing_dashboard|dashboard_queue)',allphp,re.I))
add('File24 blocked fairness assurance withholds merit ranking and uses 31-day freshness',
    'spcrc/evaluate_ranking_fairness' in rank and 'file24_ranking_assurance_blocked' in rank and 'MAX_SNAPSHOT_AGE = 2678400' in rank)
add('File25 visual ownership is consumed through platform tokens',
    all(x in css for x in ['--sabri-primary','--sabri-text','--sabri-border','--sabri-focus']))
add('File26 current provider ranking constitution and appeal service are consumed',
    all(x in rank+appeal for x in ['sabri_file25_search_provider','ranking_constitution','doctor_appeals']) and 'file26_ranking_safe_fallback' in rank)
add('F07-FUT-01..24 and advanced fee truth are enforced',
    all(f'F07-FUT-{i:02d}' in rd('docs/FUTURE-DISCOVERY-24-ENHANCEMENTS.md') for i in range(1,25)) and "!isset($fee['max'])" in fq.replace(' ','') and "!isset($fee['min'])" in fq.replace(' ',''))
add('Security privacy abuse controls remain present',
    all(x in allphp for x in ['wp_verify_nonce','rate_limit','same_origin_url']) and 'wp_privacy_personal_data_exporters' in privacy and 'wp_privacy_personal_data_erasers' in privacy)
add('Accessibility RTL reduced-motion and 44px targets remain present',
    all(x in css for x in ['focus-visible','prefers-reduced-motion','html[dir="rtl"]','44px']))
add('Migration packaging and exact-source gates remain reproducible',
    all(x in wf for x in ['Exact source checkout integrity','Exact source checksum manifest','Deterministic package build A','Deterministic package build B','Clean-extract package verification','1.2.2.zip']) and "DDD_DB_VERSION', '1.1.1" in plug)
expected_heads=[
'2fa7c022ee9cd1b65432e900579512f304532442','636e3ef965423887f810718abec3cd1c11c3659d',
'70541974ce0ffb16aebef557c3016eb7447662f4','cfc5f781a766330314dc98c42abeca0eb7786eba',
'8ae656e51796d1f05865d8be5dca2480443d79ca','04078025b643ab7696e4cb4e37826bf152defa18',
'8a4dbcaf4fef8e926b9b834ecfde16c21a0f00ca','f2eb7e95ddea327af36ea725ffb923b029f885e6',
'b7a7f2e69411cbd32f0574fd12d766fb70c01b7a','dcae138e6073f4d0ff596623deb05b9940b8271b',
'a5b8d49968a7a5a7d6f3f4655bea541bf38a9acb','e35563b7f3d8ebf0acbbc80982b7bcf2e1b78c0a',
'bbea3aad466792a4a6a62b53532bbd45c7c592de']
add('Fresh companion HEAD evidence and status honesty are recorded',
    all(x in snap for x in expected_heads) and 'staging' in readme.lower() and 'live' in readme.lower() and 'REVIEW-CYCLE-20-CROSS-FILE-2026-10-08.md' in manifest)

bad=[(i+1,n) for i,(n,v) in enumerate(C) if not v]
for i,(n,v) in enumerate(C,1): print(f"{'PASS' if v else 'FAIL'} R{i:02d}: {n}")
print(f'TOTAL ROUNDS: {len(C)}')
print(f'PASS AFTER CORRECTION: {len(C)-len(bad)}')
print(f'FAIL AFTER CORRECTION: {len(bad)}')
if len(C)!=20:
    print(f'Exactly 20 rounds required; got {len(C)}.',file=sys.stderr); sys.exit(2)
if bad:
    print('FAILED ROUNDS: '+', '.join(f'R{i:02d} {n}' for i,n in bad),file=sys.stderr); sys.exit(1)
