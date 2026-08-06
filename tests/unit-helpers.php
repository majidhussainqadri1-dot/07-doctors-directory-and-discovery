<?php
require __DIR__.'/bootstrap.php';
$tests=0;
function ok($condition,$message){ global $tests; $tests++; if(!$condition){ fwrite(STDERR,"FAIL: $message\n"); exit(1);} echo "PASS: $message\n"; }

ok(DDD_Helpers::valid_public_id('12345678-1234-4abc-8def-123456789abc'),'valid UUID v4 accepted');
ok(!DDD_Helpers::valid_public_id('123'),'invalid public ID rejected');
$id1=DDD_Helpers::uuid_from_user(42); $id2=DDD_Helpers::uuid_from_user(42);
ok($id1===$id2 && DDD_Helpers::valid_public_id($id1),'opaque public ID persists');
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
add_filter(DDD_Contracts::PROFILE_FILTER,function($v,$uid){ return array('user_id'=>$uid,'public'=>true,'discoverable'=>true,'display_name'=>'Doctor One','professional_title'=>'Homeopathic Doctor','specialty'=>'Classical Homeopathy','country'=>'Pakistan','city'=>'Gujrat','languages'=>array('Urdu','English'),'qualification'=>'DHMS','experience_years'=>10,'profile_url'=>'https://sabrihomeopathy.test/profile/doctor-one/','profile_version'=>'p1'); });
$elig=DDD_Contracts::eligibility(7);
ok($elig['eligible']===true && $elig['status']==='eligible','complete explicit owner claims become eligible');
$GLOBALS['ddd_test_options']['smc_founder_user_id']=7;
$founder_elig=DDD_Contracts::eligibility(7);
ok($founder_elig['eligible']===false && in_array('founder_separate',$founder_elig['reasons'],true),'Founder excluded from ordinary directory');

$GLOBALS['ddd_test_filters'][DDD_Contracts::PROFILE_FILTER]=array(function($v,$uid){ return array('user_id'=>$uid,'public'=>true,'discoverable'=>true,'display_name'=>'Doctor','specialty'=>'Homeopathy','country'=>'Pakistan','profile_url'=>'https://evil.example/a'); });
$p=DDD_Contracts::public_profile(8);
ok($p['profile_url']==='','foreign owner destination removed');

echo "TOTAL PASS: $tests\n";
