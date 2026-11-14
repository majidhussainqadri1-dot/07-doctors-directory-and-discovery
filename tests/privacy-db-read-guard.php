<?php
/**
 * Source-only privacy read-failure regressions against the real File07 class.
 * WP Core/real MySQL and legal-hold >50 tests remain independent staging gates.
 */
define( 'ABSPATH', __DIR__ . '/' );
define( 'DDD_TEXT_DOMAIN', 'doctors-directory-discovery' );
define( 'ARRAY_A', 'ARRAY_A' );
class WP_Error {
	private $code;
	private $message;
	private $data;
	public function __construct( $code, $message = '', $data = array() ) { $this->code=$code; $this->message=$message; $this->data=$data; }
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
	public function get_error_data() { return $this->data; }
}
function is_wp_error( $x ) { return $x instanceof WP_Error; }
function __( $s, $domain = null ) { return $s; }
function absint( $x ) { return abs( (int) $x ); }
function sanitize_key( $x ) { return strtolower( preg_replace( '/[^a-z0-9_-]/i', '', (string) $x ) ); }
function get_user_by( $field, $email ) { return (object) array( 'ID'=>77 ); }
function apply_filters( $hook, $value, ...$args ) { return false; }
function delete_user_meta( $id, $key ) { $GLOBALS['writes'][]='delete_meta'; return true; }
function current_time( $format, $gmt=false ) { return '2026-11-14 00:00:00'; }
class DDD_Helpers {
	public static function meta( $id, $key, $default='' ) { return $default; }
	public static function set_meta( $id, $key, $value ) { $GLOBALS['writes'][]='set_meta'; return true; }
}
class DDD_Repository {
	public static function get_status( $id ) { return array( 'eligible'=>false, 'reasons'=>array(), 'public_id'=>'', 'updated_at'=>'' ); }
	public static function table( $t ) { return 'wp_ddd_' . $t; }
	public static function delete_doctor_projection( $id, $reason ) { $GLOBALS['writes'][]='delete_projection'; return true; }
	public static function audit_admin( ...$args ) { $GLOBALS['writes'][]='audit'; }
}
class DDD_Test_DB {
	public $last_error='';
	public $mode='report_null';
	public $queries=0;
	public $reports=array();
	public $saves=array();
	public function prepare( $sql, ...$params ) { return (string) $sql; }
	public function get_results( $sql, $format ) {
		++$this->queries;
		$is_saved = false !== strpos( (string) $sql, 'LEFT JOIN' );
		if ( ! $is_saved && 'report_null' === $this->mode ) { $this->last_error='PRIVATE REPORT ERROR'; return null; }
		if ( ! $is_saved && 'report_error_empty' === $this->mode ) { $this->last_error='PRIVATE REPORT ERROR'; return array(); }
		if ( $is_saved && 'saved_null' === $this->mode ) { $this->last_error='PRIVATE SAVED ERROR'; return null; }
		if ( $is_saved && 'saved_error_empty' === $this->mode ) { $this->last_error='PRIVATE SAVED ERROR'; return array(); }
		return $is_saved ? $this->saves : $this->reports;
	}
	public function delete( ...$args ) { $GLOBALS['writes'][]='db_delete'; return 1; }
	public function update( ...$args ) { $GLOBALS['writes'][]='db_update'; return 1; }
}
$wpdb = new DDD_Test_DB();
$GLOBALS['writes'] = array();
require dirname( __DIR__ ) . '/doctors-directory/includes/class-sdd-privacy.php';
$privacy = new DDD_Privacy();
$passed=0;
function ok( $truth, $description ) {
	global $passed;
	if ( ! $truth ) { fwrite( STDERR, 'FAIL: ' . $description . PHP_EOL ); exit(1); }
	++$passed; echo 'PASS: ' . $description . PHP_EOL;
}
foreach ( array(
	'report_null'=>'ddd_privacy_export_reports_unavailable',
	'report_error_empty'=>'ddd_privacy_export_reports_unavailable',
	'saved_null'=>'ddd_privacy_export_saves_unavailable',
	'saved_error_empty'=>'ddd_privacy_export_saves_unavailable',
) as $mode=>$expected ) {
	$wpdb->mode=$mode; $wpdb->queries=0; $wpdb->last_error='stale'; $GLOBALS['writes']=array();
	$response=$privacy->export( 'private@example.test', 1 );
	ok( is_wp_error($response), 'Export returns structured error on ' . $mode );
	ok( $response->get_error_code()===$expected, 'Export names the failed source on '.$mode );
	ok( 503 === (int)($response->get_error_data()['status']??0), 'Export failed read is HTTP 503 on '.$mode );
	ok( false===strpos($response->get_error_message(),'PRIVATE'), 'Export error redacts database text on '.$mode );
	ok( !$GLOBALS['writes'], 'Export remains non-mutating on '.$mode );
}
$wpdb->mode='valid';$wpdb->last_error='STALE';$wpdb->reports=array();$wpdb->saves=array();
$response=$privacy->export('private@example.test',1);
ok( is_array($response)&&$response['done']===true, 'Legitimate empty export completes');
$wpdb->reports=array(array(
	'id'=>5,'reporter_id'=>77,'doctor_id'=>0,'doctor_public_id'=>'abc',
	'reason'=>'other','details'=>'My details','evidence_url'=>'',
	'status'=>'open','created_at'=>'2026-11-01 00:00:00','updated_at'=>'2026-11-02 00:00:00'
));
$response=$privacy->export('private@example.test',1);
ok( is_array($response)&&count($response['data'])===2, 'Legitimate one-report export includes profile and report');
$wpdb->reports=array();$wpdb->saves=array(array('public_id'=>'12345678','display_name'=>'Doctor','created_at'=>'2026-11-01 00:00:00'));
$response=$privacy->export('private@example.test',1);
ok( is_array($response)&&count($response['data'])===2, 'Legitimate saved doctor appears in export');
foreach(array('report_null','report_error_empty') as $mode) {
	$wpdb->mode=$mode; $wpdb->reports=array(); $GLOBALS['writes']=array();
	$response=$privacy->erase('private@example.test',1);
	ok( is_wp_error($response) && 'ddd_privacy_erasure_reports_unavailable'===$response->get_error_code(), 'Erasure errors truthfully on '.$mode);
	ok( !$GLOBALS['writes'], 'Erasure report-read failure causes ZERO mutation on '.$mode);
	ok( false===strpos($response->get_error_message(),'PRIVATE'), 'Erasure does not reveal SQL errors on '.$mode);
}
$wpdb->mode='valid';$wpdb->reports=array();$wpdb->saves=array();$GLOBALS['writes']=array();
$response=$privacy->erase('private@example.test',1);
ok( is_array($response)&&$response['done']===true&&$response['items_removed']===true, 'Legitimate empty report erasure completes with authorized local cleanup');
ok( in_array('delete_projection',$GLOBALS['writes'],true) && in_array('audit',$GLOBALS['writes'],true), 'Valid erasure invokes known projection and redacted audit');
echo 'TOTAL PASS: '.$passed.PHP_EOL;
