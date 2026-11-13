<?php
/**
 * Isolated executable regression: public doctor reads cannot trust stale projections.
 * This tests the actual File07 repository class against a bounded mock WP database.
 * It is NOT a substitute for integrated WordPress/MySQL staging acceptance.
 */
define( 'ABSPATH', __DIR__ . '/' );
define( 'DDD_TEXT_DOMAIN', 'doctors-directory-discovery' );
define( 'DAY_IN_SECONDS', 86400 );
define( 'ARRAY_A', 'ARRAY_A' );
class WP_Error {
    private $code;
    public function __construct( $code, $message = '', $data = array() ) { $this->code = $code; }
    public function get_error_code() { return $this->code; }
}
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function __( $value, $domain = null ) { return $value; }
function absint( $value ) { return abs( (int) $value ); }
function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-z0-9_-]/i', '', (string) $value ) ); }
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function esc_url_raw( $value ) { return $value; }
function wp_json_encode( $value ) { return json_encode( $value ); }
function current_time( $format, $gmt = false ) { return gmdate( 'Y-m-d H:i:s' ); }
function wp_parse_args( $args, $defaults = array() ) { return array_merge( $defaults, (array) $args ); }
function wp_get_attachment_image_url( $id, $size ) { return ''; }
class DDD_Helpers {
    public static function valid_public_id( $value ) { return (bool) preg_match( '/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/i', (string) $value ); }
    public static function safe_error( $code, $message, $status ) { return new WP_Error( $code, $message, array( 'status' => $status ) ); }
    public static function filter_hash( $input ) { return hash( 'sha256', json_encode( $input ) ); }
    public static function normalize_token( $input ) { return strtolower( trim( (string) $input ) ); }
    public static function decimal_or_null( $input ) { return null === $input || '' === $input ? null : ( is_numeric( $input ) ? (float) $input : null ); }
    public static function public_profile_url( $id ) { return 'https://sabrihomeopathy.test/doctors/' . $id . '/'; }
    public static function same_origin_url( $input ) { return $input; }
    public static function cursor_encode( $input ) { return json_encode( $input ); }
    public static function cursor_decode( $input, $hash ) { return json_decode( $input, true ); }
}
class DDD_Contracts {
    public static function eligibility( $doctor_id ) { return $GLOBALS['current_owner'][$doctor_id] ?? array( 'eligible' => false ); }
}
class DDD_Test_Database {
    public $prefix = 'wp_';
    public $last_error = '';
    public $rows = array();
    public $fail_read = false;
    public function esc_like( $value ) { return $value; }
    public function prepare( $query, ...$values ) { return $query; }
    public function get_results( $query, $mode ) { if ( $this->fail_read ) { $this->last_error = 'simulated query failure'; return null; } return $this->rows; }
    public function get_row( $query, $mode ) { return $this->rows[0] ?? null; }
    public function query( $query ) { return 1; }
}
$wpdb = new DDD_Test_Database();
require dirname( __DIR__ ) . '/doctors-directory/includes/class-sdd-directory.php';
$passed = 0;
function verify_owner_visibility( $condition, $description ) {
    global $passed;
    if ( ! $condition ) { fwrite( STDERR, 'FAIL: ' . $description . PHP_EOL ); exit( 1 ); }
    ++$passed; echo 'PASS: ' . $description . PHP_EOL;
}
$id = '12345678-1234-4abc-8def-123456789abc';
$good = array(
    'eligible' => true,
    'identity' => array( 'claim_version' => 'i1' ),
    'verification' => array( 'decision_version' => 'v1' ),
    'profile' => array( 'public_id' => $id, 'profile_version' => 'p1' ),
    'clinic' => array( 'clinic_version' => 'c1' ),
);
$GLOBALS['current_owner'][42] = $good;
$row = array(
    'doctor_id' => 42, 'public_id' => $id, 'eligible' => 1,
    'owner_versions_json' => wp_json_encode( array( 'identity' => 'i1', 'verification' => 'v1', 'profile' => 'p1', 'clinic' => 'c1' ) ),
    'display_name' => 'Doctor One', 'professional_title' => 'Doctor', 'specialty' => 'Classical Homeopathy',
    'country' => 'Pakistan', 'city' => 'Gujrat', 'qualification' => 'DHMS',
    'languages_json' => '["Urdu"]', 'consultation_modes_json' => '["online"]',
    'fee_min' => null, 'fee_max' => null, 'currency' => '', 'avatar_id' => 0, 'avatar_url' => '',
    'profile_url' => 'https://sabrihomeopathy.test/doctor', 'clinic_url' => '', 'appointment_url' => '',
    'experience_years' => 10, 'accepting_patients' => 1, 'completeness' => 90,
    'verified_at' => '2026-01-01 00:00:00', 'featured' => 0
);
$wpdb->rows = array( $row );
verify_owner_visibility( DDD_Repository::current_public_projection( $row ), 'Current canonical doctor and versions accepted' );
verify_owner_visibility( ! empty( DDD_Repository::get_by_public_id( $id ) ), 'Current detail is available' );
$search = DDD_Repository::search( array( 'limit' => 2 ) );
verify_owner_visibility( ! is_wp_error( $search ) && count( $search['items'] ) === 1, 'Current search result is visible' );
$GLOBALS['current_owner'][42]['eligible'] = false;
verify_owner_visibility( ! DDD_Repository::current_public_projection( $row ), 'Revoked professional immediately denied' );
$search = DDD_Repository::search( array( 'limit' => 2 ) );
verify_owner_visibility( ! is_wp_error( $search ) && count( $search['items'] ) === 0, 'Revoked professional absent from search' );
verify_owner_visibility( empty( DDD_Repository::get_by_public_id( $id ) ), 'Revoked professional detail unavailable' );
$GLOBALS['current_owner'][42] = $good;
$GLOBALS['current_owner'][42]['profile']['public_id'] = '12345678-1234-4abc-8def-123456789abd';
verify_owner_visibility( ! DDD_Repository::current_public_projection( $row ), 'Changed canonical File03 public identity denied' );
foreach ( array( array( 'profile', 'profile_version', 'p2' ), array( 'verification', 'decision_version', 'v2' ), array( 'clinic', 'clinic_version', 'c2' ), array( 'identity', 'claim_version', 'i2' ) ) as $change ) {
    $GLOBALS['current_owner'][42] = $good;
    $GLOBALS['current_owner'][42][ $change[0] ][ $change[1] ] = $change[2];
    verify_owner_visibility( ! DDD_Repository::current_public_projection( $row ), 'Owner version change denied: ' . $change[0] );
}
$GLOBALS['current_owner'][42] = $good;
$row['owner_versions_json'] = 'invalid-json';
verify_owner_visibility( ! DDD_Repository::current_public_projection( $row ), 'Malformed owner version record fails closed' );
$wpdb->rows = array( $row );
$wpdb->fail_read = true;
$failure = DDD_Repository::search();
verify_owner_visibility( is_wp_error( $failure ) && 'directory_search_unavailable' === $failure->get_error_code(), 'Unreadable database produces safe 503-class error' );
$plugin = file_get_contents( dirname( __DIR__ ) . '/doctors-directory/includes/class-sdd-plugin.php' );
$seo = file_get_contents( dirname( __DIR__ ) . '/doctors-directory/includes/class-sdd-seo.php' );
verify_owner_visibility( substr_count( $plugin, "'Cache-Control', 'private, no-store, max-age=0'" ) >= 3, 'Public REST result/detail no-store release guard' );
verify_owner_visibility( false !== strpos( $seo, "DDD_Repository::get_by_public_id( \$row['public_id'] )" ), 'Sitemap current-owner recheck release guard' );
echo 'TOTAL PASS: ' . $passed . PHP_EOL;
