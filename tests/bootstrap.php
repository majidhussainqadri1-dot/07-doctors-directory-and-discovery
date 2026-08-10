<?php
// Minimal WordPress-compatible test bootstrap for pure File 07 business rules.
define( 'ABSPATH', __DIR__ . '/' );
define( 'DDD_TEXT_DOMAIN', 'doctors-directory-discovery' );
define( 'DDD_CONTRACT_VERSION', '1.1.0' );
define( 'DDD_MIN_FILE03_VERSION', '0.1.0' );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'DAY_IN_SECONDS', 86400 );

$GLOBALS['ddd_test_options'] = array();
$GLOBALS['ddd_test_meta'] = array();
$GLOBALS['ddd_test_filters'] = array();
$GLOBALS['ddd_test_users'] = array();

class WP_Error {
    private $code; private $message; private $data;
    public function __construct($code='', $message='', $data=array()) { $this->code=$code; $this->message=$message; $this->data=$data; }
    public function get_error_code(){ return $this->code; }
    public function get_error_message(){ return $this->message; }
    public function get_error_data(){ return $this->data; }
}
class WP_REST_Request {}
function is_wp_error($v){ return $v instanceof WP_Error; }
function __($s,$d=null){ return $s; }
function sanitize_key($v){ $v=strtolower((string)$v); return preg_replace('/[^a-z0-9_\-]/','',$v); }
function sanitize_text_field($v){ return trim(strip_tags((string)$v)); }
function sanitize_textarea_field($v){ return trim(strip_tags((string)$v)); }
function sanitize_title($v){ $v=strtolower(trim((string)$v)); $v=preg_replace('/[^a-z0-9]+/','-',$v); return trim($v,'-'); }
function esc_url_raw($v,$protocols=null){ return filter_var((string)$v,FILTER_VALIDATE_URL) ? (string)$v : ''; }
function absint($v){ return abs((int)$v); }
function wp_strip_all_tags($v){ return strip_tags((string)$v); }
function remove_accents($v){ return (string)$v; }
function wp_json_encode($v){ return json_encode($v, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); }
function wp_parse_args($args,$defaults=array()){ return array_merge($defaults,(array)$args); }
function wp_salt($scheme='auth'){ return 'test-salt-'.$scheme; }
function wp_hash($v){ return hash('sha256',(string)$v); }
function wp_rand($min=0,$max=0){ return $max ? $min : 123456; }
function wp_generate_uuid4(){ static $n=0; $n++; return sprintf('12345678-1234-4%03x-8%03x-%012x',$n,$n,$n); }
function home_url($path=''){ return 'https://sabrihomeopathy.test'.('/'===substr((string)$path,0,1)?$path:'/'.$path); }
function user_trailingslashit($v){ return rtrim((string)$v,'/').'/'; }
function wp_parse_url($v){ return parse_url((string)$v); }
function get_option($key,$default=false){ return array_key_exists($key,$GLOBALS['ddd_test_options'])?$GLOBALS['ddd_test_options'][$key]:$default; }
function update_option($key,$value,$autoload=null){ $GLOBALS['ddd_test_options'][$key]=$value; return true; }
function get_user_meta($uid,$key,$single=true){ return $GLOBALS['ddd_test_meta'][$uid][$key]??''; }
function add_user_meta($uid,$key,$value,$unique=false){ if($unique && isset($GLOBALS['ddd_test_meta'][$uid][$key])) return false; $GLOBALS['ddd_test_meta'][$uid][$key]=$value; return true; }
function update_user_meta($uid,$key,$value){ $GLOBALS['ddd_test_meta'][$uid][$key]=$value; return true; }
function get_userdata($uid){ return $GLOBALS['ddd_test_users'][$uid]??false; }
function has_filter($tag){ return !empty($GLOBALS['ddd_test_filters'][$tag]); }
function apply_filters($tag,$value,...$args){ if(empty($GLOBALS['ddd_test_filters'][$tag])) return $value; foreach($GLOBALS['ddd_test_filters'][$tag] as $cb){ $value=$cb($value,...$args); } return $value; }
function add_filter($tag,$cb){ $GLOBALS['ddd_test_filters'][$tag][]=$cb; }
function class_exists_stub($x){ return false; }

require dirname(__DIR__).'/doctors-directory/includes/class-sdd-helpers.php';
