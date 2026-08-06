<?php
define( 'ABSPATH', __DIR__ . '/' );
define( 'DDD_TEXT_DOMAIN', 'doctors-directory-discovery' );
define( 'DDD_CONTRACT_VERSION', '1.0.0' );
define( 'DDD_MIN_FILE03_VERSION', '0.1.0' );
function __( $text, $domain = null ) { return $text; }
function absint( $value ) { return abs( (int) $value ); }
function sanitize_text_field( $value ) { return trim( preg_replace( '/\s+/', ' ', strip_tags( (string) $value ) ) ); }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) ); }
function sanitize_textarea_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function sanitize_title( $value ) { return trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( (string) $value ) ), '-' ); }
function wp_strip_all_tags( $value ) { return strip_tags( (string) $value ); }
function remove_accents( $value ) { return (string) $value; }
function wp_json_encode( $value ) { return json_encode( $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ); }
function wp_salt( $scheme = 'auth' ) { return 'unit-test-salt-' . $scheme; }
function wp_hash( $value ) { return hash( 'sha256', $value ); }
function wp_rand() { return 123456; }
function wp_generate_uuid4() { return '11111111-1111-4111-a111-111111111111'; }
function wp_parse_args( $args, $defaults ) { return array_merge( $defaults, $args ); }
function esc_url_raw( $url ) { return filter_var( $url, FILTER_SANITIZE_URL ); }
function home_url( $path = '' ) { return 'https://example.test' . $path; }
function user_trailingslashit( $path ) { return rtrim( $path, '/' ) . '/'; }
function wp_sanitize_redirect( $url ) { return $url; }
function get_user_meta() { return ''; }
function update_user_meta() { return true; }
function get_option() { return 0; }
function get_userdata() { return false; }
function user_can() { return false; }
function has_filter() { return false; }
function apply_filters( $tag, $value ) { return $value; }
function class_exists_stub() { return false; }
function current_time() { return gmdate( 'Y-m-d H:i:s' ); }
function get_transient() { return 0; }
function set_transient() { return true; }
function wp_unslash( $value ) { return $value; }

foreach ( glob( __DIR__ . '/../doctors-directory/includes/trait-ddd-contracts-*.php' ) as $file ) { require_once $file; }
foreach ( glob( __DIR__ . '/../doctors-directory/includes/trait-ddd-helpers-*.php' ) as $file ) { require_once $file; }
require_once __DIR__ . '/../doctors-directory/includes/class-sdd-helpers.php';

$failures = array();
$assert = function ( $condition, $message ) use ( &$failures ) {
	if ( ! $condition ) { $failures[] = $message; }
};

$list = DDD_Helpers::list_value( "Urdu, English;Arabic\nUrdu" );
$assert( $list === array( 'Urdu', 'English', 'Arabic' ), 'list_value deduplication failed' );
$assert( DDD_Helpers::decimal_or_null( 'PKR 1,250.50' ) === 1250.50, 'decimal normalization failed' );
$assert( DDD_Helpers::decimal_or_null( 'not-a-number' ) === null, 'invalid decimal did not fail closed' );
$normalized = DDD_Helpers::normalize_token( 'Classical—Homeopathy  ڈاکٹر' );
$assert( strpos( $normalized, 'classical homeopathy' ) !== false, 'token normalization failed' );
$cursor = DDD_Helpers::cursor_encode( array( 'f' => 1, 'q' => 91.5, 'v' => '2026-01-01 00:00:00', 'id' => 10 ) );
$decoded = DDD_Helpers::cursor_decode( $cursor );
$assert( isset( $decoded['id'] ) && 10 === $decoded['id'], 'cursor round trip failed' );
$assert( DDD_Helpers::cursor_decode( $cursor . 'tampered' ) === array(), 'cursor tamper check failed' );
$assert( preg_match( '/^[a-f0-9]{16}$/', DDD_Helpers::trace_id() ) === 1, 'trace id shape failed' );

if ( $failures ) {
	fwrite( STDERR, "FAIL\n- " . implode( "\n- ", $failures ) . "\n" );
	exit( 1 );
}
echo "PASS: helper contract tests\n";
