<?php
require __DIR__.'/bootstrap.php';
$tests=0;
function ok($condition,$message){ global $tests; $tests++; if(!$condition){ fwrite(STDERR,"FAIL: $message\n"); exit(1);} echo "PASS: $message\n"; }

ok(DDD_Helpers::valid_public_id('12345678-1234-4abc-8def-123456789abc'),'valid UUID v4 accepted');
ok(!DDD_Helpers::valid_public_id('123'),'invalid public ID rejected');
ok(!method_exists('DDD_Helpers','uuid_from_user'),'File 07 does not mint canonical public doctor IDs');
ok(DDD_Helpers::decimal_or_null('10.25')===10.25,'valid decimal accepted');
ok(DDD_Helpers::decimal_or_null('-1')===null,'negative fee rejected');
ok(DDD_Helpers::decimal_or_null('1.234')===null,'excess decimal precision rejected');
ok(DDD_Helpers::consultation_modes(array('In Person','video call','telephone','unknown'))===array('in-person','video','phone'),'consultation modes normalized and allowlisted');
ok(DDD_Helpers::same_origin_url('/doctors/abc/')==='https://sabrihomeopathy.test/doctors/abc/','relative route canonicalized');
ok(DDD_Helpers::same_origin_url('https://sabrihomeopathy.test/profile/a')==='https://sabrihomeopathy.test/profile/a','same-origin route accepted');
ok(DDD_Helpers::same_origin_url('https://evil.example/profile/a')==='','foreign route rejected');

$args=array('q'=>'Heart Doctor','country'=>'Pakistan','city'=>'Lahore','specialty'=>'Cardiology','language'=>'Urdu','qualification'=>'BHMS','min_experience'=>5,'mode'=>'online','accepting'=>1,'currency'=>'PKR','fee_min'=>'100','fee_max'=>'500','featured_only'=>0,'recent_only'=>30);
$hash=DDD_Helpers::filter_hash($args);
$cursor=DDD_Helpers::cursor_encode(array('fh'=>$hash,'r'=>100,'f'=>1,'q'=>88.2,'v'=>'2026-08-06 00:00:00','p'=>'12345678-1234-4abc-8def-123456789abc'));
$decoded=DDD_Helpers::cursor_decode($cursor,$hash);
ok(!empty($decoded) && $decoded['p']==='12345678-1234-4abc-8def-123456789abc','signed cursor decodes for exact filters');
ok(DDD_Helpers::cursor_decode($cursor,DDD_Helpers::filter_hash(array_merge($args,array('city'=>'Karachi'))))===array(),'cursor cannot cross filter sets');
$tampered=substr($cursor,0,-1).(substr($cursor,-1)==='a'?'b':'a');
ok(DDD_Helpers::cursor_decode($tampered,$hash)===array(),'tampered cursor rejected');

$health=DDD_Contracts::dependency_health();
ok($health['ready']===false && $health['code']==='mandatory_contract_missing','mandatory owner contracts fail closed');

add_filter(DDD_Contracts::IDENTITY_FILTER,function($v,$uid){ return array('user_id'=>$uid,'account_active'=>true,'suspended'=>false,'risk_blocked'=>false,'age_eligible'=>true,'guardian_valid'=>true,'institutional'=>false,'claim_version'=>'i1'); });
add_filter(DDD_Contracts::VERIFICATION_FILTER,function($v,$uid){ return array('user_id'=>$uid,'doctor'=>true,'verified'=>true,'status'=>'verified','effective_at'=>'2026-08-01 00:00:00','decision_version'=>'v1'); });
add_filter(DDD_Contracts::PROFILE_FILTER,function($v,$uid){ return array('user_id'=>$uid,'public_id'=>'12345678-1234-4abc-8def-123456789abc','public'=>true,'discoverable'=>true,'display_name'=>'Doctor One','professional_title'=>'Homeopathic Doctor','specialty'=>'Classical Homeopathy','country'=>'Pakistan','city'=>'Gujrat','languages'=>array('Urdu','English'),'qualification'=>'DHMS','experience_years'=>10,'profile_url'=>'https://sabrihomeopathy.test/profile/doctor-one/','profile_version'=>'p1'); });
$elig=DDD_Contracts::eligibility(7);
ok($elig['eligible']===true && $elig['status']==='eligible','complete explicit owner claims become eligible');
$GLOBALS['ddd_test_options']['smc_founder_user_id']=7;
$founder_elig=DDD_Contracts::eligibility(7);
ok($founder_elig['eligible']===false && in_array('founder_separate',$founder_elig['reasons'],true),'Founder excluded from ordinary directory');

