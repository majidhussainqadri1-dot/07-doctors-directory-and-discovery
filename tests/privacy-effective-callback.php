<?php
/**
 * File07 V12.3: actual WordPress privacy eraser callback dispatch (source-only).
 * This deliberately tests DDD_Privacy_Hardening, NOT the overridden DDD_Privacy::erase.
 * Mock WPDB: not evidence of MySQL or deployed code.
 */
define( 'ABSPATH', __DIR__ . '/' );
define( 'DDD_TEXT_DOMAIN', 'doctors-directory-discovery' );
define( 'ARRAY_A', 'ARRAY_A' );
class WP_Error {
    private $c; private $m; private $d;
    public function __construct($c,$m='',$d=array()){$this->c=$c;$this->m=$m;$this->d=$d;}
    public function get_error_code(){return $this->c;}
    public function get_error_message(){return $this->m;}
    public function get_error_data(){return $this->d;}
}
function is_wp_error($v){return $v instanceof WP_Error;}
function __($s,$domain=''){return $s;}
function absint($x){return abs((int)$x);}
function get_user_by($kind,$email){return (object)array('ID'=>77);}
function apply_filters($tag,$initial,...$args){return 'ddd_privacy_legal_hold'===$tag ? !empty($GLOBALS['global_hold']) : $initial;}
function delete_user_meta($user,$key){$GLOBALS['writes'][]='meta_del';return true;}
function current_time($format,$gmt=true){return '2026-11-15 00:00:00';}
class DDD_Helpers{
 public static function set_meta($user,$key,$value){$GLOBALS['writes'][]='meta_set';return true;}
}
class DDD_Repository{
 public static function table($name){return 'wp_ddd_'.$name;}
 public static function delete_doctor_projection($user,$why){$GLOBALS['writes'][]='projection_del';return true;}
 public static function audit_admin(...$args){$GLOBALS['writes'][]='audit_success';return true;}
}
class DDD_Test_Hardening_DB{
 public $mode='ok';public $last_error='';public $reports=array();public $queries=0;
 public function prepare($sql,...$args){return (string)$sql;}
 public function get_var($sql){
  ++$this->queries;
  if(str_contains($sql,'retention_hold=1')){
   if('held_fail'===$this->mode){$this->last_error='PRIVATE HELD READ ERROR';return null;}
   return (string)count(array_filter($this->reports, fn($r)=>!empty($r['retention_hold'])&&($r['reporter_id']==77||$r['doctor_id']==77)));
  }
  if(str_contains($sql,'retention_hold=0')){
   if('remaining_fail'===$this->mode){$this->last_error='PRIVATE COUNT ERROR';return null;}
   return (string)count(array_filter($this->reports, fn($r)=>empty($r['retention_hold'])&&($r['reporter_id']==77||$r['doctor_id']==77)));
  }
  return null;
 }
 public function get_results($sql,$output){
  ++$this->queries;
  if('rows_null'===$this->mode){$this->last_error='';return null;}
  if('rows_empty_error'===$this->mode){$this->last_error='PRIVATE ROWS ERROR';return array();}
  return array_slice(array_values(array_filter($this->reports,fn($r)=>empty($r['retention_hold'])&&($r['reporter_id']==77||$r['doctor_id']==77))),0,50);
 }
 public function update($table,$changes,$where,...$args){
  $GLOBALS['writes'][]='report_update';
  if('update_fail'===$this->mode){$this->last_error='PRIVATE WRITE ERROR';return false;}
  foreach($this->reports as &$row){if($row['id']===$where['id']){$row=array_merge($row,$changes);break;}}
  unset($row);
  return 1;
 }
 public function delete(...$args){$GLOBALS['writes'][]='saved_refs_delete';return 1;}
}
$wpdb=new DDD_Test_Hardening_DB();
require dirname(__DIR__).'/doctors-directory/includes/class-sdd-privacy.php';
require dirname(__DIR__).'/doctors-directory/includes/class-ddd-review-hardening.php';
$entry=DDD_Review_Hardening::replace_privacy_eraser(array());
$callback=$entry['doctors-directory-discovery']['callback']??null;
$passed=0;
function check_act($condition,$label){global $passed;if(!$condition){fwrite(STDERR,'FAIL: '.$label.PHP_EOL);exit(1);}++$passed;echo 'PASS: '.$label.PHP_EOL;}
function start_case($mode,$rows=array(),$hold=false){global $wpdb;$wpdb->mode=$mode;$wpdb->reports=$rows;$wpdb->last_error='STALE';$wpdb->queries=0;$GLOBALS['writes']=array();$GLOBALS['global_hold']=$hold;}
function one_report($id,$hold=false){return array('id'=>$id,'reporter_id'=>77,'doctor_id'=>0,'retention_hold'=>$hold?1:0,'version'=>1);}
check_act(is_array($callback) && $callback===array('DDD_Privacy_Hardening','erase'),'Effective priority-99 callback is the hardening class');
foreach(array('held_fail'=>'ddd_privacy_hardening_hold_unavailable','rows_null'=>'ddd_privacy_hardening_rows_unavailable','rows_empty_error'=>'ddd_privacy_hardening_rows_unavailable') as $mode=>$code){
 start_case($mode);
 $out=call_user_func($callback,'subject@example.invalid',1);
 check_act(is_wp_error($out),'Actual eraser returns WordPress error on '.$mode);
 check_act($out->get_error_code()===$code,'Actual eraser identifies failed read on '.$mode);
 check_act((int)($out->get_error_data()['status']??0)===503,'Actual eraser signals 503 on '.$mode);
 check_act(!in_array('meta_set',$GLOBALS['writes'],true),'Actual eraser makes no preflight mutation on '.$mode);
 check_act(!in_array('audit_success',$GLOBALS['writes'],true),'No false success audit on '.$mode);
}
foreach(array('update_fail'=>'ddd_privacy_hardening_update_unavailable','remaining_fail'=>'ddd_privacy_hardening_remaining_unavailable') as $mode=>$code){
 start_case($mode,array(one_report(1,false)));
 $out=call_user_func($callback,'subject@example.invalid',1);
 check_act(is_wp_error($out) && $out->get_error_code()===$code,'Actual eraser detects partial-state operation failure on '.$mode);
 check_act(!in_array('audit_success',$GLOBALS['writes'],true),'No false success audit after failed update/count on '.$mode);
}
start_case('ok');
$out=call_user_func($callback,'subject@example.invalid',1);
check_act(is_array($out)&&$out['done']===true&&!$out['items_retained'],'Legitimate zero-row erasure completes');
check_act(in_array('audit_success',$GLOBALS['writes'],true),'Successful erasure audited');
start_case('ok',array_map(fn($n)=>one_report($n,false),range(1,51)));
$first=call_user_func($callback,'subject@example.invalid',1);
check_act(is_array($first)&&$first['done']===false,'51 nonheld rows require second page');
$second=call_user_func($callback,'subject@example.invalid',2);
check_act(is_array($second)&&$second['done']===true,'Second page finishes all 51 nonheld rows');
check_act(count(array_filter($wpdb->reports,fn($r)=>$r['reporter_id']===77))===0,'All 51 report identities anonymized');
start_case('ok',array_map(fn($n)=>one_report($n,true),range(1,51)));
$held=call_user_func($callback,'subject@example.invalid',1);
check_act(is_array($held)&&$held['done']===true&&$held['items_retained']===true,'51 held reports retained without starvation');
check_act(count(array_filter($wpdb->reports,fn($r)=>$r['reporter_id']===77))===51,'Legal-hold record identities retained');
start_case('ok',array(one_report(1,false)),true);
$heldGlobal=call_user_func($callback,'subject@example.invalid',1);
check_act(is_array($heldGlobal)&&$heldGlobal['done']===true&&$heldGlobal['items_retained']===true,'Global hold is retained without processing unheld reports');
echo 'TOTAL PASS: '.$passed.PHP_EOL;
