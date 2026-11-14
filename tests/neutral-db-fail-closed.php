<?php
/**
 * Source-level PHP 8 regression for File07 neutral All Verified fallback.
 * Actual production failure/recovery still requires exact deployed artifact,
 * MySQL staging, backup/restore, rollback and live re-test evidence.
 */
define( 'ABSPATH', __DIR__ . '/' );
define( 'DDD_TEXT_DOMAIN', 'doctors-directory-discovery' );
define( 'ARRAY_A', 'ARRAY_A' );
class WP_Error {
	private $code;
	private $message;
	private $data;
	public function __construct( $code, $message = '', $data = array() ) {
		$this->code = $code; $this->message = $message; $this->data = $data;
	}
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
	public function get_error_data() { return $this->data; }
}
function __( $text, $domain = '' ) { return $text; }
function add_action( $hook, $callback, $priority = 10 ) { return true; }
function absint( $x ) { return abs( (int) $x ); }
function sanitize_text_field( $x ) { return trim( strip_tags( (string) $x ) ); }
function sanitize_key( $x ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $x ) ); }
class DDD_Helpers {
	public static function safe_error( $code, $message, $status ) { return new WP_Error( $code, $message, array( 'status' => $status ) ); }
	public static function normalize_token( $s ) { return strtolower( trim( (string) $s ) ); }
	public static function filter_hash( $s ) { return hash( 'sha256', json_encode( $s ) ); }
	public static function cursor_decode( $s, $h ) { return array(); }
	public static function cursor_encode( $data ) { return 'unused'; }
}
class DDD_Repository {
	public static function table( $name ) { return 'wp_ddd_projection'; }
	public static function taxonomy_normalize( $type, $s ) { return strtolower( trim( (string) $s ) ); }
	public static function get_by_public_id( $id ) { return array( 'public_id' => (string) $id ); }
}
class DDD_Fake_DB {
	public $last_error = '';
	public $mode = 'null_error';
	public $rows = array();
	public $queries = 0;
	public function esc_like( $s ) { return (string) $s; }
	public function prepare( $sql, $arguments ) { return (string) $sql; }
	public function get_results( $query, $output ) {
		++$this->queries;
		switch ( $this->mode ) {
			case 'null_error': $this->last_error = 'PRIVATE SQL DIAGNOSTIC'; return null;
			case 'empty_error': $this->last_error = 'PRIVATE STORAGE FAILURE'; return array();
			case 'null_no_error': $this->last_error = ''; return null;
			case 'false_error': $this->last_error = 'PRIVATE FAILURE'; return false;
			default: return $this->rows;
		}
	}
}
$wpdb = new DDD_Fake_DB();
require dirname( __DIR__ ) . '/doctors-directory/includes/class-ddd-central-ranking.php';
$filters = array(
	'q' => '', 'country' => '', 'city' => '', 'specialty' => '', 'language' => '',
	'qualification' => '', 'min_experience' => 0, 'mode' => '', 'accepting' => false,
	'currency' => '', 'fee_min' => null, 'fee_max' => null,
);
$invoke = new ReflectionMethod( 'DDD_Central_Ranking', 'neutral' );
$invoke->setAccessible( true );
$passed = 0;
function check_neutral( $condition, $label ) {
	global $passed;
	if ( ! $condition ) { fwrite( STDERR, 'FAIL: ' . $label . PHP_EOL ); exit( 1 ); }
	++$passed; echo 'PASS: ' . $label . PHP_EOL;
}
foreach ( array( 'null_error', 'empty_error', 'null_no_error', 'false_error' ) as $failure ) {
	$wpdb->mode = $failure;
	$wpdb->last_error = 'OLD ERROR';
	$response = $invoke->invoke( null, $filters );
	check_neutral( $response instanceof WP_Error, 'Safe error on invalid DB query response: ' . $failure );
	check_neutral( 'neutral_ranking_store_unavailable' === $response->get_error_code(), 'Fail-closed structured error: ' . $failure );
	check_neutral( 503 === (int) ( $response->get_error_data()['status'] ?? 0 ), 'HTTP 503 status: ' . $failure );
	check_neutral( false === strpos( $response->get_error_message(), 'PRIVATE' ), 'No raw DB error text leakage: ' . $failure );
}
$wpdb->mode = 'valid';
$wpdb->last_error = 'STALE PREVIOUS ERROR';
$wpdb->rows = array();
$response = $invoke->invoke( null, $filters );
check_neutral( is_array( $response ) && 'neutral' === $response['source'] && empty( $response['items'] ), 'Legitimate empty result remains successful' );
$wpdb->rows = array( array(
	'public_id' => '12345678-1234-4abc-8def-123456789abc',
	'display_name_norm' => 'doctor one',
) );
$response = $invoke->invoke( null, $filters );
check_neutral( is_array( $response ) && count( $response['items'] ) === 1 && 0 === $response['items'][0]['rank'], 'Single public doctor has neutral rank zero' );
check_neutral( false !== strpos( implode( ' ', $response['items'][0]['explanation'] ), 'no merit rank' ), 'No accidental merit-rank claim in neutral fallback' );
check_neutral( 6 === $wpdb->queries, 'Exactly one bounded read per path; no retries or writes' );
echo 'TOTAL PASS: ' . $passed . PHP_EOL;
