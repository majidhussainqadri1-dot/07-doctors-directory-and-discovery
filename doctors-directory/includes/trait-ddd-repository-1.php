<?php
defined( 'ABSPATH' ) || exit;

trait DDD_Repository_Trait_1 {
	public static function table( $name ) {
		global $wpdb;
		$allowed = array( 'projection', 'taxonomy', 'saved_refs', 'reports', 'report_audit', 'outbox', 'inbox', 'health_log' );
		if ( ! in_array( $name, $allowed, true ) ) {
			return '';
		}
		return $wpdb->prefix . 'ddd_' . $name;
	}
}
