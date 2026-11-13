<?php
/**
 * Current File07 native source: File26 connector stays proposed until real owners,
 * deletion/tombstone and staging acceptance are independently established.
 * Isolated test: NOT a substitute for a live WordPress/MySQL/File26 end-to-end run.
 */
require __DIR__ . '/bootstrap.php';
require dirname( __DIR__ ) . '/doctors-directory/includes/class-ddd-file26-connector.php';
$passed = 0;
function connector_check( $condition, $label ) {
    global $passed;
    if ( ! $condition ) { fwrite( STDERR, 'FAIL: ' . $label . PHP_EOL ); exit( 1 ); }
    $passed++; echo 'PASS: ' . $label . PHP_EOL;
}
$m = DDD_File26_Connector::manifest();
connector_check( $m['slug'] === 'file07-verified-doctors', 'Canonical File07 connector slug' );
connector_check( $m['owner_file'] === 'File 07', 'Native File07 is current public-projection owner' );
connector_check( $m['status'] === 'proposed', 'Connector never pretends to be production approved' );
connector_check( $m['deletion_semantics'] === 'owner_verification_revocation_gate_pending', 'Revocation approval explicitly outstanding' );
connector_check( $m['privacy_classes'] === array( 'public' ) && $m['visibility_fields'] === array( 'public' ), 'No private indexing entitlement granted' );
connector_check( $m['entity_types'] === array( 'doctor_directory_projection' ), 'Only public directory projection entity permitted' );
foreach ( array( 'list_batch', 'fetch_object', 'can_view', 'health' ) as $call ) {
    connector_check( is_callable( $m[$call] ), 'Native connector callable: ' . $call );
}
$health = DDD_File26_Connector::health();
connector_check( $health['state'] === 'degraded' && ! isset( $health['status'] ), 'Absent true File00/03/09/07 readiness remains degraded' );
$batch = DDD_File26_Connector::list_batch( '', 10, array() );
connector_check( is_wp_error( $batch ), 'Incomplete owner identities refuse search batch' );
$fake = array( 'connector_slug' => $m['slug'], 'visibility' => 'public', 'entity_type' => 'doctor_directory_projection', 'object_id' => '12345678-1234-4abc-8def-123456789abc', 'object_version' => 1 );
connector_check( DDD_File26_Connector::can_view( $fake, array() ) === false, 'Unknown doctor never passes click-time verification' );
connector_check( DDD_File26_Connector::fetch_object( 'doctor_directory_projection', $fake['object_id'] ) === null, 'Unknown canonical doctor is never fabricated' );
$many = DDD_File26_Connector::append( array( $m ) );
connector_check( count( $many ) === 1, 'Duplicate connector manifest is not registered twice' );
echo 'TOTAL PASS: ' . $passed . PHP_EOL;
