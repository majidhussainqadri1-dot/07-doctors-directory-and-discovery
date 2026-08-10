<?php
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/*
 * Non-destructive by default. File 07 tables, reports, audit records and public
 * identifier mappings are retained. Uninstall only removes runtime wiring and
 * restores pages that File 07 adopted. A separately authenticated operations
 * tool is required for a backed-up, audited data purge.
 */

foreach ( array( 'ddd_reconcile_tick', 'ddd_expire_features_tick', 'ddd_process_outbox_tick', 'ddd_continue_legacy_migration' ) as $hook ) {
	wp_clear_scheduled_hook( $hook );
}

$map = (array) get_option( 'ddd_page_map', array() );
foreach ( $map as $page_id ) {
	$page_id = absint( $page_id );
	if ( ! $page_id || '1' !== get_post_meta( $page_id, '_ddd_managed_page', true ) ) { continue; }
	if ( '1' === get_post_meta( $page_id, '_ddd_created_page', true ) ) {
		wp_update_post( array( 'ID' => $page_id, 'post_status' => 'draft' ) );
	} else {
		$previous_content = metadata_exists( 'post', $page_id, '_ddd_previous_content' ) ? (string) get_post_meta( $page_id, '_ddd_previous_content', true ) : '';
		$previous_status = metadata_exists( 'post', $page_id, '_ddd_previous_status' ) ? sanitize_key( (string) get_post_meta( $page_id, '_ddd_previous_status', true ) ) : 'draft';
		if ( ! in_array( $previous_status, array( 'publish','private','draft','pending' ), true ) ) { $previous_status = 'draft'; }
		wp_update_post( array( 'ID' => $page_id, 'post_content' => $previous_content, 'post_status' => $previous_status ) );
	}
	delete_post_meta( $page_id, '_ddd_managed_page' );
	delete_post_meta( $page_id, '_ddd_previous_content' );
	delete_post_meta( $page_id, '_ddd_previous_status' );
	delete_post_meta( $page_id, '_ddd_created_page' );
}

$caps = array( 'ddd_manage_directory', 'ddd_manage_features', 'ddd_manage_taxonomy', 'ddd_review_reports', 'ddd_run_reconciliation', 'ddd_view_health', 'ddd_repair_directory' );
$role = get_role( 'administrator' );
if ( $role ) { foreach ( $caps as $cap ) { $role->remove_cap( $cap ); } }

foreach ( array( 'ddd_page_map', 'sdd_page_map', 'ddd_activation_lock', 'ddd_activation_notice', 'ddd_reconcile_cursor', 'ddd_cache_version', 'ddd_safe_mode' ) as $option ) {
	delete_option( $option ); delete_transient( $option );
}

flush_rewrite_rules();