$GLOBALS['ddd_test_filters'][DDD_Contracts::PROFILE_FILTER]=array(function($v,$uid){ return array('user_id'=>$uid,'public_id'=>'12345678-1234-4abc-8def-123456789abc','public'=>true,'discoverable'=>true,'display_name'=>'Doctor','specialty'=>'Homeopathy','country'=>'Pakistan','profile_url'=>'https://evil.example/a'); });
$p=DDD_Contracts::public_profile(8);
ok($p['profile_url']==='','foreign owner destination removed');


if (!function_exists('mb_strtolower')) { function mb_strtolower($s) { return strtolower((string)$s); } }
if (!function_exists('mb_strpos')) { function mb_strpos($h,$n) { return strpos((string)$h,(string)$n); } }
require_once dirname(__DIR__).'/doctors-directory/includes/class-ddd-future-query.php';
$semantic_cases=array(
    array('online doctor in Finland','finland','online'),
    array('online doctor in Singapore','singapore','online'),
    array('online doctor in Indonesia','indonesia','online'),
    array('online doctor in India','india','online'),
    array('doctor in Finland','finland',''),
    array('in-person doctor in Finland','finland','in-person'),
    array('teleclinic doctor in Finland','teleclinic finland',''),
    array('urduvian doctor in Finland','urduvian finland',''),
    array('اردو ڈاکٹر لاہور','لاہور',''),
);
foreach($semantic_cases as $case){
    $parsed=DDD_Future_Query::interpret($case[0]);
    ok($parsed['residual_q']===$case[1], 'semantic residual preserves whole terms: '.$case[0]);
    ok(($parsed['mode']??'')===$case[2], 'semantic mode only matches whole terms: '.$case[0]);
}
$parsed=DDD_Future_Query::interpret('doctor in Finland');
$merged=DDD_Future_Query::merge(DDD_Future_Query::sanitize(array('q'=>'doctor in Finland')),$parsed);
ok($merged['q']==='finland','semantic residual applied without dictionary expansion');
$parsed=DDD_Future_Query::interpret('urduvian doctor in Finland');
ok(!isset($parsed['language']),'semantic language does not match within a longer word');
$parsed=DDD_Future_Query::interpret('اردو ڈاکٹر لاہور');
ok(($parsed['language']??'')==='Urdu','whole-word Urdu language intent recognized');


$availability_params=DDD_Future_Query::sanitize(array('availability_days'=>7,'timezone'=>'Asia/Karachi'));
$availability_doctor=array('public_id'=>'12345678-1234-4abc-8def-123456789abc','verified_at'=>gmdate('c'),'languages'=>array('Urdu'),'consultation_modes'=>array('online'));
$GLOBALS['ddd_test_filters']['ddd_file08_public_discovery_v1']=array(function(){return array('next_available_at'=>gmdate('c',time()-DAY_IN_SECONDS),'clinic_timezone'=>'UTC');});
$expired=DDD_Future_Query::enrich($availability_doctor,$availability_params);
ok(!DDD_Future_Query::matches($expired,$availability_params),'expired next appointment cannot satisfy upcoming availability filter');
ok(empty($expired['local_availability']),'expired next appointment cannot be advertised as upcoming');
$GLOBALS['ddd_test_filters']['ddd_file08_public_discovery_v1']=array(function(){return array('next_available_at'=>gmdate('c',time()+DAY_IN_SECONDS),'clinic_timezone'=>'UTC');});
$upcoming=DDD_Future_Query::enrich($availability_doctor,$availability_params);
ok(DDD_Future_Query::matches($upcoming,$availability_params),'genuine future appointment inside window is included');
ok(!empty($upcoming['local_availability']['next_local']),'genuine future appointment has local-time projection');
$GLOBALS['ddd_test_filters']['ddd_file08_public_discovery_v1']=array(function(){return array('next_available_at'=>gmdate('c',time()+10*DAY_IN_SECONDS),'clinic_timezone'=>'UTC');});
$distant=DDD_Future_Query::enrich($availability_doctor,$availability_params);
ok(!DDD_Future_Query::matches($distant,$availability_params),'appointment outside requested window is excluded');
$GLOBALS['ddd_test_filters']['ddd_file08_public_discovery_v1']=array(function(){return array('next_available_at'=>'not-a-date','clinic_timezone'=>'UTC');});
$invalid=DDD_Future_Query::enrich($availability_doctor,$availability_params);
ok(!DDD_Future_Query::matches($invalid,$availability_params),'invalid availability timestamp fails closed');
unset($GLOBALS['ddd_test_filters']['ddd_file08_public_discovery_v1']);

echo "TOTAL PASS: $tests\n";
